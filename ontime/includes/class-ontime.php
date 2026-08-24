<?php
/**
 * Main plugin orchestrator (Singleton).
 *
 * @package OnTime
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class OnTime {

	private static $instance = null;
	public $database = null;
	public $calendar = null;
	public $payments = null;
	public $settings = null;
	public $frontend = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->setup_hooks();
			self::$instance->init_components();
		}
		return self::$instance;
	}

	private function __construct() {}
	private function __clone() {}

	public function __wakeup() {
		_doing_it_wrong( __METHOD__, esc_html__( 'Cannot unserialize a singleton.', 'ontime' ), '0.1.0' );
	}

	private function setup_hooks() {
		register_activation_hook( ONTIME_PATH . 'ontime.php', array( $this, 'activate' ) );
		register_deactivation_hook( ONTIME_PATH . 'ontime.php', array( $this, 'deactivate' ) );
	}

	private function init_components() {
		if ( class_exists( 'OnTime_Database' ) ) {
			$this->database = OnTime_Database::instance();
		}
		if ( class_exists( 'OnTime_Calendar_Engine' ) ) {
			$this->calendar = OnTime_Calendar_Engine::instance();
		}
		if ( class_exists( 'OnTime_Payment_Handler' ) ) {
			$this->payments = OnTime_Payment_Handler::instance();
		}
		if ( is_admin() && class_exists( 'OnTime_Admin_Settings' ) ) {
			$this->settings = OnTime_Admin_Settings::instance();
			// Process appointments list-table actions (row + bulk) early on admin screens.
			add_action( 'admin_init', array( $this, 'process_admin_actions' ) );
		}
		if ( class_exists( 'OnTime_Frontend_Booking_Form' ) ) {
			$this->frontend = OnTime_Frontend_Booking_Form::instance();
		}
	}

	/**
	 * Delegate list-table action processing to the List Table class when on an OnTime admin page.
	 *
	 * @since 0.4.0
	 * @return void
	 */
	public function process_admin_actions() {
		if ( ! is_admin() || ! class_exists( 'OnTime_Admin_List_Table' ) ) {
			return;
		}
		// Only act on the appointments page with an action present.
		$page   = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
		if ( 'ontime' !== $page || '' === $action || '-1' === $action ) {
			return;
		}
		$table = new OnTime_Admin_List_Table();
		$table->process_actions();
	}

	public function activate() {
		if ( class_exists( 'OnTime_Database' ) ) {
			OnTime_Database::instance()->create_tables();
			OnTime_Database::instance()->seed_defaults();
		}
		$current = get_option( 'ontime_version' );
		if ( version_compare( $current, ONTIME_VERSION, '<' ) ) {
			update_option( 'ontime_version', ONTIME_VERSION );
		}
		flush_rewrite_rules();
	}

	public function deactivate() {
		flush_rewrite_rules();
	}
}
