<?php
/**
 * OnTime Mock Payment Provider
 * 
 * A mock payment provider for development and testing purposes
 * Simulates successful and failed payment scenarios
 * 
 * @package OnTime
 * @subpackage Payment
 * @since 1.1.0
 */

namespace OnTime\Payment;

/**
 * Mock Payment Provider
 * 
 * Provides a testing payment gateway that simulates different scenarios
 */
final class Mock_Provider implements Payment_Provider_Interface
{
    /**
     * Provider ID
     * @var string
     */
    private $id = 'mock';

    /**
     * Test mode setting
     * @var string 'success', 'failure', or 'pending'
     */
    private $test_mode;

    /**
     * Transaction counter for generating unique IDs
     * @var int
     */
    private static $transaction_counter = 0;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Get test mode from options, default to 'success'
        $this->test_mode = get_option('ontime_mock_payment_mode', 'success');
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
        return __('Mock Payment (Test)', 'ontime');
    }

    /**
     * Get the description for this payment provider
     * 
     * @return string
     */
    public function get_description()
    {
        return __('Mock payment gateway for testing purposes. No actual payment is processed.', 'ontime');
    }

    /**
     * Initialize the payment process
     * 
     * @param array $order_data Order/Appointment data
     * @return array Payment initiation result
     */
    public function initiate_payment($order_data)
    {
        // Generate a unique transaction ID
        self::$transaction_counter++;
        $transaction_id = 'mock_txn_' . time() . '_' . self::$transaction_counter;

        // Store order data in transient for verification
        $transient_key = 'ontime_mock_payment_' . $transaction_id;
        set_transient($transient_key, $order_data, HOUR_IN_SECONDS);

        // Generate a mock redirect URL (in production, this would be the gateway URL)
        // For mock, we'll return a URL that triggers the verification
        $verification_url = add_query_arg([
            'ontime_mock_verify' => '1',
            'transaction_id' => $transaction_id,
            'status' => $this->test_mode,
            'nonce' => wp_create_nonce('ontime_mock_verify_' . $transaction_id),
        ], home_url());

        // Simulate different scenarios based on test mode
        switch ($this->test_mode) {
            case 'failure':
                return [
                    'success' => true,
                    'redirect_url' => $verification_url,
                    'transaction_id' => $transaction_id,
                ];

            case 'pending':
                return [
                    'success' => true,
                    'redirect_url' => $verification_url,
                    'transaction_id' => $transaction_id,
                ];

            case 'success':
            default:
                return [
                    'success' => true,
                    'redirect_url' => $verification_url,
                    'transaction_id' => $transaction_id,
                ];
        }
    }

    /**
     * Verify payment from callback
     * 
     * @param array $callback_data Data received from payment gateway callback
     * @return array Verification result
     */
    public function verify_payment($callback_data)
    {
        $transaction_id = isset($callback_data['transaction_id']) ? $callback_data['transaction_id'] : '';
        $status = isset($callback_data['status']) ? $callback_data['status'] : '';
        $nonce = isset($callback_data['nonce']) ? $callback_data['nonce'] : '';

        // Verify nonce
        if (!wp_verify_nonce($nonce, 'ontime_mock_verify_' . $transaction_id)) {
            return [
                'success' => false,
                'error' => __('Invalid verification nonce', 'ontime'),
                'status' => 'failed',
            ];
        }

        // Get stored order data
        $transient_key = 'ontime_mock_payment_' . $transaction_id;
        $order_data = get_transient($transient_key);

        if (!$order_data) {
            return [
                'success' => false,
                'error' => __('Transaction expired or not found', 'ontime'),
                'status' => 'failed',
            ];
        }

        // Delete transient after use
        delete_transient($transient_key);

        // Simulate different verification results based on test mode or callback status
        if ($status === 'failure' || $this->test_mode === 'failure') {
            return [
                'success' => false,
                'error' => __('Mock payment failed for testing purposes', 'ontime'),
                'status' => 'failed',
            ];
        }

        if ($status === 'pending' || $this->test_mode === 'pending') {
            return [
                'success' => false,
                'error' => __('Mock payment is pending', 'ontime'),
                'status' => 'pending',
            ];
        }

        // Success case
        return [
            'success' => true,
            'status' => 'completed',
            'transaction_id' => $transaction_id,
            'reference_id' => 'mock_ref_' . substr($transaction_id, 10),
            'amount' => $order_data['amount'] ?? 0,
            'currency' => $order_data['currency'] ?? 'IRT',
        ];
    }

    /**
     * Get the callback URL for this provider
     * 
     * @return string
     */
    public function get_callback_url()
    {
        return home_url('/ontime-payment-callback/mock');
    }

    /**
     * Check if this provider is available/enabled
     * 
     * @return bool
     */
    public function is_available()
    {
        // Mock provider is always available in development
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return true;
        }

        // Check if enabled in production
        return (bool) get_option('ontime_enable_mock_payments', true);
    }

    /**
     * Get supported currencies
     * 
     * @return array
     */
    public function get_supported_currencies()
    {
        return ['IRT', 'IRR', 'USD', 'EUR'];
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
     * Set test mode for simulating different scenarios
     * 
     * @param string $mode 'success', 'failure', or 'pending'
     */
    public function set_test_mode($mode)
    {
        $valid_modes = ['success', 'failure', 'pending'];
        if (in_array($mode, $valid_modes)) {
            $this->test_mode = $mode;
        }
    }

    /**
     * Get current test mode
     * 
     * @return string
     */
    public function get_test_mode()
    {
        return $this->test_mode;
    }

    /**
     * Generate a mock payment form for testing
     * 
     * @param array $order_data Order data
     * @return string HTML form for mock payment
     */
    public function generate_test_form($order_data)
    {
        $transaction_id = 'mock_txn_' . time() . '_' . (self::$transaction_counter + 1);
        $nonce = wp_create_nonce('ontime_mock_verify_' . $transaction_id);

        ob_start();
        ?>
        <div class="ontime-mock-payment-form" style="max-width: 500px; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; direction: rtl; text-align: right;">
            <h2 style="margin-top: 0;"><?php esc_html_e('درگاه پرداخت آزمایشی', 'ontime'); ?></h2>
            <p style="color: #666;"><?php esc_html_e('این یک درگاه پرداخت آزمایشی است. هیچ پرداخت واقعی انجام نمی‌شود.', 'ontime'); ?></p>
            
            <div style="background: #f8f9fa; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                <h3 style="margin-top: 0;"><?php esc_html_e('جزئیات پرداخت', 'ontime'); ?></h3>
                <p><strong><?php esc_html_e('مبلغ:'); ?></strong> <?php echo esc_html(number_format($order_data['amount'])); ?> <?php echo esc_html($order_data['currency']); ?></p>
                <p><strong><?php esc_html_e('توضیحات:'); ?></strong> <?php echo esc_html($order_data['description']); ?></p>
            </div>

            <form method="post" action="<?php echo esc_url(home_url()); ?>">
                <input type="hidden" name="ontime_mock_verify" value="1">
                <input type="hidden" name="transaction_id" value="<?php echo esc_attr($transaction_id); ?>">
                <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>">

                <fieldset style="border: 1px solid #ddd; padding: 15px; border-radius: 4px; margin-bottom: 15px;">
                    <legend style="padding: 0 5px;"><?php esc_html_e('نتیجه پرداخت را انتخاب کنید', 'ontime'); ?></legend>
                    
                    <label style="display: block; margin-bottom: 8px;">
                        <input type="radio" name="status" value="success" checked> 
                        <?php esc_html_e('موفق', 'ontime'); ?>
                    </label>
                    
                    <label style="display: block; margin-bottom: 8px;">
                        <input type="radio" name="status" value="failure"> 
                        <?php esc_html_e('ناموفق', 'ontime'); ?>
                    </label>
                    
                    <label style="display: block; margin-bottom: 8px;">
                        <input type="radio" name="status" value="pending"> 
                        <?php esc_html_e('در انتظار', 'ontime'); ?>
                    </label>
                </fieldset>

                <button type="submit" style="background: #2563eb; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px;">
                    <?php esc_html_e('شبیه‌سازی پرداخت', 'ontime'); ?>
                </button>
                
                <button type="button" onclick="window.close();" style="background: #6c757d; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; margin-right: 10px;">
                    <?php esc_html_e('انصراف', 'ontime'); ?>
                </button>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
}
