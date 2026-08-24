<?php
/**
 * OnTime Plugin Core
 * 
 * Main plugin class using Singleton pattern
 * 
 * @package OnTime
 * @subpackage Core
 * @since 1.0.0
 */

namespace OnTime\Core;

use OnTime\Database\Database;

/**
 * Main plugin class
 * 
 * Initializes all plugin components and handles core functionality
 */
final class Plugin
{
    /**
     * Singleton instance
     * @var Plugin|null
     */
    private static $instance = null;

    /**
     * Database instance
     * @var Database
     */
    private $db;

    /**
     * Constructor - Private for Singleton pattern
     */
    private function __construct()
    {
        $this->db = Database::get_instance();
        $this->init_hooks();
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    private function __wakeup() {}

    /**
     * Get singleton instance
     * 
     * @return Plugin
     */
    public static function get_instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks()
    {
        // Load text domain
        add_action('init', [$this, 'load_textdomain']);

        // Load admin components
        if (is_admin()) {
            $this->load_admin();
        }

        // Load public components
        $this->load_public();
    }

    /**
     * Load text domain
     */
    public function load_textdomain()
    {
        load_plugin_textdomain(
            'ontime',
            false,
            ONTIME_PLUGIN_DIR . 'languages/'
        );
    }

    /**
     * Load admin components
     */
    private function load_admin()
    {
        require_once ONTIME_PLUGIN_DIR . 'admin/class-ontime-admin.php';
        Admin\Admin::get_instance();
    }

    /**
     * Load public components
     */
    private function load_public()
    {
        // Load main public handler
        require_once ONTIME_PLUGIN_DIR . 'public/class-ontime-public.php';
        Public_\Public_Handler::get_instance();

        // Load multi-step booking form
        require_once ONTIME_PLUGIN_DIR . 'public/class-ontime-booking-form.php';
        Public_\Booking_Form::get_instance()->init();

        // Load payment handler
        require_once ONTIME_PLUGIN_DIR . 'includes/Payment/class-ontime-payment-interface.php';
        require_once ONTIME_PLUGIN_DIR . 'includes/Payment/class-ontime-payment-handler.php';
        require_once ONTIME_PLUGIN_DIR . 'includes/Payment/class-ontime-mock-provider.php';
        require_once ONTIME_PLUGIN_DIR . 'includes/Payment/class-ontime-iranian-gateway-base.php';
        Payment\Payment_Handler::get_instance()->init_hooks();
    }

    /**
     * Get database instance
     * 
     * @return Database
     */
    public function db()
    {
        return $this->db;
    }
}
