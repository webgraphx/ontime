<?php
/**
 * OnTime Iranian Payment Gateway Base Class
 * 
 * Base class for Iranian payment gateways (ZarinPal, Mellat, Pay.ir, etc.)
 * Provides common functionality and security helpers
 * 
 * @package OnTime
 * @subpackage Payment
 * @since 1.1.0
 */

namespace OnTime\Payment;

/**
 * Iranian Gateway Base Class
 * 
 * Abstract base class for Iranian payment gateways
 * Implements common functionality and security measures
 */
abstract class Iranian_Gateway_Base implements Payment_Provider_Interface
{
    /**
     * Gateway settings
     * @var array
     */
    protected $settings = [];

    /**
     * Gateway API endpoint
     * @var string
     */
    protected $api_endpoint = '';

    /**
     * Merchant ID or API key
     * @var string
     */
    protected $merchant_id = '';

    /**
     * Provider ID
     * @var string
     */
    protected $id = '';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->load_settings();
    }

    /**
     * Load gateway settings from options
     */
    protected function load_settings()
    {
        $option_name = 'ontime_payment_' . $this->id . '_settings';
        $this->settings = get_option($option_name, []);
        
        $this->merchant_id = isset($this->settings['merchant_id']) ? $this->settings['merchant_id'] : '';
    }

    /**
     * Get the unique identifier for this payment provider
     * 
     * @return string
     */
    public function get_id()
    {
        return $this->id;
    }

    /**
     * Get the display name for this payment provider
     * 
     * @return string
     */
    public function get_name()
    {
        return $this->settings['display_name'] ?? $this->get_default_name();
    }

    /**
     * Get default display name
     * 
     * @return string
     */
    abstract protected function get_default_name();

    /**
     * Get the description for this payment provider
     * 
     * @return string
     */
    public function get_description()
    {
        return $this->settings['description'] ?? '';
    }

    /**
     * Get the callback URL for this provider
     * 
     * @return string
     */
    public function get_callback_url()
    {
        return home_url('/ontime-payment-callback/' . $this->id);
    }

    /**
     * Check if this provider is available/enabled
     * 
     * @return bool
     */
    public function is_available()
    {
        // Check if enabled
        if (!isset($this->settings['enabled']) || !$this->settings['enabled']) {
            return false;
        }

        // Check if merchant ID is set
        if (empty($this->merchant_id)) {
            return false;
        }

        // Check if in test mode (sandbox)
        if (isset($this->settings['test_mode']) && $this->settings['test_mode']) {
            return true; // Test mode is always available for development
        }

        return true;
    }

    /**
     * Get supported currencies
     * 
     * @return array
     */
    public function get_supported_currencies()
    {
        return ['IRT', 'IRR'];
    }

    /**
     * Get default currency for this provider
     * 
     * @return string
     */
    public function get_default_currency()
    {
        return 'IRT';
    }

    /**
     * Convert IRT to IRR (1 IRT = 10 IRR)
     * 
     * @param int|float $amount Amount in IRT
     * @return int Amount in IRR
     */
    protected function convert_irt_to_irr($amount)
    {
        return (int) ($amount * 10);
    }

    /**
     * Generate a secure callback verification
     * 
     * @param array $callback_data Raw callback data from gateway
     * @return array Verified and normalized callback data
     */
    protected function verify_callback_signature($callback_data)
    {
        // This should be implemented by each gateway
        // to verify the authenticity of the callback
        return $callback_data;
    }

    /**
     * Send request to gateway API
     * 
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @param string $method HTTP method (POST, GET)
     * @return array Response data
     */
    protected function send_api_request($endpoint, $data, $method = 'POST')
    {
        $url = $this->api_endpoint . $endpoint;
        
        $args = [
            'body' => $data,
            'method' => $method,
            'timeout' => 30,
            'sslverify' => true,
        ];

        // Add headers if needed
        if ($method === 'POST') {
            $args['headers'] = [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ];
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message(),
                'error_code' => $response->get_error_code(),
            ];
        }

        $body = wp_remote_retrieve_body($response);
        $code = wp_remote_retrieve_response_code($response);

        if ($code !== 200) {
            return [
                'success' => false,
                'error' => sprintf(__('API request failed with status code: %d', 'ontime'), $code),
                'error_code' => $code,
            ];
        }

        // Parse response (could be XML or JSON depending on gateway)
        return $this->parse_response($body);
    }

    /**
     * Parse gateway response
     * 
     * @param string $response Raw response body
     * @return array Parsed response
     */
    abstract protected function parse_response($response);

    /**
     * Generate a unique transaction reference
     * 
     * @return string Unique reference ID
     */
    protected function generate_reference_id()
    {
        return 'ontime_' . $this->id . '_' . time() . '_' . mt_rand(1000, 9999);
    }

    /**
     * Log payment event
     * 
     * @param string $event_type Type of event
     * @param array $data Event data
     */
    protected function log_event($event_type, $data)
    {
        $log_data = [
            'timestamp' => current_time('mysql'),
            'provider' => $this->id,
            'event_type' => $event_type,
            'data' => $data,
        ];

        // Store log in database or file
        do_action('ontime_payment_log', $this->id, $event_type, $data);
    }
}
