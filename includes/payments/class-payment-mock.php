<?php
/**
 * Mock payment provider for development and testing.
 *
 * @package OnTime
 */

defined( 'ABSPATH' ) || exit;

/**
 * A no-network payment provider used during development. It "requests"
 * a payment by redirecting to an internal callback that auto-verifies,
 * so the full booking + callback flow can be exercised offline.
 */
class OnTime_Payment_Mock implements OnTime_Payment_Provider {

	/**
	 * {@inheritdoc}
	 */
	public function get_key() {
		return 'mock';
	}

	/**
	 * {@inheritdoc}
	 */
	public function request_payment( $appointment_id, $amount ) {
		$args = array(
			'ontime-callback' => '1',
			'provider'        => 'mock',
			'appointment_id'  => (int) $appointment_id,
			'amount'          => (float) $amount,
			'mock_verify'     => wp_create_nonce( 'ontime_mock_verify' ),
		);
		return add_query_arg( $args, home_url( '/' ) );
	}

	/**
	 * {@inheritdoc}
	 */
	public function verify_callback() {
		$appointment_id = isset( $_REQUEST['appointment_id'] ) ? absint( $_REQUEST['appointment_id'] ) : 0;
		$nonce          = isset( $_REQUEST['mock_verify'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['mock_verify'] ) ) : '';

		if ( ! $appointment_id || ! wp_verify_nonce( $nonce, 'ontime_mock_verify' ) ) {
			return array(
				'success'        => false,
				'appointment_id' => $appointment_id,
				'transaction_id'=> '',
				'message'        => __( 'تأییدیه شبیه‌سازی نامعتبر است.', 'ontime' ),
			);
		}

		return array(
			'success'        => true,
			'appointment_id' => $appointment_id,
			'transaction_id'=> sprintf( 'MOCK-%d-%d', $appointment_id, time() ),
			'message'        => __( 'پرداخت شبیه‌سازی‌شده با موفقیت تأیید شد.', 'ontime' ),
		);
	}
}