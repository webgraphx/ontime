<?php
/**
 * Plugin Name:       OnTime — سیستم رزرو و نوبت‌دهی آنلاین
 * Plugin URI:        https://github.com/webgraphx/ontime
 * Description:       افزونه سبک‌وزن و امن رزرو و مدیریت نوبت آنلاین با پشتیبانی کامل تقویم جلالی، آماده برای انتشار در ژاکت و راستچین. (OnTime — lightweight secure online appointment booking with full Jalali calendar support.)
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Erfan Mirzaii
 * Author URI:        https://github.com/webgraphx
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ontime
 * Domain Path:       /languages
 *
 * @package OnTime
 */

defined( 'ABSPATH' ) || exit;

define( 'ONTIME_VERSION', '1.0.0' );
define( 'ONTIME_FILE', __FILE__ );
define( 'ONTIME_DIR', plugin_dir_path( __FILE__ ) );
define( 'ONTIME_URL', plugin_dir_url( __FILE__ ) );
define( 'ONTIME_BASENAME', plugin_basename( __FILE__ ) );
define( 'ONTIME_DB_VERSION', '1.0.0' );
define( 'ONTIME_OPTION_DB_VERSION', 'ontime_db_version' );

require_once ONTIME_DIR . 'includes/class-ontime.php';

register_activation_hook( __FILE__, array( 'OnTime_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'OnTime_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'OnTime_Plugin', 'instance' ) );
