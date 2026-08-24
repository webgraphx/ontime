<?php
/**
 * Frontend booking form (Singleton).
 *
 * Registers the [ontime_booking_form] shortcode and renders a mobile-first,
 * step-by-step booking widget. All step transitions use secure AJAX
 * endpoints (nonce-gated, sanitized input). Uses pure Vanilla JS — no jQuery.
 *
 * @package OnTime
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class OnTime_Frontend_Booking_Form {

	/** @since 0.1.0 @var OnTime_Frontend_Booking_Form|null */
	private static $instance = null;

	/** @since 0.5.0 @var bool Whether assets were enqueued for this request. */
	private $enqueued = false;

	/**
	 * Singleton accessor.
	 *
	 * @since 0.1.0
	 * @return OnTime_Frontend_Booking_Form
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->init();
		}
		return self::$instance;
	}

	private function __construct() {}

	/**
	 * Register shortcode + AJAX endpoints.
	 *
	 * @since 0.5.0
	 * @return void
	 */
	private function init() {
		add_shortcode( 'ontime_booking_form', array( $this, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue' ) );

		// AJAX endpoints (both logged-in and guests).
		add_action( 'wp_ajax_ontime_get_services', array( $this, 'ajax_get_services' ) );
		add_action( 'wp_ajax_nopriv_ontime_get_services', array( $this, 'ajax_get_services' ) );
		add_action( 'wp_ajax_ontime_get_slots', array( $this, 'ajax_get_slots' ) );
		add_action( 'wp_ajax_nopriv_ontime_get_slots', array( $this, 'ajax_get_slots' ) );
		add_action( 'wp_ajax_ontime_submit_booking', array( $this, 'ajax_submit_booking' ) );
		add_action( 'wp_ajax_nopriv_ontime_submit_booking', array( $this, 'ajax_submit_booking' ) );
	}

	/**
	 * Enqueue frontend assets only when the shortcode is present.
	 *
	 * @since 0.5.0
	 * @return void
	 */
	public function maybe_enqueue() {
		if ( ! $this->enqueued ) {
			return;
		}
		wp_enqueue_style(
			'ontime-frontend',
			ONTIME_URL . 'assets/css/ontime-frontend.css',
			array(),
			ONTIME_VERSION
		);
		wp_enqueue_script(
			'ontime-frontend',
			ONTIME_URL . 'assets/js/ontime-frontend.js',
			array(),
			ONTIME_VERSION,
			true
		);
		wp_localize_script( 'ontime-frontend', 'OnTimeData', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'ontime_nonce' ),
			'i18n'    => array(
				'selectService' => __( 'لطفاً یک سرویس انتخاب کنید.', 'ontime' ),
				'selectDate'    => __( 'لطفاً یک تاریخ انتخاب کنید.', 'ontime' ),
				'selectSlot'    => __( 'لطفاً یک ساعت انتخاب کنید.', 'ontime' ),
				'nameRequired'  => __( 'نام الزامی است.', 'ontime' ),
				'phoneRequired' => __( 'شماره تماس الزامی است.', 'ontime' ),
				'emailInvalid'  => __( 'ایمیل نامعتبر است.', 'ontime' ),
				'loading'       => __( 'در حال بارگذاری...', 'ontime' ),
				'error'         => __( 'خطایی رخ داد. دوباره تلاش کنید.', 'ontime' ),
				'success'       => __( 'نوبت شما با موفقیت ثبت شد.', 'ontime' ),
				'noSlots'       => __( 'هیچ ساعت آزادی برای این روز وجود ندارد.', 'ontime' ),
				'next'          => __( 'بعدی', 'ontime' ),
				'prev'          => __( 'قبلی', 'ontime' ),
				'confirm'       => __( 'تأیید نهایی', 'ontime' ),
			),
		) );
	}

	/**
	 * Mark assets for enqueue (called when shortcode renders).
	 *
	 * @since 0.5.0
	 * @return void
	 */
	private function flag_enqueue() {
		$this->enqueued = true;
	}

	/**
	 * Shortcode renderer: outputs the booking widget container.
	 *
	 * @since 0.5.0
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'service_id' => 0,
			'theme'      => 'light',
		), $atts, 'ontime_booking_form' );

		$this->flag_enqueue();

		$service_id = (int) $atts['service_id'];
		$theme      = sanitize_key( $atts['theme'] );

		ob_start();
		?>
		<div class="ontime-widget ontime-theme-<?php echo esc_attr( $theme ); ?>"
			data-service="<?php echo esc_attr( $service_id ); ?>"
			data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
			<div class="ontime-steps">
				<!-- Step 1: Service -->
				<div class="ontime-step ontime-step-1" data-step="1">
					<h3 class="ontime-step-title"><?php esc_html_e( 'انتخاب سرویس', 'ontime' ); ?></h3>
					<div class="ontime-service-list" id="ontime-services"></div>
				</div>

				<!-- Step 2: Date -->
				<div class="ontime-step ontime-step-2 ontime-hidden" data-step="2">
					<h3 class="ontime-step-title"><?php esc_html_e( 'انتخاب تاریخ', 'ontime' ); ?></h3>
					<div class="ontime-calendar" id="ontime-calendar">
						<div class="ontime-cal-nav">
							<button type="button" class="ontime-cal-prev" id="ontime-prev-month">&laquo;</button>
							<span id="ontime-month-label"></span>
							<button type="button" class="ontime-cal-next" id="ontime-next-month">&raquo;</button>
						</div>
						<div class="ontime-cal-grid" id="ontime-cal-grid"></div>
					</div>
				</div>

				<!-- Step 3: Time slot -->
				<div class="ontime-step ontime-step-3 ontime-hidden" data-step="3">
					<h3 class="ontime-step-title"><?php esc_html_e( 'انتخاب ساعت', 'ontime' ); ?></h3>
					<div class="ontime-slots" id="ontime-slots"></div>
				</div>

				<!-- Step 4: Customer info -->
				<div class="ontime-step ontime-step-4 ontime-hidden" data-step="4">
					<h3 class="ontime-step-title"><?php esc_html_e( 'اطلاعات تماس', 'ontime' ); ?></h3>
					<form id="ontime-customer-form" class="ontime-form">
						<label class="ontime-field">
							<span><?php esc_html_e( 'نام و نام خانوادگی', 'ontime' ); ?> *</span>
							<input type="text" name="customer_name" required />
						</label>
						<label class="ontime-field">
							<span><?php esc_html_e( 'شماره تماس', 'ontime' ); ?> *</span>
							<input type="tel" name="customer_phone" required />
						</label>
						<label class="ontime-field">
							<span><?php esc_html_e( 'ایمیل', 'ontime' ); ?></span>
							<input type="email" name="customer_email" />
						</label>
						<label class="ontime-field">
							<span><?php esc_html_e( 'توضیحات', 'ontime' ); ?></span>
							<textarea name="notes" rows="3"></textarea>
						</label>
					</form>
				</div>

				<!-- Step 5: Confirmation -->
				<div class="ontime-step ontime-step-5 ontime-hidden" data-step="5">
					<h3 class="ontime-step-title"><?php esc_html_e( 'تأیید نهایی', 'ontime' ); ?></h3>
					<div class="ontime-summary" id="ontime-summary"></div>
					<div class="ontime-result" id="ontime-result"></div>
				</div>
			</div>

			<div class="ontime-nav">
				<button type="button" class="ontime-btn ontime-btn-prev" id="ontime-prev"><?php esc_html_e( 'قبلی', 'ontime' ); ?></button>
				<button type="button" class="ontime-btn ontime-btn-next" id="ontime-next"><?php esc_html_e( 'بعدی', 'ontime' ); ?></button>
			</div>

			<div class="ontime-progress" aria-hidden="true">
				<div class="ontime-progress-bar" id="ontime-progress-bar"></div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ------------------------------------------------------------------ */
	/* AJAX endpoints.                                                     */
	/* ------------------------------------------------------------------ */

	/**
	 * Verify the OnTime nonce for AJAX requests.
	 *
	 * @since 0.5.0
	 * @return bool True if valid.
	 */
	private function verify_nonce() {
		return check_ajax_referer( 'ontime_nonce', 'nonce', false );
	}

	/**
	 * AJAX: list active services.
	 *
	 * @since 0.5.0
	 * @return void
	 */
	public function ajax_get_services() {
		if ( ! $this->verify_nonce() ) {
			wp_send_json_error( array( 'message' => __( 'خطای امنیتی.', 'ontime' ) ), 403 );
		}

		global $wpdb;
		$table = OnTime_Database::instance()->get_table( 'services' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$services = $wpdb->get_results(
			"SELECT id, name, description, duration, price FROM {$table} WHERE is_active = 1 ORDER BY id ASC",
			ARRAY_A
		);

		$out = array();
		if ( is_array( $services ) ) {
			foreach ( $services as $s ) {
				$out[] = array(
					'id'          => (int) $s['id'],
					'name'        => esc_html( $s['name'] ),
					'description' => esc_html( $s['description'] ?? '' ),
					'duration'    => (int) $s['duration'],
					'price'       => OnTime_Calendar_Engine::instance()->to_persian_digits( (string) $s['price'] ),
				);
			}
		}

		wp_send_json_success( array( 'services' => $out ) );
	}

	/**
	 * AJAX: get free slots for a Jalali day.
	 *
	 * @since 0.5.0
	 * @return void
	 */
	public function ajax_get_slots() {
		if ( ! $this->verify_nonce() ) {
			wp_send_json_error( array( 'message' => __( 'خطای امنیتی.', 'ontime' ) ), 403 );
		}

		$service_id = isset( $_POST['service_id'] ) ? (int) $_POST['service_id'] : 0;
		$j_year     = isset( $_POST['j_year'] ) ? (int) $_POST['j_year'] : 0;
		$j_month    = isset( $_POST['j_month'] ) ? (int) $_POST['j_month'] : 0;
		$j_day      = isset( $_POST['j_day'] ) ? (int) $_POST['j_day'] : 0;

		if ( $service_id < 1 || $j_year < 1300 || $j_month < 1 || $j_month > 12 || $j_day < 1 || $j_day > 31 ) {
			wp_send_json_error( array( 'message' => __( 'ورودی نامعتبر.', 'ontime' ) ), 400 );
		}

		$calendar = OnTime_Calendar_Engine::instance();
		$slots    = $calendar->get_free_slots( $service_id, $j_year, $j_month, $j_day );

		$out = array();
		foreach ( $slots as $ts ) {
			$j = $calendar->to_jalali( $ts );
			$out[] = array(
				'ts'    => (int) $ts,
				'label' => $calendar->format_jalali( $ts, 'H:i' ),
			);
		}

		wp_send_json_success( array( 'slots' => $out ) );
	}

	/**
	 * AJAX: submit a new booking (creates a pending appointment).
	 *
	 * @since 0.5.0
	 * @return void
	 */
	public function ajax_submit_booking() {
		if ( ! $this->verify_nonce() ) {
			wp_send_json_error( array( 'message' => __( 'خطای امنیتی.', 'ontime' ) ), 403 );
		}

		$service_id = isset( $_POST['service_id'] ) ? (int) $_POST['service_id'] : 0;
		$slot_ts    = isset( $_POST['slot_ts'] ) ? (int) $_POST['slot_ts'] : 0;
		$name       = isset( $_POST['customer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_name'] ) ) : '';
		$phone      = isset( $_POST['customer_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_phone'] ) ) : '';
		$email      = isset( $_POST['customer_email'] ) ? sanitize_email( wp_unslash( $_POST['customer_email'] ) ) : '';
		$notes      = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';

		// Validate required fields.
		if ( $service_id < 1 || $slot_ts < time() || '' === $name || '' === $phone ) {
			wp_send_json_error( array( 'message' => __( 'اطلاعات ناقص یا نامعتبر.', 'ontime' ) ), 400 );
		}
		if ( '' !== $email && ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'ایمیل نامعتبر.', 'ontime' ) ), 400 );
		}

		global $wpdb;
		$table_services    = OnTime_Database::instance()->get_table( 'services' );
		$table_appointments = OnTime_Database::instance()->get_table( 'appointments' );

		// Fetch service to compute duration and ensure it exists/active.
		$service = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT id, duration, price, is_active FROM {$table_services} WHERE id = %d",
				$service_id
			),
			ARRAY_A
		);
		if ( ! $service || (int) $service['is_active'] !== 1 ) {
			wp_send_json_error( array( 'message' => __( 'سرویس نامعتبر.', 'ontime' ) ), 400 );
		}

		$duration_min = (int) $service['duration'];
		$start_dt     = gmdate( 'Y-m-d H:i:s', $slot_ts );
		$end_dt       = gmdate( 'Y-m-d H:i:s', $slot_ts + ( $duration_min * MINUTE_IN_SECONDS ) );

		// Insert with ON ... rely on the UNIQUE (service_id, start_time) to prevent double-booking.
		$inserted = $wpdb->insert(
			$table_appointments,
			array(
				'service_id'      => $service_id,
				'customer_name'   => $name,
				'customer_phone'  => $phone,
				'customer_email'  => $email,
				'start_time'      => $start_dt,
				'end_time'        => $end_dt,
				'status'          => 'pending',
				'payment_status'  => 'unpaid',
				'notes'           => $notes,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			// Likely a UNIQUE constraint violation (slot taken) or DB error.
			wp_send_json_error( array( 'message' => __( 'این زمان قبلاً رزرو شده است.', 'ontime' ) ), 409 );
		}

		$appointment_id = (int) $wpdb->insert_id;

		// Stage 5 will handle payment redirect here. For now, return success with summary.
		$calendar = OnTime_Calendar_Engine::instance();
		$summary  = array(
			'id'    => $appointment_id,
			'date'  => $calendar->format_jalali( $slot_ts, 'j F Y، H:i' ),
			'price' => $calendar->to_persian_digits( (string) $service['price'] ),
		);

		wp_send_json_success( array(
			'message' => __( 'نوبت شما ثبت شد و در انتظار تأیید است.', 'ontime' ),
			'appointment' => $summary,
		) );
	}
}
