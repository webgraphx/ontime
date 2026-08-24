<?php
/**
 * OnTime Public Handler
 * 
 * Main public class using Singleton pattern
 * 
 * @package OnTime
 * @subpackage Public
 * @since 1.0.0
 */

namespace OnTime\Public_;

use OnTime\Database\Database;
use OnTime\Calendar\Calendar_Engine;

/**
 * Public class for OnTime plugin
 * 
 * Handles all front-end functionality
 */
final class Public_Handler
{
    /**
     * Singleton instance
     * @var Public_Handler|null
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
     * Constructor - Private for Singleton pattern
     */
    private function __construct()
    {
        $this->db = Database::get_instance();
        $this->calendar = Calendar_Engine::get_instance();
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
     * @return Public_Handler
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
        // Register shortcode
        add_shortcode('ontime_booking', [$this, 'render_booking_form']);

        // Enqueue public assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_public_assets']);

        // AJAX handlers
        add_action('wp_ajax_ontime_get_available_slots', [$this, 'ajax_get_available_slots']);
        add_action('wp_ajax_nopriv_ontime_get_available_slots', [$this, 'ajax_get_available_slots']);
        add_action('wp_ajax_ontime_create_appointment', [$this, 'ajax_create_appointment']);
        add_action('wp_ajax_nopriv_ontime_create_appointment', [$this, 'ajax_create_appointment']);
    }

    /**
     * Enqueue public assets
     */
    public function enqueue_public_assets()
    {
        // CSS
        wp_enqueue_style(
            'ontime-public',
            ONTIME_PLUGIN_URL . 'public/css/public.css',
            [],
            ONTIME_VERSION
        );

        // JS
        wp_enqueue_script(
            'ontime-public',
            ONTIME_PLUGIN_URL . 'public/js/public.js',
            ['jquery'],
            ONTIME_VERSION,
            true
        );

        // Localize script
        wp_localize_script(
            'ontime-public',
            'OnTimePublic',
            [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ontime_public_nonce'),
                'texts' => [
                    'loading' => __('در حال بارگذاری...', 'ontime'),
                    'error' => __('خطایی رخ داد', 'ontime'),
                    'success' => __('نوبت با موفقیت رزرو شد', 'ontime'),
                ],
            ]
        );
    }

    /**
     * Render booking form shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string Form HTML
     */
    public function render_booking_form($atts)
    {
        ob_start();
        
        $services = $this->db->get_services(true);
        $staff = $this->db->get_staff_members(true);

        if (empty($services)) {
            return '<p>' . esc_html__('هیچ سرویسی یافت نشد', 'ontime') . '</p>';
        }

        echo '<div class="ontime-booking-form">';
        echo '<h3>' . esc_html__('رزرو نوبت', 'ontime') . '</h3>';
        echo '<form id="ontime-booking-form" method="post">';
        
        // Customer info
        echo '<div class="ontime-form-group">';
        echo '<label for="ontime_customer_name">' . esc_html__('نام و نام خانوادگی', 'ontime') . '</label>';
        echo '<input type="text" id="ontime_customer_name" name="customer_name" required>';
        echo '</div>';

        echo '<div class="ontime-form-group">';
        echo '<label for="ontime_customer_phone">' . esc_html__('تلفن', 'ontime') . '</label>';
        echo '<input type="tel" id="ontime_customer_phone" name="customer_phone">';
        echo '</div>';

        echo '<div class="ontime-form-group">';
        echo '<label for="ontime_customer_email">' . esc_html__('ایمیل', 'ontime') . '</label>';
        echo '<input type="email" id="ontime_customer_email" name="customer_email">';
        echo '</div>';

        // Service selection
        echo '<div class="ontime-form-group">';
        echo '<label for="ontime_service_id">' . esc_html__('سرویس', 'ontime') . '</label>';
        echo '<select id="ontime_service_id" name="service_id" required>';
        echo '<option value="">' . esc_html__('انتخاب کنید', 'ontime') . '</option>';
        foreach ($services as $service) {
            echo '<option value="' . esc_attr($service['id']) . '">' . esc_html($service['name']) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        // Staff selection
        echo '<div class="ontime-form-group">';
        echo '<label for="ontime_staff_id">' . esc_html__('پرسنل', 'ontime') . '</label>';
        echo '<select id="ontime_staff_id" name="staff_id" required>';
        echo '<option value="">' . esc_html__('انتخاب کنید', 'ontime') . '</option>';
        foreach ($staff as $member) {
            echo '<option value="' . esc_attr($member['id']) . '">' . esc_html($member['display_name']) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        // Date selection
        echo '<div class="ontime-form-group">';
        echo '<label for="ontime_date">' . esc_html__('تاریخ', 'ontime') . '</label>';
        echo '<input type="text" id="ontime_date" name="date" class="ontime-datepicker" required>';
        echo '</div>';

        // Time slots
        echo '<div class="ontime-form-group">';
        echo '<label>' . esc_html__('ساعت آزاد', 'ontime') . '</label>';
        echo '<div id="ontime_slots_container">';
        echo '<p>' . esc_html__('ابتدا سرویس، پرسنل و تاریخ را انتخاب کنید', 'ontime') . '</p>';
        echo '</div>';
        echo '</div>';

        echo '<button type="submit">' . esc_html__('رزرو نوبت', 'ontime') . '</button>';
        echo '<input type="hidden" name="action" value="ontime_create_appointment">';
        echo '<input type="hidden" name="nonce" value="' . wp_create_nonce('ontime_public_nonce') . '">';
        echo '</form>';
        echo '</div>';

        return ob_get_clean();
    }

    /**
     * AJAX: Get available slots
     */
    public function ajax_get_available_slots()
    {
        check_ajax_referer('ontime_public_nonce', 'nonce');

        $service_id = isset($_POST['service_id']) ? absint($_POST['service_id']) : 0;
        $staff_id = isset($_POST['staff_id']) ? absint($_POST['staff_id']) : 0;
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';

        if (empty($service_id) || empty($staff_id) || empty($date)) {
            wp_send_json_error(['message' => __('لطفا تمام فیلدها را پر کنید', 'ontime')]);
        }

        // Get service duration
        $service = $this->db->get_service($service_id);
        if (empty($service)) {
            wp_send_json_error(['message' => __('سرویس یافت نشد', 'ontime')]);
        }

        $duration = absint($service['duration_minutes']);

        // Use Calendar Engine to get available slots
        $slots = $this->calendar->get_available_slots($staff_id, $date, $duration);

        // Check if date is holiday
        if ($this->calendar->is_holiday($date)) {
            $holiday_name = $this->calendar->get_holiday_name($date);
            wp_send_json_error([
                'message' => sprintf(__('تاریخ انتخاب شده تعطیل است: %s', 'ontime'), $holiday_name)
            ]);
        }

        wp_send_json_success(['slots' => $slots]);
    }

    /**
     * AJAX: Create appointment
     */
    public function ajax_create_appointment()
    {
        check_ajax_referer('ontime_public_nonce', 'nonce');

        $data = [
            'customer_name' => isset($_POST['customer_name']) ? sanitize_text_field($_POST['customer_name']) : '',
            'customer_phone' => isset($_POST['customer_phone']) ? sanitize_text_field($_POST['customer_phone']) : '',
            'customer_email' => isset($_POST['customer_email']) ? sanitize_email($_POST['customer_email']) : '',
            'service_id' => isset($_POST['service_id']) ? absint($_POST['service_id']) : 0,
            'staff_id' => isset($_POST['staff_id']) ? absint($_POST['staff_id']) : 0,
            'date' => isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '',
            'slot' => isset($_POST['slot']) ? sanitize_text_field($_POST['slot']) : '',
        ];

        if (empty($data['customer_name']) || empty($data['service_id']) || empty($data['staff_id']) || empty($data['date']) || empty($data['slot'])) {
            wp_send_json_error(['message' => __('لطفا تمام فیلدها را پر کنید', 'ontime')]);
        }

        // Get service info
        $service = $this->db->get_service($data['service_id']);
        if (empty($service)) {
            wp_send_json_error(['message' => __('سرویس یافت نشد', 'ontime')]);
        }

        // Parse slot time (format: HH:MM)
        $slot_parts = explode(':', $data['slot']);
        $hour = isset($slot_parts[0]) ? absint($slot_parts[0]) : 0;
        $minute = isset($slot_parts[1]) ? absint($slot_parts[1]) : 0;

        // Convert Jalali date to Gregorian using Calendar Engine
        $gregorian_date = $this->calendar->jalali_to_gregorian($data['date']);
        
        if (empty($gregorian_date)) {
            wp_send_json_error(['message' => __('فرمت تاریخ نامعتبر است', 'ontime')]);
        }

        $start_datetime = sprintf('%s %02d:%02d:00', $gregorian_date, $hour, $minute);
        $end_datetime = date('Y-m-d H:i:s', strtotime($start_datetime . ' UTC') + ($service['duration_minutes'] * 60));

        // Insert appointment
        $appointment_data = [
            'customer_name' => $data['customer_name'],
            'customer_phone' => $data['customer_phone'],
            'customer_email' => $data['customer_email'],
            'service_id' => $data['service_id'],
            'staff_id' => $data['staff_id'],
            'start_datetime' => $start_datetime,
            'end_datetime' => $end_datetime,
            'status' => 'pending',
            'total_price' => $service['price'],
        ];

        $appointment_id = $this->db->insert_appointment($appointment_data);

        if ($appointment_id) {
            wp_send_json_success([
                'message' => __('نوبت با موفقیت رزرو شد', 'ontime'),
                'appointment_id' => $appointment_id
            ]);
        } else {
            wp_send_json_error(['message' => __('خطا در رزرو نوبت', 'ontime')]);
        }
    }


}
