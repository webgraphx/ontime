<?php
/**
 * Zarinpal payment gateway integration.
 *
 * Communicates with the Zarinpal v4 REST API to request payments and
 * verify callbacks. Uses `wp_remote_post` for all HTTP communication.
 *
 * Supports a sandbox mode for testing without hitting the live API.
 * Enable it in Settings → OnTime → Payment → Sandbox Mode.
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

	/** @since 1.0.0 @var string API request endpoint (live). */
	const API_REQUEST_LIVE = 'https://api.zarinpal.com/pg/v4/payment-request.json';

	/** @since 1.0.0 @var string API verify endpoint (live). */
	const API_VERIFY_LIVE  = 'https://api.zarinpal.com/pg/v4/payment-verify.json';

	/** @since 1.0.0 @var string API request endpoint (sandbox). */
	const API_REQUEST_SANDBOX = 'https://sandbox.zarinpal.com/pg/v4/payment-request.json';

	/** @since 1.0.0 @var string API verify endpoint (sandbox). */
	const API_VERIFY_SANDBOX  = 'https://sandbox.zarinpal.com/pg/v4/payment-verify.json';

	/** @since 1.0.0 @var string StartPay base URL (live). */
	const START_PAY_LIVE    = 'https://www.zarinpal.com/pg/StartPay/';

	/** @since 1.0.0 @var string StartPay base URL (sandbox). */
	const START_PAY_SANDBOX = 'https://sandbox.zarinpal.com/pg/StartPay/';

	/** @since 1.0.0 @var int Success codes from Zarinpal verify. */
	const CODE_OK     = 100;
	const CODE_ALREADY = 101;

	public function get_slug() {
		return 'zarinpal';
	}

	public function get_name() {
		return __( 'زرین‌پال', 'ontime' );
	}

	private function is_sandbox() {
		$opts = get_option( 'ontime_settings', array() );
		if ( is_array( $opts ) && isset( $opts['zarinpal_sandbox'] ) ) {
			return (bool) $opts['zarinpal_sandbox'];
		}
		return (bool) OnTime_Database::instance()->get_setting( 'zarinpal_sandbox', 0 );
	}

	private function get_api_request_url() {
		return $this->is_sandbox() ? self::API_REQUEST_SANDBOX : self::API_REQUEST_LIVE;
	}

	private function get_api_verify_url() {
		return $this->is_sandbox() ? self::API_VERIFY_SANDBOX : self::API_VERIFY_LIVE;
	}

	private function get_start_pay_url() {
		return $this->is_sandbox() ? self::START_PAY_SANDBOX : self::START_PAY_LIVE;
	}

	private function get_merchant_id() {
		$opts = get_option( 'ontime_settings', array() );
		if ( is_array( $opts ) && ! empty( $opts['merchant_code'] ) ) {
			return sanitize_text_field( $opts['merchant_code'] );
		}
		$merchant = (string) OnTime_Database::instance()->get_setting( 'merchant_code', '' );
		if ( '' === $merchant && $this->is_sandbox() ) {
			return '00000000-0000-0000-0000-000000000000';
		}
		return $merchant;
	}

	private function get_callback_url( $appointment_id ) {
		return add_query_arg(
			array(
				'ontime_callback' => '1',
				'appointment_id'   => (int) $appointment_id,
			),
			home_url( '/' )
		);
	}

	public function request( $appointment_id, $amount ) {
		$merchant_id = $this->get_merchant_id();
		if ( '' === $merchant_id ) {
			return new WP_Error( 'ontime_zarinpal_no_merchant', __( 'کد پذیرنده زرین‌پال تنظیم نشده است.', 'ontime' ) );
		}

		$amount = absint( $amount );
		if ( $amount < 1000 ) {
			return new WP_Error( 'ontime_zarinpal_low_amount', __( 'مبلغ پرداخت کمتر از حداقل مجاز است.', 'ontime' ) );
		}

		$body = array(
			'merchant_id'  => $merchant_id,
			'amount'       => $amount,
			'callback_url' => $this->get_callback_url( $appointment_id ),
			'description'  => sprintf(
				__( 'رزرو نوبت شماره %d — OnTime', 'ontime' ),
				$appointment_id
			),
			'metadata'     => array(
				'appointment_id' => (int) $appointment_id,
			),
		);

		$response = wp_remote_post( $this->get_api_request_url(), array(
			'body'    => wp_json_encode( $body ),
			'headers' => array( 'Content-Type' => 'application/json' ),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'ontime_zarinpal_http', __( 'ارتباط با درگاه زرین‌پال ناموفق بود.', 'ontime' ), $response->get_error_message() );
		}

		$code    = wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== (int) $code || empty( $decoded ) ) {
			return new WP_Error( 'ontime_zarinpal_api', __( 'پاسخ نامعتبر از درگاه زرین‌پال.', 'ontime' ) );
		}

		if ( ! empty( $decoded['errors'] ) ) {
			$error_msg = isset( $decoded['errors']['message'] ) ? $decoded['errors']['message'] : __( 'خطای درگاه زرین‌پال.', 'ontime' );
			return new WP_Error( 'ontime_zarinpal_error', $error_msg );
		}

		if ( empty( $decoded['data']['authority'] ) ) {
			return new WP_Error( 'ontime_zarinpal_no_authority', __( 'کد Authority از درگاه دریافت نشد.', 'ontime' ) );
		}

		$authority = sanitize_text_field( $decoded['data']['authority'] );
		return $this->get_start_pay_url() . $authority;
	}

	public function verify( $appointment_id ) {
		$authority = isset( $_GET['Authority'] ) ? sanitize_text_field( wp_unslash( $_GET['Authority'] ) ) : '';
		$status     = isset( $_GET['Status'] ) ? sanitize_text_field( wp_unslash( $_GET['Status'] ) ) : '';

		if ( 'OK' !== $status || '' === $authority ) {
			return array(
				'success'        => false,
				'transaction_id' => '',
				'message'        => __( 'پرداخت توسط کاربر لغو شد یا ناموفق بود.', 'ontime' ),
			);
		}

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
				'message'        => __( 'نوبت یافت نشد.', 'ontime' ),
			);
		}

		$raw_price = $apt['price'];

		if ( ! is_numeric( $raw_price ) ) {
			$persian_map = array(
				'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
				'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
				'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
				'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
			);
			$raw_price = strtr( (string) $raw_price, $persian_map );
		}

		$amount = absint( $raw_price );

		if ( $amount < 1000 ) {
			return array(
				'success'        => false,
				'transaction_id' => '',
				'message'        => __( 'مبلغ پرداخت نامعتبر است.', 'ontime' ),
			);
		}

		$merchant_id = $this->get_merchant_id();
		$body = array(
			'merchant_id' => $merchant_id,
			'amount'      => $amount,
			'authority'   => $authority,
		);

		$response = wp_remote_post( $this->get_api_verify_url(), array(
			'body'    => wp_json_encode( $body ),
			'headers' => array( 'Content-Type' => 'application/json' ),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return array(
				'success'        => false,
				'transaction_id' => '',
				'message'        => __( 'ارتباط با درگاه برای تأیید پرداخت ناموفق بود.', 'ontime' ),
			);
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $decoded ) || ! empty( $decoded['errors'] ) ) {
			$error_msg = isset( $decoded['errors']['message'] ) ? $decoded['errors']['message'] : __( 'تأیید پرداخت ناموفق بود.', 'ontime' );
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
				'message'        => __( 'پرداخت با موفقیت تأیید شد.', 'ontime' ),
			);
		}

		return array(
			'success'        => false,
			'transaction_id' => $ref_id,
			'message'        => __( 'تأیید پرداخت ناموفق بود.', 'ontime' ),
		);
	}
}
