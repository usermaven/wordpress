<?php
declare(strict_types=1);

/**
 * Usermaven WooCommerce Integration
 * 
 * Provides comprehensive tracking of WooCommerce events including product views,
 * cart interactions, checkout process, and order completion.
 */
class Usermaven_WooCommerce {
    private Usermaven_API $api;
    private string $api_key;

    /**
     * Constructor
     * 
     * @param string $tracking_host The Usermaven tracking host
     */
    public function __construct(string $tracking_host) {
        $this->api = new Usermaven_API($tracking_host);
        $this->api_key = (string) get_option('usermaven_api_key');
        $this->init_hooks();

        // Initialize cart abandonment tracking after WooCommerce is fully initialized
        add_action('woocommerce_init', array($this, 'init_cart_abandonment_tracking'));

        // Check cart abandonment on shutdown
        add_action('shutdown', array($this, 'check_cart_abandonment'), 20);
    }

    /**
     * Initialize all WooCommerce hooks
     */
    private function init_hooks(): void {
        // Track on WooCommerce specific login (accepts two parameters)
        add_action('woocommerce_login_credentials', array($this, 'identify_wc_user'), 10, 2);
    
        // Product Viewing
        add_action('template_redirect', array($this, 'track_product_view'));
    
        // Cart Actions
        add_action('woocommerce_add_to_cart', array($this, 'track_add_to_cart'), 10, 6);
        add_action('woocommerce_cart_item_removed', array($this, 'track_remove_from_cart'), 10, 2);
        add_action('woocommerce_after_cart_item_quantity_update', array($this, 'track_cart_update'), 10, 4);
    
        // Checkout Process - Consolidated tracking
        // add_action('woocommerce_before_checkout_form', array($this, 'track_initiate_checkout'), 10); // Removed because it was not triggering for custom checkout pages
        add_action('wp', array($this, 'maybe_track_checkout_init'));
        // add_action('woocommerce_checkout_order_processed', array($this, 'track_order_submission'), 10, 3); // Removed because it was not triggering for custom checkout pages
        add_action('woocommerce_new_order', array($this, 'track_order_submission'), 10, 1);
    
        // Order Status and Completion
        // add_action('woocommerce_payment_complete', array($this, 'track_order_completed'));
        add_action('woocommerce_order_status_changed', array($this, 'track_order_status_changed'), 10, 4);
        add_action('woocommerce_order_status_completed', array($this, 'track_order_completed'), 10, 1);
        add_action('woocommerce_order_status_failed', array($this, 'track_order_failed'), 10, 2);
        add_action('woocommerce_order_status_processing', array($this, 'track_order_processing'), 10, 1);
        add_action('woocommerce_order_status_on-hold', array($this, 'track_order_on_hold'), 10, 1);
        add_action('woocommerce_order_status_pending', array($this, 'track_order_pending'), 10, 1);
        add_action('woocommerce_order_status_cancelled', array($this, 'track_order_cancelled'), 10, 1);
        add_action('woocommerce_order_status_refunded', array($this, 'track_order_refunded'), 10, 2);
        add_action('woocommerce_order_status_draft', array($this, 'track_order_draft'), 10, 1);
    
        // Track customer creation
        add_action('woocommerce_created_customer', array($this, 'track_customer_created'), 10, 3);
    
        // Reset tracking state for cart updates
        add_action('woocommerce_cart_updated', array($this, 'reset_checkout_tracking'));
        add_action('woocommerce_cart_emptied', array($this, 'reset_checkout_tracking'));
        add_action('woocommerce_after_cart_item_quantity_update', array($this, 'reset_checkout_tracking'));
        
        // Reset tracking for order completion/failure
        add_action('woocommerce_order_status_completed', array($this, 'reset_initiate_checkout_tracking'));
        add_action('woocommerce_order_status_failed', array($this, 'reset_initiate_checkout_tracking'));
        
        // Track order thank you page
        add_action('woocommerce_thankyou', array($this, 'track_order_thankyou'), 10);
    
        // Initialize cart abandonment tracking
        add_action('woocommerce_init', function(): void {
            $this->init_cart_abandonment_tracking();
        });
    
        // Add session cleanup on successful order
        add_action('woocommerce_checkout_order_processed', function(): void {
            // Clear abandonment tracking when order is completed
            WC()->session->set('checkout_started', false);
            WC()->session->set('last_activity', null);
        });
    
        // Wishlist Integration (if using WooCommerce Wishlist)
        if (class_exists('YITH_WCWL')) {
            add_action('yith_wcwl_added_to_wishlist', array($this, 'track_add_to_wishlist'), 10, 3);
            add_action('yith_wcwl_removed_from_wishlist', array($this, 'track_remove_from_wishlist'), 10, 2);
            add_action('yith_wcwl_moved_to_another_wishlist', array($this, 'track_move_to_another_wishlist'), 10, 4);
        }
    }

    /**
     * Identify user on WooCommerce login
     *
     * @param array $credentials
     * @param mixed $unused Second parameter (unused)
     * @return array
     */
    public function identify_wc_user(array $credentials, $unused = ''): array {
        if (!empty($credentials['user_login'])) {
            // Try getting user by login first
            $user = get_user_by('login', $credentials['user_login']);
            
            // If no user found, try by email
            if (!$user) {
                $user = get_user_by('email', $credentials['user_login']);
            }

            if ($user instanceof WP_User) {
                $this->send_user_identify_request($user);
            }
        }
        return $credentials;
    }

    private function get_anonymous_id(): string {
        $eventn_cookie_name = '__eventn_id_' . $this->api_key;
        $usermaven_cookie_name = 'usermaven_id_' . $this->api_key;
        
        if (isset($_COOKIE[$eventn_cookie_name])) {
            return (string) $_COOKIE[$eventn_cookie_name];
        } elseif (isset($_COOKIE[$usermaven_cookie_name])) {
            return (string) $_COOKIE[$usermaven_cookie_name];
        }
        return '';
    }

    private function is_user_role_tracking_allowed(string $current_user_role): bool {
        $usermaven_roles = array(
            'administrator',
            'author',
            'contributor',
            'editor',
            'subscriber',
            'translator'
        );
        
        if (in_array($current_user_role, $usermaven_roles, true)) {
            $usermaven_tracking_enabled = get_option('usermaven_role_' . $current_user_role);
            return $usermaven_tracking_enabled === "1";
        }
        
        // For roles other than the specified Usermaven roles in settings form, return true
        return true;
    }

    /**
     * Send identify call to Usermaven API
     *
     * @param WP_User|null $user
     */
    private function send_user_identify_request(?WP_User $user): void {
        if (!$user) {
            return;
        }

        if ($user->ID) {
            // Get WooCommerce customer
            $customer = new WC_Customer($user->ID);
            
            // Get custom user data
            $roles = $user->roles;
            $primary_role = !empty($roles) ? $roles[0] : '';

            $is_user_tracking_allowed = $this->is_user_role_tracking_allowed($primary_role);
    
            // Get the anonymous_id from cookie if available
            $anonymous_id = $this->get_anonymous_id();
    
            $user_id = $user->ID;
            $user_email = $user->user_email;
    
            // if user id is null use email as user id
            if (empty($user_id)) {
                $user_id = $user_email;
            }
    
            // Prepare user data
            $user_data = array(
                'anonymous_id' => $anonymous_id,
                'id' => $user_id ? (string)$user_id : $user_email,
                'email' => (string)$user_email,
                'created_at' => date('Y-m-d\TH:i:s', strtotime($user->user_registered)),
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'custom' => array(
                    'type' => $user_id ? 'registered' : 'guest', 
                    'role' => $primary_role,
                    'username' => $user->user_login,
                    'display_name' => $user->display_name,
                    'billing_company' => $customer->get_billing_company(),
                    'billing_email' => $customer->get_billing_email(),
                    'billing_phone' => $customer->get_billing_phone(),
                    'billing_postcode' => $customer->get_billing_postcode(),
                    'billing_city' => $customer->get_billing_city(),
                    'billing_state' => $customer->get_billing_state(),
                    'billing_country' => $customer->get_billing_country(),
                    'billing_address_1' => (string) $customer->get_billing_address_1(),
                    'billing_address_2' => (string) $customer->get_billing_address_2(),
                    'is_paying_customer' => (bool) $customer->get_is_paying_customer()
                )
            );

            // Prepare company data if available
            $company_data = array();
            $company_name = $customer->get_billing_company();
            if (!empty($company_name)) {
                $company_data = array(
                    'id' => 'wc_' . md5($company_name . $customer->get_billing_email()),
                    'name' => $company_name,
                    'created_at' => date('Y-m-d\TH:i:s', strtotime($user->user_registered)),
                    'custom' => array(
                        'billing_country' => $customer->get_billing_country(),
                        'billing_city' => $customer->get_billing_city(),
                        'billing_postcode' => $customer->get_billing_postcode()
                    )
                );
            }

        } else {
            $roles = $user->roles;
            $primary_role = !empty($roles) ? $roles[0] : '';

            $is_user_tracking_allowed = $this->is_user_role_tracking_allowed($primary_role);
            $user_data = $user;
            $company_data = array();
        }

        // Send identify request using the API if is_user_tracking_allowed is true
        if ($is_user_tracking_allowed) {
            $this->api->identify($user_data, $company_data);
        }
    }


    /**
     * Reset the initiate checkout tracking flag
     */
    public function reset_initiate_checkout_tracking(): void {
        WC()->session->__unset('usermaven_initiate_checkout_tracked');
    }

    /**
     * Track product view events
     */
    public function track_product_view(): void {
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

            // Create items array with single product
            $items = array(
                array(
                    'product_id'   => (int) $product->get_id(),
                    'product_name' => (string) $product->get_name(),
                    'price'        => (float) $product->get_price(),
                    'currency'     => (string) get_woocommerce_currency(),
                    'type'         => (string) $product->get_type(),
                    'categories'   => array_map('strval', $categories),
                    'sku'          => (string) $product->get_sku(),
                    'stock_status' => (string) $product->get_stock_status()
                )
            );

            $event_attributes = array_merge($items[0], array(
                // Items Array
                'items' => $items,
            ));

            // Send the event
            $this->send_event('viewed_product', $event_attributes);

        } catch (Exception $e) {
            // Log the error but don't halt execution
            error_log('Usermaven: Error tracking product view - ' . $e->getMessage());
        }
    }


    /**
     * Track add to cart events
     *
     * @param string $cart_item_key
     * @param int $product_id
     * @param int $quantity
     * @param int $variation_id
     * @param array $variation
     * @param array $cart_item_data
     */
    public function track_add_to_cart(string $cart_item_key, int $product_id, int $quantity, int $variation_id, array $variation, array $cart_item_data): void {
        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }

        // Get product categories
        $categories = array();
        $terms = get_the_terms($product->get_id(), 'product_cat');
        if ($terms && !is_wp_error($terms)) {
            $categories = wp_list_pluck($terms, 'name');
        }

        // Cast variation attributes to proper format
        $variation_attributes = array();
        if (is_array($variation)) {
            foreach ($variation as $attr_key => $attr_value) {
                $variation_attributes[$attr_key] = (string) $attr_value;
            }
        }

        // Get and validate prices
        $unit_price = $product->get_price();
        $unit_price = ($unit_price === '') ? 0.0 : (float) $unit_price;
        $price_total = (float) ($quantity * $unit_price);

        // Create items array with single product
        $items = array(
            array(
                // Product Information
                'product_id'      => (int) $product_id,
                'product_name'    => (string) $product->get_name(),
                'product_sku'     => (string) $product->get_sku(),
                'product_type'    => (string) $product->get_type(),
                'categories'      => array_map('strval', $categories),
                'tags'            => array_map('strval', wp_get_post_terms($product_id, 'product_tag', array('fields' => 'names'))),
                // Quantity and Price Details
                'quantity'        => (int) $quantity,
                'unit_price'      => $unit_price,
                'regular_price'   => (float) $product->get_regular_price(),
                'sale_price'      => (float) $product->get_sale_price(),
                'price_total'     => $price_total,
                'is_on_sale'      => (bool) $product->is_on_sale(),
                'currency'        => (string) get_woocommerce_currency(),
                // Stock Information
                'stock_status'    => (string) $product->get_stock_status(),
                'stock_quantity'  => $product->get_stock_quantity() !== null ? (int) $product->get_stock_quantity() : null,
                'is_in_stock'     => (bool) $product->is_in_stock(),
                // Variation Details
                'variation_id'         => (int) $variation_id,
                'variation_attributes' => $variation_attributes
            )
        );

        $event_attributes = array_merge($items[0], array(
            'cart_total'          => (float) WC()->cart->get_cart_contents_total(),
            'cart_subtotal'       => (float) WC()->cart->get_subtotal(),
            'cart_tax'            => (float) WC()->cart->get_cart_tax(),
            'cart_items_count'    => (int) WC()->cart->get_cart_contents_count(),
            'cart_unique_items'   => (int) count(WC()->cart->get_cart()),
            'applied_coupons'     => array_map('strval', WC()->cart->get_applied_coupons()),
            // Additional Context
            'added_from'          => (string) (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : ''),
            'device_type'         => (string) (wp_is_mobile() ? 'mobile' : 'desktop'),
            'user_agent'          => (string) (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '')
        ));

        $this->send_event('added_to_cart', $event_attributes);
    }

    /**
     * Track remove from cart events
     *
     * @param string $cart_item_key
     * @param WC_Cart $cart
     */
    public function track_remove_from_cart(string $cart_item_key, WC_Cart $cart): void {
        $cart_item = isset($cart->removed_cart_contents[$cart_item_key]) ? $cart->removed_cart_contents[$cart_item_key] : null;
        if (!$cart_item) {
            return;
        }

        $product_id = $cart_item['product_id'];
        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }

        // Get product categories
        $categories = array();
        $terms = get_the_terms($product->get_id(), 'product_cat');
        if ($terms && !is_wp_error($terms)) {
            $categories = wp_list_pluck($terms, 'name');
        }

        // Process variation attributes
        $variation_attributes = array();
        if (!empty($cart_item['variation'])) {
            foreach ($cart_item['variation'] as $attr_key => $attr_value) {
                $variation_attributes[$attr_key] = (string) $attr_value;
            }
        }

        // Get line totals and ensure they're properly typed
        $line_total = !empty($cart_item['line_total']) ? (float) $cart_item['line_total'] : 0.0;
        $line_tax = !empty($cart_item['line_tax']) ? (float) $cart_item['line_tax'] : 0.0;
        $price_per_unit = $product->get_price();
        $price_per_unit = ($price_per_unit === '') ? 0.0 : (float) $price_per_unit;

        // Create items array with single product
        $items = array(
            array(
                'product_id'         => (int) $product_id,
                'product_name'       => (string) $product->get_name(),
                'product_sku'        => (string) $product->get_sku(),
                'product_type'       => (string) $product->get_type(),
                'categories'         => array_map('strval', $categories),
                'tags'               => array_map('strval', wp_get_post_terms($product_id, 'product_tag', array('fields' => 'names'))),
                // Removed Item Details
                'quantity_removed'   => (int) $cart_item['quantity'],
                'line_total'         => $line_total,
                'line_tax'           => $line_tax,
                'price_per_unit'     => $price_per_unit,
                'currency'           => (string) get_woocommerce_currency(),
                // Variation Details
                'variation_id'       => !empty($cart_item['variation_id']) ? (int) $cart_item['variation_id'] : 0,
                'variation_attributes'=> $variation_attributes
            )
        );

        $event_attributes = array_merge($items[0], array(
            'cart_total'              => (float) $cart->get_cart_contents_total(),
            'cart_subtotal'           => (float) $cart->get_subtotal(),
            'cart_tax'                => (float) $cart->get_cart_tax(),
            'remaining_items'         => (int) $cart->get_cart_contents_count(),
            'remaining_unique_items'  => (int) count($cart->get_cart()),
            'applied_coupons'         => array_map('strval', $cart->get_applied_coupons()),
            // Additional Context
            'removed_from_page'       => (string) (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : ''),
            'device_type'             => (string) (wp_is_mobile() ? 'mobile' : 'desktop'),
            'session_id'              => (string) WC()->session->get_customer_id(),
            'user_agent'              => (string) (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '')
        ));

        $this->send_event('removed_from_cart', $event_attributes);
    }

    /**
     * Track cart updates
     *
     * @param string $cart_item_key
     * @param int $quantity
     * @param int $old_quantity
     * @param WC_Cart $cart
     */
    public function track_cart_update(string $cart_item_key, int $quantity, int $old_quantity, WC_Cart $cart): void {
        $cart_item = $cart->get_cart_item($cart_item_key);
        if (!$cart_item) {
            return;
        }

        $product_id = $cart_item['product_id'];
        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }

        // Get product categories
        $categories = array();
        $terms = get_the_terms($product->get_id(), 'product_cat');
        if ($terms && !is_wp_error($terms)) {
            $categories = wp_list_pluck($terms, 'name');
        }

        // Process variation attributes
        $variation_attributes = array();
        if (!empty($cart_item['variation'])) {
            foreach ($cart_item['variation'] as $attr_key => $attr_value) {
                $variation_attributes[$attr_key] = (string) $attr_value;
            }
        }

        // Calculate line totals
        $unit_price = $product->get_price();
        $unit_price = ($unit_price === '') ? 0.0 : (float) $unit_price;
        $old_line_total = (float) ($old_quantity * $unit_price);
        $new_line_total = (float) ($quantity * $unit_price);

        // Create items array with the updated product
        $items = array(
            array(
                // Product Information
                'product_id'      => (int) $product_id,
                'product_name'    => (string) $product->get_name(),
                'product_type'    => (string) $product->get_type(),
                'categories'      => array_map('strval', $categories),
                'tags'            => array_map('strval', wp_get_post_terms($product_id, 'product_tag', array('fields' => 'names'))),
                // Quantity Change Details
                'old_quantity'    => (int) $old_quantity,
                'new_quantity'    => (int) $quantity,
                'quantity_change' => (int) ($quantity - $old_quantity),
                'price'           => (float) $unit_price,
                'old_line_total'  => (float) $old_line_total,
                'new_line_total'  => (float) $new_line_total,
                'currency'        => (string) get_woocommerce_currency(),
                // Stock Information
                'stock_status'    => (string) $product->get_stock_status(),
                'remaining_stock' => $product->get_stock_quantity() !== null ? (int) $product->get_stock_quantity() : null,
                // Variation Details
                'variation_id'    => (int) (isset($cart_item['variation_id']) ? $cart_item['variation_id'] : 0),
                'variation_attributes' => $variation_attributes
            )
        );

        $event_attributes = array_merge($items[0], array(
            // Cart State
            'items'               => $items,
            'cart_total'          => (float) $cart->get_total('numeric'),
            'cart_subtotal'       => (float) $cart->get_subtotal(),
            'cart_tax'            => (float) $cart->get_cart_tax(),
            'cart_items_count'    => (int) $cart->get_cart_contents_count(),
            'cart_unique_items'   => (int) count($cart->get_cart()),
            'cart_discount'       => (float) $cart->get_discount_total(),
            'applied_coupons'     => array_map('strval', $cart->get_applied_coupons()),
            // Additional Context
            'update_source'       => (string) (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : ''),
            'device_type'         => (string) (wp_is_mobile() ? 'mobile' : 'desktop'),
            'timestamp'           => (string) current_time('mysql'),
            'session_id'          => (string) WC()->session->get_customer_id(),
            'user_agent'          => (string) (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '')
        ));

        $this->send_event('updated_cart_item', $event_attributes);
    }

    /**
     * Single entry point for tracking checkout initialization
     * This ensures we don't have multiple competing triggers
     */
    public function maybe_track_checkout_init(): void {
        // Early exit conditions
        if (WC()->cart->is_empty()) {
            return;
        }

        // Get a unique identifier for current cart state
        $cart_hash = WC()->cart->get_cart_hash();
        
        // Get stored tracking data
        $tracked_cart_hash = WC()->session->get('usermaven_tracked_cart_hash');
        $last_tracked_time = WC()->session->get('usermaven_last_checkout_track_time');
        $current_time = time();

        // Check if this specific cart state has been tracked recently
        if ($tracked_cart_hash === $cart_hash && 
            $last_tracked_time && 
            ($current_time - $last_tracked_time) < 300) { // 5 minutes threshold
            return;
        }

        if (is_cart()) {
            return;
        }

        // Check if we're actually on a checkout page/process
        $is_checkout_context = (
            // Only track on actual checkout page, not cart page
            (function_exists('is_checkout') && is_checkout()) ||
            // For custom checkout pages
            (has_shortcode((get_post()->post_content ?? ''), 'woocommerce_checkout')) ||
            // For AJAX checkout requests
            (isset($_REQUEST['wc-ajax']) && $_REQUEST['wc-ajax'] === 'checkout')
        );

        if (!$is_checkout_context) {
            return;
        }

        $this->track_initiate_checkout($cart_hash);
        WC()->session->set('usermaven_initiate_checkout_tracked', true);
    }

    /**
     * Track checkout initiation with duplicate prevention
     *
     * @param string $cart_hash
     */
    private function track_initiate_checkout(string $cart_hash): void {
        // Prevent duplicate tracking
        if (WC()->session->get('usermaven_initiate_checkout_tracked')) {
            return;
        }

        $cart = WC()->cart;
        $items = array();

        // Get WC Countries object for location handling
        $wc_countries = new WC_Countries();
        
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            if (!($product instanceof WC_Product)) {
                continue;
            }

            // Get the parent product if this is a variation
            $parent_product = $product;
            if ($product instanceof WC_Product_Variation) {
                $parent_product = wc_get_product($product->get_parent_id());
            }

            // Get product categories from parent product
            $categories = array();
            $terms = get_the_terms($parent_product->get_id(), 'product_cat');
            if ($terms && !is_wp_error($terms)) {
                $categories = wp_list_pluck($terms, 'name');
            }

            // Process variation attributes - keeping attribute_ prefix
            $variation_attributes = array();
            if (!empty($cart_item['variation'])) {
                foreach ($cart_item['variation'] as $attr_key => $attr_value) {
                    $variation_attributes[$attr_key] = (string) $attr_value;
                }
            }

            // Get and validate prices
            $unit_price = $product->get_price();
            $unit_price = ($unit_price === '') ? 0.0 : (float) $unit_price;

            $items[] = array(
                'product_id'          => (int) $parent_product->get_id(),
                'product_name'        => (string) $parent_product->get_name(),
                'product_sku'         => (string) $product->get_sku(),
                'quantity'            => (int) $cart_item['quantity'],
                'unit_price'          => $unit_price,
                'line_total'          => (float) $cart_item['line_total'],
                'line_tax'            => (float) $cart_item['line_tax'],
                'categories'          => array_map('strval', $categories),
                'variation_id'        => isset($cart_item['variation_id']) ? (int) $cart_item['variation_id'] : null,
                'variation_attributes'=> $variation_attributes,
                'is_on_sale'          => (bool) $product->is_on_sale(),
                'stock_status'        => (string) $product->get_stock_status()
            );
        }
            
        $billing_country_code = WC()->customer->get_billing_country();
        $shipping_country_code = WC()->customer->get_shipping_country();

        $event_attributes = array(
            'cart_hash'            => $cart_hash,
            'total'                => (float) $cart->get_total('numeric'),
            'subtotal'             => (float) $cart->get_subtotal(),
            'cart_tax'             => (float) $cart->get_cart_tax(),
            'tax'                  => (float) $cart->get_total_tax(),
            'shipping_total'       => (float) $cart->get_shipping_total(),
            'discount_total'       => (float) $cart->get_discount_total(),
            'currency'             => (string) get_woocommerce_currency(),
            'items_count'          => (int) $cart->get_cart_contents_count(),
            'unique_items'         => (int) count($cart->get_cart()),
            'items'                => $items,
            'weight_total'         => (float) $cart->get_cart_contents_weight(),
            'coupons'              => array_map('strval', $cart->get_applied_coupons()),
            'coupon_discount'      => (float) $cart->get_discount_total(),
            'tax_discount'         => (float) $cart->get_discount_tax(),
            'needs_shipping'       => (bool) $cart->needs_shipping(),
            'shipping_methods'     => array_map('strval', WC()->session->get('chosen_shipping_methods') ?: array()),
            'available_payment_methods' => array_map('strval', array_keys(WC()->payment_gateways->get_available_payment_gateways())),
            'is_logged_in'         => (bool) is_user_logged_in(),
            'customer_id'          => (int) get_current_user_id(),
            'session_id'           => (string) WC()->session->get_customer_id(),
            'billing_country_code' => (string) $billing_country_code,
            'billing_country_name' => (string) ($billing_country_code ? (new WC_Countries())->countries[$billing_country_code] : ''),
            'shipping_country_code'=> (string) $shipping_country_code,
            'shipping_country_name'=> (string) ($shipping_country_code ? (new WC_Countries())->countries[$shipping_country_code] : ''),
            'payment_methods'      => array_keys(WC()->payment_gateways->get_available_payment_gateways()),
            'device_type'          => (string) (wp_is_mobile() ? 'mobile' : 'desktop'),
            'referrer'             => (string) (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : ''),
            'timestamp'            => (string) current_time('mysql'),
            'checkout_page'        => (string) (is_checkout() ? 'standard' : 'custom'),
            'user_agent'           => (string) (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '')
        );

        // Send the event
        $this->send_event('initiated_checkout', $event_attributes);

        // Update tracking state
        WC()->session->set('usermaven_tracked_cart_hash', $cart_hash);
        WC()->session->set('usermaven_last_checkout_track_time', time());
    }

    /**
     * Reset tracking state when cart is updated
     */
    public function reset_checkout_tracking(): void {
        WC()->session->set('usermaven_tracked_cart_hash', null);
        WC()->session->set('usermaven_last_checkout_track_time', null);
    }

    /**
     * Check if this is a returning customer based on previous orders
     *
     * @param int|null $customer_id Customer ID if registered
     * @param string $billing_email Customer's email address
     * @return bool True if customer has previous orders
     */
    private function is_returning_customer(?int $customer_id, string $billing_email): bool {
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
     * Calculate time taken from order creation to completion
     *
     * @param WC_Order $order The order object
     * @return float Time in hours, rounded to 2 decimal places
     */
    private function calculate_processing_time(WC_Order $order): float {
        try {
            $date_created = $order->get_date_created();
            $date_completed = $order->get_date_completed();

            if (!$date_created || !$date_completed) {
                return 0.0;
            }

            // Get the difference in seconds
            $time_diff = $date_completed->getTimestamp() - $date_created->getTimestamp();
            
            // Convert to hours and round to 2 decimal places
            return round($time_diff / 3600, 2);
        } catch (Exception $e) {
            error_log('Usermaven: Error calculating processing time - ' . $e->getMessage());
            return 0.0;
        }
    }


    /**
     * Track order status changes
     *
     * @param int $order_id
     * @param string $old_status
     * @param string $new_status
     * @param WC_Order $order
     */
    public function track_order_status_changed(int $order_id, string $old_status, string $new_status, WC_Order $order): void {
        // Track only significant status changes
        $significant_statuses = array(
            'pending', 
            'processing', 
            'on-hold', 
            'completed', 
            'cancelled', 
            'refunded', 
            'failed',
            'draft'
        );
        if (!in_array($new_status, $significant_statuses, true)) {
            return;
        }

        // Get items in consistent format
        $items = $this->get_formatted_order_items($order);
        $location_details = $this->get_location_details($order);

        $event_attributes = array(
            'order_id'       => (int) $order_id,
            'old_status'     => (string) $old_status,
            'new_status'     => (string) $new_status,
            'total'          => (float) $order->get_total(),
            'currency'       => (string) $order->get_currency(),
            'payment_method' => (string) $order->get_payment_method(),
            // Items Information
            'items'          => $items,
            'items_count'    => (int) $order->get_item_count(),
            'customer_id'    => $order->get_customer_id() ? (int) $order->get_customer_id() : null,
            'billing_email'  => (string) $order->get_billing_email(),
            'customer_note'  => (string) $order->get_customer_note(),
            // Additional Context
            'device_type'    => (string) (wp_is_mobile() ? 'mobile' : 'desktop'),
            'timestamp'      => (string) current_time('mysql')
        );

        $this->send_event('order_status_changed', $event_attributes);
    }

    /**
     * Get location details from order
     *
     * @param WC_Order $order The order object
     * @return array Location details including billing and shipping info
     */
    private function get_location_details(WC_Order $order): array {
        $wc_countries = new WC_Countries();

        // Get billing country details
        $billing_country_code = $order->get_billing_country();
        $billing_country_name = $billing_country_code && isset($wc_countries->countries[$billing_country_code]) ? 
            $wc_countries->countries[$billing_country_code] : '';

        // Get billing state details
        $billing_state_code = $order->get_billing_state();
        $billing_states = $wc_countries->get_states($billing_country_code);
        $billing_state_name = ($billing_states && isset($billing_states[$billing_state_code])) 
            ? $billing_states[$billing_state_code] 
            : '';

        // Get shipping country details
        $shipping_country_code = $order->get_shipping_country();
        
        // If shipping country is empty, use billing country
        if (empty($shipping_country_code)) {
            $shipping_country_code = $billing_country_code;
            $shipping_country_name = $billing_country_name;
        } else {
            $shipping_country_name = isset($wc_countries->countries[$shipping_country_code]) ? 
                $wc_countries->countries[$shipping_country_code] : '';
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
                : '';
        }

        return array(
            'billing_country_code' => $billing_country_code,
            'billing_country_name' => $billing_country_name,
            'billing_state_code'   => $billing_state_code,
            'billing_state_name'   => $billing_state_name,
            'shipping_country_code'=> $shipping_country_code,
            'shipping_country_name'=> $shipping_country_name,
            'shipping_state_code'  => $shipping_state_code,
            'shipping_state_name'  => $shipping_state_name
        );
    }

    /**
     * Get variation attributes for a product
     *
     * @param WC_Order_Item $item Order item
     * @param WC_Product $product Product object
     * @param int $variation_id Variation ID
     * @return array Variation attributes
     */
    private function get_variation_attributes(WC_Order_Item $item, WC_Product $product, int $variation_id): array {
        $variation_attributes = array();
        if ($variation_id) {
            // Method 1: Try getting from item metadata
            $variation_data = $item->get_meta_data();
            foreach ($variation_data as $meta) {
                if (strpos($meta->key, 'pa_') === 0) {
                    $variation_attributes['attribute_' . $meta->key] = (string) $meta->value;
                }
            }

            // Method 2: If attributes are missing, try getting from variation product
            if ($product instanceof WC_Product_Variation && count($variation_attributes) < 2) {
                $attributes = $product->get_variation_attributes();
                foreach ($attributes as $key => $value) {
                    $attr_key = str_replace('attribute_', '', strtolower($key));
                    if (!isset($variation_attributes['attribute_' . $attr_key])) {
                        $variation_attributes['attribute_' . $attr_key] = (string) $value;
                    }
                }
            }
        }
        return $variation_attributes;
    }

    /**
     * Get formatted order items with all details
     *
     * @param WC_Order|WC_Order_Refund $order The order or refund object
     * @param bool $is_refund Whether this is for a refund
     * @return array Formatted order items
     */
    private function get_formatted_order_items(WC_Order $order, bool $is_refund = false): array {
        $items = array();
        foreach ($order->get_items() as $item) {
            $product_data = $item->get_data();
            $product = wc_get_product($product_data['product_id']);
            if (!$product) {
                continue;
            }

            // Get parent product if variation
            $parent_product = $product;
            $variation_id = $item->get_variation_id();
            if ($variation_id) {
                $parent_product = wc_get_product($product->get_id());
                $product = wc_get_product($variation_id);
                if (!$parent_product || !$product) {
                    continue;
                }
            }

            // Get product categories
            $categories = $this->get_product_categories($parent_product);
            $variation_attributes = $this->get_variation_attributes($item, $product, $variation_id);

            $item_data = array(
                'product_id'         => (int) $parent_product->get_id(),
                'product_name'       => (string) $parent_product->get_name(),
                'quantity'           => (int) ($is_refund ? abs($item->get_quantity()) : $item->get_quantity()),
                'price'              => (float) $product->get_price(),
                'subtotal'           => (float) ($is_refund ? abs($item->get_subtotal()) : $item->get_subtotal()),
                'total'              => (float) ($is_refund ? abs($item->get_total()) : $item->get_total()),
                'sku'                => (string) $product->get_sku(),
                'categories'         => array_map('strval', $categories),
                'variation_id'       => $variation_id ? (int) $variation_id : null,
                'variation_attributes'=> $variation_attributes,
                'tax'                => (float) ($is_refund ? abs($item->get_total_tax()) : $item->get_total_tax())
            );

            if ($is_refund) {
                $item_data['refund_total'] = (float) abs($item->get_total());
                $item_data['refund_tax'] = (float) abs($item->get_total_tax());
            }

            $items[] = $item_data;
        }

        return $items;
    }

    /**
     * Get shipping methods from order
     *
     * @param WC_Order $order The order object
     * @return array Shipping methods
     */
    private function get_shipping_methods(WC_Order $order): array {
        $shipping_methods = array();
        foreach ($order->get_shipping_methods() as $shipping_method) {
            $shipping_methods[] = array(
                'method_id'    => (string) $shipping_method->get_method_id(),
                'method_title' => (string) $shipping_method->get_method_title(),
                'total'        => (float) $shipping_method->get_total(),
                'total_tax'    => (float) $shipping_method->get_total_tax()
            );
        }
        return $shipping_methods;
    }

    /**
     * Get common order attributes
     *
     * @param WC_Order $order The order object
     * @param array $location_details Location details from get_location_details()
     * @return array Common order attributes
     */
    private function get_common_order_attributes(WC_Order $order, array $location_details): array {
        return array(
            // Order Information
            'order_id'           => (int) $order->get_id(),
            'order_currency'     => (string) $order->get_currency(),
            'created_via'        => (string) $order->get_created_via(),
            'order_version'      => (string) $order->get_version(),
            'prices_include_tax' => (bool) $order->get_prices_include_tax(),
            'order_key'          => (string) $order->get_order_key(),
            'order_number'       => (string) $order->get_order_number(),
            'order_status'       => (string) $order->get_status(),
            
            // Financial Details
            'total'              => (float) $order->get_total(),
            'subtotal'           => (float) $order->get_subtotal(),
            'tax_total'          => (float) $order->get_total_tax(),
            'shipping_total'     => (float) $order->get_shipping_total(),
            'discount_total'     => (float) $order->get_total_discount(),
            'cart_tax'           => (float) $order->get_cart_tax(),
            'shipping_tax'       => (float) $order->get_shipping_tax(),
            'discount_tax'       => (float) $order->get_discount_tax(),
            
            // Payment Details
            'payment_method'     => (string) $order->get_payment_method(),
            'payment_method_title'=> (string) $order->get_payment_method_title(),
            'transaction_id'     => (string) $order->get_transaction_id(),
            'date_paid'          => $order->get_date_paid() ? (string) $order->get_date_paid()->format('Y-m-d H:i:s') : null,
            
            // Customer Information
            'customer_id'       => $order->get_customer_id() ? (int) $order->get_customer_id() : null,
            'customer_note'     => (string) $order->get_customer_note(),
            'customer_type'     => (string) ($order->get_customer_id() ? 'registered' : 'guest'),
            'is_registered_customer' => (bool) $order->get_customer_id(),
            
            // Billing Details
            'billing_email'     => (string) $order->get_billing_email(),
            'billing_phone'     => (string) $order->get_billing_phone(),
            'billing_first_name'=> (string) $order->get_billing_first_name(),
            'billing_last_name' => (string) $order->get_billing_last_name(),
            'billing_company'   => (string) $order->get_billing_company(),
            'billing_address_1' => (string) $order->get_billing_address_1(),
            'billing_address_2' => (string) $order->get_billing_address_2(),
            'billing_city'      => (string) $order->get_billing_city(),
            'billing_state'     => (string) $location_details['billing_state_name'],
            'billing_state_code'=> (string) $location_details['billing_state_code'],
            'billing_postcode'  => (string) $order->get_billing_postcode(),
            'billing_country'   => (string) $location_details['billing_country_name'],
            'billing_country_code'=> (string) $location_details['billing_country_code'],
            
            // Shipping Details
            'shipping_first_name'=> (string) ($order->get_shipping_first_name() ?: $order->get_billing_first_name()),
            'shipping_last_name' => (string) ($order->get_shipping_last_name() ?: $order->get_billing_last_name()),
            'shipping_company'   => (string) ($order->get_shipping_company() ?: $order->get_billing_company()),
            'shipping_address_1' => (string) $order->get_shipping_address_1(),
            'shipping_address_2' => (string) $order->get_shipping_address_2(),
            'shipping_city'      => (string) ($order->get_shipping_city() ?: $order->get_billing_city()),
            'shipping_state'     => (string) $location_details['shipping_state_name'],
            'shipping_state_code'=> (string) $location_details['shipping_state_code'],
            'shipping_postcode'  => (string) ($order->get_shipping_postcode() ?: $order->get_billing_postcode()),
            'shipping_country'   => (string) $location_details['shipping_country_name'],
            'shipping_country_code'=> (string) $location_details['shipping_country_code'],
            'shipping_same_as_billing' => (bool) empty($order->get_shipping_country())
        );
    }

    /**
     * Early tracking for order submission to catch all checkout types
     *
     * @param int $order_id
     */
    public function track_order_submission(int $order_id): void {
        error_log('track_order_submission early triggered for order: ' . $order_id);

        WC()->session->set('usermaven_initiate_checkout_tracked', null);

        if (!$order_id) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Check if this order has already been tracked
        $tracked = get_post_meta($order_id, '_usermaven_order_tracked', true);
        if ($tracked) {
            return;
        }

        // Reset checkout tracking
        $this->reset_checkout_tracking();

        // Get user identification details for user identify event
        $user_id = $order->get_customer_id();
        $user = get_user_by('id', $user_id);
        $user_email = $user ? $user->user_email : $order->get_billing_email();

        if (!$user) {
            return;

            // We are not tracking guest users right now. 

            // TODO: Add guest user tracking (Need to Discuss with Amad bhai)
            // $user = array(
            //     'anonymous_id' => $this->get_anonymous_id(),
            //     'id' => $user_id ? (string)$user_id : $user_email, // Use email as ID for guests
            //     'email' => $user_email,
            //     'created_at' => '', // Empty for guest users
            //     'first_name' => $order->get_billing_first_name(),
            //     'last_name' => $order->get_billing_last_name(),
            //     'custom' => array(
            //         'type' => $user ? 'registered' : 'guest',
            //         'role' => '',
            //         'username' => '',
            //         'display_name' => '',
            //         'billing_company' => $order->get_billing_company(),
            //         'billing_email' => $billing_email,
            //         'billing_phone' => $order->get_billing_phone(),
            //         'billing_postcode' => $order->get_billing_postcode(),
            //         'billing_city' => $order->get_billing_city(),
            //         'billing_state' => $order->get_billing_state(),
            //         'billing_country' => $order->get_billing_country(),
            //         'billing_address_1' => $order->get_billing_address_1(),
            //         'billing_address_2' => $order->get_billing_address_2(),
            //     )
            // );
        }

        $this->send_user_identify_request($user);

        // Rest of your tracking code
        $location_details = $this->get_location_details($order);
        $items = $this->get_formatted_order_items($order);

        // event attributes
        $event_attributes = array_merge(
            $this->get_common_order_attributes($order, $location_details),
            array(
                // Customer-specific details
                'is_returning_customer' => (bool) $this->is_returning_customer(
                    $order->get_customer_id(), 
                    $order->get_billing_email()
                ),

                // Items Information
                'items_count'      => (int) $order->get_item_count(),
                'items'            => $items,

                // Additional Details
                'customer_note'    => (string) $order->get_customer_note(),
                'order_status'     => (string) $order->get_status(),
                'coupon_codes'     => array_map('strval', $order->get_coupon_codes()),
                'timestamp'        => (string) current_time('mysql')
            )
        );

        $this->send_event('order_submitted', $event_attributes);

        // Mark this order as tracked
        update_post_meta($order_id, '_usermaven_order_tracked', true);
    }


    /**
     * Track completed orders
     *
     * @param int $order_id
     */
    public function track_order_completed(int $order_id): void {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $location_details = $this->get_location_details($order);
        $items = $this->get_formatted_order_items($order);
        $shipping_methods = $this->get_shipping_methods($order);

        // Get common attributes and merge with completion-specific ones
        $event_attributes = array_merge(
            $this->get_common_order_attributes($order, $location_details),
            array(
                // Customer-specific details
                'is_returning_customer' => (bool) $this->is_returning_customer(
                    $order->get_customer_id(), 
                    $order->get_billing_email()
                ),
                // Items Details
                'items'            => $items,
                'items_count'      => (int) $order->get_item_count(),

                // Shipping Details
                'shipping_methods' => $shipping_methods,

                // Customer-specific details
                'is_first_order'   => (bool) $this->is_first_order($order->get_customer_id()),
                'customer_ip_address' => (string) $order->get_customer_ip_address(),
                'customer_user_agent' => (string) $order->get_customer_user_agent(),
                
                // Marketing Data
                'coupons_used'     => array_map('strval', $order->get_coupon_codes()),
                'marketing_source' => (string) get_post_meta($order_id, '_marketing_source', true),
                'marketing_medium' => (string) get_post_meta($order_id, '_marketing_medium', true),
                'marketing_campaign'=> (string) get_post_meta($order_id, '_marketing_campaign', true),
                
                // Additional Context
                'customer_note'    => (string) $order->get_customer_note(),
                'completion_date'  => (string) current_time('mysql'),
                'order_processing_time' => (float) $this->calculate_processing_time($order),
                'device_type'      => (string) (wp_is_mobile() ? 'mobile' : 'desktop'),
                'timestamp'        => (string) current_time('mysql')
            )
        );

        $this->send_event('order_completed', $event_attributes);
    }

    /**
     * Add missing first order check
     *
     * @param int|null $customer_id
     * @return bool
     */
    private function is_first_order(?int $customer_id): bool {
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
     *
     * @param WC_Order $order
     * @return array
     */
    private function get_order_items(WC_Order $order): array {
        $items = array();
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) {
                continue;
            }

            $items[] = array(
                'product_id'   => $product->get_id(),
                'product_name' => $product->get_name(),
                'quantity'     => $item->get_quantity(),
                'price'        => $product->get_price(),
                'subtotal'     => $item->get_subtotal(),
                'total'        => $item->get_total(),
                'tax'          => $item->get_total_tax()
            );
        }
        return $items;
    }

    /**
     * Track failed order events
     *
     * @param int $order_id
     * @param WC_Order $order
     */
    public function track_order_failed(int $order_id, WC_Order $order): void {
        if (!$order instanceof WC_Order) {
            return;
        }

        $location_details = $this->get_location_details($order);
        $items = $this->get_formatted_order_items($order);

        // Get gateway response
        $payment_gateway = wc_get_payment_gateway_by_order($order);
        $gateway_response = '';
        if ($payment_gateway && method_exists($payment_gateway, 'get_last_error')) {
            $gateway_response = $payment_gateway->get_last_error();
        }

        $event_attributes = array_merge(
            $this->get_common_order_attributes($order, $location_details),
            array(
                // Items Information
                'items'             => $items,
                'items_count'       => (int) $order->get_item_count(),

                // Customer-specific details
                'customer_ip_address'=> (string) $order->get_customer_ip_address(),
                'customer_user_agent'=> (string) $order->get_customer_user_agent(),
                'customer_note'     => (string) $order->get_customer_note(),

                // Payment Failure Details
                'gateway_response'  => (string) $gateway_response,
                'failure_codes'     => array_map('strval', (array) get_post_meta($order_id, '_failure_codes', true)),
                'attempts'          => (int) get_post_meta($order_id, '_retry_attempts', true),
                
                // Additional Context
                'device_type'       => (string) (wp_is_mobile() ? 'mobile' : 'desktop'),
                'timestamp'         => (string) current_time('mysql'),
                'cart_hash'         => (string) $order->get_cart_hash()
            )
        );

        $this->send_event('order_failed', $event_attributes);
    }


    /**
     * Track processing orders
     *
     * Triggered when an order status is changed to "processing"
     * 
     * @param int $order_id
     */
    public function track_order_processing(int $order_id): void {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $location_details = $this->get_location_details($order);
        $items = $this->get_formatted_order_items($order);
        $shipping_methods = $this->get_shipping_methods($order);

        $event_attributes = array_merge(
            $this->get_common_order_attributes($order, $location_details),
            array(
                // Items Information
                'items'             => $items,
                'items_count'       => (int) $order->get_item_count(),
                'shipping_methods'  => $shipping_methods,

                // Customer Information
                'customer_ip_address'=> (string) $order->get_customer_ip_address(),
                'customer_user_agent'=> (string) $order->get_customer_user_agent(),
                'customer_note'     => (string) $order->get_customer_note(),
                
                // Additional Context
                'device_type'       => (string) (wp_is_mobile() ? 'mobile' : 'desktop'),
                'timestamp'         => (string) current_time('mysql')
            )
        );

        $this->send_event('order_in_processing', $event_attributes);
    }


    /**
     * Track pending orders
     *
     * Triggered when an order status is set to "pending"
     * 
     * @param int $order_id
     */
    public function track_order_pending(int $order_id): void {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $location_details = $this->get_location_details($order);
        $items = $this->get_formatted_order_items($order);
        $shipping_methods = $this->get_shipping_methods($order);

        $event_attributes = array_merge(
            $this->get_common_order_attributes($order, $location_details),
            array(
                // Items Information
                'items'             => $items,
                'items_count'       => (int) $order->get_item_count(),
                'shipping_methods'  => $shipping_methods,
                
                // Customer Information
                'customer_ip_address'=> (string) $order->get_customer_ip_address(),
                'customer_user_agent'=> (string) $order->get_customer_user_agent(),
                'customer_note'     => (string) $order->get_customer_note(),
                
                // Additional Context
                'device_type'       => (string) (wp_is_mobile() ? 'mobile' : 'desktop'),
                'timestamp'         => (string) current_time('mysql'),
                'cart_hash'         => (string) $order->get_cart_hash()
            )
        );

        $this->send_event('order_pending', $event_attributes);
    }

    /**
     * Track orders that are placed on hold
     *
     * @param int $order_id
     */
    public function track_order_on_hold(int $order_id): void {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $location_details = $this->get_location_details($order);
        $items = $this->get_formatted_order_items($order);
        $shipping_methods = $this->get_shipping_methods($order);

        $event_attributes = array_merge(
            $this->get_common_order_attributes($order, $location_details),
            array(
                // Items Information
                'items'             => $items,
                'items_count'       => (int) $order->get_item_count(),
                'shipping_methods'  => $shipping_methods,
                
                // Customer Information
                'customer_ip_address'=> (string) $order->get_customer_ip_address(),
                'customer_user_agent'=> (string) $order->get_customer_user_agent(),
                'customer_note'     => (string) $order->get_customer_note(),
                
                // On Hold Specific Details
                'hold_reason'       => (string) get_post_meta($order_id, '_hold_reason', true),
                'hold_date'         => (string) current_time('mysql'),
                
                // Additional Context
                'device_type'       => (string) (wp_is_mobile() ? 'mobile' : 'desktop'),
                'timestamp'         => (string) current_time('mysql')
            )
        );

        $this->send_event('order_on_hold', $event_attributes);
    }


    /**
     * Track draft orders
     *
     * @param int $order_id
     */
    public function track_order_draft(int $order_id): void {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $location_details = $this->get_location_details($order);
        $items = $this->get_formatted_order_items($order);
        $shipping_methods = $this->get_shipping_methods($order);

        $event_attributes = array_merge(
            $this->get_common_order_attributes($order, $location_details),
            array(
                // Items Information
                'items'             => $items,
                'items_count'       => (int) $order->get_item_count(),
                'shipping_methods'  => $shipping_methods,
                
                // Customer Information
                'customer_ip_address'=> (string) $order->get_customer_ip_address(),
                'customer_user_agent'=> (string) $order->get_customer_user_agent(),
                'customer_note'     => (string) $order->get_customer_note(),
                
                // Draft Specific Details
                'draft_creator_id'  => (int) get_post_field('post_author', $order_id),
                'draft_created_date'=> (string) get_post_field('post_date', $order_id),
                'draft_modified_date'=> (string) get_post_field('post_modified', $order_id),
                'is_auto_draft'     => (bool) (get_post_status($order_id) === 'auto-draft'),
                
                // Additional Context
                'device_type'       => (string) (wp_is_mobile() ? 'mobile' : 'desktop'),
                'timestamp'         => (string) current_time('mysql'),
                'cart_hash'         => (string) $order->get_cart_hash()
            )
        );

        $this->send_event('order_draft', $event_attributes);
    }

    /**
     * Track cancelled orders
     *
     * @param int $order_id
     */
    public function track_order_cancelled(int $order_id): void {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $location_details = $this->get_location_details($order);
        $items = $this->get_formatted_order_items($order);
        $shipping_methods = $this->get_shipping_methods($order);

        $event_attributes = array_merge(
            $this->get_common_order_attributes($order, $location_details),
            array(
                // Items Information
                'items'             => $items,
                'items_count'       => (int) $order->get_item_count(),
                'shipping_methods'  => $shipping_methods,

                // Customer Information
                'customer_ip_address'=> (string) $order->get_customer_ip_address(),
                'customer_user_agent'=> (string) $order->get_customer_user_agent(),
                'customer_note'     => (string) $order->get_customer_note(),

                // Cancellation Details
                'cancellation_reason'=> (string) get_post_meta($order_id, '_cancellation_reason', true),
                'cancelled_by'      => (string) get_post_meta($order_id, '_cancelled_by', true),
                'cancellation_date' => (string) current_time('mysql'),

                // Additional Context
                'device_type'       => (string) (wp_is_mobile() ? 'mobile' : 'desktop'),
                'timestamp'         => (string) current_time('mysql'),
                'cart_hash'         => (string) $order->get_cart_hash()
            )
        );

        $this->send_event('order_cancelled', $event_attributes);
    }

    /**
     * Track order thank you page view
     *
     * @param int $order_id
     */
    public function track_order_thankyou(int $order_id): void {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $items = $this->get_formatted_order_items($order);

        $event_attributes = array(
            'order_id'      => (int) $order_id,
            'total'         => (float) $order->get_total(),
            'currency'      => (string) $order->get_currency(),
            'payment_method'=> (string) $order->get_payment_method(),
            'status'        => (string) $order->get_status(),
            'items_count'   => (int) $order->get_item_count(),
            'items'         => $items,
            'billing_email' => (string) $order->get_billing_email(),
            'timestamp'     => (string) current_time('mysql')
        );

        $this->send_event('order_thankyou_page_viewed', $event_attributes);    
    }

    /**
     * Track refunded orders
     *
     * @param int|WC_Order $order_id
     * @param int|WC_Order $refund_id
     */
    public function track_order_refunded(int|WC_Order $order_id, int|WC_Order $refund_id): void {
        // Handle cases where order/refund objects are passed directly
        if (is_object($order_id)) {
            $order = $order_id;
            $order_id = $order->get_id();
        } else {
            $order = wc_get_order($order_id);
        }

        if (is_object($refund_id)) {
            $refund = $refund_id;
            $refund_id = $refund->get_id();
        } else {
            $refund = wc_get_order($refund_id);
        }

        if (!$order || !$refund) {
            return;
        }

        $location_details = $this->get_location_details($order);
        $shipping_methods = $this->get_shipping_methods($order);
        
        // Get refunded items details
        $refunded_items = array();
        foreach ($refund->get_items() as $item) {
            $product_data = $item->get_data();
            $product = wc_get_product($product_data['product_id']);
            if (!$product) {
                continue;
            }

            // Get parent product if variation
            $parent_product = $product;
            $variation_id = $item->get_variation_id();
            if ($variation_id) {
                $parent_product = wc_get_product($product->get_id());
                $product = wc_get_product($variation_id);
                if (!$parent_product || !$product) {
                    continue;
                }
            }

            // Get product categories
            $categories = array();
            $terms = get_the_terms($parent_product->get_id(), 'product_cat');
            if ($terms && !is_wp_error($terms)) {
                $categories = wp_list_pluck($terms, 'name');
            }

            // Process variation attributes
            $variation_attributes = array();
            if ($variation_id) {
                $item_data = $item->get_meta_data();
                foreach ($item_data as $meta) {
                    if (strpos($meta->key, 'pa_') === 0) {
                        $variation_attributes['attribute_' . $meta->key] = (string) $meta->value;
                    }
                }
            }

            $refunded_items[] = array(
                'product_id'         => (int) $parent_product->get_id(),
                'product_name'       => (string) $parent_product->get_name(),
                'quantity'           => (int) abs($item->get_quantity()),
                'refund_total'       => (float) abs($item->get_total()),
                'refund_tax'         => (float) abs($item->get_total_tax()),
                'sku'                => (string) $product->get_sku(),
                'categories'         => array_map('strval', $categories),
                'variation_id'       => $variation_id ? (int) $variation_id : null,
                'variation_attributes'=> $variation_attributes
            );
        }

        // Merge common order attributes with refund-specific attributes
        $event_attributes = array_merge(
            $this->get_common_order_attributes($order, $location_details),
            array(
                // Refund Information
                'refund_id'           => (int) $refund_id,

                // Refund Details
                'refund_amount'       => (float) abs($refund->get_total()),
                'refund_reason'       => (string) get_post_meta($refund_id, '_refund_reason', true),
                'refund_date'         => (string) $refund->get_date_created()->format('Y-m-d H:i:s'),
                'refund_author'       => (int) get_post_meta($refund_id, '_refunded_by', true),
                'is_partial_refund'   => (bool) (abs($refund->get_total()) < $order->get_total()),
                
                // Financial Details
                'original_order_total'=> (float) $order->get_total(),
                'remaining_order_total'=> (float) $order->get_remaining_refund_amount(),
                'refunded_tax'        => (float) abs($refund->get_total_tax()),
                'refunded_shipping'   => (float) abs($refund->get_shipping_total()),
                
                // Order Contents
                'refunded_items_count'=> (int) count($refunded_items),
                'refunded_items'      => $refunded_items,
                'shipping_methods'    => $shipping_methods,
                
                // Customer Information
                'customer_ip_address' => (string) $order->get_customer_ip_address(),
                'customer_user_agent' => (string) $order->get_customer_user_agent(),
                'customer_note'       => (string) $order->get_customer_note(),
                
                // Additional Context
                'device_type'         => (string) (wp_is_mobile() ? 'mobile' : 'desktop'),
                'timestamp'           => (string) current_time('mysql')
            )
        );

        $this->send_event('order_refunded', $event_attributes);
    }

    /**
     * Track new customer creation
     *
     * @param int $customer_id
     * @param array $new_customer_data
     * @param bool $password_generated
     */
    public function track_customer_created(int $customer_id, array $new_customer_data, bool $password_generated): void {
        $customer = new WC_Customer($customer_id);
        $user = get_user_by('id', $customer_id);
        $user_email = $user ? $user->user_email : $new_customer_data['user_email'];

        if (!$user) {
            $user = array(
                'anonymous_id' => $this->get_anonymous_id(),
                'id'           => $customer_id ? (string) $customer_id : $user_email,
                'email'        => $user_email,
                'created_at'   => '',
                'first_name'   => $customer->get_billing_first_name(),
                'last_name'    => $customer->get_billing_last_name(),
                'custom'       => array(
                    'type' => $user ? 'registered' : 'guest',
                    'role' => $new_customer_data['role'],
                    'username' => '',
                    'display_name' => '',
                    'billing_company' => $customer->get_billing_company(),
                    'billing_email' => $customer->get_billing_email(),
                    'billing_phone' => $customer->get_billing_phone(),
                    'billing_postcode' => $customer->get_billing_postcode(),
                    'billing_city' => $customer->get_billing_city(),
                    'billing_state' => $customer->get_billing_state(),
                    'billing_country' => $customer->get_billing_country(),
                    'billing_address_1' => $customer->get_billing_address_1(),
                    'billing_address_2' => $customer->get_billing_address_2(),
                )
            );
        }

        $this->send_user_identify_request($user);


        $event_attributes = array(
            'customer_id'     => $customer_id,
            'email'           => $new_customer_data['user_email'],
            'username'        => $new_customer_data['user_login'],
            'password_generated' => $password_generated,
            'billing_email'   => (string) get_user_meta($customer_id, 'billing_email', true),
            'billing_country' => (string) get_user_meta($customer_id, 'billing_country', true),
            'shipping_country'=> (string) get_user_meta($customer_id, 'shipping_country', true)
        );

        $this->send_event('customer_created', $event_attributes);
    }

    /**
     * Helper method to get product categories
     *
     * @param WC_Product $product
     * @return array
     */
    private function get_product_categories(WC_Product $product): array {
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
     * Schedule cart abandonment check
     */
    public function schedule_cart_abandonment_check(): void {
        if (!wp_next_scheduled('usermaven_check_cart_abandonment')) {
            wp_schedule_event(time(), 'hourly', 'usermaven_check_cart_abandonment');
        }
    }


    /**
     * Track cart abandonment event
     */
    public function track_cart_abandonment(): void {
        $cart = WC()->cart;
        if (!$cart || $cart->is_empty()) {
            return;
        }

        $items = array();
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            if (!($product instanceof WC_Product)) {
                continue;
            }

            $parent_product = $product;
            if ($product instanceof WC_Product_Variation) {
                $parent_product = wc_get_product($product->get_parent_id());
            }

            $categories = array();
            $terms = get_the_terms($parent_product->get_id(), 'product_cat');
            if ($terms && !is_wp_error($terms)) {
                $categories = wp_list_pluck($terms, 'name');
            }

            $items[] = array(
                'product_id'         => (int) $product->get_id(),
                'product_name'       => (string) $product->get_name(),
                'quantity'           => (int) $cart_item['quantity'],
                'price'              => (float) $product->get_price(),
                'line_total'         => (float) $cart->get_product_subtotal($product, $cart_item['quantity']),
                'sku'                => (string) $product->get_sku(),
                'categories'         => array_map('strval', $categories),
                'variation_id'       => isset($cart_item['variation_id']) ? (int) $cart_item['variation_id'] : null,
                'variation_attributes'=> isset($cart_item['variation']) ? $cart_item['variation'] : array()
            );
        }

        // Get applied coupons
        $applied_coupons = array();
        foreach ($cart->get_applied_coupons() as $coupon_code) {
            $coupon = new WC_Coupon($coupon_code);
            $applied_coupons[] = array(
                'code'          => (string) $coupon_code,
                'discount_type' => (string) $coupon->get_discount_type(),
                'amount'        => (float) $coupon->get_amount()
            );
        }

        $event_attributes = array(
            // Cart Information
            'cart_total'          => (float) $cart->get_cart_contents_total(),
            'cart_subtotal'       => (float) $cart->get_subtotal(),
            'cart_tax'            => (float) $cart->get_cart_tax(),
            'cart_discount'       => (float) $cart->get_discount_total(),
            'cart_shipping_total' => (float) $cart->get_shipping_total(),
            'currency'            => (string) get_woocommerce_currency(),
            
            // Cart Contents
            'items_count'         => (int) $cart->get_cart_contents_count(),
            'unique_items'        => (int) count($cart->get_cart()),
            'items'               => $items,
            'applied_coupons'     => $applied_coupons,
            
            // Customer Information
            'customer_id'         => is_user_logged_in() ? (int) get_current_user_id() : null,
            'customer_type'       => (string) (is_user_logged_in() ? 'registered' : 'guest'),
            'customer_email'      => (string) (is_user_logged_in() ? wp_get_current_user()->user_email : ''),
            
            // Session Information
            'session_id'          => (string) WC()->session->get_customer_id(),
            'session_start'       => (string) WC()->session->get('session_start'),
            'cart_hash'           => (string) $cart->get_cart_hash(),
            'time_spent'          => WC()->session->get('session_start') ? (int) (time() - WC()->session->get('session_start')) : null,
            
            // Abandonment Context
            'abandonment_time'    => (string) current_time('mysql'),
            'last_activity'       => (string) WC()->session->get('last_activity'),
            'checkout_started'    => (bool) WC()->session->get('checkout_started'),
            
            // Source Information
            'landing_page'        => (string) WC()->session->get('landing_page'),
            'device_type'         => (string) (wp_is_mobile() ? 'mobile' : 'desktop'),
            'user_agent'          => (string) (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''),
            
            // Additional Context
            'timestamp'           => (string) current_time('mysql')
        );

        $this->send_event('cart_abandoned', $event_attributes);

        // Update or reset last_activity to prevent repeated triggers
        WC()->session->set('last_activity', time());
    }

    /**
     * Initialize cart abandonment tracking
     */
    public function init_cart_abandonment_tracking(): void {
        // Safety check for WooCommerce
        if (!function_exists('WC') || !WC() || !WC()->session) {
            return;
        }
    
        // Set session start time if not already set 
        if (!WC()->session->get('session_start')) {
            WC()->session->set('session_start', time());
        }

        // Set landing page if not set
        if (!WC()->session->get('landing_page') && isset($_SERVER['REQUEST_URI'])) {
            WC()->session->set('landing_page', $_SERVER['REQUEST_URI']);
        }

        // Do NOT update last_activity on every page load — only when user interacts with the cart or starts checkout.
        // This prevents continuous resets that block abandonment detection.

        // Mark checkout start as activity
        add_action('woocommerce_before_checkout_form', function(): void {
            WC()->session->set('checkout_started', true);
            $this->update_last_activity();
        });

        // Update last_activity on cart actions that indicate user presence
        add_action('woocommerce_add_to_cart', array($this, 'update_last_activity'));
        add_action('woocommerce_cart_item_removed', array($this, 'update_last_activity'));
        add_action('woocommerce_after_cart_item_quantity_update', array($this, 'update_last_activity'));
    }

    /**
     * Update the last activity timestamp
     */
    public function update_last_activity(): void {
        if (WC()->session) {
            WC()->session->set('last_activity', time());
        }
    }

    /**
     * Check for cart abandonment conditions on login
     *
     * @param string $user_login
     * @param WP_User $user
     */
    public function check_cart_abandonment_on_login(string $user_login, WP_User $user): void {
        $this->check_cart_abandonment();
    }

    /**
     * Check for cart abandonment conditions on logout
     */
    public function track_cart_abandonment_on_logout(): void {
        $this->track_cart_abandonment();
    }

    /**
     * Check cart abandonment conditions on shutdown
     */
    public function check_cart_abandonment(): void {
        if (!function_exists('WC') || !WC()->cart || !WC()->session) {
            return;
        }

        // If cart is empty, no abandonment to track
        if (WC()->cart->is_empty()) {
            return;
        }

        $last_activity = WC()->session->get('last_activity');
       
       // If no last_activity recorded or user recently interacted, do nothing
        if (!$last_activity) {
            return;
        }

        $current_time = time();
        $inactivity_threshold = 1800; // 30 minutes

        // If it's been more than 30 minutes since last activity, track abandonment
        if (($current_time - $last_activity) > $inactivity_threshold) {
            $this->track_cart_abandonment();
        }
    }

    /**
     * Track when items are added to wishlist
     *
     * @param int $product_id
     * @param int $wishlist_id
     * @param int $user_id
     */
    public function track_add_to_wishlist(int $product_id, int $wishlist_id, int $user_id): void {
        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }

        $wishlist = YITH_WCWL()->get_wishlist_detail($wishlist_id);
        
        // Create items array with single product
        $items = array(
            array(
                'product_id'     => (int) $product_id,
                'product_name'   => (string) $product->get_name(),
                'product_price'  => (float) $product->get_price(),
                'product_type'   => (string) $product->get_type(),
                'product_sku'    => (string) $product->get_sku(),
                'categories'     => array_map('strval', $this->get_product_categories($product)),
                'is_in_stock'    => (bool) $product->is_in_stock(),
                'is_on_sale'     => (bool) $product->is_on_sale(),
                'currency'       => (string) get_woocommerce_currency(),
            )
        );
        
        $event_attributes = array_merge($items[0], array(
            'items'                     => $items,
            'wishlist_id'               => (int) $wishlist_id,
            'wishlist_name'             => (string) (isset($wishlist['wishlist_name']) ? $wishlist['wishlist_name'] : 'Default'),
            'user_id'                   => (int) $user_id,
            'total_items_in_wishlist'   => (int) YITH_WCWL()->count_products($wishlist_id)
        ));

        $this->send_event('added_to_wishlist', $event_attributes);
    }

    /**
     * Track when items are removed from wishlist
     *
     * @param int $product_id
     * @param int $wishlist_id
     */
    public function track_remove_from_wishlist(int $product_id, int $wishlist_id): void {
        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }

        $wishlist = YITH_WCWL()->get_wishlist_detail($wishlist_id);
        $user_id = get_current_user_id();

        $items = array(
            array(
                'product_id'     => (int) $product_id,
                'product_name'   => (string) $product->get_name(),
                'product_price'  => (float) $product->get_price(),
                'product_type'   => (string) $product->get_type(),
                'product_sku'    => (string) $product->get_sku(),
                'categories'     => array_map('strval', $this->get_product_categories($product)),
                'is_in_stock'    => (bool) $product->is_in_stock(),
                'is_on_sale'     => (bool) $product->is_on_sale(),
                'currency'       => (string) get_woocommerce_currency(),
            )
        );

        $event_attributes = array_merge($items[0], array(
            'items'                     => $items,
            'wishlist_id'               => (int) $wishlist_id,
            'wishlist_name'             => (string) (isset($wishlist['wishlist_name']) ? $wishlist['wishlist_name'] : 'Default'),
            'wishlist_token'            => (string) (isset($wishlist['wishlist_token']) ? $wishlist['wishlist_token'] : ''),
            'user_id'                   => (int) $user_id,
            'remaining_items_in_wishlist'=> (int) YITH_WCWL()->count_products($wishlist_id)
        ));

        $this->send_event('removed_from_wishlist', $event_attributes);
    }

    /**
     * Track when items are moved between wishlists
     *
     * @param int $product_id
     * @param int $wishlist_from_id
     * @param int $wishlist_to_id
     * @param int $user_id
     */
    public function track_move_to_another_wishlist(int $product_id, int $wishlist_from_id, int $wishlist_to_id, int $user_id): void {
        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }

        $from_wishlist = YITH_WCWL()->get_wishlist_detail($wishlist_from_id);
        $to_wishlist = YITH_WCWL()->get_wishlist_detail($wishlist_to_id);
        
        // Create items array with single product
        $items = array(
            array(
                'product_id'     => (int) $product_id,
                'product_name'   => (string) $product->get_name(),
                'product_price'  => (float) $product->get_price(),
                'product_type'   => (string) $product->get_type(),
                'product_sku'    => (string) $product->get_sku(),
                'categories'     => array_map('strval', $this->get_product_categories($product)),
                'is_in_stock'    => (bool) $product->is_in_stock(),
                'is_on_sale'     => (bool) $product->is_on_sale(),
                'currency'       => (string) get_woocommerce_currency(),
            )
        );

        $event_attributes = array_merge($items[0], array(
            'items'                      => $items,
            'from_wishlist_id'           => (int) $wishlist_from_id,
            'from_wishlist_name'         => (string) (isset($from_wishlist['wishlist_name']) ? $from_wishlist['wishlist_name'] : 'Default'),
            'to_wishlist_id'             => (int) $wishlist_to_id,
            'to_wishlist_name'           => (string) (isset($to_wishlist['wishlist_name']) ? $to_wishlist['wishlist_name'] : 'Default'),
            'user_id'                    => (int) $user_id,
            'items_in_source_wishlist'   => (int) YITH_WCWL()->count_products($wishlist_from_id),
            'items_in_destination_wishlist' => (int) YITH_WCWL()->count_products($wishlist_to_id)
        ));

        $this->send_event('moved_wishlist_item', $event_attributes);
    }

    /**
     * Send event to the API
     *
     * @param string $event_type
     * @param array $event_attributes
     */
    private function send_event(string $event_type, array $event_attributes = array()): void {
        if (empty($event_type)) {
            throw new Exception('Event type is required');
        }
        $user_data = $this->get_user_data();
        $company_data = $this->get_company_data();
        
        $this->api->send_event($event_type, $user_data, $event_attributes, $company_data);
    }

    /**
     * Get user data for event payload
     *
     * @return array
     */
    private function get_user_data(): array {
        $user = wp_get_current_user();
        
        // Generate anonymous ID if user is not logged in
        $anonymous_id = $this->get_anonymous_id();

        $user_data = array(
            'anonymous_id' => $anonymous_id,
            'id' => '',
        );

        // Check if the user is logged in
        // Else return the anonymous_id and id only
        if ($user->ID !== 0) {
            // User is logged in
            $user_data['id'] = (string) $user->ID;
            $user_data['email'] = $user->user_email;
            $user_data['created_at'] = $user->user_registered;
            $user_data['first_name'] = $user->user_firstname;
            $user_data['last_name'] = $user->user_lastname;
            $user_data['custom'] = array(
                'role' => isset($user->roles[0]) ? $user->roles[0] : '',
            );
        }
    
        return $user_data;
    }

    /**
     * Get company data for event payload
     *
     * @return array
     */
    private function get_company_data(): array {
        // Customize as needed
        return array();
    }
}