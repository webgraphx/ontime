<?php
/**
 * OnTime Settings Page
 * 
 * Settings class using WordPress Settings API
 * 
 * @package OnTime
 * @subpackage Admin
 * @since 1.0.0
 */

namespace OnTime\Admin;

use OnTime\Calendar\Calendar_Engine;

/**
 * Settings class for OnTime plugin
 * 
 * Handles all plugin settings using WordPress Settings API
 */
final class Settings
{
    /**
     * Singleton instance
     * @var Settings|null
     */
    private static $instance = null;

    /**
     * Option group name
     * @var string
     */
    private $option_group = 'ontime_settings_group';

    /**
     * Option name
     * @var string
     */
    private $option_name = 'ontime_settings';

    /**
     * Constructor - Private for Singleton pattern
     */
    private function __construct()
    {
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
     * @return Settings
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
        // Register settings
        add_action('admin_init', [$this, 'register_settings']);

        // Add settings section to admin menu
        add_action('admin_menu', [$this, 'add_settings_page']);

        // Enqueue settings assets
        add_action('admin_enqueue_scripts', [$this, 'enqueue_settings_assets']);
    }

    /**
     * Register settings using Settings API
     */
    public function register_settings()
    {
        // Register option group
        register_setting(
            $this->option_group,
            $this->option_name,
            [$this, 'sanitize_settings']
        );

        // Add settings sections
        $this->add_sms_gateway_section();
        $this->add_booking_rules_section();
        $this->add_integration_section();
    }

    /**
     * Add settings page to admin menu
     */
    public function add_settings_page()
    {
        add_submenu_page(
            'ontime',
            __('تنظیمات', 'ontime'),
            __('تنظیمات', 'ontime'),
            'manage_options',
            'ontime-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Enqueue settings specific assets
     * 
     * @param string $hook Current page hook
     */
    public function enqueue_settings_assets($hook)
    {
        if ($hook !== 'toplevel_page_ontime-settings') {
            return;
        }

        // CSS
        wp_enqueue_style(
            'ontime-settings',
            ONTIME_PLUGIN_URL . 'admin/css/settings.css',
            ['ontime-admin'],
            ONTIME_VERSION
        );

        // JS
        wp_enqueue_script(
            'ontime-settings',
            ONTIME_PLUGIN_URL . 'admin/js/settings.js',
            ['jquery', 'ontime-admin'],
            ONTIME_VERSION,
            true
        );
    }

    /**
     * Add SMS Gateway settings section
     */
    private function add_sms_gateway_section()
    {
        add_settings_section(
            'ontime_sms_settings',
            __('تنظیمات درگاه پیامک', 'ontime'),
            [$this, 'render_sms_section_description'],
            'ontime-settings'
        );

        add_settings_field(
            'sms_api_key',
            __('کلید API', 'ontime'),
            [$this, 'render_text_input'],
            'ontime-settings',
            'ontime_sms_settings',
            [
                'label_for' => 'sms_api_key',
                'class' => 'ontime-settings-field',
                'description' => __('کلید API ارائه‌دهنده خدمات پیامکی خود را وارد کنید', 'ontime'),
            ]
        );

        add_settings_field(
            'sms_api_secret',
            __('رمز API', 'ontime'),
            [$this, 'render_password_input'],
            'ontime-settings',
            'ontime_sms_settings',
            [
                'label_for' => 'sms_api_secret',
                'class' => 'ontime-settings-field',
                'description' => __('رمز مخفی API ارائه‌دهنده خدمات پیامکی خود را وارد کنید', 'ontime'),
            ]
        );

        add_settings_field(
            'sms_sender_number',
            __('شماره فرستنده', 'ontime'),
            [$this, 'render_text_input'],
            'ontime-settings',
            'ontime_sms_settings',
            [
                'label_for' => 'sms_sender_number',
                'class' => 'ontime-settings-field',
                'description' => __('شماره‌ای که پیامک‌ها از آن ارسال می‌شود (مثال: 5000123456)', 'ontime'),
                'placeholder' => '5000123456',
            ]
        );

        add_settings_field(
            'sms_provider',
            __('ارائه‌دهنده خدمات پیامک', 'ontime'),
            [$this, 'render_select_input'],
            'ontime-settings',
            'ontime_sms_settings',
            [
                'label_for' => 'sms_provider',
                'class' => 'ontime-settings-field',
                'description' => __('ارائه‌دهنده خدمات پیامکی را انتخاب کنید', 'ontime'),
                'options' => [
                    '' => __('هیچکدام', 'ontime'),
                    'kavenegar' => 'Kavenegar',
                    'smsir' => 'SMS.ir',
                    'ippanel' => 'IPPanel',
                    'farazsms' => 'FarazSMS',
                    'sabapayamak' => 'SabaPayamak',
                    'custom' => __('سالم سفارشی', 'ontime'),
                ],
            ]
        );

        add_settings_field(
            'sms_enabled',
            __('فعال‌سازی ارسال پیامک', 'ontime'),
            [$this, 'render_checkbox_input'],
            'ontime-settings',
            'ontime_sms_settings',
            [
                'label_for' => 'sms_enabled',
                'class' => 'ontime-settings-field',
                'description' => __(' فعال کردن ارسال پیامک برای تایید و یادآوری نوبت‌ها', 'ontime'),
            ]
        );
    }

    /**
     * Add Booking Rules settings section
     */
    private function add_booking_rules_section()
    {
        add_settings_section(
            'ontime_booking_rules',
            __('قوانین رزرو', 'ontime'),
            [$this, 'render_booking_rules_section_description'],
            'ontime-settings'
        );

        add_settings_field(
            'min_advance_notice',
            __('حداقل زمان اطلاع قبلی', 'ontime'),
            [$this, 'render_number_input'],
            'ontime-settings',
            'ontime_booking_rules',
            [
                'label_for' => 'min_advance_notice',
                'class' => 'ontime-settings-field',
                'description' => __('حداقل ساعاتی که کاربر باید قبل از نوبت رزرو کند (به ساعت)', 'ontime'),
                'min' => 0,
                'max' => 168,
                'step' => 1,
                'placeholder' => '24',
                'suffix' => __('ساعت', 'ontime'),
            ]
        );

        add_settings_field(
            'max_booking_days',
            __('حداکثر روزهای پیش‌رو', 'ontime'),
            [$this, 'render_number_input'],
            'ontime-settings',
            'ontime_booking_rules',
            [
                'label_for' => 'max_booking_days',
                'class' => 'ontime-settings-field',
                'description' => __('حداکثر روزهایی که کاربر می‌تواند برای آینده رزرو کند', 'ontime'),
                'min' => 1,
                'max' => 365,
                'step' => 1,
                'placeholder' => '30',
                'suffix' => __('روز', 'ontime'),
            ]
        );

        add_settings_field(
            'booking_time_interval',
            __('فاصله زمانی اسلات‌ها', 'ontime'),
            [$this, 'render_number_input'],
            'ontime-settings',
            'ontime_booking_rules',
            [
                'label_for' => 'booking_time_interval',
                'class' => 'ontime-settings-field',
                'description' => __('فاصله زمانی بین اسلات‌ها به دقیقه (پیش‌فرض: 30 دقیقه)', 'ontime'),
                'min' => 5,
                'max' => 120,
                'step' => 5,
                'placeholder' => '30',
                'suffix' => __('دقیقه', 'ontime'),
            ]
        );

        add_settings_field(
            'require_phone',
            __('اجباری بودن تلفن', 'ontime'),
            [$this, 'render_checkbox_input'],
            'ontime-settings',
            'ontime_booking_rules',
            [
                'label_for' => 'require_phone',
                'class' => 'ontime-settings-field',
                'description' => __('تلفن را به عنوان فیلد اجباری در فرم رزرو در نظر بگیر', 'ontime'),
            ]
        );

        add_settings_field(
            'require_email',
            __('اجباری بودن ایمیل', 'ontime'),
            [$this, 'render_checkbox_input'],
            'ontime-settings',
            'ontime_booking_rules',
            [
                'label_for' => 'require_email',
                'class' => 'ontime-settings-field',
                'description' => __('ایمیل را به عنوان فیلد اجباری در فرم رزرو در نظر بگیر', 'ontime'),
            ]
        );

        add_settings_field(
            'auto_confirm',
            __('تایید خودکار نوبت', 'ontime'),
            [$this, 'render_checkbox_input'],
            'ontime-settings',
            'ontime_booking_rules',
            [
                'label_for' => 'auto_confirm',
                'class' => 'ontime-settings-field',
                'description' => __('نوبت‌ها بلافاصله پس از رزرو تایید شوند (در غیر این صورت در حالت «در انتظار» باقی می‌مانند)', 'ontime'),
            ]
        );
    }

    /**
     * Add Integration settings section
     */
    private function add_integration_section()
    {
        add_settings_section(
            'ontime_integration_settings',
            __('یکپارچه‌سازی', 'ontime'),
            [$this, 'render_integration_section_description'],
            'ontime-settings'
        );

        add_settings_field(
            'woocommerce_integration',
            __('سازگاری با ووکامرس', 'ontime'),
            [$this, 'render_checkbox_input'],
            'ontime-settings',
            'ontime_integration_settings',
            [
                'label_for' => 'woocommerce_integration',
                'class' => 'ontime-settings-field',
                'description' => __('فعال کردن سازگاری با ووکامرس برای پرداخت و محصولات', 'ontime'),
            ]
        );

        add_settings_field(
            'woocommerce_product_sync',
            __('همگام‌سازی خدمات با محصولات ووکامرس', 'ontime'),
            [$this, 'render_checkbox_input'],
            'ontime-settings',
            'ontime_integration_settings',
            [
                'label_for' => 'woocommerce_product_sync',
                'class' => 'ontime-settings-field',
                'description' => __('خدمات OnTime را به عنوان محصولات ووکامرس نمایش ده', 'ontime'),
                'conditional' => 'woocommerce_integration',
            ]
        );

        add_settings_field(
            'woocommerce_payment_gateway',
            __('درگاه پرداخت ووکامرس', 'ontime'),
            [$this, 'render_select_input'],
            'ontime-settings',
            'ontime_integration_settings',
            [
                'label_for' => 'woocommerce_payment_gateway',
                'class' => 'ontime-settings-field',
                'description' => __('درگاه پرداختی که برای نوبت‌ها استفاده شود', 'ontime'),
                'options' => [
                    '' => __('پیش‌فرض ووکامرس', 'ontime'),
                    'zarinpal' => 'زین‌پال',
                    'idpay' => 'آی‌دی‌پی',
                    'sadad' => 'سداد',
                    'payir' => 'پی‌یر',
                ],
                'conditional' => 'woocommerce_integration',
            ]
        );

        add_settings_field(
            'google_calendar_sync',
            __('همگام‌سازی با گوگل تقویم', 'ontime'),
            [$this, 'render_checkbox_input'],
            'ontime-settings',
            'ontime_integration_settings',
            [
                'label_for' => 'google_calendar_sync',
                'class' => 'ontime-settings-field',
                'description' => __('نوبت‌ها را به گوگل تقویم همگام‌سازی کن', 'ontime'),
            ]
        );

        add_settings_field(
            'google_calendar_api_key',
            __('کلید API گوگل', 'ontime'),
            [$this, 'render_text_input'],
            'ontime-settings',
            'ontime_integration_settings',
            [
                'label_for' => 'google_calendar_api_key',
                'class' => 'ontime-settings-field',
                'description' => __('کلید API گوگل تقویم برای همگام‌سازی', 'ontime'),
                'conditional' => 'google_calendar_sync',
                'placeholder' => 'AIzaSyD...',
            ]
        );

        add_settings_field(
            'rest_api_enabled',
            __('فعال‌سازی REST API', 'ontime'),
            [$this, 'render_checkbox_input'],
            'ontime-settings',
            'ontime_integration_settings',
            [
                'label_for' => 'rest_api_enabled',
                'class' => 'ontime-settings-field',
                'description' => __('API REST را برای دسترسی برنامه‌ها و سایت‌های دیگر فعال کن', 'ontime'),
            ]
        );

        add_settings_field(
            'rest_api_key',
            __('کلید API REST', 'ontime'),
            [$this, 'render_text_input'],
            'ontime-settings',
            'ontime_integration_settings',
            [
                'label_for' => 'rest_api_key',
                'class' => 'ontime-settings-field',
                'description' => __('کلید امنیتی برای دسترسی به REST API (خالی بگذارید تا به صورت خودکار تولید شود)', 'ontime'),
                'conditional' => 'rest_api_enabled',
            ]
        );
    }

    /**
     * Render SMS section description
     */
    public function render_sms_section_description()
    {
        echo '<p>' . esc_html__('تنظیمات مربوط به ارسال پیامک برای تایید و یادآوری نوبت‌ها', 'ontime') . '</p>';
    }

    /**
     * Render Booking Rules section description
     */
    public function render_booking_rules_section_description()
    {
        echo '<p>' . esc_html__('قوانین و محدویت‌های کلی برای رزرو نوبت توسط کاربران', 'ontime') . '</p>';
    }

    /**
     * Render Integration section description
     */
    public function render_integration_section_description()
    {
        echo '<p>' . esc_html__('تنظیمات یکپارچه‌سازی با سایر سرویس‌ها و پلتفرم‌ها', 'ontime') . '</p>';
    }

    /**
     * Render text input field
     * 
     * @param array $args Field arguments
     */
    public function render_text_input($args)
    {
        $options = get_option($this->option_name, []);
        $value = isset($options[$args['label_for']]) ? esc_attr($options[$args['label_for']]) : '';
        $name = $this->option_name . '[' . $args['label_for'] . ']';
        $id = $args['label_for'];
        $placeholder = isset($args['placeholder']) ? $args['placeholder'] : '';
        $description = isset($args['description']) ? $args['description'] : '';
        $class = isset($args['class']) ? $args['class'] : '';
        $suffix = isset($args['suffix']) ? $args['suffix'] : '';

        echo '<div class="ontime-setting-field ' . esc_attr($class) . '">';
        echo '<input type="text" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . $value . '" class="regular-text" placeholder="' . esc_attr($placeholder) . '">';
        if (!empty($suffix)) {
            echo '<span class="ontime-field-suffix">' . esc_html($suffix) . '</span>';
        }
        if (!empty($description)) {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
        echo '</div>';
    }

    /**
     * Render password input field
     * 
     * @param array $args Field arguments
     */
    public function render_password_input($args)
    {
        $options = get_option($this->option_name, []);
        $value = isset($options[$args['label_for']]) ? esc_attr($options[$args['label_for']]) : '';
        $name = $this->option_name . '[' . $args['label_for'] . ']';
        $id = $args['label_for'];
        $placeholder = isset($args['placeholder']) ? $args['placeholder'] : '';
        $description = isset($args['description']) ? $args['description'] : '';
        $class = isset($args['class']) ? $args['class'] : '';

        echo '<div class="ontime-setting-field ' . esc_attr($class) . '">';
        echo '<input type="password" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . $value . '" class="regular-text" placeholder="' . esc_attr($placeholder) . '">';
        if (!empty($description)) {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
        echo '</div>';
    }

    /**
     * Render number input field
     * 
     * @param array $args Field arguments
     */
    public function render_number_input($args)
    {
        $options = get_option($this->option_name, []);
        $value = isset($options[$args['label_for']]) ? esc_attr($options[$args['label_for']]) : '';
        $name = $this->option_name . '[' . $args['label_for'] . ']';
        $id = $args['label_for'];
        $min = isset($args['min']) ? $args['min'] : 0;
        $max = isset($args['max']) ? $args['max'] : 9999;
        $step = isset($args['step']) ? $args['step'] : 1;
        $placeholder = isset($args['placeholder']) ? $args['placeholder'] : '';
        $description = isset($args['description']) ? $args['description'] : '';
        $class = isset($args['class']) ? $args['class'] : '';
        $suffix = isset($args['suffix']) ? $args['suffix'] : '';

        echo '<div class="ontime-setting-field ' . esc_attr($class) . '">';
        echo '<input type="number" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . $value . '" class="small-text" min="' . esc_attr($min) . '" max="' . esc_attr($max) . '" step="' . esc_attr($step) . '" placeholder="' . esc_attr($placeholder) . '">';
        if (!empty($suffix)) {
            echo '<span class="ontime-field-suffix">' . esc_html($suffix) . '</span>';
        }
        if (!empty($description)) {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
        echo '</div>';
    }

    /**
     * Render select input field
     * 
     * @param array $args Field arguments
     */
    public function render_select_input($args)
    {
        $options = get_option($this->option_name, []);
        $value = isset($options[$args['label_for']]) ? esc_attr($options[$args['label_for']]) : '';
        $name = $this->option_name . '[' . $args['label_for'] . ']';
        $id = $args['label_for'];
        $description = isset($args['description']) ? $args['description'] : '';
        $class = isset($args['class']) ? $args['class'] : '';
        $field_options = isset($args['options']) ? $args['options'] : [];

        echo '<div class="ontime-setting-field ' . esc_attr($class) . '">';
        echo '<select id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" class="regular-text">';
        echo '<option value="">' . esc_html__('--- انتخاب کنید ---', 'ontime') . '</option>';
        foreach ($field_options as $option_value => $option_label) {
            echo '<option value="' . esc_attr($option_value) . '" ' . selected($value, $option_value, false) . '>' . esc_html($option_label) . '</option>';
        }
        echo '</select>';
        if (!empty($description)) {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
        echo '</div>';
    }

    /**
     * Render checkbox input field
     * 
     * @param array $args Field arguments
     */
    public function render_checkbox_input($args)
    {
        $options = get_option($this->option_name, []);
        $value = isset($options[$args['label_for']]) ? $options[$args['label_for']] : 0;
        $name = $this->option_name . '[' . $args['label_for'] . ']';
        $id = $args['label_for'];
        $description = isset($args['description']) ? $args['description'] : '';
        $class = isset($args['class']) ? $args['class'] : '';
        $conditional = isset($args['conditional']) ? $args['conditional'] : '';

        $data_conditional = !empty($conditional) ? ' data-conditional="' . esc_attr($conditional) . '"' : '';

        echo '<div class="ontime-setting-field ' . esc_attr($class) . '"' . $data_conditional . '>';
        echo '<label>';
        echo '<input type="checkbox" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="1" ' . checked($value, 1, false) . '>';
        echo '</label>';
        if (!empty($description)) {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
        echo '</div>';
    }

    /**
     * Sanitize settings before saving
     * 
     * @param array $input Input data
     * @return array Sanitized data
     */
    public function sanitize_settings($input)
    {
        if (!is_array($input)) {
            return [];
        }

        $sanitized = [];

        foreach ($input as $key => $value) {
            switch ($key) {
                case 'sms_api_key':
                case 'sms_api_secret':
                case 'sms_sender_number':
                case 'google_calendar_api_key':
                case 'rest_api_key':
                    $sanitized[$key] = sanitize_text_field($value);
                    break;

                case 'sms_provider':
                case 'woocommerce_payment_gateway':
                    $allowed = ['kavenegar', 'smsir', 'ippanel', 'farazsms', 'sabapayamak', 'custom', ''];
                    $sanitized[$key] = in_array($value, $allowed) ? $value : '';
                    break;

                case 'min_advance_notice':
                case 'max_booking_days':
                case 'booking_time_interval':
                    $sanitized[$key] = absint($value);
                    break;

                case 'sms_enabled':
                case 'require_phone':
                case 'require_email':
                case 'auto_confirm':
                case 'woocommerce_integration':
                case 'woocommerce_product_sync':
                case 'google_calendar_sync':
                case 'rest_api_enabled':
                    $sanitized[$key] = isset($value) ? 1 : 0;
                    break;

                default:
                    $sanitized[$key] = sanitize_text_field($value);
                    break;
            }
        }

        // Generate REST API key if enabled and empty
        if (isset($sanitized['rest_api_enabled']) && $sanitized['rest_api_enabled'] && empty($sanitized['rest_api_key'])) {
            $sanitized['rest_api_key'] = wp_generate_password(32, false);
        }

        return $sanitized;
    }

    /**
     * Render settings page
     */
    public function render_settings_page()
    {
        // Check capability
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('شما اجازه دسترسی به این صفحه را ندارید.', 'ontime'));
        }

        // Get calendar engine for current date display
        $calendar = Calendar_Engine::get_instance();
        $current_jalali = $calendar->get_current_jalali_date();

        echo '<div class="wrap ontime-wrap">';
        echo '<h1>' . esc_html__('تنظیمات OnTime', 'ontime') . '</h1>';

        // Display current date
        echo '<div class="ontime-settings-header">';
        echo '<p>' . sprintf(esc_html__('تاریخ امروز: %s', 'ontime'), '<strong>' . esc_html($current_jalali) . '</strong>') . '</p>';
        echo '</div>';

        // Settings form
        echo '<form method="post" action="options.php" class="ontime-settings-form">';

        // Security
        settings_fields($this->option_group);

        // Hidden action
        echo '<input type="hidden" name="action" value="update">';

        // Settings sections
        do_settings_sections('ontime-settings');

        // Submit button
        echo '<div class="ontime-settings-submit">';
        submit_button(__('ذخیره تنظیمات', 'ontime'), 'primary', 'submit', false);
        echo '</div>';

        echo '</form>';
        echo '</div>';
    }

    /**
     * Get setting value
     * 
     * @param string $key Setting key
     * @param mixed $default Default value
     * @return mixed Setting value or default
     */
    public function get_setting($key, $default = null)
    {
        $options = get_option($this->option_name, []);
        return isset($options[$key]) ? $options[$key] : $default;
    }
}
