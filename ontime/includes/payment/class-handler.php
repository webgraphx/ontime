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

	/**
	 * Get the gateway slug (machine name).
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_slug();

	/**
	 * Get the gateway display name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name();

	/**
	 * Request a payment — returns a redirect URL or WP_Error.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $appointment_id Appointment ID.
	 * @param float $amount        Amount in Toman.
	 * @return string|WP_Error Redirect URL on success, WP_Error on failure.
	 */
	public function request( $appointment_id, $amount );

	/**
	 * Verify a payment callback.
	 *
	 * @since 1.0.0
	 *
	 * @param int $appointment_id Appointment ID.
	 * @return array {
	 *     @type bool   $success        Whether verification succeeded.
	 *     @type string $transaction_id Gateway transaction / reference ID.
	 *     @type string $message        Human-readable result message.
	 * }
	 */
	public function verify( $appointment_id );
}

/**
 * Payment handler (Singleton).
 *
 * Registers available gateways, routes payment requests to the active
 * gateway, and processes payment callbacks securely.
 *
 * @since 0.1.0
 */
final class OnTime_Payment_Handler {

	/** @since 0.1.0 @var OnTime_Payment_Handler|null */
	private static $instance = null;

	/** @since 1.0.0 @var array<string,OnTime_Payment_Gateway> Registered gateways. */
	private $gateways = array();

	/**
	 * Singleton accessor.
	 *
	 * @since 0.1.0
	 * @return OnTime_Payment_Handler
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register_gateways();
			self::$instance->register_hooks();
		}
		return self::$instance;
	}

	private function __construct() {}

	/**
	 * Register built-in payment gateways.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_gateways() {
		$classes = array(
			'OnTime_Payment_Mock',
			'OnTime_Payment_Zarinpal',
		);
		foreach ( $classes as $class ) {
			if ( class_exists( $class ) ) {
				$gw = new $class();
				if ( $gw instanceof OnTime_Payment_Gateway ) {
					$this->gateways[ $gw->get_slug() ] = $gw;
				}
			}
		}
	}

	/**
	 * Hook the payment callback handler.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_hooks() {
		add_action( 'template_redirect', array( $this, 'handle_callback' ) );
	}

	/**
	 * Get all registered gateways.
	 *
	 * @since 1.0.0
	 * @return array<string,OnTime_Payment_Gateway>
	 */
	public function get_gateways() {
		return $this->gateways;
	}

	/**
	 * Get the active gateway based on stored settings.
	 *
	 * @since 1.0.0
	 * @return OnTime_Payment_Gateway|null
	 */
	public function get_active_gateway() {
		$slug = OnTime_Database::instance()->get_setting( 'payment_gateway', 'mock' );
		// Also check the WordPress options override.
		$opts = get_option( 'ontime_settings', array() );
		if ( is_array( $opts ) && ! empty( $opts['payment_gateway'] ) ) {
			$slug = $opts['payment_gateway'];
		}
		$slug = sanitize_key( $slug );
		return isset( $this->gateways[ $slug ] ) ? $this->gateways[ $slug ] : null;
	}

	/**
	 * Process a payment request for an appointment.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $appointment_id Appointment ID.
	 * @param float $amount         Amount in Toman.
	 * @return string|WP_Error Redirect URL on success, WP_Error on failure.
	 */
	public function process_payment( $appointment_id, $amount ) {
		$gateway = $this->get_active_gateway();
		if ( null === $gateway ) {
			return new WP_Error( 'ontime_no_gateway', __( 'هیچ درگاه پرداختی فعال نیست.', 'ontime' ) );
		}
		return $gateway->request( $appointment_id, $amount );
	}

	/**
	 * Handle payment gateway callbacks.
	 *
	 * Detects the `ontime_callback` query var on the front-end and delegates
	 * verification to the active gateway. Updates the appointment status
	 * and redirects the user to an appropriate page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_callback() {
		if ( is_admin() ) {
			return;
		}

		// Detect callback by query var.
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

		/**
		 * Fires after a payment callback has been processed.
		 *
		 * @since 1.0.0
		 *
		 * @param int   $appointment_id Appointment ID.
		 * @param array $result         Verification result array.
		 */
		do_action( 'ontime_payment_callback_processed', $appointment_id, $result );

		if ( ! empty( $result['success'] ) ) {
			$redirect = $this->get_redirect_url( 'success', $appointment_id );
		} else {
			$redirect = $this->get_redirect_url( 'failed', $appointment_id );
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Update the appointment record after a payment callback.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $appointment_id Appointment ID.
	 * @param array $result        Verification result.
	 * @return void
	 */
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

	/**
	 * Build a front-end redirect URL for payment results.
	 *
	 * Uses the site URL with a query string that the theme/plugin can detect.
	 *
	 * @since 1.0.0
	 *
	 * @param string $status         'success' or 'failed'.
	 * @param int    $appointment_id Appointment ID.
	 * @return string
	 */
	private function get_redirect_url( $status, $appointment_id ) {
		/**
		 * Filter the payment result redirect URL.
		 *
		 * @since 1.0.0
		 *
		 * @param string $url            Default redirect URL.
		 * @param string $status         'success' or 'failed'.
		 * @param int    $appointment_id Appointment ID.
		 */
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
