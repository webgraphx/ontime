<?php
/**
 * Frontend booking form: shortcode + AJAX handlers.
 *
 * @package OnTime
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `[ontime_booking_form]` shortcode, enqueues lightweight
 * vanilla JS/CSS, and exposes the AJAX endpoints used by each booking step.
 * All endpoints verify nonces and sanitize input before any DB write.
 */
class OnTime_Booking_Form {

	/** Nonce action name. */
	const NONCE_ACTION = 'ontime_booking';

	/**
	 * Register hooks on construction.
	 */
	public function __construct() {
		add_shortcode( 'ontime_booking_form', array( $this, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );

		add_action( 'wp_ajax_ontime_get_staff', array( $this, 'ajax_get_staff' ) );
		add_action( 'wp_ajax_nopriv_ontime_get_staff', array( $this, 'ajax_get_staff' ) );

		add_action( 'wp_ajax_ontime_get_slots', array( $this, 'ajax_get_slots' ) );
		add_action( 'wp_ajax_nopriv_ontime_get_slots', array( $this, 'ajax_get_slots' ) );

		add_action( 'wp_ajax_ontime_create_appointment', array( $this, 'ajax_create_appointment' ) );
		add_action( 'wp_ajax_nopriv_ontime_create_appointment', array( $this, 'ajax_create_appointment' ) );

		add_action( 'init', array( $this, 'add_rewrite_endpoint' ) );
		add_action( 'template_redirect', array( $this, 'maybe_handle_callback' ) );
	}

	/**
	 * Register the payment-callback rewrite endpoint.
	 */
	public function add_rewrite_endpoint() {
		add_rewrite_endpoint( 'ontime-callback', EP_ROOT );
	}

	/**
	 * Register CSS/JS assets (only enqueued on demand by the shortcode).
	 *
	 * @return void
	 */
	public function register_assets() {
		wp_register_style(
			'ontime-booking',
			ONTIME_URL . 'assets/css/booking.css',
			array(),
			ONTIME_VERSION
		);
		wp_register_script(
			'ontime-booking',
			ONTIME_URL . 'assets/js/booking.js',
			array(),
			ONTIME_VERSION,
			true
		);
	}

	/**
	 * Shortcode renderer. Emits the step container and passes config to JS.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( $atts ) {
		wp_enqueue_style( 'ontime-booking' );
		wp_enqueue_script( 'ontime-booking' );

		$services = ( new OnTime_Database() )->get_services();
		$options  = array();
		foreach ( $services as $s ) {
			$options[] = array(
				'id'       => (int) $s['id'],
				'name'     => $s['name'],
				'duration' => (int) $s['duration_minutes'],
				'price'    => (float) $s['price'],
			);
		}

		$config = array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
			'services'=> $options,
			'i18n'    => array(
				'chooseService' => __( 'انتخاب خدمت', 'ontime' ),
				'chooseStaff'   => __( 'انتخاب کارشناس', 'ontime' ),
				'chooseDateTime'=> __( 'انتخاب تاریخ و ساعت', 'ontime' ),
				'yourInfo'      => __( 'اطلاعات شما', 'ontime' ),
				'confirm'       => __( 'تأیید و رزرو', 'ontime' ),
				'loading'       => __( 'در حال بارگذاری…', 'ontime' ),
				'noSlots'       => __( 'ساعت آزادی یافت نشد.', 'ontime' ),
				'success'       => __( 'نوبت شما با موفقیت ثبت شد.', 'ontime' ),
				'error'         => __( 'خطایی رخ داد. دوباره تلاش کنید.', 'ontime' ),
				'next'          => __( 'مرحله بعد', 'ontime' ),
				'prev'          => __( 'بازگشت', 'ontime' ),
				'name'          => __( 'نام و نام خانوادگی', 'ontime' ),
				'phone'         => __( 'شماره تماس', 'ontime' ),
				'email'         => __( 'ایمیل (اختیاری)', 'ontime' ),
			),
		);

		ob_start();
		echo '<div id="ontime-booking" class="ontime-root"></div>';
		printf( '<script>window.ONTIME_CONFIG = %s;</script>', wp_json_encode( $config ) );
		return ob_get_clean();
	}

	/**
	 * Verify the AJAX nonce and optionally die on failure.
	 *
	 * @param bool $die Whether to wp_send_json_error on failure.
	 * @return bool
	 */
	private function verify_nonce( $die = true ) {
		$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';
		$ok = (bool) check_ajax_referer( self::NONCE_ACTION, 'nonce', $die );
		return $ok;
	}

	/**
	 * AJAX: return active staff members.
	 *
	 * @return void
	 */
	public function ajax_get_staff() {
		$this->verify_nonce();
		$staff = ( new OnTime_Database() )->get_staff();
		$list  = array();
		foreach ( $staff as $row ) {
			$list[] = array(
				'id'    => (int) $row['id'],
				'name'  => $row['display_name'],
				'bio'   => $row['bio'],
			);
		}
		wp_send_json_success( array( 'staff' => $list ) );
	}

	/**
	 * AJAX: return available slots for a staff member on a Jalali date.
	 *
	 * @return void
	 */
	public function ajax_get_slots() {
		$this->verify_nonce();

		$staff_id = isset( $_POST['staff_id'] ) ? absint( $_POST['staff_id'] ) : 0;
		$date     = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';

		if ( ! $staff_id || '' === $date ) {
			wp_send_json_error( array( 'message' => __( 'ورودی نامعتبر.', 'ontime' ) ) );
		}

		$engine = new OnTime_Calendar_Engine();
		$slots  = $engine->get_available_slots( $staff_id, $date );

		wp_send_json_success( array( 'slots' => $slots ) );
	}

	/**
	 * AJAX: create a new pending appointment.
	 *
	 * @return void
	 */
	public function ajax_create_appointment() {
		$this->verify_nonce();

		$service_id    = isset( $_POST['service_id'] ) ? absint( $_POST['service_id'] ) : 0;
		$staff_id      = isset( $_POST['staff_id'] ) ? absint( $_POST['staff_id'] ) : 0;
		$slot_value    = isset( $_POST['slot'] ) ? sanitize_text_field( wp_unslash( $_POST['slot'] ) ) : '';
		$customer_name = isset( $_POST['customer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_name'] ) ) : '';
		$customer_phone= isset( $_POST['customer_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_phone'] ) ) : '';
		$customer_email= isset( $_POST['customer_email'] ) ? sanitize_email( wp_unslash( $_POST['customer_email'] ) ) : '';

		if ( ! $service_id || ! $staff_id || '' === $slot_value || '' === $customer_name || '' === $customer_phone ) {
			wp_send_json_error( array( 'message' => __( 'لطفاً همه فیلدهای ضروری را پر کنید.', 'ontime' ) ) );
		}

		$db      = new OnTime_Database();
		$service = $db->get_service( $service_id );
		if ( ! $service ) {
			wp_send_json_error( array( 'message' => __( 'خدمت نامعتبر.', 'ontime' ) ) );
		}

		try {
			$start = new DateTime( $slot_value, new DateTimeZone( 'UTC' ) );
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => __( 'ساعت نامعتبر.', 'ontime' ) ) );
		}
		$end = clone $start;
		$end->modify( '+' . (int) $service['duration_minutes'] . ' minutes' );

		// Re-check no overlap just before insert.
		$existing = $db->get_appointments_in_range( $staff_id, $start->format( 'Y-m-d H:i:s' ), $end->format( 'Y-m-d H:i:s' ) );
		if ( ! empty( $existing ) ) {
			wp_send_json_error( array( 'message' => __( 'این ساعت هم‌اکنون رزرو شد. لطفاً ساعت دیگری انتخاب کنید.', 'ontime' ) ) );
		}

		$id = $db->insert_appointment( array(
			'customer_name'  => $customer_name,
			'customer_phone' => $customer_phone,
			'customer_email' => $customer_email,
			'service_id'     => $service_id,
			'staff_id'       => $staff_id,
			'start_datetime' => $start->format( 'Y-m-d H:i:s' ),
			'end_datetime'   => $end->format( 'Y-m-d H:i:s' ),
			'status'         => 'pending',
			'total_price'    => (float) $service['price'],
		) );

		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'ثبت نوبت ناموفق بود.', 'ontime' ) ) );
		}

		// Route through the payment handler (mock auto-confirms free appointments).
		$provider = OnTime_Admin::get( 'payment_provider' );
		$price    = (float) $service['price'];
		if ( 'mock' === $provider || $price <= 0 ) {
			$db->set_appointment_status( $id, 'confirmed' );
			wp_send_json_success( array(
				'appointment_id' => $id,
				'status'         => 'confirmed',
				'message'        => __( 'نوبت شما با موفقیت ثبت شد.', 'ontime' ),
				'redirect'       => '',
			) );
		}

		$handler = new OnTime_Payment_Handler();
		$gateway = $handler->get_provider( $provider );
		$redirect = $gateway ? $gateway->request_payment( $id, $price ) : '';

		wp_send_json_success( array(
			'appointment_id' => $id,
			'status'         => 'pending',
			'redirect'       => $redirect,
		) );
	}

	/**
	 * Intercept the ontime-callback endpoint to verify payment and update status.
	 *
	 * @return void
	 */
	public function maybe_handle_callback() {
		if ( ! isset( $_GET['ontime-callback'] ) && ! isset( $_POST['ontime-callback'] ) ) {
			return;
		}
		$handler = new OnTime_Payment_Handler();
		$handler->handle_callback();
	}
}
