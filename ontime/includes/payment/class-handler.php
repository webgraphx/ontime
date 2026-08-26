<?php
/**
 * Payment handler and gateway interface (Singleton).
 *
 * Provides a modular payment abstraction with a gateway interface,
 * a Mock provider for development, and a generic callback handler
 * that securely verifies payments from Iranian gateways (e.g. Zarinpal).
 *
 * @package OnTime
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Payment gateway contract.
 *
 * Every payment provider must implement this interface.
 *
 * @since 0.1.0
 */
interface OnTime_Payment_Gateway {

	public function get_slug();
	public function get_name();
	public function request( $appointment_id, $amount );
	public function verify( $appointment_id );
}

final class OnTime_Payment_Handler {

	private static $instance = null;
	private $gateways = array();

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register_gateways();
			self::$instance->register_hooks();
		}
		return self::$instance;
	}

	private function __construct() {}

	private function register_gateways() {
		$classes = array(
			'OnTime_Payment_Zarinpal',
		);

		// Only register the mock gateway in debug/test mode.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$classes[] = 'OnTime_Payment_Mock';
		}
		foreach ( $classes as $class ) {
			if ( class_exists( $class ) ) {
				$gw = new $class();
				if ( $gw instanceof OnTime_Payment_Gateway ) {
					$this->gateways[ $gw->get_slug() ] = $gw;
				}
			}
		}
	}

	private function register_hooks() {
		add_action( 'template_redirect', array( $this, 'handle_callback' ) );
	}

	public function get_gateways() {
		return $this->gateways;
	}

	public function get_active_gateway() {
		$slug = OnTime_Database::instance()->get_setting( 'payment_gateway', 'mock' );
		$opts = get_option( 'ontime_settings', array() );
		if ( is_array( $opts ) && ! empty( $opts['payment_gateway'] ) ) {
			$slug = $opts['payment_gateway'];
		}
		$slug = sanitize_key( $slug );
		return isset( $this->gateways[ $slug ] ) ? $this->gateways[ $slug ] : null;
	}

	public function process_payment( $appointment_id, $amount ) {
		$gateway = $this->get_active_gateway();
		if ( null === $gateway ) {
			return new WP_Error( 'ontime_no_gateway', __( 'هیچ درگاه پرداختی فعال نیست.', 'ontime' ) );
		}
		return $gateway->request( $appointment_id, $amount );
	}

	public function handle_callback() {
		if ( is_admin() ) {
			return;
		}

		$callback = isset( $_GET['ontime_callback'] ) ? sanitize_key( wp_unslash( $_GET['ontime_callback'] ) ) : '';
		if ( '' === $callback ) {
			return;
		}

		$appointment_id = isset( $_GET['appointment_id'] ) ? (int) $_GET['appointment_id'] : 0;
		if ( $appointment_id < 1 ) {
			wp_die( esc_html__( 'کد نوبت نامعتبر است.', 'ontime' ), esc_html__( 'خطای پرداخت', 'ontime' ), array( 'response' => 400 ) );
		}

		$gateway = $this->get_active_gateway();
		if ( null === $gateway ) {
			wp_die( esc_html__( 'درگاه پرداخت پیکربندی نشده است.', 'ontime' ), esc_html__( 'خطای پرداخت', 'ontime' ), array( 'response' => 500 ) );
		}

		$result = $gateway->verify( $appointment_id );

		$this->update_appointment_after_payment( $appointment_id, $result );

		do_action( 'ontime_payment_callback_processed', $appointment_id, $result );

		if ( ! empty( $result['success'] ) ) {
			$redirect = $this->get_redirect_url( 'success', $appointment_id );
		} else {
			$redirect = $this->get_redirect_url( 'failed', $appointment_id );
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	private function update_appointment_after_payment( $appointment_id, $result ) {
		global $wpdb;
		$table = OnTime_Database::instance()->get_table( 'appointments' );

		if ( ! empty( $result['success'] ) ) {
			$wpdb->update(
				$table,
				array(
					'payment_status'  => 'paid',
					'transaction_id'  => sanitize_text_field( $result['transaction_id'] ?? '' ),
					'status'          => 'confirmed',
				),
				array( 'id' => $appointment_id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			$wpdb->update(
				$table,
				array(
					'payment_status' => 'failed',
					'status'         => 'pending',
				),
				array( 'id' => $appointment_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		}
	}

	private function get_redirect_url( $status, $appointment_id ) {
		return apply_filters(
			'ontime_payment_redirect_url',
			add_query_arg(
				array(
					'ontime_payment' => $status,
					'appointment_id' => $appointment_id,
				),
				home_url( '/' )
			),
			$status,
			$appointment_id
		);
	}
}
