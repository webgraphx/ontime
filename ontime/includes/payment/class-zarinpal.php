<?php
/**
 * Zarinpal payment gateway integration.
 *
 * Communicates with the Zarinpal v4 REST API to request payments and
 * verify callbacks. Uses `wp_remote_post` for all HTTP communication.
 *
 * @package OnTime
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Zarinpal payment gateway.
 *
 * @since 1.0.0
 */
final class OnTime_Payment_Zarinpal implements OnTime_Payment_Gateway {

	/** @since 1.0.0 @var string API request endpoint. */
	const API_REQUEST = 'https://api.zarinpal.com/pg/v4/payment-request.json';

	/** @since 1.0.0 @var string API verify endpoint. */
	const API_VERIFY  = 'https://api.zarinpal.com/pg/v4/payment-verify.json';

	/** @since 1.0.0 @var string StartPay base URL. */
	const START_PAY   = 'https://www.zarinpal.com/pg/StartPay/';

	/** @since 1.0.0 @var int Success codes from Zarinpal verify. */
	const CODE_OK     = 100;
	const CODE_ALREADY = 101;

	/**
	 * Get the gateway slug.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_slug() {
		return 'zarinpal';
	}

	/**
	 * Get the gateway display name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name() {
		return __( 'Ø²Ø±ÛÙâÙ¾Ø§Ù', 'ontime' );
	}

	/**
	 * Get the merchant code from settings.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	private function get_merchant_id() {
		$opts = get_option( 'ontime_settings', array() );
		if ( is_array( $opts ) && ! empty( $opts['merchant_code'] ) ) {
			return sanitize_text_field( $opts['merchant_code'] );
		}
		return (string) OnTime_Database::instance()->get_setting( 'merchant_code', '' );
	}

	/**
	 * Build the callback URL for this gateway.
	 *
	 * @since 1.0.0
	 *
	 * @param int $appointment_id Appointment ID.
	 * @return string
	 */
	private function get_callback_url( $appointment_id ) {
		return add_query_arg(
			array(
				'ontime_callback' => '1',
				'appointment_id'   => (int) $appointment_id,
			),
			home_url( '/' )
		);
	}

	/**
	 * Request a Zarinpal payment.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $appointment_id Appointment ID.
	 * @param float $amount         Amount in Toman.
	 * @return string|WP_Error Redirect URL on success, WP_Error on failure.
	 */
	public function request( $appointment_id, $amount ) {
		$merchant_id = $this->get_merchant_id();
		if ( '' === $merchant_id ) {
			return new WP_Error( 'ontime_zarinpal_no_merchant', __( 'Ú©Ø¯ Ù¾Ø°ÛØ±ÙØ¯Ù Ø²Ø±ÛÙâÙ¾Ø§Ù ØªÙØ¸ÛÙ ÙØ´Ø¯Ù Ø§Ø³Øª.', 'ontime' ) );
		}

		$amount = (int) $amount;
		if ( $amount < 1000 ) {
			return new WP_Error( 'ontime_zarinpal_low_amount', __( 'ÙØ¨ÙØº Ù¾Ø±Ø¯Ø§Ø®Øª Ú©ÙØªØ± Ø§Ø² Ø­Ø¯Ø§ÙÙ ÙØ¬Ø§Ø² Ø§Ø³Øª.', 'ontime' ) );
		}

		$body = array(
			'merchant_id'  => $merchant_id,
			'amount'       => $amount,
			'callback_url' => $this->get_callback_url( $appointment_id ),
			'description'  => sprintf(
				/* translators: %d: Appointment ID */
				__( 'Ø±Ø²Ø±Ù ÙÙØ¨Øª Ø´ÙØ§Ø±Ù %d â OnTime', 'ontime' ),
				$appointment_id
			),
			'metadata'     => array(
				'appointment_id' => (int) $appointment_id,
			),
		);

		$response = wp_remote_post( self::API_REQUEST, array(
			'body'    => wp_json_encode( $body ),
			'headers' => array( 'Content-Type' => 'application/json' ),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'ontime_zarinpal_http', __( 'Ø§Ø±ØªØ¨Ø§Ø· Ø¨Ø§ Ø¯Ø±Ú¯Ø§Ù Ø²Ø±ÛÙâÙ¾Ø§Ù ÙØ§ÙÙÙÙ Ø¨ÙØ¯.', 'ontime' ), $response->get_error_message() );
		}

		$code    = wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== (int) $code || empty( $decoded ) ) {
			return new WP_Error( 'ontime_zarinpal_api', __( 'Ù¾Ø§Ø³Ø® ÙØ§ÙØ¹ØªØ¨Ø± Ø§Ø² Ø¯Ø±Ú¯Ø§Ù Ø²Ø±ÛÙâÙ¾Ø§Ù.', 'ontime' ) );
		}

		// v4 response shape: { data: { authority, ... }, errors: {...} }.
		if ( ! empty( $decoded['errors'] ) ) {
			$error_msg = isset( $decoded['errors']['message'] ) ? $decoded['errors']['message'] : __( 'Ø®Ø·Ø§Û Ø¯Ø±Ú¯Ø§Ù Ø²Ø±ÛÙâÙ¾Ø§Ù.', 'ontime' );
			return new WP_Error( 'ontime_zarinpal_error', $error_msg );
		}

		if ( empty( $decoded['data']['authority'] ) ) {
			return new WP_Error( 'ontime_zarinpal_no_authority', __( 'Ú©Ø¯Authority Ø§Ø² Ø¯Ø±Ú¯Ø§Ù Ø¯Ø±ÛØ§ÙØª ÙØ´Ø¯.', 'ontime' ) );
		}

		$authority = sanitize_text_field( $decoded['data']['authority'] );
		return self::START_PAY . $authority;
	}

	/**
	 * Verify a Zarinpal payment callback.
	 *
	 * Reads the `Authority` and `Status` query parameters that Zarinpal
	 * sends back, then calls the verify API endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @param int $appointment_id Appointment ID.
	 * @return array Verification result.
	 */
	public function verify( $appointment_id ) {
		$authority = isset( $_GET['Authority'] ) ? sanitize_text_field( wp_unslash( $_GET['Authority'] ) ) : '';
		$status     = isset( $_GET['Status'] ) ? sanitize_text_field( wp_unslash( $_GET['Status'] ) ) : '';

		if ( 'OK' !== $status || '' === $authority ) {
			return array(
				'success'        => false,
				'transaction_id' => '',
				'message'        => __( 'Ù¾Ø±Ø¯Ø§Ø®Øª ØªÙØ³Ø· Ú©Ø§Ø±Ø¨Ø± ÙØºÙ Ø´Ø¯ ÛØ§ ÙØ§ÙÙÙÙ Ø¨ÙØ¯.', 'ontime' ),
			);
		}

		// Fetch the appointment amount for verification.
		global $wpdb;
		$table    = OnTime_Database::instance()->get_table( 'appointments' );
		$apt      = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT a.id, s.price FROM {$table} a JOIN " . OnTime_Database::instance()->get_table( 'services' ) . " s ON a.service_id = s.id WHERE a.id = %d",
				$appointment_id
			),
			ARRAY_A
		);

		if ( ! $apt ) {
			return array(
				'success'        => false,
				'transaction_id' => '',
				'message'        => __( 'ÙÙØ¨Øª ÛØ§ÙØª ÙØ´Ø¯.', 'ontime' ),
			);
		}

		$amount = (int) $apt['price'];
		$merchant_id = $this->get_merchant_id();
		$body = array(
			'merchant_id' => $merchant_id,
			'amount'       => $amount,
			'authority'   == $authority,
		);

		$response = wp_remote_post( self::API_VERIFY, array(
			'body'    => wp_json_encode( $body ),
			'headers' => array( 'Content-Type' => 'application/json' ),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return array(
				'success'        => false,
				'transaction_id' => '',
				'message'        => __( 'Ø§Ø±ØªØ¨Ø§Ø· Ø¨Ø§ Ø¯Ø±Ú¯Ø§Ù Ø¨Ø±Ø§Û ØªØ£ÛÛØ¯ Ù¾Ø±Ø¯Ø§Ø®Øª ÙØ§ÙÙÙÙ Ø¨ÙØ¯.', 'ontime' ),
			);
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $decoded ) || ! empty( $decoded['errors'] ) ) {
			$error_msg = isset( $decoded['errors']['message'] ) ? $decoded['errors']['message'] : __( 'ØªØ£ÛÛØ¯ Ù¾Ø±Ø¯Ø§Ø®Øª ÙØ§ÙÙÙÙ Ø¨ÙØ¯.', 'ontime' );
			return array(
				'success'        => false,
				'transaction_id' => '',
				'message'        => $error_msg,
			);
		}

		$code   = isset( $decoded['data']['code'] ) ? (int) $decoded['data']['code'] : 0;
		$ref_id = isset( $decoded['data']['ref_id'] ) ? sanitize_text_field( $decoded['data']['ref_id'] ) : '';

		if ( self::CODE_OK === $code || self::CODE_ALREADY === $code ) {
			return array(
				'success'        => true,
				'transaction_id' => $ref_id,
				'message'        => __( 'Ù¾Ø±Ø¯Ø§Ø®Øª Ø¨Ø§ ÙÙÙÙÛØª ØªØ£ÛÛØ¯ Ø´Ø¯.', 'ontime' ),
			);
		}

		return array(
			'success'        => false,
			'transaction_id' => $ref_id,
			'message'        => __( 'ØªØ£ÛÛØ¯ Ù¾Ø±Ø¯Ø§Ø®Øª ÙØ§ÙÙÙÙ Ø¨ÙØ¯.', 'ontime' ),
		);
	}
}
