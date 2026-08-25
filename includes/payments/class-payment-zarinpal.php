<?php
/**
 * Zarinpal payment provider (reference Iranian gateway implementation).
 *
 * This is a clean, secure reference implementation. Real merchant
 * credentials are read from the OnTime settings. Network calls use
 * wp_remote_post and are only made when the provider is active.
 *
 * @package OnTime
 */

defined( 'ABSPATH' ) || exit;

/**
 * Zarinpal gateway provider: request_payment builds the redirect URL;
 * verify_callback validates the authority/status returned by Zarinpal.
 */
class OnTime_Payment_Zarinpal implements OnTime_Payment_Provider {

	/** Sandbox flag override for development. */
	const MERCHANT_META_KEY = 'ontime_zarinpal_merchant';

	/**
	 * {@inheritdoc}
	 */
	public function get_key() {
		return 'zarinpal';
	}

	/**
	 * Retrieve the configured merchant id (filterable, e.g. from settings
	 * or an integration plugin).
	 *
	 * @return string
	 */
	private function merchant_id() {
		return apply_filters( 'ontime_zarinpal_merchant', '' );
	}

	/**
	 * Return the API base URL (sandbox vs. live, filterable).
	 *
	 * @return string
	 */
	private function api_base() {
		return apply_filters( 'ontime_zarinpal_api_base', 'https://api.zarinpal.com/pg/v4/payment/request.json' );
	}

	/**
	 * Return the gateway start URL.
	 *
	 * @return string
	 */
	private function gateway_base() {
		return apply_filters( 'ontime_zarinpal_gateway', 'https://www.zarinpal.com/pg/StartPay/' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function request_payment( $appointment_id, $amount ) {
		$merchant = $this->merchant_id();
		if ( '' === $merchant ) {
			return '';
		}

		$callback = add_query_arg(
			array(
				'ontime-callback' => '1',
				'provider'         => 'zarinpal',
				'appointment_id'   => (int) $appointment_id,
			),
			home_url( '/' )
		);

		$body = wp_json_encode( array(
			'merchant_id'  => $merchant,
			'amount'       => (int) round( $amount ),
			'description'  => sprintf( __( 'رزرو نوبت شماره %d', 'ontime' ), $appointment_id ),
			'callback_url' => $callback,
		) );

		$response = wp_remote_post( $this->api_base(), array(
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => $body,
			'timeout' => 20,
		) );

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== (int) $code || empty( $body['data']['authority'] ) ) {
			return '';
		}

		$authority = sanitize_text_field( $body['data']['authority'] );
		// Persist authority so verify_callback can reuse it.
		update_option( 'ontime_zarinpal_authority_' . $appointment_id, $authority, false );

		return $this->gateway_base() . rawurlencode( $authority );
	}

	/**
	 * {@inheritdoc}
	 */
	public function verify_callback() {
		$appointment_id = isset( $_REQUEST['appointment_id'] ) ? absint( $_REQUEST['appointment_id'] ) : 0;
		$authority     = isset( $_REQUEST['Authority'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['Authority'] ) ) : '';
		$status        = isset( $_REQUEST['Status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['Status'] ) ) : '';

		if ( ! $appointment_id || 'OK' !== $status || '' === $authority ) {
			return array(
				'success'        => false,
				'appointment_id' => $appointment_id,
				'transaction_id'=> '',
				'message'        => __( 'پاسخ درگاه نامعتبر است.', 'ontime' ),
			);
		}

		$db = new OnTime_Database();
		$appt = $db->get_appointment( $appointment_id );
		if ( ! $appt ) {
			return array(
				'success'        => false,
				'appointment_id' => $appointment_id,
				'transaction_id'=> '',
				'message'        => __( 'نوبت یافت نشد.', 'ontime' ),
			);
		}

		$merchant = $this->merchant_id();
		if ( '' === $merchant ) {
			return array(
				'success'        => false,
				'appointment_id' => $appointment_id,
				'transaction_id'=> '',
				'message'        => __( 'کد پذیرنده تنظیم نشده است.', 'ontime' ),
			);
		}

		$verify_url = apply_filters( 'ontime_zarinpal_verify_url', 'https://api.zarinpal.com/pg/v4/payment/verify.json' );
		$body = wp_json_encode( array(
			'merchant_id' => $merchant,
			'amount'      => (int) round( (float) $appt['total_price'] ),
			'authority'   => $authority,
		) );

		$response = wp_remote_post( $verify_url, array(
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => $body,
			'timeout' => 20,
		) );

		if ( is_wp_error( $response ) ) {
			return array(
				'success'        => false,
				'appointment_id' => $appointment_id,
				'transaction_id'=> '',
				'message'        => __( 'ارتباط با درگاه برقرار نشد.', 'ontime' ),
			);
		}

		$payload = json_decode( wp_remote_retrieve_body( $response ), true );
		$ref_id  = isset( $payload['data']['ref_id'] ) ? sanitize_text_field( $payload['data']['ref_id'] ) : '';
		$code    = isset( $payload['data']['code'] ) ? (int) $payload['data']['code'] : 0;

		if ( 100 !== $code && 101 !== $code ) {
			return array(
				'success'        => false,
				'appointment_id' => $appointment_id,
				'transaction_id'=> '',
				'message'        => __( 'پرداخت تأیید نشد.', 'ontime' ),
			);
		}

		return array(
			'success'        => true,
			'appointment_id' => $appointment_id,
			'transaction_id'=> $ref_id,
			'message'        => __( 'پرداخت با موفقیت تأیید شد.', 'ontime' ),
		);
	}
}