<?php
/**
 * Plugin Name:       OnTime - سیستم رزرو نوبت آنلاین
 * Plugin URI:        https://ontime.ir
 * Description:       سیستم حرفه‌ای رزرو و مدیریت نوبت آنلاین با پشتیبانی کامل از تقویم جلالی و درگاه‌های پرداخت ایرانی
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Team OnTime
 * Author URI:        https://ontime.ir
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ontime
 * Domain Path:       /languages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
if (!defined('ONTIME_VERSION')) {
    define('ONTIME_VERSION', '1.0.0');
}

if (!defined('ONTIME_PLUGIN_FILE')) {
    define('ONTIME_PLUGIN_FILE', __FILE__);
}

if (!defined('ONTIME_PLUGIN_DIR')) {
    define('ONTIME_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

if (!defined('ONTIME_PLUGIN_URL')) {
    define('ONTIME_PLUGIN_URL', plugin_dir_url(__FILE__));
}

if (!defined('ONTIME_PLUGIN_BASENAME')) {
    define('ONTIME_PLUGIN_BASENAME', plugin_basename(__FILE__));
}

// Composer autoloader check
$autoloader_path = ONTIME_PLUGIN_DIR . 'vendor/autoload.php';
if (file_exists($autoloader_path)) {
    require_once $autoloader_path;
} else {
    // Fallback to custom PSR-4 autoloader
    require_once ONTIME_PLUGIN_DIR . 'includes/class-ontime-autoloader.php';
    new OnTime\Autoloader();
}

// Initialize the plugin
add_action('plugins_loaded', function() {
    OnTime\Core\Plugin::get_instance();
});

// Activation and deactivation hooks
register_activation_hook(ONTIME_PLUGIN_FILE, function() {
    OnTime\Database\Database::get_instance()->create_tables();
});

register_deactivation_hook(ONTIME_PLUGIN_FILE, function() {
    // Cleanup tasks if needed
    do_action('ontime_deactivate');
});

// Uninstall hook
register_uninstall_hook(ONTIME_PLUGIN_FILE, function() {
    if (defined('ONTIME_COMPLETE_UNINSTALL') && ONTIME_COMPLETE_UNINSTALL) {
        OnTime\Database\Database::get_instance()->drop_tables();
    }
});
