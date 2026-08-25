<?php
/**
 * Plugin Name:       OnTime
 * Plugin URI:        https://ontime.example.com
 * Description:        سیستم نوبت‌دهی و رزرو آنلاین سبک‌وزن و امن با پشتیبانی کامل از تقویم جلالی، مناسب بازار ایرانی (ژاکت و راستچین).
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Erfan Mirzaii
 * Author URI:        https://erfanmirzaii.example.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ontime
 * Domain Path:       /languages
 *
 * @package OnTime
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ONTIME_VERSION', '1.0.0' );
define( 'ONTIME_PATH', plugin_dir_path( __FILE__ ) );
define( 'ONTIME_URL', plugin_dir_url( __FILE__ ) );
define( 'ONTIME_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader for OnTime_ prefixed classes.
 *
 * Conventions:
 *   OnTime_Database           → includes/class-database.php
 *   OnTime_Calendar_Engine    → includes/class-calendar-engine.php
 *   OnTime_Admin_Settings     → includes/admin/class-settings.php
 *   OnTime_Admin_List_Table   → includes/admin/class-list-table.php
 *   OnTime_Frontend_Booking_Form → includes/frontend/class-booking-form.php
 *   OnTime_Payment_Handler    → includes/payment/class-handler.php
 *   OnTime_Payment_Mock       → includes/payment/class-mock.php
 *
 * The first segment after "OnTime_" is treated as a subdirectory only when
 * it matches a known directory (admin, frontend, payment). Otherwise the
 * entire class name is used as the file slug at the includes/ root.
 *
 * @since 0.1.0
 *
 * @param string $class_name Fully-qualified class name.
 * @return void
 */
function ontime_autoload( $class_name ) {
	if ( 0 !== strpos( $class_name, 'OnTime_' ) ) {
		return;
	}

	$relative = substr( $class_name, strlen( 'OnTime_' ) );
	$parts    = explode( '_', $relative );

	// Known subdirectories that correspond to the first path segment.
	$subdirs   = array( 'admin', 'frontend', 'payment' );
	$sub_dir  = '';

	if ( count( $parts ) > 1 && in_array( strtolower( $parts[0] ), $subdirs, true ) ) {
		$sub_dir = strtolower( array_shift( $parts ) ) . '/';
	}

	// Build the file slug from the remaining parts (kebab-case).
	$slug = strtolower( implode( '-', $parts ) );
	$slug = str_replace( '_', '-', $slug );

	$file = ONTIME_PATH . 'includes/' . $sub_dir . 'class-' . $slug . '.php';
	if ( file_exists( $file ) ) {
		require_once $file;
	}
}
spl_autoload_register( 'ontime_autoload' );

/**
 * Main plugin accessor.
 *
 * @since 0.1.0
 * @return OnTime
 */
function ontime() {
	return OnTime::instance();
}

/**
 * Load plugin text domain for i18n.
 *
 * @since 0.1.0
 * @return void
 */
function ontime_load_textdomain() {
	load_plugin_textdomain( 'ontime', false, dirname( ONTIME_BASENAME ) . '/languages' );
}
add_action( 'init', 'ontime_load_textdomain' );
add_action( 'plugins_loaded', 'ontime' );
