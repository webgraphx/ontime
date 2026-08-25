<?php
/**
 * Mock payment gateway for development and testing.
 *
 * Simulates a successful payment flow without contacting any external
 * service. Useful for local development and automated testing.
 *
 * @package OnTime
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mock payment gateway.
 *
 * @since 1.0.0
 */
final class OnTime_Payment_Mock implements OnTime_Payment_Gateway {

	/**
	 * Get the gateway slug.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_slug() {
		return 'mock';
	}

	/**
	 * Get the gateway display name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name() {
		return __( 'تست (Mock)', 'ontime' );
	}

	/**
	 * Request a mock payment.
	 *
	 * Returns a callback URL that simulates an immediate successful payment,
	 * so the browser can redirect directly and trigger the verify step.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $appointment_id Appointment ID.
	 * @param float $amount         Amount in Toman (ignored in mock).
	 * @return string Redirect URL.
	 */
	public function request( $appointment_id, $amount ) {
		return add_query_arg(
			array(
				'ontime_callback' => '1',
				'appointment_id'   => (int) $appointment_id,
				'mock_status'      => 'success',
			),
			home_url( '/' )
		);
	}

	/**
	 * Verify a mock payment callback.
	 *
	 * Always returns success with a synthetic transaction ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $appointment_id Appointment ID.
	 * @return array Verification result.
	 */
	public function verify( $appointment_id ) {
		return array(
			'success'        => true,
			'transaction_id' => 'MOCK-' . zeroise( $appointment_id, 6 ) . '-' . time(),
			'message'        => __( 'پرداخت آزمایشی موفق بود.', 'ontime' ),
		);
	}
}
