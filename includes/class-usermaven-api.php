<?php
class Usermaven_API {
    private $server_token;
    private $api_key;
    private $api_url;
    private $cookie_less_tracking;

    public function __construct($tracking_host) {
        $this->server_token = get_option('usermaven_server_token');
        $this->api_key = get_option('usermaven_api_key');
        $this->api_url = "$tracking_host/api/v1/s2s/event";
        $this->cookie_less_tracking = (bool) get_option('usermaven_cookie_less_tracking');
    }

    public function identify($user_data, $company_data = []) {
        $url = $this->api_url . "?token=$this->api_key.$this->server_token";
        
        // Get current timestamp in milliseconds
        $current_timestamp_ms = round(microtime(true) * 1000);
        $doc_encoding = get_bloginfo('charset');
        $user_agent = $this->get_user_agent();
        $source_ip = $this->get_client_ip();
        $privacy_policies = $this->get_privacy_policies();

        $data = [
            'api_key' => $this->api_key,
            'event_type' => 'user_identify',
            '_timestamp' => (string)$current_timestamp_ms,
            'user' => $user_data,
            'company' => [
                'id' => $company_data['id'] ?? '',
                'name' => $company_data['name'] ?? '',
            ],
            'src' => 'http',
            'url' => $_SERVER['HTTP_REFERER'] ?? '',
            'page_title' => wp_get_document_title(),
            'doc_path' => $_SERVER['REQUEST_URI'] ?? '',
            'doc_host' => $_SERVER['HTTP_HOST'] ?? '',
            'user_agent' => $user_agent,
            'source_ip' => $source_ip,
            'user_language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            'doc_encoding' => (string) $doc_encoding,
        ];

        if (!empty($privacy_policies)) {
            $data = array_merge($data, $privacy_policies);
        }

        $response = wp_remote_post($url, [
            'body' => json_encode($data),
            'headers' => ['Content-Type' => 'application/json']
        ]);

        if (is_wp_error($response)) {
            error_log('Usermaven API Error: ' . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        return isset($result['status']) && $result['status'] === 'ok';
    }

    public function send_event($event_type, $user_data, $event_attributes = [], $company_data = []) {
        $url = $this->api_url . "?token=$this->api_key.$this->server_token";

        // Get current timestamp in milliseconds
        $current_timestamp_ms = round(microtime(true) * 1000);
        $user_agent = $this->get_user_agent();
        $source_ip = $this->get_client_ip();
        $privacy_policies = $this->get_privacy_policies();

        $data = [
            'api_key' => $this->api_key,
            'event_type' => $event_type,
            "_timestamp"=> (string)$current_timestamp_ms,
            'event_attributes' => $event_attributes,
            'user' => $user_data,
            'company' => [
                'id' => $company_data['id'] ?? '',
                'name' => $company_data['name'] ?? '',
            ],
            'src' => 'ecommerce',
            'url' => $_SERVER['HTTP_REFERER'] ?? '',
            'page_title' => wp_get_document_title(),
            'doc_path' => $_SERVER['REQUEST_URI'] ?? '',
            'doc_host' => $_SERVER['HTTP_HOST'] ?? '',
            'user_agent' => $user_agent,
            'source_ip' => $source_ip,
            'user_language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            'doc_encoding' => 'UTF-8',
        ];

        if (!empty($privacy_policies)) {
            $data = array_merge($data, $privacy_policies);
        }

        $response = wp_remote_post($url, [
            'body' => json_encode($data),
            'headers' => ['Content-Type' => 'application/json']
        ]);

        if (is_wp_error($response)) {
            error_log('Usermaven API Error: ' . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        return isset($result['status']) && $result['status'] === 'ok';
    }

    /**
     * Safely resolve client IP, preferring X-Forwarded-For when available.
     *
     * @return string
     */
    private function get_client_ip() {
        $ip_sources = array(
            'HTTP_X_FORWARDED_FOR',
            'REMOTE_ADDR',
        );

        foreach ($ip_sources as $key) {
            if (empty($_SERVER[$key])) {
                continue;
            }

            // X-Forwarded-For can contain multiple comma-separated addresses.
            $candidates = $key === 'HTTP_X_FORWARDED_FOR'
                ? explode(',', $_SERVER[$key])
                : array($_SERVER[$key]);

            foreach ($candidates as $candidate) {
                $candidate = trim($candidate);

                if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false ||
                    filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
                    return $candidate;
                }
            }
        }

        return '';
    }

    /**
     * Retrieve user agent string with a safe fallback.
     *
     * @return string
     */
    private function get_user_agent() {
        return isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
    }

    /**
     * Return privacy flags when cookie-less tracking is enabled.
     *
     * @return array
     */
    private function get_privacy_policies() {
        if ($this->cookie_less_tracking) {
            return array(
                'cookie_policy' => 'strict',
                'ip_policy' => 'strict',
            );
        }

        return array();
    }
}