<?php
/**
 * Core plugin bootstrap: Singleton container + PSR-ish autoloader.
 *
 * @package OnTime
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin class. Acts as the singleton container that wires up
 * autoloading, activation/deactivation, and runtime initialization
 * of every subsystem (database, calendar, admin, frontend, payments).
 */
final class OnTime_Plugin {

	/** @var OnTime_Plugin|null */
	private static $instance = null;

	/** @var OnTime_Database|null */
	public $database = null;

	/** @var OnTime_Calendar_Engine|null */
	public $calendar = null;

	/** @var OnTime_Admin|null */
	public $admin = null;

	/** @var OnTime_Booking_Form|null */
	public $frontend = null;

	/** @var OnTime_Payment_Handler|null */
	public $payments = null;

	/**
	 * Retrieve the singleton instance.
	 *
	 * @return OnTime_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->bootstrap();
		}
		return self::$instance;
	}

	/**
	 * Register the autoloader and load i18n textdomain.
	 *
	 * @return void
	 */
	private function bootstrap() {
		spl_autoload_register( array( $this, 'autoload' ) );

		add_action( 'init', array( $this, 'load_textdomain' ) );

		$this->database = new OnTime_Database();
		$this->calendar = new OnTime_Calendar_Engine();
		$this->payments = new OnTime_Payment_Handler();

		if ( is_admin() ) {
			$this->admin = new OnTime_Admin();
		}

		$this->frontend = new OnTime_Booking_Form();
	}

	/**
	 * Class autoloader. Maps class names to file paths following
	 * WordPress naming conventions (class-foo-bar.php).
	 *
	 * @param string $class Class name to load.
	 * @return void
	 */
	public function autoload( $class ) {
		if ( 0 !== strpos( $class, 'OnTime_' ) ) {
			return;
		}

		$relative  = substr( $class, strlen( 'OnTime_' ) );
		$sanitized = strtolower( $relative );
		$sanitized = str_replace( '_', '-', $sanitized );

		$candidates = array(
			ONTIME_DIR . 'includes/class-' . $sanitized . '.php',
			ONTIME_DIR . 'includes/admin/class-' . $sanitized . '.php',
			ONTIME_DIR . 'includes/frontend/class-' . $sanitized . '.php',
			ONTIME_DIR . 'includes/payments/class-' . $sanitized . '.php',
		);

		foreach ( $candidates as $path ) {
			if ( file_exists( $path ) ) {
				require_once $path;
				return;
			}
		}
	}

	/**
	 * Load the plugin translation files.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'ontime', false, dirname( ONTIME_BASENAME ) . '/languages' );
	}

	/**
	 * Activation routine: create/upgrade custom tables and seed defaults.
	 *
	 * @return void
	 */
	public static function activate() {
		spl_autoload_register( array( self::instance(), 'autoload' ) );

		require_once ONTIME_DIR . 'includes/class-database.php';
		$db = new OnTime_Database();
		$db->install();
		$db->seed_defaults();

		update_option( ONTIME_OPTION_DB_VERSION, ONTIME_DB_VERSION );

		// Ensure rewrite rules are flushed so custom endpoints (payment callback) resolve.
		$booking = new OnTime_Booking_Form();
		$booking->add_rewrite_endpoint();

		flush_rewrite_rules();
	}

	/**
	 * Deactivation routine: flush rewrite rules. No data is destroyed.
	 *
	 * @return void
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Prevent direct cloning.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Prevent unserialization.
	 *
	 * @return void
	 */
	public function __wakeup() {}
}
