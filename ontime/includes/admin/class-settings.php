<?php
/**
 * Admin settings page (Singleton).
 *
 * Registers the top-level "OnTime" admin menu and the Settings API page
 * (General, Payments, Display sections). All access is gated by the
 * `manage_ontime` capability (falls back to `manage_options`).
 *
 * @package OnTime
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class OnTime_Admin_Settings {

	/** @since 0.1.0 @var OnTime_Admin_Settings|null */
	private static $instance = null;

	/** @since 0.4.0 @var string Settings option group. */
	const OPTION_GROUP = 'ontime_settings_group';

	/**
	 * Singleton accessor.
	 *
	 * @since 0.1.0
	 * @return OnTime_Admin_Settings
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
	 * Wire up admin menu, settings registration and asset enqueue.
	 *
	 * @since 0.4.0
	 * @return void
	 */
	private function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Required capability for all OnTime admin actions.
	 *
	 * Defaults to `manage_options` if the custom capability was not granted.
	 *
	 * @since 0.4.0
	 * @return string
	 */
	public function capability() {
		$cap = 'manage_ontime';
		if ( ! current_user_can( $cap ) ) {
			$cap = 'manage_options';
		}
		return $cap;
	}

	/**
	 * Register the admin menu and submenu pages.
	 *
	 * @since 0.4.0
	 * @return void
	 */
	public function register_menu() {
		$cap      = $this->capability();
		$icon     = 'dashicons-calendar-alt';
		$position = 26;

		// Top-level: Appointments list (default page).
		add_menu_page(
			__( 'نوبت‌ها', 'ontime' ),
			__( 'OnTime', 'ontime' ),
			$cap,
			'ontime',
			array( $this, 'render_appointments_page' ),
			$icon,
			$position
		);

		// Submenu: Appointments (re-list for clarity).
		add_submenu_page(
			'ontime',
			__( 'نوبت‌ها', 'ontime' ),
			__( 'نوبت‌ها', 'ontime' ),
			$cap,
			'ontime',
			array( $this, 'render_appointments_page' )
		);

		// Submenu: Services management.
		add_submenu_page(
			'ontime',
			__( 'سرویس‌ها', 'ontime' ),
			__( 'سرویس‌ها', 'ontime' ),
			$cap,
			'ontime-services',
			array( $this, 'render_services_page' )
		);

		// Submenu: Settings.
		add_submenu_page(
			'ontime',
			__( 'تنظیمات OnTime', 'ontime' ),
			__( 'تنظیمات', 'ontime' ),
			$cap,
			'ontime-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings, sections and fields via the Settings API.
	 *
	 * @since 0.4.0
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			self::OPTION_GROUP,
			'ontime_settings',
			array( $this, 'sanitize_settings' )
		);

		// --- General section ---
		add_settings_section(
			'ontime_general',
			__( 'عمومی', 'ontime' ),
			array( $this, 'section_general_desc' ),
			'ontime-settings'
		);

		$this->add_field( 'ontime_general', 'timezone', __( 'منطقه زمانی', 'ontime' ), 'field_timezone' );
		$this->add_field( 'ontime_general', 'work_start', __( 'شروع ساعت کاری', 'ontime' ), 'field_time', array( 'work_start' ) );
		$this->add_field( 'ontime_general', 'work_end', __( 'پایان ساعت کاری', 'ontime' ), 'field_time', array( 'work_end' ) );
		$this->add_field( 'ontime_general', 'slot_length', __( 'طول اسلات (دقیقه)', 'ontime' ), 'field_number', array( 'slot_length', 5, 240, 5 ) );
		$this->add_field( 'ontime_general', 'buffer_minutes', __( 'فاصله بین نوبت‌ها (دقیقه)', 'ontime' ), 'field_number', array( 'buffer_minutes', 0, 120, 5 ) );
		$this->add_field( 'ontime_general', 'min_lead_hours', __( 'حداقل زمان پیش از رزرو (ساعت)', 'ontime' ), 'field_number', array( 'min_lead_hours', 0, 168, 1 ) );
		$this->add_field( 'ontime_general', 'max_future_days', __( 'حداکثر آینده قابل‌رزرو (روز)', 'ontime' ), 'field_number', array( 'max_future_days', 1, 365, 1 ) );
		$this->add_field( 'ontime_general', 'weekend_days', __( 'روزهای تعطیل', 'ontime' ), 'field_weekend' );

		// --- Payments section ---
		add_settings_section(
			'ontime_payments',
			__( 'پرداخت', 'ontime' ),
			array( $this, 'section_payments_desc' ),
			'ontime-settings'
		);
		$this->add_field( 'ontime_payments', 'payment_gateway', __( 'درگاه پرداخت', 'ontime' ), 'field_gateway' );
		$this->add_field( 'ontime_payments', 'merchant_code', __( 'کد پذیرنده', 'ontime' ), 'field_text', array( 'merchant_code' ) );

		// --- Display section ---
		add_settings_section(
			'ontime_display',
			__( 'نمایش', 'ontime' ),
			array( $this, 'section_display_desc' ),
			'ontime-settings'
		);
		$this->add_field( 'ontime_display', 'persian_digits', __( 'اعداد فارسی', 'ontime' ), 'field_checkbox', array( 'persian_digits' ) );
		$this->add_field( 'ontime_display', 'date_format', __( 'قالب تاریخ', 'ontime' ), 'field_text', array( 'date_format' ) );
	}

	/**
	 * Helper to register a settings field bound to this class.
	 *
	 * @since 0.4.0
	 *
	 * @param string $section Settings section id.
	 * @param string $id      Field id (also the option key).
	 * @param string $title   Field title.
	 * @param string $render  Method name on this class for rendering.
	 * @param array  $args    Extra args passed to the renderer.
	 * @return void
	 */
	private function add_field( $section, $id, $title, $render, $args = array() ) {
		add_settings_field(
			$id,
			$title,
			array( $this, $render ),
			'ontime-settings',
			$section,
			array_merge( array( 'key' => $id ), $args )
		);
	}

	/**
	 * Sanitize the settings array before it is saved.
	 *
	 * @since 0.4.0
	 *
	 * @param array $input Raw input.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( $input ) {
		$out = array();

		$out['timezone']       = sanitize_text_field( $input['timezone'] ?? 'Asia/Tehran' );
		$out['work_start']     = preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $input['work_start'] ?? '' ) ? $input['work_start'] : '09:00';
		$out['work_end']       = preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $input['work_end'] ?? '' ) ? $input['work_end'] : '18:00';
		$out['slot_length']    = max( 5, min( 240, (int) ( $input['slot_length'] ?? 30 ) ) );
		$out['buffer_minutes'] = max( 0, min( 120, (int) ( $input['buffer_minutes'] ?? 0 ) ) );
		$out['min_lead_hours'] = max( 0, min( 168, (int) ( $input['min_lead_hours'] ?? 2 ) ) );
		$out['max_future_days'] = max( 1, min( 365, (int) ( $input['max_future_days'] ?? 30 ) ) );

		// Weekend days: array of PHP w weekday numbers (0-6).
		$wd = $input['weekend_days'] ?? array( '5' );
		if ( ! is_array( $wd ) ) { $wd = array( $wd ); }
		$out['weekend_days'] = implode( ',', array_filter( array_map( 'strval', $wd ), static function ( $v ) {
			return in_array( $v, array( '0','1','2','3','4','5','6' ), true );
		} ) );

		$out['payment_gateway'] = sanitize_text_field( $input['payment_gateway'] ?? 'mock' );
		$out['merchant_code']   = sanitize_text_field( $input['merchant_code'] ?? '' );
		$out['persian_digits']  = ! empty( $input['persian_digits'] ) ? 1 : 0;
		$out['date_format']     = sanitize_text_field( $input['date_format'] ?? 'j F Y' );

		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* Section descriptions.                                               */
	/* ------------------------------------------------------------------ */

	public function section_general_desc() {
		echo '<p>' . esc_html__( 'تنظیمات عمومی نوبت‌دهی.', 'ontime' ) . '</p>';
	}

	public function section_payments_desc() {
		echo '<p>' . esc_html__( 'تنظیمات درگاه پرداخت.', 'ontime' ) . '</p>';
	}

	public function section_display_desc() {
		echo '<p>' . esc_html__( 'تنظیمات نمایش و قالب‌بندی.', 'ontime' ) . '</p>';
	}

	/* ------------------------------------------------------------------ */
	/* Field renderers.                                                    */
	/* ------------------------------------------------------------------ */

	/**
	 * Get the current value for a settings key (option override then DB table).
	 *
	 * @since 0.4.0
	 * @param string $key Setting key.
	 * @return mixed
	 */
	private function value( $key ) {
		$opts = get_option( 'ontime_settings', array() );
		if ( is_array( $opts ) && isset( $opts[ $key ] ) ) {
			return $opts[ $key ];
		}
		// Fall back to the database-stored setting (seeded defaults).
		return OnTime_Database::instance()->get_setting( $key );
	}

	public function field_timezone( $args ) {
		$cur = $this->value( $args['key'] );
		echo '<select name="ontime_settings[' . esc_attr( $args['key'] ) . ']">';
		$zones = array( 'Asia/Tehran', 'UTC', 'Asia/Dubai', 'Europe/London', 'America/New_York' );
		foreach ( $zones as $z ) {
			echo '<option value="' . esc_attr( $z ) . '" ' . selected( $cur, $z, false ) . '>' . esc_html( $z ) . '</option>';
		}
		echo '</select>';
	}

	public function field_time( $args ) {
		$key = isset( $args[0] ) ? $args[0] : $args['key'];
		$cur = $this->value( $key );
		echo '<input type="time" name="ontime_settings[' . esc_attr( $key ) . ']" value="' . esc_attr( $cur ) . '" class="regular-text" />';
	}

	public function field_number( $args ) {
		$key  = isset( $args[0] ) ? $args[0] : $args['key'];
		$min  = isset( $args[1] ) ? $args[1] : 0;
		$max  = isset( $args[2] ) ? $args[2] : 999;
		$step = isset( $args[3] ) ? $args[3] : 1;
		$cur  = $this->value( $key );
		echo '<input type="number" name="ontime_settings[' . esc_attr( $key ) . ']" value="' . esc_attr( $cur ) . '" min="' . esc_attr( $min ) . '" max="' . esc_attr( $max ) . '" step="' . esc_attr( $step ) . '" class="small-text" />';
	}

	public function field_text( $args ) {
		$key = isset( $args[0] ) ? $args[0] : $args['key'];
		$cur = $this->value( $key );
		echo '<input type="text" name="ontime_settings[' . esc_attr( $key ) . ']" value="' . esc_attr( $cur ) . '" class="regular-text" />';
	}

	public function field_weekend( $args ) {
		$cur = $this->value( $args['key'] );
		$cur_arr = explode( ',', (string) $cur );
		$days = array(
			'0' => __( 'یک‌شنبه', 'ontime' ),
			'1' => __( 'دوشنبه', 'ontime' ),
			'2' => __( 'سه‌شنبه', 'ontime' ),
			'3' => __( 'چهارشنبه', 'ontime' ),
			'4' => __( 'پنج‌شنبه', 'ontime' ),
			'5' => __( 'جمعه', 'ontime' ),
			'6' => __( 'شنبه', 'ontime' ),
		);
		foreach ( $days as $w => $label ) {
			$checked = in_array( (string) $w, array_map( 'strval', $cur_arr ), true ) ? 'checked' : '';
			echo '<label style="margin-inline-end:12px;"><input type="checkbox" name="ontime_settings[' . esc_attr( $args['key'] ) . '][]" value="' . esc_attr( $w ) . '" ' . $checked . ' /> ' . esc_html( $label ) . '</label>';
		}
	}

	public function field_gateway( $args ) {
		$cur = $this->value( $args['key'] );
		$gates = array(
			'mock'      => __( 'تست (Mock)', 'ontime' ),
			'zarinpal'  => __( 'زرین‌پال', 'ontime' ),
		);
		echo '<select name="ontime_settings[' . esc_attr( $args['key'] ) . ']">';
		foreach ( $gates as $slug => $label ) {
			echo '<option value="' . esc_attr( $slug ) . '" ' . selected( $cur, $slug, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	public function field_checkbox( $args ) {
		$key = isset( $args[0] ) ? $args[0] : $args['key'];
		$cur = (int) $this->value( $key );
		echo '<label><input type="checkbox" name="ontime_settings[' . esc_attr( $key ) . ']" value="1" ' . checked( $cur, 1, false ) . ' /> ' . esc_html__( 'فعال', 'ontime' ) . '</label>';
	}

	/* ------------------------------------------------------------------ */
	/* Page renderers.                                                     */
	/* ------------------------------------------------------------------ */

	/**
	 * Render the appointments list page (delegates to the List Table).
	 *
	 * @since 0.4.0
	 * @return void
	 */
	public function render_appointments_page() {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( 'دسترسی غیرمجاز.', 'ontime' ) );
		}

		// The list table class is loaded in a later commit of this stage.
		if ( ! class_exists( 'OnTime_Admin_List_Table' ) ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'نوبت‌ها', 'ontime' ) . '</h1><p>' . esc_html__( 'جدول نوبت‌ها به‌زودی بارگذاری می‌شود.', 'ontime' ) . '</p></div>';
			return;
		}

		$table = new OnTime_Admin_List_Table();
		$table->prepare_items();
		echo '<div class="wrap"><h1>' . esc_html__( 'نوبت‌ها', 'ontime' ) . '</h1>';
		echo '<form method="get">';
		echo '<input type="hidden" name="page" value="ontime" />';
		$table->search_box( __( 'جستجوی نوبت', 'ontime' ), 'ontime-search' );
		$table->display();
		echo '</form></div>';
	}

	/**
	 * Render the services management page.
	 *
	 * @since 0.4.0
	 * @return void
	 */
	public function render_services_page() {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( 'دسترسی غیرمجاز.', 'ontime' ) );
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'سرویس‌ها', 'ontime' ) . '</h1>';
		echo '<p>' . esc_html__( 'مدیریت سرویس‌ها در مرحلهٔ بعد تکمیل می‌شود.', 'ontime' ) . '</p></div>';
	}

	/**
	 * Render the settings page using the Settings API.
	 *
	 * @since 0.4.0
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( 'دسترسی غیرمجاز.', 'ontime' ) );
		}
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'تنظیمات OnTime', 'ontime' ) . '</h1>';
		echo '<form method="post" action="options.php">';
		settings_fields( self::OPTION_GROUP );
		do_settings_sections( 'ontime-settings' );
		submit_button( __( 'ذخیره تنظیمات', 'ontime' ) );
		echo '</form></div>';
	}

	/**
	 * Enqueue admin assets only on OnTime screens.
	 *
	 * @since 0.4.0
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, 'ontime' ) ) {
			return;
		}
		wp_enqueue_style(
			'ontime-admin',
			ONTIME_URL . 'assets/css/ontime-admin.css',
			array(),
			ONTIME_VERSION
		);
	}
}
