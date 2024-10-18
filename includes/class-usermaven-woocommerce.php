<?php

class Usermaven_WooCommerce {
    private $api;

    public function __construct() {
        $this->api = new Usermaven_API();
        $this->api_key = get_option('usermaven_api_key');
        $this->init_hooks();
    }

    private function init_hooks() {
        // Non-AJAX Add to Cart
        add_action('woocommerce_add_to_cart', array($this, 'track_add_to_cart'), 10, 6);
        // AJAX Add to Cart
        add_action('woocommerce_ajax_added_to_cart', array($this, 'track_ajax_add_to_cart'));
        // Remove from Cart
        add_action('woocommerce_cart_item_removed', array($this, 'track_remove_from_cart'), 10, 2);
        // Update Cart
        add_action('woocommerce_after_cart_item_quantity_update', array($this, 'track_update_cart'), 10, 4);
        // Initiate Checkout
        add_action('woocommerce_checkout_process', array($this, 'track_initiate_checkout'));
        // Order Completed
        add_action('woocommerce_payment_complete', array($this, 'track_order_completed'));
    }

    public function track_add_to_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
        $product = wc_get_product($product_id);
        $event_attributes = array(
            'product_id' => $product_id,
            'product_name' => $product->get_name(),
            'quantity' => $quantity,
            'price' => $product->get_price(),
            'variation_id' => $variation_id,
        );
        $this->send_event('add_to_cart', $event_attributes);
    }

    public function track_ajax_add_to_cart($product_id) {
        $product = wc_get_product($product_id);
        $event_attributes = array(
            'product_id' => $product_id,
            'product_name' => $product->get_name(),
            'price' => $product->get_price(),
            'is_ajax' => true,
        );
        $this->send_event('add_to_cart', $event_attributes);
    }

    public function track_remove_from_cart($cart_item_key, $cart) {
        $product_id = $cart->cart_contents[$cart_item_key]['product_id'];
        $product = wc_get_product($product_id);
        $event_attributes = array(
            'product_id' => $product_id,
            'product_name' => $product->get_name(),
            'price' => $product->get_price(),
        );
        $this->send_event('remove_from_cart', $event_attributes);
    }

    public function track_update_cart($cart_item_key, $quantity, $old_quantity, $cart) {
        $product_id = $cart->cart_contents[$cart_item_key]['product_id'];
        $product = wc_get_product($product_id);
        $event_attributes = array(
            'product_id' => $product_id,
            'product_name' => $product->get_name(),
            'new_quantity' => $quantity,
            'old_quantity' => $old_quantity,
            'price' => $product->get_price(),
        );
        $this->send_event('update_cart', $event_attributes);
    }

    public function track_initiate_checkout() {
        $cart = WC()->cart;
        $event_attributes = array(
            'total' => $cart->get_total(),
            'currency' => get_woocommerce_currency(),
            'items_count' => $cart->get_cart_contents_count(),
        );
        $this->send_event('initiate_checkout', $event_attributes);
    }

    public function track_order_completed($order_id) {
        $order = wc_get_order($order_id);
        $event_attributes = array(
            'order_id' => $order_id,
            'total' => $order->get_total(),
            'currency' => $order->get_currency(),
            'payment_method' => $order->get_payment_method(),
            'items_count' => $order->get_item_count(),
        );
        $this->send_event('order_completed', $event_attributes);
    }

    private function send_event($event_type, $event_attributes = array()) {
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