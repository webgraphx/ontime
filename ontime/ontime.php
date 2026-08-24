<?php
/**
 * Plugin Name:       OnTime
 * Plugin URI:        https://ontime.example.com
 * Description:        سیستم نوبت‌دهی و رزرو آنلاین سبک‌وزن و امن با پشتیبانی کامل از تقویم جلالی، مناسب بازار ایرانی (ژاکت و راستچین).
 * Version:           0.5.0
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

define( 'ONTIME_VERSION', '0.5.0' );
define( 'ONTIME_PATH', plugin_dir_path( __FILE__ ) );
define( 'ONTIME_URL', plugin_dir_url( __FILE__ ) );
define( 'ONTIME_BASENAME', plugin_basename( __FILE__ ) );

function ontime_autoload( $class_name ) {
	if ( 0 !== strpos( $class_name, 'OnTime_' ) ) {
		return;
	}
	$relative = substr( $class_name, strlen( 'OnTime_' ) );
	$parts    = explode( '_', $relative );
	$class    = array_pop( $parts );
	$sub_dir  = ! empty( $parts ) ? strtolower( implode( '/', $parts ) ) . '/' : '';
	$slug     = strtolower( preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', $class ) );
	$slug     = strtolower( str_replace( '_', '-', $slug ) );
	$file     = ONTIME_PATH . 'includes/' . $sub_dir . 'class-' . $slug . '.php';
	if ( file_exists( $file ) ) {
		require_once $file;
	}
}
spl_autoload_register( 'ontime_autoload' );

function ontime() {
	return OnTime::instance();
}

function ontime_load_textdomain() {
	load_plugin_textdomain( 'ontime', false, dirname( ONTIME_BASENAME ) . '/languages' );
}
add_action( 'init', 'ontime_load_textdomain' );
add_action( 'plugins_loaded', 'ontime' );
