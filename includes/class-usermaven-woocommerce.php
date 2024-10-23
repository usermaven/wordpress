<?php
/**
 * Usermaven WooCommerce Integration
 * 
 * Provides comprehensive tracking of WooCommerce events including product views,
 * cart interactions, checkout process, and order completion.
 */
class Usermaven_WooCommerce {
    private $api;
    private $api_key;

    /**
     * Constructor
     * 
     * @param string $tracking_host The Usermaven tracking host
     */
    public function __construct($tracking_host) {
        $this->api = new Usermaven_API($tracking_host);
        $this->api_key = get_option('usermaven_api_key');
        $this->init_hooks();
    }

    /**
     * Initialize all WooCommerce hooks
     */
    private function init_hooks() {
        // Product Viewing
        add_action('template_redirect', array($this, 'track_product_view'));

        // Cart Actions
        add_action('woocommerce_add_to_cart', array($this, 'track_add_to_cart'), 10, 6);
        add_action('woocommerce_cart_item_removed', array($this, 'track_remove_from_cart'), 10, 2);
        add_action('woocommerce_after_cart_item_quantity_update', array($this, 'track_cart_update'), 10, 4);

        // Checkout Process
        add_action('woocommerce_before_checkout_form', array($this, 'track_initiate_checkout'), 10);
        add_action('woocommerce_checkout_order_processed', array($this, 'track_order_submission'), 10, 3);
        
        // Order Status and Completion
        add_action('woocommerce_order_status_changed', array($this, 'track_order_status_changed'), 10, 4);
        add_action('woocommerce_payment_complete', array($this, 'track_order_completed'));
        add_action('woocommerce_order_status_failed', array($this, 'track_failed_order'), 10, 2);
        add_action('woocommerce_thankyou', array($this, 'track_order_thankyou'), 10, 1);
        add_action('woocommerce_order_refunded', array($this, 'track_order_refunded'), 10, 2);

        // Track customer creation
        add_action('woocommerce_created_customer', array($this, 'track_customer_created'), 10, 3);

        // Reset the tracking flag when the cart is updated
        add_action('woocommerce_cart_updated', array($this, 'reset_initiate_checkout_tracking'));
        // Reset the tracking flag after the order is placed
        add_action('woocommerce_thankyou', array($this, 'reset_initiate_checkout_tracking'));
        // Reset the tracking flag when an order is completed
        add_action('woocommerce_order_status_completed', array($this, 'reset_initiate_checkout_tracking'));
        // Reset the tracking flag when an order fails
        add_action('woocommerce_order_status_failed', array($this, 'reset_initiate_checkout_tracking'));

        // Wishlist Integration (if using WooCommerce Wishlist)
        if (class_exists('YITH_WCWL')) {
            add_action('yith_wcwl_added_to_wishlist', array($this, 'track_add_to_wishlist'), 10, 3);
            add_action('yith_wcwl_removed_from_wishlist', array($this, 'track_remove_from_wishlist'), 10, 2);
            add_action('yith_wcwl_moved_to_another_wishlist', array($this, 'track_move_to_another_wishlist'), 10, 4);
        }
    }

    /**
     * Reset the initiate checkout tracking flag
     */
    public function reset_initiate_checkout_tracking() {
        WC()->session->__unset('usermaven_initiate_checkout_tracked');
    }

    /**
     * Track product view events
     */
    public function track_product_view() {
        try {
            // Only track on single product pages
            if (!is_singular('product')) {
                return;
            }
    
            // Get the product ID
            $product_id = get_the_ID();
            if (!$product_id) {
                return;
            }
    
            // Get the product
            global $product;
            if (!$product instanceof WC_Product) {
                $product = wc_get_product($product_id);
            }
    
            // Silently return if no valid product
            if (!$product instanceof WC_Product) {
                return;
            }
    
            // Get product categories
            $categories = array();
            $terms = get_the_terms($product->get_id(), 'product_cat');
            if ($terms && !is_wp_error($terms)) {
                $categories = wp_list_pluck($terms, 'name');
            }
    
            // Prepare event attributes
            $event_attributes = array(
                'product_id' => $product->get_id(),
                'product_name' => $product->get_name(),
                'price' => $product->get_price(),
                'currency' => get_woocommerce_currency(),
                'type' => $product->get_type(),
                'categories' => $categories,
                'sku' => $product->get_sku(),
                'stock_status' => $product->get_stock_status(),
                'view_type' => 'detail'
            );
    
            // Send the event
            $this->send_event('view_product', $event_attributes);
    
        } catch (Exception $e) {
            // Log the error but don't halt execution
            error_log('Usermaven: Error tracking product view - ' . $e->getMessage());
        }
    }
    

    /**
     * Track add to cart events
     */
    public function track_add_to_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }

        $event_attributes = array(
            'product_id' => $product_id,
            'product_name' => $product->get_name(),
            'quantity' => $quantity,
            'price' => $product->get_price(),
            'currency' => get_woocommerce_currency(),
            'cart_total' => WC()->cart->get_cart_contents_total(),
            'cart_items_count' => WC()->cart->get_cart_contents_count(),
        );
        
        if ($variation_id && $variation) {
            $event_attributes['variation_id'] = $variation_id;
            $event_attributes['variation_attributes'] = $variation;
        }

        $this->send_event('add_to_cart', $event_attributes);
    }

    /**
     * Track remove from cart events
     */
    public function track_remove_from_cart($cart_item_key, $cart) {
        $cart_item = $cart->removed_cart_contents[$cart_item_key] ?? null;
        if (!$cart_item) {
            return;
        }

        $product_id = $cart_item['product_id'];
        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }

        $event_attributes = array(
            'product_id' => $product_id,
            'product_name' => $product->get_name(),
            'price' => $product->get_price(),
            'quantity' => $cart_item['quantity'],
            'currency' => get_woocommerce_currency(),
            'cart_total' => $cart->get_cart_contents_total(),
            'remaining_items' => $cart->get_cart_contents_count()
        );

        $this->send_event('remove_from_cart', $event_attributes);
    }

    /**
     * Track cart updates
     */
    public function track_cart_update($cart_item_key, $quantity, $old_quantity, $cart) {
        $cart_item = $cart->get_cart_item($cart_item_key);
        if (!$cart_item) {
            return;
        }

        $product = $cart_item['data'];
        $event_attributes = array(
            'product_id' => $product->get_id(),
            'product_name' => $product->get_name(),
            'old_quantity' => $old_quantity,
            'new_quantity' => $quantity,
            'price' => $product->get_price(),
            'currency' => get_woocommerce_currency(),
            'cart_total' => $cart->get_total('numeric'),
            'cart_items_count' => $cart->get_cart_contents_count()
        );

        $this->send_event('update_cart_item', $event_attributes);
    }

    /**
     * Track checkout initiation
     */
    public function track_initiate_checkout() {
        if (WC()->cart->is_empty()) {
            return;
        }

        // Prevent duplicate tracking by checking a session variable
        if (WC()->session->get('usermaven_initiate_checkout_tracked')) {
            return;
        }

        $cart = WC()->cart;
        $items = array();
        
        foreach ($cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            $items[] = array(
                'product_id' => $product->get_id(),
                'product_name' => $product->get_name(),
                'quantity' => $cart_item['quantity'],
                'price' => $product->get_price(),
                'variation_id' => $cart_item['variation_id'] ?? null,
                'variation' => $cart_item['variation'] ?? null
            );
        }

        $event_attributes = array(
            'total' => $cart->get_total('numeric'),
            'subtotal' => $cart->get_subtotal(),
            'tax' => $cart->get_total_tax(),
            'shipping_total' => $cart->get_shipping_total(),
            'currency' => get_woocommerce_currency(),
            'items_count' => $cart->get_cart_contents_count(),
            'items' => $items,
            'coupons' => $cart->get_applied_coupons()
        );
        
        $this->send_event('initiate_checkout', $event_attributes);

        // Set the session variable to prevent duplicate tracking
        WC()->session->set('usermaven_initiate_checkout_tracked', true);
    }

    /**
    * Comprehensive tracking of order submission with full details
    *
    * @param int $order_id The WooCommerce order ID
    * @param array $posted_data Posted checkout form data
    * @param WC_Order $order The WooCommerce order object
    */
    public function track_order_submission($order_id, $posted_data, $order) {
        if (!$order_id) {
            return;
        }

        // Get WC Countries object for location handling
        $wc_countries = new WC_Countries();

        // Get billing country details
        $billing_country_code = $order->get_billing_country();
        $billing_country_name = $billing_country_code ? $wc_countries->countries[$billing_country_code] : '';
        
        // Get billing state details
        $billing_state_code = $order->get_billing_state();
        $billing_states = $wc_countries->get_states($billing_country_code);
        $billing_state_name = ($billing_states && isset($billing_states[$billing_state_code])) 
            ? $billing_states[$billing_state_code] 
            : $billing_state_code;

        // Get shipping country details
        $shipping_country_code = $order->get_shipping_country();
        
        // If shipping country is empty, use billing country
        if (empty($shipping_country_code)) {
            $shipping_country_code = $billing_country_code;
            $shipping_country_name = $billing_country_name;
        } else {
            $shipping_country_name = $wc_countries->countries[$shipping_country_code];
        }

        // Get shipping state details
        $shipping_state_code = $order->get_shipping_state();
        
        // If shipping state is empty, use billing state
        if (empty($shipping_state_code)) {
            $shipping_state_code = $billing_state_code;
            $shipping_state_name = $billing_state_name;
        } else {
            $shipping_states = $wc_countries->get_states($shipping_country_code);
            $shipping_state_name = ($shipping_states && isset($shipping_states[$shipping_state_code])) 
                ? $shipping_states[$shipping_state_code] 
                : $shipping_state_code;
        }

        // Get customer information
        $customer_id = $order->get_customer_id();
        
        // Get order items details
        $items = array();
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) {
                continue;
            }

            // Get product categories
            $categories = array();
            $terms = get_the_terms($product->get_id(), 'product_cat');
            if ($terms && !is_wp_error($terms)) {
                $categories = wp_list_pluck($terms, 'name');
            }

            $items[] = array(
                'product_id' => $product->get_id(),
                'product_name' => $product->get_name(),
                'quantity' => $item->get_quantity(),
                'price' => $product->get_price(),
                'subtotal' => $item->get_subtotal(),
                'total' => $item->get_total(),
                'sku' => $product->get_sku(),
                'categories' => $categories,
                'variation_id' => $product->is_type('variation') ? $product->get_id() : null,
                'variation_attributes' => $product->is_type('variation') ? $product->get_attributes() : null
            );
        }

        // Prepare complete event attributes
        $event_attributes = array(
            // Order Details
            'order_id' => $order_id,
            'order_currency' => $order->get_currency(),
            'created_via' => $order->get_created_via(),
            'prices_include_tax' => $order->get_prices_include_tax(),
            
            // Financial Details
            'total' => $order->get_total(),
            'subtotal' => $order->get_subtotal(),
            'tax_total' => $order->get_total_tax(),
            'shipping_total' => $order->get_shipping_total(),
            'discount_total' => $order->get_total_discount(),
            'cart_tax' => $order->get_cart_tax(),
            'shipping_tax' => $order->get_shipping_tax(),
            
            // Payment Details
            'payment_method' => $order->get_payment_method(),
            'payment_method_title' => $order->get_payment_method_title(),
            'transaction_id' => $order->get_transaction_id(),
            
            // Customer Information
            'customer_id' => $customer_id ?: null,
            'customer_type' => $customer_id ? 'registered' : 'guest',
            'is_registered_customer' => (bool)$customer_id,
            'is_returning_customer' => $this->is_returning_customer($customer_id, $order->get_billing_email()),
            
            // Billing Details
            'billing_first_name' => $order->get_billing_first_name(),
            'billing_last_name' => $order->get_billing_last_name(),
            'billing_company' => $order->get_billing_company(),
            'billing_email' => $order->get_billing_email(),
            'billing_phone' => $order->get_billing_phone(),
            'billing_country' => $billing_country_name,
            'billing_country_code' => $billing_country_code,
            'billing_state' => $billing_state_name,
            'billing_state_code' => $billing_state_code,
            'billing_city' => $order->get_billing_city(),
            'billing_postcode' => $order->get_billing_postcode(),
            
            // Shipping Details
            'shipping_first_name' => $order->get_shipping_first_name() ?: $order->get_billing_first_name(),
            'shipping_last_name' => $order->get_shipping_last_name() ?: $order->get_billing_last_name(),
            'shipping_company' => $order->get_shipping_company() ?: $order->get_billing_company(),
            'shipping_country' => $shipping_country_name,
            'shipping_country_code' => $shipping_country_code,
            'shipping_state' => $shipping_state_name,
            'shipping_state_code' => $shipping_state_code,
            'shipping_city' => $order->get_shipping_city() ?: $order->get_billing_city(),
            'shipping_postcode' => $order->get_shipping_postcode() ?: $order->get_billing_postcode(),
            'shipping_same_as_billing' => empty($order->get_shipping_country()),
            
            // Items Information
            'items_count' => $order->get_item_count(),
            'items' => $items,
            
            // Additional Details
            'customer_note' => $order->get_customer_note(),
            'order_status' => $order->get_status(),
            'coupon_codes' => $order->get_coupon_codes(),
        );

        $this->send_event('order_submitted', $event_attributes);
    }

    /**
     * Check if this is a returning customer based on previous orders
     *
     * @param int|null $customer_id Customer ID if registered
     * @param string $billing_email Customer's email address
     * @return bool True if customer has previous orders
     */
    private function is_returning_customer($customer_id, $billing_email) {
        if ($customer_id) {
            // For registered customers, check orders by customer ID
            $previous_orders = wc_get_orders(array(
                'customer_id' => $customer_id,
                'status' => array('wc-completed'),
                'limit' => 1,
                'return' => 'ids',
            ));
            return !empty($previous_orders);
        } else {
            // For guest customers, check orders by email
            $previous_orders = wc_get_orders(array(
                'billing_email' => $billing_email,
                'status' => array('wc-completed'),
                'limit' => 1,
                'return' => 'ids',
            ));
            return !empty($previous_orders);
        }
    }

    /**
     * Track order status changes
     */
    public function track_order_status_changed($order_id, $old_status, $new_status, $order) {
        // Track only significant status changes
        $significant_statuses = ['processing', 'completed', 'failed', 'refunded'];
        
        if (!in_array($new_status, $significant_statuses)) {
            return;
        }

        $event_attributes = array(
            'order_id' => $order_id,
            'old_status' => $old_status,
            'new_status' => $new_status,
            'total' => $order->get_total(),
            'currency' => $order->get_currency(),
            'payment_method' => $order->get_payment_method(),
            'customer_id' => $order->get_customer_id(),
            'billing_email' => $order->get_billing_email()
        );

        $this->send_event('order_status_changed', $event_attributes);
    }

    /**
     * Track completed orders
     */
    public function track_order_completed($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $items = array();
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) {
                continue;
            }

            $items[] = array(
                'product_id' => $product->get_id(),
                'product_name' => $product->get_name(),
                'quantity' => $item->get_quantity(),
                'price' => $product->get_price(),
                'subtotal' => $item->get_subtotal(),
                'total' => $item->get_total(),
                'tax' => $item->get_total_tax()
            );
        }

        $event_attributes = array(
            'order_id' => $order_id,
            'total' => $order->get_total(),
            'subtotal' => $order->get_subtotal(),
            'shipping_total' => $order->get_shipping_total(),
            'tax_total' => $order->get_total_tax(),
            'currency' => $order->get_currency(),
            'payment_method' => $order->get_payment_method(),
            'payment_method_title' => $order->get_payment_method_title(),
            'items_count' => $order->get_item_count(),
            'items' => $items,
            'billing_email' => $order->get_billing_email(),
            'billing_country' => $order->get_billing_country(),
            'billing_state' => $order->get_billing_state(),
            'shipping_country' => $order->get_shipping_country(),
            'shipping_state' => $order->get_shipping_state(),
            'coupons' => $order->get_coupon_codes(),
            'is_first_order' => $this->is_first_order($order->get_customer_id()),
            'customer_note' => $order->get_customer_note()
        );

        $this->send_event('order_completed', $event_attributes);
    }

    /**
     * Add missing first order check
     */
    private function is_first_order($customer_id) {
        if (!$customer_id) {
            return true;
        }
        
        $orders = wc_get_orders(array(
            'customer_id' => $customer_id,
            'status' => array('completed'),
            'limit' => 2,
            'return' => 'ids',
        ));
        
        return count($orders) <= 1;
    }

    /**
     * Helper method to get order items
     */
    private function get_order_items($order) {
        $items = array();
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) {
                continue;
            }

            $items[] = array(
                'product_id' => $product->get_id(),
                'product_name' => $product->get_name(),
                'quantity' => $item->get_quantity(),
                'price' => $product->get_price(),
                'subtotal' => $item->get_subtotal(),
                'total' => $item->get_total(),
                'tax' => $item->get_total_tax()
            );
        }
        return $items;
    }

    /**
     * Track failed order events
     */
    public function track_failed_order($order_id, $order) {
        $items = $this->get_order_items($order);

        $event_attributes = array(
            'order_id' => $order_id,
            'total' => $order->get_total(),
            'currency' => $order->get_currency(),
            'payment_method' => $order->get_payment_method(),
            'payment_method_title' => $order->get_payment_method_title(),
            'items' => $items,
            'failure_message' => $order->get_status_message(),
            'billing_email' => $order->get_billing_email(),
            'billing_country' => $order->get_billing_country(),
            'shipping_country' => $order->get_shipping_country(),
            'customer_note' => $order->get_customer_note(),
            'created_via' => $order->get_created_via(),
            'customer_id' => $order->get_customer_id(),
            'user_agent' => $order->get_customer_user_agent()
        );

        $this->send_event('order_failed', $event_attributes);
    }

    /**
     * Track order thank you page view
     */
    public function track_order_thankyou($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $event_attributes = array(
            'order_id' => $order_id,
            'total' => $order->get_total(),
            'currency' => $order->get_currency(),
            'payment_method' => $order->get_payment_method(),
            'status' => $order->get_status(),
            'items_count' => $order->get_item_count(),
            'billing_email' => $order->get_billing_email()
        );

        $this->send_event('order_thankyou_page_view', $event_attributes);
    }

    /**
     * Track refunded orders
     */
    public function track_order_refunded($order_id, $refund_id) {
        $order = wc_get_order($order_id);
        $refund = wc_get_order($refund_id);

        if (!$order || !$refund) {
            return;
        }

        $event_attributes = array(
            'order_id' => $order_id,
            'refund_id' => $refund_id,
            'refund_amount' => $refund->get_amount(),
            'refund_reason' => $refund->get_reason(),
            'original_order_total' => $order->get_total(),
            'remaining_order_total' => $order->get_remaining_refund_amount(),
            'currency' => $order->get_currency(),
            'customer_id' => $order->get_customer_id(),
            'billing_email' => $order->get_billing_email(),
            'payment_method' => $order->get_payment_method(),
            'refunded_items' => $this->get_refunded_items($refund)
        );

        $this->send_event('order_refunded', $event_attributes);
    }

    /**
     * Track new customer creation
     */
    public function track_customer_created($customer_id, $new_customer_data, $password_generated) {
        $event_attributes = array(
            'customer_id' => $customer_id,
            'email' => $new_customer_data['user_email'],
            'username' => $new_customer_data['user_login'],
            'password_generated' => $password_generated,
            'billing_email' => get_user_meta($customer_id, 'billing_email', true),
            'billing_country' => get_user_meta($customer_id, 'billing_country', true),
            'shipping_country' => get_user_meta($customer_id, 'shipping_country', true)
        );

        $this->send_event('customer_created', $event_attributes);
    }

    /**
     * Helper function to get refunded items
     */
    private function get_refunded_items($refund) {
        $refunded_items = array();
        foreach ($refund->get_items() as $item_id => $item) {
            $refunded_items[] = array(
                'product_id' => $item->get_product_id(),
                'variation_id' => $item->get_variation_id(),
                'quantity' => abs($item->get_quantity()),
                'subtotal' => abs($item->get_subtotal()),
                'total' => abs($item->get_total()),
                'tax' => abs($item->get_total_tax()),
                'name' => $item->get_name()
            );
        }
        return $refunded_items;
    }

    /**
     * Helper method to get product categories
     */
    private function get_product_categories($product) {
        if (!($product instanceof WC_Product)) {
            return array();
        }

        try {
            $categories = array();
            $terms = get_the_terms($product->get_id(), 'product_cat');
            
            if ($terms && !is_wp_error($terms)) {
                $categories = wp_list_pluck($terms, 'name');
            }
            
            return $categories;
        } catch (Exception $e) {
            error_log('Usermaven: Error getting product categories - ' . $e->getMessage());
            return array();
        }
    }

    /**
     * Track when items are added to wishlist
     * 
     * @param int $product_id The product ID
     * @param int $wishlist_id The wishlist ID
     * @param int $user_id The user ID
     */
    public function track_add_to_wishlist($product_id, $wishlist_id, $user_id) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }

        $wishlist = YITH_WCWL()->get_wishlist_detail($wishlist_id);
        
        $event_attributes = array(
            'product_id' => $product_id,
            'product_name' => $product->get_name(),
            'product_price' => $product->get_price(),
            'currency' => get_woocommerce_currency(),
            'wishlist_id' => $wishlist_id,
            'wishlist_name' => $wishlist['wishlist_name'] ?? 'Default',
            'user_id' => $user_id,
            'total_items_in_wishlist' => YITH_WCWL()->count_products($wishlist_id),
            'product_categories' => $this->get_product_categories($product),
            'product_type' => $product->get_type(),
            'product_sku' => $product->get_sku(),
            'is_product_in_stock' => $product->is_in_stock(),
            'is_product_on_sale' => $product->is_on_sale()
        );

        $this->send_event('add_to_wishlist', $event_attributes);
    }

    /**
     * Track when items are removed from wishlist
     * 
     * @param int $product_id The product ID
     * @param int $wishlist_id The wishlist ID
     */
    public function track_remove_from_wishlist($product_id, $wishlist_id) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }

        $wishlist = YITH_WCWL()->get_wishlist_detail($wishlist_id);
        $user_id = get_current_user_id();

        $event_attributes = array(
            'product_id' => $product_id,
            'product_name' => $product->get_name(),
            'product_price' => $product->get_price(),
            'currency' => get_woocommerce_currency(),
            'wishlist_id' => $wishlist_id,
            'wishlist_name' => $wishlist['wishlist_name'] ?? 'Default',
            'wishlist_token' => $wishlist['wishlist_token'] ?? '',
            'user_id' => $user_id,
            'remaining_items_in_wishlist' => YITH_WCWL()->count_products($wishlist_id),
            'product_categories' => $this->get_product_categories($product)
        );

        $this->send_event('remove_from_wishlist', $event_attributes);
    }

    /**
     * Track when items are moved between wishlists
     * 
     * @param int $product_id The product ID
     * @param int $wishlist_from_id Origin wishlist ID
     * @param int $wishlist_to_id Destination wishlist ID
     * @param int $user_id The user ID
     */
    public function track_move_to_another_wishlist($product_id, $wishlist_from_id, $wishlist_to_id, $user_id) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }

        $from_wishlist = YITH_WCWL()->get_wishlist_detail($wishlist_from_id);
        $to_wishlist = YITH_WCWL()->get_wishlist_detail($wishlist_to_id);

        $event_attributes = array(
            'product_id' => $product_id,
            'product_name' => $product->get_name(),
            'from_wishlist_id' => $wishlist_from_id,
            'from_wishlist_name' => $from_wishlist['wishlist_name'] ?? 'Default',
            'to_wishlist_id' => $wishlist_to_id,
            'to_wishlist_name' => $to_wishlist['wishlist_name'] ?? 'Default',
            'user_id' => $user_id,
            'items_in_source_wishlist' => YITH_WCWL()->count_products($wishlist_from_id),
            'items_in_destination_wishlist' => YITH_WCWL()->count_products($wishlist_to_id)
        );

        $this->send_event('move_wishlist_item', $event_attributes);
    }

    private function send_event($event_type, $event_attributes = array()) {
        if (empty($event_type)) {
            throw new Exception('Event type is required');
        }
        $user_data = $this->get_user_data();
        $company_data = $this->get_company_data();
        
        $this->api->send_event($event_type, $user_data, $event_attributes, $company_data);
    }

    private function get_user_data() {
        $user = wp_get_current_user();

        // Construct the cookie names
        $eventn_cookie_name = '__eventn_id_' . $this->api_key;
        $usermaven_cookie_name = 'usermaven_id_' . $this->api_key;

        // Check for the cookies in order of preference
        if (isset($_COOKIE[$eventn_cookie_name])) {
            $anonymous_id = $_COOKIE[$eventn_cookie_name]; // for old pixel
        } elseif (isset($_COOKIE[$usermaven_cookie_name])) {
            $anonymous_id = $_COOKIE[$usermaven_cookie_name]; // for new pixel
        } else {
            $anonymous_id = '';
        }

        $user_data = array(
            'anonymous_id' => $anonymous_id,
            'id' => '',
        );

        // Check if the user is logged in
        // Else return the anonymous_id and id only
        if ($user->ID !== 0) {
            // User is logged in
            $user_data['id'] = (string)$user->ID;
            $user_data['email'] = $user->user_email;
            $user_data['created_at'] = $user->user_registered;
            $user_data['first_name'] = $user->user_firstname;
            $user_data['last_name'] = $user->user_lastname;
            $user_data['custom'] = array(
                'role' => $user->roles[0] ?? '',
            );
        }
    
        return $user_data;
    }

    private function get_company_data() {
        // You can customize this method to include relevant company data
        // For now, we'll return an empty array
        return array();
    }

}