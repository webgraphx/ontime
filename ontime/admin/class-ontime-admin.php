<?php
/**
 * OnTime Admin Handler
 * 
 * Main admin class using Singleton pattern with proper security
 * 
 * @package OnTime
 * @subpackage Admin
 * @since 1.0.0
 */

namespace OnTime\Admin;

use OnTime\Database\Database;
use OnTime\Calendar\Calendar_Engine;

/**
 * Admin class for OnTime plugin
 * 
 * Handles all admin panel functionality with proper security measures
 */
final class Admin
{
    /**
     * Singleton instance
     * @var Admin|null
     */
    private static $instance = null;

    /**
     * Database instance
     * @var Database
     */
    private $db;

    /**
     * Calendar Engine instance
     * @var Calendar_Engine
     */
    private $calendar;

    /**
     * Settings instance
     * @var Settings
     */
    private $settings;

    /**
     * Appointments List Table instance
     * @var Appointments_List_Table
     */
    private $appointments_table;

    /**
     * Constructor - Private for Singleton pattern
     */
    private function __construct()
    {
        $this->db = Database::get_instance();
        $this->calendar = Calendar_Engine::get_instance();
        $this->settings = Settings::get_instance();
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
     * @return Admin
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
        // Add admin menu
        add_action('admin_menu', [$this, 'add_admin_menu']);

        // Enqueue admin assets
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // Handle form submissions
        add_action('admin_init', [$this, 'handle_form_submissions']);

        // Handle bulk actions
        add_action('admin_init', [$this, 'handle_bulk_actions']);

        // Add admin notices
        add_action('admin_notices', [$this, 'display_admin_notices']);
    }

    /**
     * Add admin menu items
     */
    public function add_admin_menu()
    {
        $capability = apply_filters('ontime_menu_capability', 'manage_options');

        // Main menu item
        add_menu_page(
            __('OnTime', 'ontime'),
            __('OnTime', 'ontime'),
            $capability,
            'ontime',
            [$this, 'render_main_page'],
            'dashicons-calendar-alt',
            58
        );

        // Submenu items
        add_submenu_page(
            'ontime',
            __('داشبورد', 'ontime'),
            __('داشبورد', 'ontime'),
            $capability,
            'ontime',
            [$this, 'render_main_page']
        );

        add_submenu_page(
            'ontime',
            __('نوبت‌ها', 'ontime'),
            __('نوبت‌ها', 'ontime'),
            $capability,
            'ontime-appointments',
            [$this, 'render_appointments_page']
        );

        add_submenu_page(
            'ontime',
            __('خدمات', 'ontime'),
            __('خدمات', 'ontime'),
            $capability,
            'ontime-services',
            [$this, 'render_services_page']
        );

        add_submenu_page(
            'ontime',
            __('پرسنل', 'ontime'),
            __('پرسنل', 'ontime'),
            $capability,
            'ontime-staff',
            [$this, 'render_staff_page']
        );

        // Settings page is handled by Settings class
        // It's registered via admin_menu hook in Settings class
    }

    /**
     * Enqueue admin assets
     * 
     * @param string $hook Current page hook
     */
    public function enqueue_admin_assets($hook)
    {
        if (strpos($hook, 'ontime') === false) {
            return;
        }

        // CSS
        wp_enqueue_style(
            'ontime-admin',
            ONTIME_PLUGIN_URL . 'admin/css/admin.css',
            [],
            ONTIME_VERSION
        );

        // JS
        wp_enqueue_script(
            'ontime-admin',
            ONTIME_PLUGIN_URL . 'admin/js/admin.js',
            ['jquery'],
            ONTIME_VERSION,
            true
        );

        // Localize script
        wp_localize_script(
            'ontime-admin',
            'OnTimeAdmin',
            [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ontime_admin_nonce'),
                'texts' => [
                    'confirm_delete' => __('آیا از حذف مطمئن هستید؟', 'ontime'),
                    'saved' => __('تغییرات ذخیره شد', 'ontime'),
                    'no_items_selected' => __('هیچ موردی انتخاب نشد', 'ontime'),
                ],
            ]
        );
    }

    /**
     * Handle form submissions
     */
    public function handle_form_submissions()
    {
        // Only process on OnTime pages
        if (!isset($_GET['page']) || strpos($_GET['page'], 'ontime') !== 0) {
            return;
        }

        // Check capability
        if (!current_user_can('manage_options')) {
            return;
        }

        // Handle service form
        if (isset($_POST['ontime_action']) && $_POST['ontime_action'] === 'add_service' && wp_verify_nonce($_POST['_wpnonce'], 'ontime_services_nonce')) {
            $this->handle_service_form();
        }

        if (isset($_POST['ontime_action']) && $_POST['ontime_action'] === 'edit_service' && wp_verify_nonce($_POST['_wpnonce'], 'ontime_services_nonce')) {
            $this->handle_service_form();
        }

        // Handle staff form
        if (isset($_POST['ontime_action']) && $_POST['ontime_action'] === 'add_staff' && wp_verify_nonce($_POST['_wpnonce'], 'ontime_staff_nonce')) {
            $this->handle_staff_form();
        }

        if (isset($_POST['ontime_action']) && $_POST['ontime_action'] === 'edit_staff' && wp_verify_nonce($_POST['_wpnonce'], 'ontime_staff_nonce')) {
            $this->handle_staff_form();
        }

        // Handle delete actions
        if (isset($_POST['ontime_action']) && $_POST['ontime_action'] === 'delete_service' && wp_verify_nonce($_POST['_wpnonce'], 'ontime_services_nonce')) {
            $this->handle_delete_service();
        }

        if (isset($_POST['ontime_action']) && $_POST['ontime_action'] === 'delete_staff' && wp_verify_nonce($_POST['_wpnonce'], 'ontime_staff_nonce')) {
            $this->handle_delete_staff();
        }
    }

    /**
     * Handle bulk actions
     */
    public function handle_bulk_actions()
    {
        if (isset($_GET['page']) && $_GET['page'] === 'ontime-appointments') {
            $this->appointments_table = new Appointments_List_Table();
            $this->appointments_table->process_bulk_action();
        }
    }

    /**
     * Display admin notices
     */
    public function display_admin_notices()
    {
        // Check if we have a success message
        if (isset($_GET['action']) && in_array($_GET['action'], ['saved', 'updated', 'created', 'deleted']) && isset($_GET['page']) && strpos($_GET['page'], 'ontime') === 0) {
            $message = '';
            
            switch ($_GET['action']) {
                case 'saved':
                case 'updated':
                    $message = __('تغییرات با موفقیت ذخیره شد.', 'ontime');
                    break;
                case 'created':
                    $message = __('مورد جدید با موفقیت ایجاد شد.', 'ontime');
                    break;
                case 'deleted':
                    $message = __('مورد مورد نظر با موفقیت حذف شد.', 'ontime');
                    break;
            }

            if (!empty($message)) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
            }
        }

        // Bulk action notices
        if (isset($_GET['action']) && isset($_GET['count']) && isset($_GET['page']) && $_GET['page'] === 'ontime-appointments') {
            $action = str_replace('d', '', $_GET['action']);
            $count = absint($_GET['count']);
            
            $messages = [
                'confirm' => _n('%d نوبت تایید شد.', '%d نوبت تایید شد.', $count, 'ontime'),
                'cancel' => _n('%d نوبت لغو شد.', '%d نوبت لغو شد.', $count, 'ontime'),
                'complete' => _n('%d نوبت تکمیل شد.', '%d نوبت تکمیل شد.', $count, 'ontime'),
                'delete' => _n('%d نوبت حذف شد.', '%d نوبت حذف شد.', $count, 'ontime'),
            ];

            if (isset($messages[$action])) {
                echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(esc_html($messages[$action]), $count) . '</p></div>';
            }
        }
    }

    /**
     * Handle service form submission
     */
    private function handle_service_form()
    {
        $service_id = isset($_POST['service_id']) ? absint($_POST['service_id']) : 0;
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $duration = isset($_POST['duration_minutes']) ? absint($_POST['duration_minutes']) : 30;
        $price = isset($_POST['price']) ? (float) $_POST['price'] : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if (empty($name)) {
            return;
        }

        $data = [
            'name' => $name,
            'duration_minutes' => $duration,
            'price' => $price,
            'is_active' => $is_active,
        ];

        if ($service_id > 0) {
            $this->db->update_service($service_id, $data);
        } else {
            $this->db->insert_service($data);
        }

        // Redirect to prevent form resubmission
        $redirect = add_query_arg([
            'page' => 'ontime-services',
            'action' => $service_id > 0 ? 'updated' : 'created',
        ], admin_url('admin.php'));

        wp_redirect($redirect);
        exit;
    }

    /**
     * Handle staff form submission
     */
    private function handle_staff_form()
    {
        $staff_id = isset($_POST['staff_id']) ? absint($_POST['staff_id']) : 0;
        $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
        $display_name = isset($_POST['display_name']) ? sanitize_text_field($_POST['display_name']) : '';
        $bio = isset($_POST['bio']) ? wp_kses_post($_POST['bio']) : '';
        $working_hours = isset($_POST['working_hours']) ? sanitize_textarea_field($_POST['working_hours']) : '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if (empty($display_name)) {
            return;
        }

        $data = [
            'user_id' => $user_id,
            'display_name' => $display_name,
            'bio' => $bio,
            'working_hours_json' => $working_hours,
            'is_active' => $is_active,
        ];

        if ($staff_id > 0) {
            $this->db->update_staff($staff_id, $data);
        } else {
            $this->db->insert_staff($data);
        }

        // Redirect to prevent form resubmission
        $redirect = add_query_arg([
            'page' => 'ontime-staff',
            'action' => $staff_id > 0 ? 'updated' : 'created',
        ], admin_url('admin.php'));

        wp_redirect($redirect);
        exit;
    }

    /**
     * Handle delete service
     */
    private function handle_delete_service()
    {
        $service_id = isset($_POST['service_id']) ? absint($_POST['service_id']) : 0;

        if ($service_id > 0) {
            $this->db->delete_service($service_id);
        }

        // Redirect
        $redirect = add_query_arg([
            'page' => 'ontime-services',
            'action' => 'deleted',
        ], admin_url('admin.php'));

        wp_redirect($redirect);
        exit;
    }

    /**
     * Handle delete staff
     */
    private function handle_delete_staff()
    {
        $staff_id = isset($_POST['staff_id']) ? absint($_POST['staff_id']) : 0;

        if ($staff_id > 0) {
            $this->db->delete_staff($staff_id);
        }

        // Redirect
        $redirect = add_query_arg([
            'page' => 'ontime-staff',
            'action' => 'deleted',
        ], admin_url('admin.php'));

        wp_redirect($redirect);
        exit;
    }

    /**
     * Render main page (Dashboard)
     */
    public function render_main_page()
    {
        // Check capability
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('شما اجازه دسترسی به این صفحه را ندارید.', 'ontime'));
        }

        // Get counts
        $total_appointments = $this->db->count_appointments();
        $pending_appointments = $this->db->count_appointments(['status' => 'pending']);
        $confirmed_appointments = $this->db->count_appointments(['status' => 'confirmed']);
        $cancelled_appointments = $this->db->count_appointments(['status' => 'cancelled']);
        $completed_appointments = $this->db->count_appointments(['status' => 'completed']);
        $total_services = count($this->db->get_services(false));
        $active_services = count($this->db->get_services(true));
        $total_staff = count($this->db->get_staff_members(false));
        $active_staff = count($this->db->get_staff_members(true));

        echo '<div class="wrap ontime-wrap">';
        echo '<h1>' . esc_html__('OnTime - داشبورد', 'ontime') . '</h1>';

        // Dashboard cards
        echo '<div class="ontime-dashboard-cards">';
        
        // Appointments stats
        echo '<div class="ontime-card ontime-card-appointments">';
        echo '<h3>' . esc_html__('کل نوبت‌ها', 'ontime') . '</h3>';
        echo '<p class="ontime-card-count">' . esc_html(number_format_i18n($total_appointments)) . '</p>';
        echo '<div class="ontime-card-sub">';
        echo '<span>' . esc_html__('تایید شده:', 'ontime') . ' ' . esc_html(number_format_i18n($confirmed_appointments)) . '</span>';
        echo '<span>' . esc_html__('در انتظار:', 'ontime') . ' ' . esc_html(number_format_i18n($pending_appointments)) . '</span>';
        echo '<span>' . esc_html__('لغو شده:', 'ontime') . ' ' . esc_html(number_format_i18n($cancelled_appointments)) . '</span>';
        echo '<span>' . esc_html__('تکمیل شده:', 'ontime') . ' ' . esc_html(number_format_i18n($completed_appointments)) . '</span>';
        echo '</div>';
        echo '</div>';

        // Services stats
        echo '<div class="ontime-card ontime-card-services">';
        echo '<h3>' . esc_html__('خدمات', 'ontime') . '</h3>';
        echo '<p class="ontime-card-count">' . esc_html(number_format_i18n($total_services)) . '</p>';
        echo '<p class="ontime-card-sub">' . esc_html__('فعال:', 'ontime') . ' ' . esc_html(number_format_i18n($active_services)) . '</p>';
        echo '</div>';

        // Staff stats
        echo '<div class="ontime-card ontime-card-staff">';
        echo '<h3>' . esc_html__('پرسنل', 'ontime') . '</h3>';
        echo '<p class="ontime-card-count">' . esc_html(number_format_i18n($total_staff)) . '</p>';
        echo '<p class="ontime-card-sub">' . esc_html__('فعال:', 'ontime') . ' ' . esc_html(number_format_i18n($active_staff)) . '</p>';
        echo '</div>';

        // Current date
        echo '<div class="ontime-card ontime-card-date">';
        echo '<h3>' . esc_html__('تاریخ امروز', 'ontime') . '</h3>';
        echo '<p class="ontime-card-count">' . esc_html($this->calendar->get_current_jalali_date()) . '</p>';
        echo '<p class="ontime-card-sub">' . esc_html($this->calendar->get_current_jalali_datetime()) . '</p>';
        echo '</div>';

        echo '</div>';

        // Clear float
        echo '<div style="clear:both;"></div>';

        echo '</div>';
    }

    /**
     * Render appointments page
     */
    public function render_appointments_page()
    {
        // Check capability
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('شما اجازه دسترسی به این صفحه را ندارید.', 'ontime'));
        }

        // Create list table
        $this->appointments_table = new Appointments_List_Table();
        $this->appointments_table->prepare_items();

        echo '<div class="wrap ontime-wrap">';
        echo '<h1>' . esc_html__('نوبت‌ها', 'ontime') . '</h1>';

        // Form for bulk actions
        echo '<form method="post">';
        $this->appointments_table->display();
        echo '</form>';

        echo '</div>';
    }

    /**
     * Render services page
     */
    public function render_services_page()
    {
        // Check capability
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('شما اجازه دسترسی به این صفحه را ندارید.', 'ontime'));
        }

        // Get all services
        $services = $this->db->get_services(false);

        // Get edit data if editing
        $edit_service = null;
        if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
            $edit_service = $this->db->get_service(absint($_GET['id']));
        }

        echo '<div class="wrap ontime-wrap">';
        echo '<h1>' . esc_html__('مدیریت خدمات', 'ontime') . '</h1>';

        // Add/Edit Form
        echo '<div class="ontime-card ontime-services-form">';
        echo '<h2>' . ($edit_service ? esc_html__('ویرایش سرویس', 'ontime') : esc_html__('افزودن سرویس جدید', 'ontime')) . '</h2>';
        echo '<form method="post">';
        
        // Security
        wp_nonce_field('ontime_services_nonce');
        
        // Hidden fields
        echo '<input type="hidden" name="ontime_action" value="' . ($edit_service ? 'edit_service' : 'add_service') . '">';
        if ($edit_service) {
            echo '<input type="hidden" name="service_id" value="' . esc_attr($edit_service['id']) . '">';
        }

        // Name
        echo '<div class="ontime-form-group">';
        echo '<label for="service_name">' . esc_html__('نام سرویس', 'ontime') . '</label>';
        echo '<input type="text" id="service_name" name="name" value="' . ($edit_service ? esc_attr($edit_service['name']) : '') . '" required>';
        echo '</div>';

        // Duration
        echo '<div class="ontime-form-group">';
        echo '<label for="service_duration">' . esc_html__('مدت زمان (دقیقه)', 'ontime') . '</label>';
        echo '<input type="number" id="service_duration" name="duration_minutes" value="' . ($edit_service ? esc_attr($edit_service['duration_minutes']) : 30) . '" min="1" required>';
        echo '</div>';

        // Price
        echo '<div class="ontime-form-group">';
        echo '<label for="service_price">' . esc_html__('قیمت (تومان)', 'ontime') . '</label>';
        echo '<input type="number" id="service_price" name="price" value="' . ($edit_service ? esc_attr($edit_service['price']) : 0) . '" min="0" step="0.01">';
        echo '</div>';

        // Active
        echo '<div class="ontime-form-group">';
        echo '<label>';
        echo '<input type="checkbox" name="is_active" value="1" ' . checked($edit_service ? $edit_service['is_active'] : 1, 1) . '>';
        echo ' ' . esc_html__('فعال', 'ontime');
        echo '</label>';
        echo '</div>';

        // Submit button
        echo '<button type="submit" class="ontime-btn">' . ($edit_service ? esc_html__('ذخیره تغییرات', 'ontime') : esc_html__('افزودن سرویس', 'ontime')) . '</button>';
        if ($edit_service) {
            echo '<a href="?page=ontime-services" class="ontime-btn">' . esc_html__('انصراف', 'ontime') . '</a>';
        }
        echo '</form>';
        echo '</div>';

        // Services List
        echo '<div class="ontime-services-list">';
        echo '<table class="ontime-table wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('شناسه', 'ontime') . '</th>';
        echo '<th>' . esc_html__('نام', 'ontime') . '</th>';
        echo '<th>' . esc_html__('مدت زمان', 'ontime') . '</th>';
        echo '<th>' . esc_html__('قیمت', 'ontime') . '</th>';
        echo '<th>' . esc_html__('وضعیت', 'ontime') . '</th>';
        echo '<th>' . esc_html__('عملیات', 'ontime') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        if (empty($services)) {
            echo '<tr><td colspan="6">' . esc_html__('هیچ سرویسی یافت نشد', 'ontime') . '</td></tr>';
        } else {
            foreach ($services as $service) {
                $status_text = $service['is_active'] ? esc_html__('فعال', 'ontime') : esc_html__('غیرفعال', 'ontime');
                $status_class = $service['is_active'] ? 'ontime-status-confirmed' : 'ontime-status-cancelled';
                $price = number_format($service['price']) . ' ' . __('تومان', 'ontime');
                $duration = $service['duration_minutes'] . ' ' . __('دقیقه', 'ontime');

                echo '<tr>';
                echo '<td>' . esc_html($service['id']) . '</td>';
                echo '<td>' . esc_html($service['name']) . '</td>';
                echo '<td>' . esc_html($duration) . '</td>';
                echo '<td>' . esc_html($price) . '</td>';
                echo '<td><span class="ontime-status ' . esc_attr($status_class) . '">' . esc_html($status_text) . '</span></td>';
                echo '<td>';
                echo '<a href="?page=ontime-services&action=edit&id=' . esc_attr($service['id']) . '" class="ontime-btn">' . esc_html__('ویرایش', 'ontime') . '</a> ';
                echo '<form method="post" style="display: inline;">';
                wp_nonce_field('ontime_services_nonce');
                echo '<input type="hidden" name="ontime_action" value="delete_service">';
                echo '<input type="hidden" name="service_id" value="' . esc_attr($service['id']) . '">';
                echo '<button type="submit" class="ontime-btn ontime-btn-danger ontime-delete">' . esc_html__('حذف', 'ontime') . '</button>';
                echo '</form>';
                echo '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
        echo '</div>';

        echo '</div>';
    }

    /**
     * Render staff page
     */
    public function render_staff_page()
    {
        // Check capability
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('شما اجازه دسترسی به این صفحه را ندارید.', 'ontime'));
        }

        // Get all staff
        $staff_members = $this->db->get_staff_members(false);
        
        // Get all users for dropdown
        $users = get_users(['role__not_in' => ['subscriber']]);

        // Get edit data if editing
        $edit_staff = null;
        if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
            $edit_staff = $this->db->get_staff(absint($_GET['id']));
        }

        echo '<div class="wrap ontime-wrap">';
        echo '<h1>' . esc_html__('مدیریت پرسنل', 'ontime') . '</h1>';

        // Add/Edit Form
        echo '<div class="ontime-card ontime-staff-form">';
        echo '<h2>' . ($edit_staff ? esc_html__('ویرایش پرسنل', 'ontime') : esc_html__('افزودن پرسنل جدید', 'ontime')) . '</h2>';
        echo '<form method="post">';
        
        // Security
        wp_nonce_field('ontime_staff_nonce');
        
        // Hidden fields
        echo '<input type="hidden" name="ontime_action" value="' . ($edit_staff ? 'edit_staff' : 'add_staff') . '">';
        if ($edit_staff) {
            echo '<input type="hidden" name="staff_id" value="' . esc_attr($edit_staff['id']) . '">';
        }

        // User ID
        echo '<div class="ontime-form-group">';
        echo '<label for="staff_user_id">' . esc_html__('کاربر وردپرس', 'ontime') . '</label>';
        echo '<select id="staff_user_id" name="user_id">';
        echo '<option value="">' . esc_html__('هیچکدام', 'ontime') . '</option>';
        foreach ($users as $user) {
            echo '<option value="' . esc_attr($user->ID) . '" ' . selected($edit_staff ? $edit_staff['user_id'] : 0, $user->ID) . '>' . esc_html($user->display_name) . ' (' . esc_html($user->user_login) . ')</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__('optional - ارتباط پرسنل با کاربر وردپرس', 'ontime') . '</p>';
        echo '</div>';

        // Display Name
        echo '<div class="ontime-form-group">';
        echo '<label for="staff_display_name">' . esc_html__('نام نمایشی', 'ontime') . '</label>';
        echo '<input type="text" id="staff_display_name" name="display_name" value="' . ($edit_staff ? esc_attr($edit_staff['display_name']) : '') . '" required>';
        echo '</div>';

        // Bio
        echo '<div class="ontime-form-group">';
        echo '<label for="staff_bio">' . esc_html__('بیوگرافی', 'ontime') . '</label>';
        echo '<textarea id="staff_bio" name="bio" rows="4">' . ($edit_staff ? esc_textarea($edit_staff['bio']) : '') . '</textarea>';
        echo '</div>';

        // Working Hours
        echo '<div class="ontime-form-group">';
        echo '<label for="staff_working_hours">' . esc_html__('ساعات کاری (JSON)', 'ontime') . '</label>';
        echo '<textarea id="staff_working_hours" name="working_hours" rows="4" placeholder=\'{"شنبه": ["09:00-18:00"]}\'>' . ($edit_staff ? esc_textarea($edit_staff['working_hours_json']) : '') . '</textarea>';
        echo '<p class="description">' . esc_html__('ساعات کاری به صورت JSON - روزهای هفته به فارسی', 'ontime') . '</p>';
        echo '</div>';

        // Active
        echo '<div class="ontime-form-group">';
        echo '<label>';
        echo '<input type="checkbox" name="is_active" value="1" ' . checked($edit_staff ? $edit_staff['is_active'] : 1, 1) . '>';
        echo ' ' . esc_html__('فعال', 'ontime');
        echo '</label>';
        echo '</div>';

        // Submit button
        echo '<button type="submit" class="ontime-btn">' . ($edit_staff ? esc_html__('ذخیره تغییرات', 'ontime') : esc_html__('افزودن پرسنل', 'ontime')) . '</button>';
        if ($edit_staff) {
            echo '<a href="?page=ontime-staff" class="ontime-btn">' . esc_html__('انصراف', 'ontime') . '</a>';
        }
        echo '</form>';
        echo '</div>';

        // Staff List
        echo '<div class="ontime-staff-list">';
        echo '<table class="ontime-table wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('شناسه', 'ontime') . '</th>';
        echo '<th>' . esc_html__('نام', 'ontime') . '</th>';
        echo '<th>' . esc_html__('کاربر وردپرس', 'ontime') . '</th>';
        echo '<th>' . esc_html__('وضعیت', 'ontime') . '</th>';
        echo '<th>' . esc_html__('عملیات', 'ontime') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        if (empty($staff_members)) {
            echo '<tr><td colspan="5">' . esc_html__('هیچ پرسنلی یافت نشد', 'ontime') . '</td></tr>';
        } else {
            foreach ($staff_members as $staff) {
                $user_name = '';
                if ($staff['user_id']) {
                    $user = get_user_by('ID', $staff['user_id']);
                    $user_name = $user ? $user->display_name : __('حذف شده', 'ontime');
                }
                $status_text = $staff['is_active'] ? esc_html__('فعال', 'ontime') : esc_html__('غیرفعال', 'ontime');
                $status_class = $staff['is_active'] ? 'ontime-status-confirmed' : 'ontime-status-cancelled';

                echo '<tr>';
                echo '<td>' . esc_html($staff['id']) . '</td>';
                echo '<td>' . esc_html($staff['display_name']) . '</td>';
                echo '<td>' . esc_html($user_name) . '</td>';
                echo '<td><span class="ontime-status ' . esc_attr($status_class) . '">' . esc_html($status_text) . '</span></td>';
                echo '<td>';
                echo '<a href="?page=ontime-staff&action=edit&id=' . esc_attr($staff['id']) . '" class="ontime-btn">' . esc_html__('ویرایش', 'ontime') . '</a> ';
                echo '<form method="post" style="display: inline;">';
                wp_nonce_field('ontime_staff_nonce');
                echo '<input type="hidden" name="ontime_action" value="delete_staff">';
                echo '<input type="hidden" name="staff_id" value="' . esc_attr($staff['id']) . '">';
                echo '<button type="submit" class="ontime-btn ontime-btn-danger ontime-delete">' . esc_html__('حذف', 'ontime') . '</button>';
                echo '</form>';
                echo '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
        echo '</div>';

        echo '</div>';
    }

    /**
     * Render settings page
     */
    public function render_settings_page()
    {
        // Settings are handled by Settings class
        $this->settings->render_settings_page();
    }
}
