<?php
/**
 * OnTime Payment Provider Interface
 * 
 * Defines the contract for all payment providers
 * 
 * @package OnTime
 * @subpackage Payment
 * @since 1.1.0
 */

namespace OnTime\Payment;

/**
 * Payment Provider Interface
 * 
 * All payment gateways must implement this interface to ensure
 * consistent behavior across different payment providers.
 */
interface Payment_Provider_Interface
{
    /**
     * Get the unique identifier for this payment provider
     * 
     * @return string Provider ID (e.g., 'zarinpal', 'mellat', 'mock')
     */
    public function get_id();

    /**
     * Get the display name for this payment provider
     * 
     * @return string Human-readable name
     */
    public function get_name();

    /**
     * Get the description for this payment provider
     * 
     * @return string Description
     */
    public function get_description();

    /**
     * Initialize the payment process
     * 
     * @param array $order_data Order/Appointment data
     * @return array Payment initiation result with 'success', 'redirect_url', and 'transaction_id'
     */
    public function initiate_payment($order_data);

    /**
     * Verify payment from callback
     * 
     * @param array $callback_data Data received from payment gateway callback
     * @return array Verification result with 'success', 'status', 'transaction_id', 'reference_id'
     */
    public function verify_payment($callback_data);

    /**
     * Get the callback URL for this provider
     * 
     * @return string Callback URL
     */
    public function get_callback_url();

    /**
     * Check if this provider is available/enabled
     * 
     * @return bool
     */
    public function is_available();

    /**
     * Get supported currencies
     * 
     * @return array Array of currency codes (e.g., ['IRT', 'IRR', 'USD'])
     */
    public function get_supported_currencies();

    /**
     * Get default currency for this provider
     * 
     * @return string Default currency code
     */
    public function get_default_currency();
}
