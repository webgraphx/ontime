<?php
/**
 * OnTime Payment Handler
 * 
 * Main payment processing class that manages multiple payment providers
 * Handles appointment payment lifecycle and status updates
 * 
 * @package OnTime
 * @subpackage Payment
 * @since 1.1.0
 */

namespace OnTime\Payment;

use OnTime\Database\Database;

/**
 * Payment Handler
 * 
 * Central class for managing payments and providers
 */
final class Payment_Handler
{
    /**
     * Singleton instance
     * @var Payment_Handler|null
     */
    private static $instance = null;

    /**
     * Database instance
     * @var Database
     */
    private $db;

    /**
     * Registered payment providers
     * @var array
     */
    private $providers = [];

    /**
     * Constructor - Private for Singleton pattern
     */
    private function __construct()
    {
        $this->db = Database::get_instance();
        $this->register_providers();
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    private function __wakeup() {}

    /**
     * Get singleton instance
     * 
     * @return Payment_Handler
     */
    public static function get_instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register default payment providers
     */
    private function register_providers()
    {
        // Register built-in providers
        $this->register_provider(new Mock_Provider());
        
        // Allow third-party providers to be registered via filter
        $third_party_providers = apply_filters('ontime_payment_providers', []);
        
        foreach ($third_party_providers as $provider) {
            if ($provider instanceof Payment_Provider_Interface) {
                $this->register_provider($provider);
            }
        }
    }

    /**
     * Register a payment provider
     * 
     * @param Payment_Provider_Interface $provider Payment provider instance
     */
    public function register_provider(Payment_Provider_Interface $provider)
    {
        $this->providers[$provider->get_id()] = $provider;
    }

    /**
     * Get all registered providers
     * 
     * @return array Array of Payment_Provider_Interface instances
     */
    public function get_providers()
    {
        return $this->providers;
    }

    /**
     * Get a specific provider by ID
     * 
     * @param string $provider_id Provider ID
     * @return Payment_Provider_Interface|null
     */
    public function get_provider($provider_id)
    {
        return $this->providers[$provider_id] ?? null;
    }

    /**
     * Get available providers (enabled and available)
     * 
     * @return array Array of available Payment_Provider_Interface instances
     */
    public function get_available_providers()
    {
        $available = [];
        
        foreach ($this->providers as $id => $provider) {
            if ($provider->is_available()) {
                $available[$id] = $provider;
            }
        }
        
        return $available;
    }

    /**
     * Get the default provider ID
     * 
     * @return string
     */
    public function get_default_provider_id()
    {
        $default = get_option('ontime_default_payment_provider', 'mock');
        
        // Fallback to first available provider
        if (!isset($this->providers[$default])) {
            $available = $this->get_available_providers();
            if (!empty($available)) {
                return key($available);
            }
            return 'mock';
        }
        
        return $default;
    }

    /**
     * Initiate payment for an appointment
     * 
     * @param int $appointment_id Appointment ID
     * @param string $provider_id Payment provider ID
     * @param array $additional_data Additional data for the payment
     * @return array Payment result
     */
    public function initiate_appointment_payment($appointment_id, $provider_id = null, $additional_data = [])
    {
        $appointment_id = absint($appointment_id);
        
        if (empty($appointment_id)) {
            return [
                'success' => false,
                'error' => __('Invalid appointment ID', 'ontime')
            ];
        }

        // Get appointment data
        $appointment = $this->db->get_appointment($appointment_id);
        
        if (empty($appointment)) {
            return [
                'success' => false,
                'error' => __('Appointment not found', 'ontime')
            ];
        }

        // Check if appointment is already paid
        if ($appointment['status'] === 'paid') {
            return [
                'success' => false,
                'error' => __('This appointment is already paid', 'ontime')
            ];
        }

        // Get provider
        $provider_id = $provider_id ?: $this->get_default_provider_id();
        $provider = $this->get_provider($provider_id);
        
        if (!$provider) {
            return [
                'success' => false,
                'error' => __('Payment provider not available', 'ontime')
            ];
        }

        // Prepare order data
        $order_data = [
            'appointment_id' => $appointment_id,
            'amount' => $appointment['total_price'],
            'currency' => $provider->get_default_currency(),
            'customer_name' => $appointment['customer_name'],
            'customer_phone' => $appointment['customer_phone'],
            'customer_email' => $appointment['customer_email'],
            'service_name' => $this->get_service_name($appointment['service_id']),
            'description' => sprintf(
                /* translators: 1: Service name, 2: Date */
                __('Payment for %1$s appointment on %2$s', 'ontime'),
                $this->get_service_name($appointment['service_id']),
                $this->format_jalali_datetime($appointment['start_datetime'])
            ),
            'callback_url' => $provider->get_callback_url(),
        ];

        // Merge additional data
        $order_data = wp_parse_args($additional_data, $order_data);

        // Initiate payment with provider
        try {
            $result = $provider->initiate_payment($order_data);
            
            if ($result['success']) {
                // Store transaction reference in appointment
                $this->update_appointment_transaction($appointment_id, [
                    'transaction_id' => $result['transaction_id'],
                    'payment_provider' => $provider_id,
                    'payment_status' => 'pending',
                    'payment_amount' => $appointment['total_price'],
                    'payment_currency' => $order_data['currency'],
                ]);

                return [
                    'success' => true,
                    'redirect_url' => $result['redirect_url'],
                    'transaction_id' => $result['transaction_id'],
                    'provider_id' => $provider_id,
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $result['error'] ?? __('Payment initiation failed', 'ontime')
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => __('Payment error: ', 'ontime') . $e->getMessage()
            ];
        }
    }

    /**
     * Handle payment callback/verification
     * 
     * This is the universal callback handler for all payment providers
     * 
     * @param string $provider_id Payment provider ID
     * @param array $callback_data Callback data from payment gateway
     * @return array Verification result
     */
    public function handle_payment_callback($provider_id, $callback_data)
    {
        $provider = $this->get_provider($provider_id);
        
        if (!$provider) {
            return [
                'success' => false,
                'error' => __('Invalid payment provider', 'ontime')
            ];
        }

        try {
            // Verify payment with provider
            $verification = $provider->verify_payment($callback_data);
            
            if (!$verification['success']) {
                return [
                    'success' => false,
                    'error' => $verification['error'] ?? __('Payment verification failed', 'ontime'),
                    'status' => 'failed'
                ];
            }

            // Payment is verified, update appointment status
            $transaction_id = $verification['transaction_id'] ?? '';
            $reference_id = $verification['reference_id'] ?? '';
            
            if (empty($transaction_id)) {
                return [
                    'success' => false,
                    'error' => __('No transaction ID provided', 'ontime'),
                    'status' => 'failed'
                ];
            }

            // Find appointment by transaction ID
            $appointment = $this->db->get_appointments([
                'transaction_id' => $transaction_id,
                'limit' => 1
            ]);
            
            if (empty($appointment)) {
                return [
                    'success' => false,
                    'error' => __('Appointment not found for this transaction', 'ontime'),
                    'status' => 'failed'
                ];
            }

            $appointment = $appointment[0];
            $appointment_id = $appointment['id'];

            // Check if already paid
            if ($appointment['status'] === 'paid') {
                return [
                    'success' => true,
                    'error' => '',
                    'status' => 'already_paid',
                    'appointment_id' => $appointment_id
                ];
            }

            // Update appointment status to paid
            $update_result = $this->db->update_appointment($appointment_id, [
                'status' => 'paid',
                'transaction_id' => $transaction_id,
                'payment_reference' => $reference_id,
                'payment_verified_at' => current_time('mysql'),
            ]);

            if ($update_result) {
                // Trigger success actions
                do_action('ontime_payment_completed', $appointment_id, $transaction_id, $reference_id);

                return [
                    'success' => true,
                    'status' => 'completed',
                    'appointment_id' => $appointment_id,
                    'transaction_id' => $transaction_id,
                    'reference_id' => $reference_id,
                    'message' => __('Payment successful. Your appointment is confirmed.', 'ontime')
                ];
            } else {
                return [
                    'success' => false,
                    'error' => __('Failed to update appointment status', 'ontime'),
                    'status' => 'failed'
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => __('Payment verification error: ', 'ontime') . $e->getMessage(),
                'status' => 'error'
            ];
        }
    }

    /**
     * Get payment status for an appointment
     * 
     * @param int $appointment_id Appointment ID
     * @return array Payment status information
     */
    public function get_payment_status($appointment_id)
    {
        $appointment_id = absint($appointment_id);
        
        if (empty($appointment_id)) {
            return [
                'status' => 'invalid',
                'message' => __('Invalid appointment ID', 'ontime')
            ];
        }

        $appointment = $this->db->get_appointment($appointment_id);
        
        if (empty($appointment)) {
            return [
                'status' => 'not_found',
                'message' => __('Appointment not found', 'ontime')
            ];
        }

        return [
            'status' => $appointment['status'],
            'transaction_id' => $appointment['transaction_id'] ?? '',
            'payment_reference' => $appointment['payment_reference'] ?? '',
            'payment_provider' => $appointment['payment_provider'] ?? '',
            'payment_amount' => $appointment['total_price'] ?? 0,
            'payment_currency' => $appointment['payment_currency'] ?? '',
            'is_paid' => $appointment['status'] === 'paid',
        ];
    }

    /**
     * Get service name by ID
     * 
     * @param int $service_id Service ID
     * @return string Service name
     */
    private function get_service_name($service_id)
    {
        $service_id = absint($service_id);
        
        if (empty($service_id)) {
            return __('Unknown Service', 'ontime');
        }

        $service = $this->db->get_service($service_id);
        
        return $service ? $service['name'] : __('Unknown Service', 'ontime');
    }

    /**
     * Format Gregorian datetime to Jalali for display
     * 
     * @param string $datetime Gregorian datetime (YYYY-MM-DD HH:MM:SS)
     * @return string Formatted Jalali datetime
     */
    private function format_jalali_datetime($datetime)
    {
        if (empty($datetime)) {
            return '';
        }

        // Use Calendar Engine if available
        if (class_exists('OnTime\Calendar\Calendar_Engine')) {
            $calendar = \OnTime\Calendar\Calendar_Engine::get_instance();
            return $calendar->gregorian_to_jalali_datetime($datetime);
        }

        // Fallback
        return $datetime;
    }

    /**
     * Update appointment with transaction data
     * 
     * @param int $appointment_id Appointment ID
     * @param array $transaction_data Transaction data
     */
    private function update_appointment_transaction($appointment_id, $transaction_data)
    {
        $appointment_id = absint($appointment_id);
        
        if (empty($appointment_id)) {
            return;
        }

        $update_data = [];
        
        if (isset($transaction_data['transaction_id'])) {
            $update_data['transaction_id'] = sanitize_text_field($transaction_data['transaction_id']);
        }
        
        if (isset($transaction_data['payment_provider'])) {
            $update_data['payment_provider'] = sanitize_text_field($transaction_data['payment_provider']);
        }
        
        if (isset($transaction_data['payment_status'])) {
            $update_data['payment_status'] = sanitize_text_field($transaction_data['payment_status']);
        }
        
        if (isset($transaction_data['payment_amount'])) {
            $update_data['payment_amount'] = (float) $transaction_data['payment_amount'];
        }
        
        if (isset($transaction_data['payment_currency'])) {
            $update_data['payment_currency'] = sanitize_text_field($transaction_data['payment_currency']);
        }

        if (!empty($update_data)) {
            $this->db->update_appointment($appointment_id, $update_data);
        }
    }

    /**
     * Get payment providers as options for dropdown
     * 
     * @return array Array of provider options [id => name]
     */
    public function get_provider_options()
    {
        $options = [];
        $providers = $this->get_available_providers();
        
        foreach ($providers as $id => $provider) {
            $options[$id] = $provider->get_name();
        }
        
        return $options;
    }

    /**
     * Check if payment is required for an appointment
     * 
     * @param int $appointment_id Appointment ID
     * @return bool
     */
    public function is_payment_required($appointment_id)
    {
        $appointment_id = absint($appointment_id);
        
        if (empty($appointment_id)) {
            return false;
        }

        $appointment = $this->db->get_appointment($appointment_id);
        
        if (empty($appointment)) {
            return false;
        }

        // Payment is required if total_price > 0 and status is not 'paid'
        return ($appointment['total_price'] > 0 && $appointment['status'] !== 'paid');
    }

    /**
     * Get payment URL for an appointment
     * 
     * @param int $appointment_id Appointment ID
     * @param string $provider_id Optional provider ID
     * @return string Payment URL
     */
    public function get_payment_url($appointment_id, $provider_id = null)
    {
        $provider_id = $provider_id ?: $this->get_default_provider_id();
        
        return add_query_arg([
            'action' => 'ontime_payment',
            'appointment_id' => $appointment_id,
            'provider' => $provider_id,
            'nonce' => wp_create_nonce('ontime_payment_' . $appointment_id),
        ], home_url());
    }

    /**
     * Process payment initiation from frontend
     * 
     * @param array $request_data Request data
     * @return array Result
     */
    public function process_payment_initiation($request_data)
    {
        // Verify nonce
        $appointment_id = isset($request_data['appointment_id']) ? absint($request_data['appointment_id']) : 0;
        $provider_id = isset($request_data['provider']) ? sanitize_text_field($request_data['provider']) : null;
        $nonce = isset($request_data['nonce']) ? $request_data['nonce'] : '';
        
        if (empty($appointment_id)) {
            return [
                'success' => false,
                'error' => __('Invalid appointment ID', 'ontime')
            ];
        }

        if (!wp_verify_nonce($nonce, 'ontime_payment_' . $appointment_id)) {
            return [
                'success' => false,
                'error' => __('Invalid nonce', 'ontime')
            ];
        }

        return $this->initiate_appointment_payment($appointment_id, $provider_id);
    }

    /**
     * Initialize hooks
     */
    public function init_hooks()
    {
        // Register payment callback endpoint
        add_action('init', [$this, 'register_callback_endpoint']);
        
        // AJAX handler for payment initiation
        add_action('wp_ajax_ontime_initiate_payment', [$this, 'ajax_initiate_payment']);
        add_action('wp_ajax_nopriv_ontime_initiate_payment', [$this, 'ajax_initiate_payment']);
        
        // AJAX handler for payment verification
        add_action('wp_ajax_ontime_verify_payment', [$this, 'ajax_verify_payment']);
        add_action('wp_ajax_nopriv_ontime_verify_payment', [$this, 'ajax_verify_payment']);
    }

    /**
     * Register payment callback endpoint
     */
    public function register_callback_endpoint()
    {
        add_rewrite_rule(
            '^ontime-payment-callback/([^/]+)/?$',
            'index.php?ontime_payment_callback=1&provider=$matches[1]',
            'top'
        );
        
        add_rewrite_tag('%ontime_payment_callback%', '([^&]+)');
        add_rewrite_tag('%provider%', '([^&]+)');
        
        // Flush rules on activation
        if (get_option('ontime_flush_rewrite_rules') || !get_transient('ontime_rewrite_rules_flushed')) {
            flush_rewrite_rules();
            delete_option('ontime_flush_rewrite_rules');
            set_transient('ontime_rewrite_rules_flushed', true, DAY_IN_SECONDS);
        }
    }

    /**
     * AJAX handler for payment initiation
     */
    public function ajax_initiate_payment()
    {
        check_ajax_referer('ontime_payment_nonce', 'nonce');

        $appointment_id = isset($_POST['appointment_id']) ? absint($_POST['appointment_id']) : 0;
        $provider_id = isset($_POST['provider_id']) ? sanitize_text_field($_POST['provider_id']) : null;
        
        $result = $this->initiate_appointment_payment($appointment_id, $provider_id);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error(['message' => $result['error']]);
        }
    }

    /**
     * AJAX handler for payment verification
     */
    public function ajax_verify_payment()
    {
        check_ajax_referer('ontime_payment_nonce', 'nonce');

        $provider_id = isset($_POST['provider_id']) ? sanitize_text_field($_POST['provider_id']) : '';
        $callback_data = isset($_POST['callback_data']) ? $_POST['callback_data'] : [];
        
        // Sanitize callback data
        $sanitized_callback = [];
        foreach ($callback_data as $key => $value) {
            if (is_array($value)) {
                $sanitized_callback[$key] = array_map('sanitize_text_field', $value);
            } else {
                $sanitized_callback[$key] = sanitize_text_field($value);
            }
        }
        
        $result = $this->handle_payment_callback($provider_id, $sanitized_callback);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error(['message' => $result['error'], 'status' => $result['status']]);
        }
    }
}
