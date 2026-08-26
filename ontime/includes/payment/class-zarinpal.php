<?php
/**
 * Zarinpal payment gateway integration.
 *
 * Communicates with the Zarinpal v4 REST API to request payments and
 * verify callbacks. Uses wp_remote_post for all HTTP communication.
 *
 * Supports sandbox mode for testing without a real merchant account.
 * When sandbox is enabled, requests go to sandbox.zarinpal.com and a
 * default test merchant ID is used if none is configured.
 *
 * @package OnTime
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class OnTime_Payment_Zarinpal implements OnTime_Payment_Gateway {

	const API_REQUEST = 'https://api.zarinpal.com/pg/v4/payment-request.json';
	const API_VERIFY  = 'https://api.zarinpal.com/pg/v4/payment-verify.json';
	const START_PAY   = 'https://www.zarinpal.com/pg/StartPay/';
	const SANDBOX_API_REQUEST = 'https://sandbox.zarinpal.com/pg/v4/payment-request.json';
	const SANDBOX_API_VERIFY  = 'https://sandbox.zarinpal.com/pg/v4/payment-verify.json';
	const SANDBOX_START_PAY   = 'https://sandbox.zarinpal.com/pg/StartPay/';
	const SANDBOX_MERCHANT    = '00000000-0000-0000-0000-000000000000';
	const CODE_OK     = 100;
	const CODE_ALREADY = 101;

	private function is_sandbox() {
		$opts = get_option( 'ontime_settings', array() );
		if ( is_array( $opts ) && ! empty( $opts['zarinpal_sandbox'] ) ) {
			return (bool) $opts['zarinpal_sandbox'];
		}
		return (bool) OnTime_Database::instance()->get_setting( 'zarinpal_sandbox', 0 );
	}

	public function get_slug() {
		return 'zarinpal';
	}

	public function get_name() {
		return __( 'زرین‌پال', 'ontime' );
	}

	private function get_merchant_id() {
		$opts = get_option( 'ontime_settings', array() );
		if ( is_array( $opts ) && ! empty( $opts['merchant_code'] ) ) {
			return sanitize_text_field( $opts['merchant_code'] );
		}
		$merchant = (string) OnTime_Database::instance()->get_setting( 'merchant_code', '' );
		if ( '' === $merchant && $this->is_sandbox() ) {
			return self::SANDBOX_MERCHANT;
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

	private function sanitize_amount( $amount ) {
		if ( ! is_numeric( $amount ) ) {
			$amount = strtr( (string) $amount, array(
				'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
				'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
				'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
				'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
			) );
			if ( ! is_numeric( $amount ) ) {
				return 0;
			}
		}
		return absint( $amount );
	}

	public function request( $appointment_id, $amount ) {
		$merchant_id = $this->get_merchant_id();
		if ( '' === $merchant_id ) {
			return new WP_Error( 'ontime_zarinpal_no_merchant', __( 'کد پذیرنده زرین‌پال تنظیم نشده است.', 'ontime' ) );
		}

		$amount = $this->sanitize_amount( $amount );
		if ( $amount < 1000 ) {
			return new WP_Error( 'ontime_zarinpal_low_amount', __( 'مبلغ پرداخت کمتر از حداقل مجاز است.', 'ontime' ) );
		}

		$body = array(
			'merchant_id'  => $merchant_id,
			'amount'       => $amount,
			'callback_url' => $this->get_callback_url( $appointment_id ),
			'description'  => sprintf( __( 'رزرو نوبت شماره %d — OnTime', 'ontime' ), $appointment_id ),
			'metadata'     => array( 'appointment_id' => (int) $appointment_id ),
		);

		$endpoint = $this->is_sandbox() ? self::SANDBOX_API_REQUEST : self::API_REQUEST;

		$response = wp_remote_post( $endpoint, array(
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
		$start_pay = $this->is_sandbox() ? self::SANDBOX_START_PAY : self::START_PAY;
		return $start_pay . $authority;
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

		$amount = $this->sanitize_amount( $apt['price'] );
		if ( $amount < 1000 ) {
			return array(
				'success'        => false,
				'transaction_id' => '',
				'message'        => __( 'مبلغ نوبت نامعتبر است.', 'ontime' ),
			);
		}

		$merchant_id = $this->get_merchant_id();
		$body = array(
			'merchant_id' => $merchant_id,
			'amount'      => $amount,
			'authority'   => $authority,
		);

		$endpoint = $this->is_sandbox() ? self::SANDBOX_API_VERIFY : self::API_VERIFY;

		$response = wp_remote_post( $endpoint, array(
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
