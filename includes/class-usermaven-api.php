<?php
class Usermaven_API {
    private $server_token;
    private $api_key;
    private $api_url;

    public function __construct($tracking_host) {
        $this->server_token = get_option('usermaven_server_token');
        $this->api_key = get_option('usermaven_api_key');
        $this->api_url = "$tracking_host/api/v1/s2s/event";
    }

    public function send_event($event_type, $user_data, $event_attributes = [], $company_data = []) {
        $url = $this->api_url . "?token=$this->api_key.$this->server_token";

        // Get current timestamp in milliseconds
        $current_timestamp_ms = round(microtime(true) * 1000);

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
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            // 'source_ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            'doc_encoding' => 'UTF-8',
        ];

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

        return $result['status'] === 'ok';
    }
}