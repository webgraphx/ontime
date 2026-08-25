<?php
/**
 * Payment abstraction layer.
 *
 * @package OnTime
 */

defined( 'ABSPATH' ) || exit;

/**
 * Interface that every OnTime payment provider must implement.
 */
interface OnTime_Payment_Provider {

	/**
	 * Request a payment and return the gateway redirect URL.
	 *
	 * @param int   $appointment_id Appointment id.
	 * @param float $amount        Amount in major currency units.
	 * @return string Redirect URL (empty on failure).
	 */
	public function request_payment( $appointment_id, $amount );

	/**
	 * Verify a callback / IPN request and return the verification result.
	 *
	 * @return array{success:bool, appointment_id:int, transaction_id:string, message:string}
	 */
	public function verify_callback();

	/**
	 * Unique provider key (matches the settings dropdown).
	 *
	 * @return string
	 */
	public function get_key();
}

/**
 * Central payment handler: selects the configured provider, dispatches
 * the callback request and updates the appointment status securely.
 */
class OnTime_Payment_Handler {

	/**
	 * Instantiate a provider by key, falling back to the mock provider.
	 *
	 * @param string $key Provider key.
	 * @return OnTime_Payment_Provider|null
	 */
	public function get_provider( $key = '' ) {
		$key = '' === $key ? OnTime_Admin::get( 'payment_provider' ) : $key;

		$provider = null;
		switch ( $key ) {
			case 'zarinpal':
				require_once ONTIME_DIR . 'includes/payments/class-payment-zarinpal.php';
				$provider = new OnTime_Payment_Zarinpal();
				break;
			case 'saman':
				// Generic placeholder provider; extendable by third parties.
				$provider = apply_filters( 'ontime_payment_provider_saman', null );
				break;
			case 'mock':
			default:
				require_once ONTIME_DIR . 'includes/payments/class-payment-mock.php';
				$provider = new OnTime_Payment_Mock();
				break;
		}

		/**
		 * Filter the resolved payment provider so integrators can supply
		 * their own implementation.
		 */
		return apply_filters( 'ontime_payment_provider', $provider, $key );
	}

	/**
	 * Handle an inbound payment callback / IPN. Verifies the request,
	 * then securely updates the appointment status.
	 *
	 * @return void
	 */
	public function handle_callback() {
		$key = isset( $_REQUEST['provider'] ) ? sanitize_key( wp_unslash( $_REQUEST['provider'] ) ) : '';
		if ( '' === $key ) {
			$key = OnTime_Admin::get( 'payment_provider' );
		}

		$provider = $this->get_provider( $key );
		if ( ! $provider ) {
			status_header( 400 );
			wp_die( esc_html__( 'درگاه پرداخت نامعتبر.', 'ontime' ) );
		}

		$result = $provider->verify_callback();

		if ( empty( $result['success'] ) || empty( $result['appointment_id'] ) ) {
			status_header( 402 );
			wp_die( esc_html( isset( $result['message'] ) ? $result['message'] : __( 'تأیید پرداخت ناموفق بود.', 'ontime' ) ) );
		}

		$db = new OnTime_Database();
		$ok = $db->set_appointment_status(
			(int) $result['appointment_id'],
			'confirmed',
			isset( $result['transaction_id'] ) ? sanitize_text_field( $result['transaction_id'] ) : ''
		);

		if ( ! $ok ) {
			status_header( 500 );
			wp_die( esc_html__( 'به‌روزرسانی وضعیت نوبت ناموفق بود.', 'ontime' ) );
		}

		wp_safe_redirect( add_query_arg( 'ontime_paid', '1', home_url() ) );
		exit;
	}
}