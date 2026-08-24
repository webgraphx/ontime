<?php
/**
 * Payment handler (Singleton) — placeholder for Stage 5.
 *
 * @package OnTime
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface OnTime_Payment_Gateway {

	public function request( $appointment_id, $amount );

	public function verify();
}

final class OnTime_Payment_Handler {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}
}
