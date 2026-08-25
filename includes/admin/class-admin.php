<?php
/**
 * Admin controller: registers menus, wires Settings API and List Table.
 *
 * @package OnTime
 */

defined( 'ABSPATH' ) || exit;

/**
 * Bootstraps the OnTime admin experience (menu, settings page and
 * appointments list table). Enforces capability checks on every page.
 */
class OnTime_Admin {

	/** Settings page slug. */
	const SETTINGS_SLUG = 'ontime-settings';

	/** Appointments page slug. */
	const APPOINTMENTS_SLUG = 'ontime-appointments';

	/** Settings option name. */
	const OPTION_NAME = 'ontime_settings';

	/**
	 * Hook admin pages into the menu.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_ontime_bulk_action', array( $this, 'handle_bulk_action' ) );
	}

	/**
	 * Register the top-level admin menu and subpages.
	 *
	 * @return void
	 */
	public function register_menu() {
		$cap = $this->capability();

		add_menu_page(
			__( 'OnTime', 'ontime' ),
			__( 'OnTime', 'ontime' ),
			$cap,
			self::APPOINTMENTS_SLUG,
			array( $this, 'render_appointments_page' ),
			'dashicons-calendar-alt',
			26
		);

		add_submenu_page(
			self::APPOINTMENTS_SLUG,
			__( 'نوبت‌ها', 'ontime' ),
			__( 'نوبت‌ها', 'ontime' ),
			$cap,
			self::APPOINTMENTS_SLUG,
			array( $this, 'render_appointments_page' )
		);

		add_submenu_page(
			self::APPOINTMENTS_SLUG,
			__( 'تنظیمات OnTime', 'ontime' ),
			__( 'تنظیمات', 'ontime' ),
			$cap,
			self::SETTINGS_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Capability required to manage OnTime. Defaults to manage_options,
	 * filterable for custom roles.
	 *
	 * @return string
	 */
	private function capability() {
		return apply_filters( 'ontime_capability', 'manage_options' );
	}

	/**
	 * Register all settings, sections and fields via the Settings API.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'ontime_settings_group',
			self::OPTION_NAME,
			array( $this, 'sanitize_settings' )
		);

		add_settings_section(
			'ontime_sms',
			__( 'تنظیمات پیامک', 'ontime' ),
			array( $this, 'section_sms' ),
			self::SETTINGS_SLUG
		);

		add_settings_section(
			'ontime_booking',
			__( 'قوانین رزرو', 'ontime' ),
			array( $this, 'section_booking' ),
			self::SETTINGS_SLUG
		);

		add_settings_section(
			'ontime_integration',
			__( 'یکپارچه‌سازی', 'ontime' ),
			array( $this, 'section_integration' ),
			self::SETTINGS_SLUG
		);

		add_settings_field( 'sms_api_key', __( 'کلید API پیامک', 'ontime' ), array( $this, 'field_sms_api_key' ), self::SETTINGS_SLUG, 'ontime_sms' );
		add_settings_field( 'sms_secret',  __( 'رمز پیامک', 'ontime' ), array( $this, 'field_sms_secret' ), self::SETTINGS_SLUG, 'ontime_sms' );

		add_settings_field( 'min_notice_hours', __( 'حداقل زمان پیش‌اخطار (ساعت)', 'ontime' ), array( $this, 'field_min_notice' ), self::SETTINGS_SLUG, 'ontime_booking' );
		add_settings_field( 'max_advance_days', __( 'حداکثر روزهای آینده قابل رزرو', 'ontime' ), array( $this, 'field_max_advance' ), self::SETTINGS_SLUG, 'ontime_booking' );

		add_settings_field( 'woo_compatible', __( 'سازگاری با ووکامرس', 'ontime' ), array( $this, 'field_woo' ), self::SETTINGS_SLUG, 'ontime_integration' );
		add_settings_field( 'payment_provider', __( 'درگاه پرداخت', 'ontime' ), array( $this, 'field_payment_provider' ), self::SETTINGS_SLUG, 'ontime_integration' );
	}

	/**
	 * Sanitize the settings array before persistence.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$out = self::get_defaults();

		$out['sms_api_key']     = isset( $input['sms_api_key'] ) ? sanitize_text_field( $input['sms_api_key'] ) : '';
		$out['sms_secret']      = isset( $input['sms_secret'] ) ? sanitize_text_field( $input['sms_secret'] ) : '';
		$out['min_notice_hours']= isset( $input['min_notice_hours'] ) ? absint( $input['min_notice_hours'] ) : 0;
		$out['max_advance_days']= isset( $input['max_advance_days'] ) ? absint( $input['max_advance_days'] ) : 30;
		$out['woo_compatible']  = ! empty( $input['woo_compatible'] ) ? 1 : 0;
		$out['payment_provider']= isset( $input['payment_provider'] ) ? sanitize_text_field( $input['payment_provider'] ) : 'mock';

		return $out;
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	private static function get_defaults() {
		return array(
			'sms_api_key'      => '',
			'sms_secret'       => '',
			'min_notice_hours' => 1,
			'max_advance_days' => 30,
			'woo_compatible'   => 0,
			'payment_provider' => 'mock',
		);
	}

	/**
	 * Retrieve a single option value. Static-safe: does not re-instantiate
	 * the admin controller (which would re-register hooks).
	 *
	 * @param string $key Option key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$opts = get_option( self::OPTION_NAME, array() );
		$opts = wp_parse_args( $opts, self::get_defaults() );
		return isset( $opts[ $key ] ) ? $opts[ $key ] : null;
	}

	/** Section descriptions. */
	public function section_sms() {
		echo '<p>' . esc_html__( 'اطلاعات اتصال به سامانه پیامک ایرانی خود را وارد کنید.', 'ontime' ) . '</p>';
	}
	public function section_booking() {
		echo '<p>' . esc_html__( 'قوانین کلی رزرو نوبت را تعیین کنید.', 'ontime' ) . '</p>';
	}
	public function section_integration() {
		echo '<p>' . esc_html__( 'اتصال به سایر افزونه‌ها و درگاه‌های پرداخت.', 'ontime' ) . '</p>';
	}

	/** Field renderers. */
	public function field_sms_api_key() {
		$v = self::get( 'sms_api_key' );
		printf( '<input type="text" name="%s[sms_api_key]" value="%s" class="regular-text" />', esc_attr( self::OPTION_NAME ), esc_attr( $v ) );
	}
	public function field_sms_secret() {
		$v = self::get( 'sms_secret' );
		printf( '<input type="password" name="%s[sms_secret]" value="%s" class="regular-text" autocomplete="new-password" />', esc_attr( self::OPTION_NAME ), esc_attr( $v ) );
	}
	public function field_min_notice() {
		$v = self::get( 'min_notice_hours' );
		printf( '<input type="number" min="0" name="%s[min_notice_hours]" value="%d" />', esc_attr( self::OPTION_NAME ), (int) $v );
	}
	public function field_max_advance() {
		$v = self::get( 'max_advance_days' );
		printf( '<input type="number" min="1" name="%s[max_advance_days]" value="%d" />', esc_attr( self::OPTION_NAME ), (int) $v );
	}
	public function field_woo() {
		$v = self::get( 'woo_compatible' );
		printf( '<label><input type="checkbox" name="%s[woo_compatible]" value="1" %s /> %s</label>',
			esc_attr( self::OPTION_NAME ),
			checked( $v, 1, false ),
			esc_html__( 'فعال‌سازی پرچم سازگاری با ووکامرس', 'ontime' )
		);
	}
	public function field_payment_provider() {
		$v = self::get( 'payment_provider' );
		$providers = apply_filters( 'ontime_payment_providers', array(
			'mock'   => __( 'شبیه‌سازی (توسعه)', 'ontime' ),
			'zarinpal'=> __( 'زرین‌پال', 'ontime' ),
			'saman'  => __( 'سامان', 'ontime' ),
		) );
		echo '<select name="' . esc_attr( self::OPTION_NAME ) . '[payment_provider]">';
		foreach ( $providers as $key => $label ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $key ), selected( $v, $key, false ), esc_html( $label ) );
		}
		echo '</select>';
	}

	/**
	 * Render the settings page using the Settings API.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( 'دسترسی غیرمجاز.', 'ontime' ) );
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'تنظیمات OnTime', 'ontime' ) . '</h1>';
		echo '<form method="post" action="options.php">';
		settings_fields( 'ontime_settings_group' );
		do_settings_sections( self::SETTINGS_SLUG );
		submit_button( __( 'ذخیره تنظیمات', 'ontime' ) );
		echo '</form></div>';
	}

	/**
	 * Render the appointments list table page.
	 *
	 * @return void
	 */
	public function render_appointments_page() {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( 'دسترسی غیرمجاز.', 'ontime' ) );
		}
		$table = new OnTime_List_Table();
		$table->prepare_items();
		echo '<div class="wrap"><h1>' . esc_html__( 'نوبت‌های OnTime', 'ontime' ) . '</h1>';
		echo '<form method="get">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::APPOINTMENTS_SLUG ) . '" />';
		$table->search_box( __( 'جستجوی مشتری', 'ontime' ), 'ontime_search' );
		$table->display();
		echo '</form></div>';
	}

	/**
	 * Handle the bulk-action form submission securely.
	 *
	 * @return void
	 */
	public function handle_bulk_action() {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( 'دسترسی غیرمجاز.', 'ontime' ) );
		}
		check_admin_referer( 'ontime_bulk' );

		$action = isset( $_POST['action2'] ) ? sanitize_text_field( wp_unslash( $_POST['action2'] ) ) : '';
		if ( '-1' === $action ) {
			$action = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';
		}
		$ids = isset( $_POST['appointment'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['appointment'] ) ) : array();

		$map = array(
			'confirm'   => 'confirmed',
			'complete'  => 'completed',
			'cancel'    => 'cancelled',
		);
		if ( ! isset( $map[ $action ] ) || empty( $ids ) ) {
			wp_safe_redirect( wp_get_referer() );
			exit;
		}

		$db = new OnTime_Database();
		$db->bulk_set_status( $ids, $map[ $action ] );

		wp_safe_redirect( add_query_arg( 'updated', '1', wp_get_referer() ) );
		exit;
	}
}
