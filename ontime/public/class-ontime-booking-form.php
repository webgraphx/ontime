<?php
/**
 * OnTime Multi-Step Booking Form
 * 
 * Step-by-step, mobile-first booking experience
 * Steps: Service -> Staff -> Date & Time -> Customer Info -> Confirmation
 * 
 * @package OnTime
 * @subpackage Public
 * @since 1.0.0
 */

namespace OnTime\Public_;

use OnTime\Database\Database;
use OnTime\Calendar\Calendar_Engine;

/**
 * Multi-step booking form handler
 * Implements conversion-focused UX with minimal dependencies
 */
final class Booking_Form
{
    /**
     * Singleton instance
     * @var Booking_Form|null
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
     * @return Booking_Form
     */
    public static function get_instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Check rate limiting for booking submissions
     * 
     * @return void
     */
    private function check_rate_limit()
    {
        // Use WordPress transient to track submissions
        $transient_key = 'ontime_booking_rate_limit_' . $this->get_client_ip();
        $submissions = get_transient($transient_key);
        
        if ($submissions === false) {
            // First submission, set transient with 5 minute expiry
            set_transient($transient_key, 1, 5 * MINUTE_IN_SECONDS);
            return;
        }
        
        // Allow max 5 submissions per 5 minutes
        if ($submissions >= 5) {
            wp_send_json_error([
                'message' => __('Too many requests. Please wait and try again.', 'ontime'),
                'error_code' => 'rate_limit_exceeded'
            ]);
        }
        
        // Increment counter
        set_transient($transient_key, $submissions + 1, 5 * MINUTE_IN_SECONDS);
    }

    /**
     * Get client IP address for rate limiting
     * 
     * @return string Client IP
     */
    private function get_client_ip()
    {
        if (isset($_SERVER['REMOTE_ADDR'])) {
            return sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }
        return 'unknown';
    }

    /**
     * Log booking form errors
     * 
     * @param string $message Error message
     * @param array $context Additional context
     */
    private function log_error($message, $context = [])
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[OnTime Booking Error] ' . $message);
            if (!empty($context)) {
                error_log('[OnTime Booking Context] ' . print_r($context, true));
            }
        }
    }

    /**
     * Initialize hooks
     */
    public function init()
    {
        // Register the new shortcode
        add_shortcode('ontime_booking_form', [$this, 'render_booking_form']);

        // AJAX handlers for each step
        add_action('wp_ajax_ontime_get_services', [$this, 'ajax_get_services']);
        add_action('wp_ajax_nopriv_ontime_get_services', [$this, 'ajax_get_services']);

        add_action('wp_ajax_ontime_get_staff', [$this, 'ajax_get_staff']);
        add_action('wp_ajax_nopriv_ontime_get_staff', [$this, 'ajax_get_staff']);

        add_action('wp_ajax_ontime_get_available_dates', [$this, 'ajax_get_available_dates']);
        add_action('wp_ajax_nopriv_ontime_get_available_dates', [$this, 'ajax_get_available_dates']);

        add_action('wp_ajax_ontime_get_available_slots', [$this, 'ajax_get_available_slots']);
        add_action('wp_ajax_nopriv_ontime_get_available_slots', [$this, 'ajax_get_available_slots']);

        add_action('wp_ajax_ontime_validate_customer_info', [$this, 'ajax_validate_customer_info']);
        add_action('wp_ajax_nopriv_ontime_validate_customer_info', [$this, 'ajax_validate_customer_info']);

        add_action('wp_ajax_ontime_confirm_booking', [$this, 'ajax_confirm_booking']);
        add_action('wp_ajax_nopriv_ontime_confirm_booking', [$this, 'ajax_confirm_booking']);

        // Enqueue assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Enqueue CSS and JS for the booking form
     */
    public function enqueue_assets()
    {
        // Only load on pages with the shortcode
        if (!has_shortcode(get_the_content(), 'ontime_booking_form') && !isset($_GET['ontime_booking'])) {
            return;
        }

        // CSS with CSS variables for easy customization
        wp_enqueue_style(
            'ontime-booking-form',
            ONTIME_PLUGIN_URL . 'public/css/booking-form.css',
            [],
            ONTIME_VERSION
        );

        // Vanilla JavaScript (no jQuery dependency)
        wp_enqueue_script(
            'ontime-booking-form',
            ONTIME_PLUGIN_URL . 'public/js/booking-form.js',
            [], // No dependencies - pure vanilla JS
            ONTIME_VERSION,
            true
        );

        // Localize script with translations and config
        wp_localize_script(
            'ontime-booking-form',
            'OnTimeBooking',
            [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ontime_booking_nonce'),
                'texts' => [
                    'loading' => __('در حال بارگذاری...', 'ontime'),
                    'next' => __('ادامه', 'ontime'),
                    'back' => __('بازگشت', 'ontime'),
                    'confirm' => __('تایید و رزرو', 'ontime'),
                    'select_service' => __('لطفاً یک سرویس انتخاب کنید', 'ontime'),
                    'select_staff' => __('لطفاً یک پرسنل انتخاب کنید', 'ontime'),
                    'select_date' => __('لطفاً یک تاریخ انتخاب کنید', 'ontime'),
                    'select_time' => __('لطفاً یک ساعت انتخاب کنید', 'ontime'),
                    'enter_name' => __('لطفاً نام و نام خانوادگی را وارد کنید', 'ontime'),
                    'enter_phone' => __('لطفاً شماره تلفن را وارد کنید', 'ontime'),
                    'invalid_email' => __('آدرس ایمیل معتبر نیست', 'ontime'),
                    'success' => __('نوبت شما با موفقیت رزرو شد!', 'ontime'),
                    'error' => __('خطایی رخ داد. لطفاً دوباره تلاش کنید.', 'ontime'),
                    'step_service' => __('انتخاب سرویس', 'ontime'),
                    'step_staff' => __('انتخاب پرسنل', 'ontime'),
                    'step_datetime' => __('انتخاب تاریخ و ساعت', 'ontime'),
                    'step_customer' => __('اطلاعات مشتری', 'ontime'),
                    'step_confirm' => __('تایید نهایی', 'ontime'),
                    'holiday' => __('تعطیل', 'ontime'),
                    'no_slots' => __('در این تاریخ ساعت آزاد وجود ندارد', 'ontime'),
                ],
                'config' => [
                    'date_format' => 'YYYY/MM/DD',
                    'min_date' => 0, // Today
                    'max_date' => 60, // 60 days ahead
                ],
            ]
        );
    }

    /**
     * Render the multi-step booking form
     * 
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_booking_form($atts)
    {
        // Parse shortcode attributes
        $atts = shortcode_atts([
            'service_id' => '',
            'staff_id' => '',
            'title' => __('رزرو نوبت آنلاین', 'ontime'),
        ], $atts);

        ob_start();
        
        // Generate a unique form ID for multiple instances
        $form_id = 'ontime-booking-form-' . uniqid();
        
        // Get current Jalali date for datepicker default
        $current_date = $this->calendar->get_current_jalali_date();
        
        // Get services and staff for initial render (cached)
        $services = $this->db->get_services(true);
        $staff_members = $this->db->get_staff_members(true);
        
        // Check if we have any services
        if (empty($services)) {
            return '<div class="ontime-error">' . esc_html__('هیچ سرویسی برای رزرو یافت نشد.', 'ontime') . '</div>';
        }

        // Check if specific service or staff is requested via shortcode
        $preset_service = $atts['service_id'] ? absint($atts['service_id']) : 0;
        $preset_staff = $atts['staff_id'] ? absint($atts['staff_id']) : 0;

        // Verify preset values exist
        $preset_service_valid = false;
        if ($preset_service) {
            foreach ($services as $s) {
                if ($s['id'] == $preset_service) {
                    $preset_service_valid = true;
                    break;
                }
            }
        }

        $preset_staff_valid = false;
        if ($preset_staff) {
            foreach ($staff_members as $s) {
                if ($s['id'] == $preset_staff) {
                    $preset_staff_valid = true;
                    break;
                }
            }
        }

        // Build services JSON for JS
        $services_json = [];
        foreach ($services as $service) {
            $services_json[] = [
                'id' => $service['id'],
                'name' => html_entity_decode($service['name'], ENT_QUOTES, 'UTF-8'),
                'duration' => $service['duration_minutes'],
                'price' => $service['price'],
                'description' => !empty($service['description']) ? html_entity_decode($service['description'], ENT_QUOTES, 'UTF-8') : '',
            ];
        }

        // Build staff JSON for JS
        $staff_json = [];
        foreach ($staff_members as $staff) {
            $staff_json[] = [
                'id' => $staff['id'],
                'display_name' => html_entity_decode($staff['display_name'], ENT_QUOTES, 'UTF-8'),
                'bio' => !empty($staff['bio']) ? wp_strip_all_tags($staff['bio']) : '',
                'avatar' => !empty($staff['user_id']) ? get_avatar_url($staff['user_id'], ['size' => 150]) : '',
            ];
        }

        // Inline script data (minimal for performance)
        $inline_data = [
            'services' => $services_json,
            'staff' => $staff_json,
            'presetServiceId' => $preset_service_valid ? $preset_service : 0,
            'presetStaffId' => $preset_staff_valid ? $preset_staff : 0,
            'currentDate' => $current_date,
        ];

        $nonce = wp_create_nonce('ontime_booking_nonce');

        // Modern, accessible, mobile-first HTML structure
        ?>
        <div id="<?php echo esc_attr($form_id); ?>" 
             class="ontime-booking-container" 
             data-form-id="<?php echo esc_attr($form_id); ?>"
             data-preset-service="<?php echo esc_attr($preset_service); ?>"
             data-preset-staff="<?php echo esc_attr($preset_staff); ?>">

            <!-- Progress indicator -->
            <div class="ontime-progress" role="progressbar" aria-label="<?php esc_attr_e('مراحل رزرو نوبت', 'ontime'); ?>">
                <div class="ontime-progress-steps">
                    <div class="ontime-progress-step active" data-step="1">
                        <span class="ontime-step-number">1</span>
                        <span class="ontime-step-label"><?php esc_html_e('سرویس', 'ontime'); ?></span>
                    </div>
                    <div class="ontime-progress-step" data-step="2">
                        <span class="ontime-step-number">2</span>
                        <span class="ontime-step-label"><?php esc_html_e('پرسنل', 'ontime'); ?></span>
                    </div>
                    <div class="ontime-progress-step" data-step="3">
                        <span class="ontime-step-number">3</span>
                        <span class="ontime-step-label"><?php esc_html_e('تاریخ و ساعت', 'ontime'); ?></span>
                    </div>
                    <div class="ontime-progress-step" data-step="4">
                        <span class="ontime-step-number">4</span>
                        <span class="ontime-step-label"><?php esc_html_e('اطلاعات', 'ontime'); ?></span>
                    </div>
                    <div class="ontime-progress-step" data-step="5">
                        <span class="ontime-step-number">5</span>
                        <span class="ontime-step-label"><?php esc_html_e('تایید', 'ontime'); ?></span>
                    </div>
                </div>
                <div class="ontime-progress-bar">
                    <div class="ontime-progress-fill" style="width: 20%;"></div>
                </div>
            </div>

            <!-- Form title -->
            <header class="ontime-form-header">
                <h2 class="ontime-form-title"><?php echo esc_html($atts['title']); ?></h2>
                <p class="ontime-form-subtitle"><?php esc_html_e('مراحل رزرو را به صورت گام به گام تکمیل کنید', 'ontime'); ?></p>
            </header>

            <!-- Form -->
            <form id="ontime-booking-form-<?php echo esc_attr($form_id); ?>" 
                  class="ontime-booking-form" 
                  method="post"
                  novalidate>

                <!-- Step 1: Service Selection -->
                <div class="ontime-form-step active" data-step="1">
                    <div class="ontime-step-content">
                        <h3 class="ontime-step-title"><?php esc_html_e('سرویس مورد نظر خود را انتخاب کنید', 'ontime'); ?></h3>
                        <div class="ontime-services-grid">
                            <?php foreach ($services as $service) : 
                                $is_preset = ($preset_service && $preset_service == $service['id']);
                            ?>
                            <button type="button" 
                                    class="ontime-service-card<?php echo $is_preset ? ' selected' : ''; ?>"
                                    data-service-id="<?php echo esc_attr($service['id']); ?>"
                                    data-service-name="<?php echo esc_attr($service['name']); ?>"
                                    data-service-price="<?php echo esc_attr($service['price']); ?>"
                                    data-service-duration="<?php echo esc_attr($service['duration_minutes']); ?>">
                                <div class="ontime-service-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <line x1="12" y1="8" x2="12" y2="12"></line>
                                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                    </svg>
                                </div>
                                <div class="ontime-service-info">
                                    <h4 class="ontime-service-name"><?php echo esc_html($service['name']); ?></h4>
                                    <div class="ontime-service-meta">
                                        <span class="ontime-service-price"><?php echo esc_html(number_format($service['price'])); ?> <?php esc_html_e('تومان', 'ontime'); ?></span>
                                        <span class="ontime-service-duration"><?php echo esc_html($service['duration_minutes']); ?> <?php esc_html_e('دقیقه', 'ontime'); ?></span>
                                    </div>
                                </div>
                                <span class="ontime-service-check">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="20 6 9 17 4 12"></polyline>
                                    </svg>
                                </span>
                            </button>
                            <?php endforeach; ?>
                        </div>
                        <p class="ontime-error-message" data-step="1"></p>
                    </div>
                    <div class="ontime-step-footer">
                        <button type="button" class="ontime-btn ontime-btn-primary ontime-next-btn" data-to-step="2">
                            <?php esc_html_e('ادامه', 'ontime'); ?>
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Step 2: Staff Selection -->
                <div class="ontime-form-step" data-step="2">
                    <div class="ontime-step-content">
                        <h3 class="ontime-step-title"><?php esc_html_e('پرسنل مورد نظر خود را انتخاب کنید', 'ontime'); ?></h3>
                        <div class="ontime-staff-grid">
                            <?php foreach ($staff_members as $staff) : 
                                $is_preset = ($preset_staff && $preset_staff == $staff['id']);
                            ?>
                            <button type="button" 
                                    class="ontime-staff-card<?php echo $is_preset ? ' selected' : ''; ?>"
                                    data-staff-id="<?php echo esc_attr($staff['id']); ?>"
                                    data-staff-name="<?php echo esc_attr($staff['display_name']); ?>">
                                <?php if (!empty($staff['user_id'])) : 
                                    $avatar_url = get_avatar_url($staff['user_id'], ['size' => 80]);
                                ?>
                                <div class="ontime-staff-avatar" style="background-image: url('<?php echo esc_url($avatar_url); ?>');"></div>
                                <?php else : ?>
                                <div class="ontime-staff-avatar ontime-staff-avatar-placeholder">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="12" cy="7" r="4"></circle>
                                    </svg>
                                </div>
                                <?php endif; ?>
                                <div class="ontime-staff-info">
                                    <h4 class="ontime-staff-name"><?php echo esc_html($staff['display_name']); ?></h4>
                                    <?php if (!empty($staff['bio'])) : ?>
                                        <p class="ontime-staff-bio"><?php echo esc_html(wp_trim_words($staff['bio'], 10)); ?></p>
                                    <?php endif; ?>
                                </div>
                                <span class="ontime-staff-check">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="20 6 9 17 4 12"></polyline>
                                    </svg>
                                </span>
                            </button>
                            <?php endforeach; ?>
                        </div>
                        <p class="ontime-error-message" data-step="2"></p>
                    </div>
                    <div class="ontime-step-footer">
                        <button type="button" class="ontime-btn ontime-btn-secondary ontime-prev-btn" data-to-step="1">
                            <?php esc_html_e('بازگشت', 'ontime'); ?>
                        </button>
                        <button type="button" class="ontime-btn ontime-btn-primary ontime-next-btn" data-to-step="3">
                            <?php esc_html_e('ادامه', 'ontime'); ?>
                        </button>
                    </div>
                </div>

                <!-- Step 3: Date and Time Selection -->
                <div class="ontime-form-step" data-step="3">
                    <div class="ontime-step-content">
                        <h3 class="ontime-step-title"><?php esc_html_e('تاریخ و ساعت را انتخاب کنید', 'ontime'); ?></h3>
                        
                        <div class="ontime-datepicker-container">
                            <label class="ontime-field-label" for="ontime-date-<?php echo esc_attr($form_id); ?>">
                                <?php esc_html_e('تاریخ', 'ontime'); ?>
                                <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   id="ontime-date-<?php echo esc_attr($form_id); ?>"
                                   class="ontime-input ontime-datepicker"
                                   placeholder="<?php esc_attr_e('مثال: 1403/05/15', 'ontime'); ?>"
                                   autocomplete="off"
                                   value="<?php echo esc_attr($current_date); ?>">
                            <div class="ontime-datepicker-calendar" id="ontime-calendar-<?php echo esc_attr($form_id); ?>"></div>
                        </div>

                        <div class="ontime-time-slots-container" id="ontime-slots-<?php echo esc_attr($form_id); ?>">
                            <label class="ontime-field-label"><?php esc_html_e('ساعت‌های آزاد', 'ontime'); ?></label>
                            <div class="ontime-slots-grid" id="ontime-slots-grid-<?php echo esc_attr($form_id); ?>">
                                <div class="ontime-slots-loading">
                                    <div class="ontime-spinner"></div>
                                    <p><?php esc_html_e('لطفاً ابتدا تاریخ را انتخاب کنید', 'ontime'); ?></p>
                                </div>
                            </div>
                        </div>
                        <p class="ontime-error-message" data-step="3"></p>
                    </div>
                    <div class="ontime-step-footer">
                        <button type="button" class="ontime-btn ontime-btn-secondary ontime-prev-btn" data-to-step="2">
                            <?php esc_html_e('بازگشت', 'ontime'); ?>
                        </button>
                        <button type="button" class="ontime-btn ontime-btn-primary ontime-next-btn" data-to-step="4">
                            <?php esc_html_e('ادامه', 'ontime'); ?>
                        </button>
                    </div>
                </div>

                <!-- Step 4: Customer Information -->
                <div class="ontime-form-step" data-step="4">
                    <div class="ontime-step-content">
                        <h3 class="ontime-step-title"><?php esc_html_e('اطلاعات تماس خود را وارد کنید', 'ontime'); ?></h3>
                        
                        <div class="ontime-form-grid">
                            <div class="ontime-form-group">
                                <label class="ontime-field-label" for="ontime-name-<?php echo esc_attr($form_id); ?>">
                                    <?php esc_html_e('نام و نام خانوادگی', 'ontime'); ?>
                                    <span class="required">*</span>
                                </label>
                                <input type="text" 
                                       id="ontime-name-<?php echo esc_attr($form_id); ?>"
                                       class="ontime-input"
                                       name="customer_name"
                                       placeholder="<?php esc_attr_e('مثال: علی محمدی', 'ontime'); ?>"
                                       required
                                       autocomplete="name">
                                <span class="ontime-field-error"></span>
                            </div>

                            <div class="ontime-form-group">
                                <label class="ontime-field-label" for="ontime-phone-<?php echo esc_attr($form_id); ?>">
                                    <?php esc_html_e('تلفن همراه', 'ontime'); ?>
                                    <span class="required">*</span>
                                </label>
                                <input type="tel" 
                                       id="ontime-phone-<?php echo esc_attr($form_id); ?>"
                                       class="ontime-input"
                                       name="customer_phone"
                                       placeholder="<?php esc_attr_e('مثال: 09123456789', 'ontime'); ?>"
                                       required
                                       autocomplete="tel"
                                       dir="ltr">
                                <span class="ontime-field-error"></span>
                            </div>

                            <div class="ontime-form-group ontime-full-width">
                                <label class="ontime-field-label" for="ontime-email-<?php echo esc_attr($form_id); ?>">
                                    <?php esc_html_e('آدرس ایمیل', 'ontime'); ?>
                                </label>
                                <input type="email" 
                                       id="ontime-email-<?php echo esc_attr($form_id); ?>"
                                       class="ontime-input"
                                       name="customer_email"
                                       placeholder="<?php esc_attr_e('مثال: email@example.com', 'ontime'); ?>"
                                       autocomplete="email"
                                       dir="ltr">
                                <span class="ontime-field-error"></span>
                            </div>

                            <div class="ontime-form-group ontime-full-width">
                                <label class="ontime-field-label" for="ontime-notes-<?php echo esc_attr($form_id); ?>">
                                    <?php esc_html_e('یادداشت‌ها (اختیاری)', 'ontime'); ?>
                                </label>
                                <textarea id="ontime-notes-<?php echo esc_attr($form_id); ?>"
                                          class="ontime-input ontime-textarea"
                                          name="customer_notes"
                                          rows="3"
                                          placeholder="<?php esc_attr_e(' هر گونه توضیح یا درخواست خاص', 'ontime'); ?>"></textarea>
                            </div>
                        </div>
                        <p class="ontime-error-message" data-step="4"></p>
                    </div>
                    <div class="ontime-step-footer">
                        <button type="button" class="ontime-btn ontime-btn-secondary ontime-prev-btn" data-to-step="3">
                            <?php esc_html_e('بازگشت', 'ontime'); ?>
                        </button>
                        <button type="button" class="ontime-btn ontime-btn-primary ontime-next-btn" data-to-step="5">
                            <?php esc_html_e('ادامه', 'ontime'); ?>
                        </button>
                    </div>
                </div>

                <!-- Step 5: Confirmation -->
                <div class="ontime-form-step" data-step="5">
                    <div class="ontime-step-content">
                        <h3 class="ontime-step-title"><?php esc_html_e('اطلاعات نوبت را بررسی کنید', 'ontime'); ?></h3>
                        
                        <div class="ontime-confirmation-card">
                            <div class="ontime-confirmation-section">
                                <h4 class="ontime-confirmation-section-title"><?php esc_html_e('سرویس', 'ontime'); ?></h4>
                                <p class="ontime-confirmation-value" id="ontime-confirm-service-<?php echo esc_attr($form_id); ?>"></p>
                            </div>

                            <div class="ontime-confirmation-section">
                                <h4 class="ontime-confirmation-section-title"><?php esc_html_e('پرسنل', 'ontime'); ?></h4>
                                <p class="ontime-confirmation-value" id="ontime-confirm-staff-<?php echo esc_attr($form_id); ?>"></p>
                            </div>

                            <div class="ontime-confirmation-section">
                                <h4 class="ontime-confirmation-section-title"><?php esc_html_e('تاریخ و ساعت', 'ontime'); ?></h4>
                                <p class="ontime-confirmation-value" id="ontime-confirm-datetime-<?php echo esc_attr($form_id); ?>"></p>
                            </div>

                            <div class="ontime-confirmation-section">
                                <h4 class="ontime-confirmation-section-title"><?php esc_html_e('مدت زمان', 'ontime'); ?></h4>
                                <p class="ontime-confirmation-value" id="ontime-confirm-duration-<?php echo esc_attr($form_id); ?>"></p>
                            </div>

                            <div class="ontime-confirmation-section">
                                <h4 class="ontime-confirmation-section-title"><?php esc_html_e('قیمت', 'ontime'); ?></h4>
                                <p class="ontime-confirmation-value ontime-confirmation-price" id="ontime-confirm-price-<?php echo esc_attr($form_id); ?>"></p>
                            </div>

                            <div class="ontime-confirmation-section ontime-confirmation-customer">
                                <h4 class="ontime-confirmation-section-title"><?php esc_html_e('اطلاعات مشتری', 'ontime'); ?></h4>
                                <div class="ontime-customer-info">
                                    <p><strong><?php esc_html_e('نام:'); ?></strong> <span id="ontime-confirm-name-<?php echo esc_attr($form_id); ?>"></span></p>
                                    <p><strong><?php esc_html_e('تلفن:'); ?></strong> <span id="ontime-confirm-phone-<?php echo esc_attr($form_id); ?>"></span></p>
                                    <p><strong><?php esc_html_e('ایمیل:'); ?></strong> <span id="ontime-confirm-email-<?php echo esc_attr($form_id); ?>"></span></p>
                                    <p><strong><?php esc_html_e('یادداشت‌ها:'); ?></strong> <span id="ontime-confirm-notes-<?php echo esc_attr($form_id); ?>"></span></p>
                                </div>
                            </div>
                        </div>

                        <div class="ontime-terms-checkbox">
                            <input type="checkbox" 
                                   id="ontime-terms-<?php echo esc_attr($form_id); ?>"
                                   name="accept_terms"
                                   required>
                            <label for="ontime-terms-<?php echo esc_attr($form_id); ?>">
                                <?php esc_html_e('با ', 'ontime'); ?>
                                <a href="#" class="ontime-terms-link"><?php esc_html_e('قوانین و مقررات', 'ontime'); ?></a>
                                <?php esc_html_e(' موافقم', 'ontime'); ?>
                                <span class="required">*</span>
                            </label>
                        </div>
                        <p class="ontime-error-message" data-step="5"></p>
                    </div>
                    <div class="ontime-step-footer">
                        <button type="button" class="ontime-btn ontime-btn-secondary ontime-prev-btn" data-to-step="4">
                            <?php esc_html_e('ویرایش', 'ontime'); ?>
                        </button>
                        <button type="submit" class="ontime-btn ontime-btn-primary ontime-confirm-btn" data-form="<?php echo esc_attr($form_id); ?>">
                            <span class="ontime-btn-text"><?php esc_html_e('تایید و رزرو نهایی', 'ontime'); ?></span>
                            <span class="ontime-btn-loading" style="display: none;">
                                <span class="ontime-spinner-small"></span>
                                <?php esc_html_e('در حال پردازش...', 'ontime'); ?>
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Hidden fields for form submission -->
                <input type="hidden" name="action" value="ontime_confirm_booking">
                <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>">
                <input type="hidden" name="form_id" value="<?php echo esc_attr($form_id); ?>">
                <input type="hidden" name="service_id" id="ontime-hidden-service-<?php echo esc_attr($form_id); ?>" value="">
                <input type="hidden" name="staff_id" id="ontime-hidden-staff-<?php echo esc_attr($form_id); ?>" value="">
                <input type="hidden" name="appointment_date" id="ontime-hidden-date-<?php echo esc_attr($form_id); ?>" value="">
                <input type="hidden" name="appointment_time" id="ontime-hidden-time-<?php echo esc_attr($form_id); ?>" value="">
                <input type="hidden" name="service_price" id="ontime-hidden-price-<?php echo esc_attr($form_id); ?>" value="">
                <input type="hidden" name="service_duration" id="ontime-hidden-duration-<?php echo esc_attr($form_id); ?>" value="">
                
                <!-- Inline data for JavaScript -->
                <script type="application/json" id="ontime-form-data-<?php echo esc_attr($form_id); ?>">
                    <?php echo wp_json_encode($inline_data); ?>
                </script>
            </form>

            <!-- Success Modal -->
            <div class="ontime-modal" id="ontime-success-modal-<?php echo esc_attr($form_id); ?>" role="dialog" aria-hidden="true">
                <div class="ontime-modal-overlay"></div>
                <div class="ontime-modal-content">
                    <div class="ontime-modal-header">
                        <svg class="ontime-success-icon" xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                    </div>
                    <h2 class="ontime-modal-title"><?php esc_html_e('نوبت شما با موفقیت رزرو شد!', 'ontime'); ?></h2>
                    <div class="ontime-modal-body">
                        <p class="ontime-success-message" id="ontime-success-msg-<?php echo esc_attr($form_id); ?>"></p>
                        <div class="ontime-appointment-summary" id="ontime-success-summary-<?php echo esc_attr($form_id); ?>"></div>
                    </div>
                    <div class="ontime-modal-footer">
                        <button type="button" class="ontime-btn ontime-btn-primary ontime-modal-close" data-modal="<?php echo esc_attr($form_id); ?>">
                            <?php esc_html_e('بستن', 'ontime'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <?php
        return ob_get_clean();
    }

    // ========================================================================
    // AJAX HANDLERS
    // ========================================================================

    /**
     * Get services (for dynamic loading)
     */
    public function ajax_get_services()
    {
        check_ajax_referer('ontime_booking_nonce', 'nonce');

        $services = $this->db->get_services(true);
        
        $result = [];
        foreach ($services as $service) {
            $result[] = [
                'id' => $service['id'],
                'name' => html_entity_decode($service['name'], ENT_QUOTES, 'UTF-8'),
                'duration' => $service['duration_minutes'],
                'price' => $service['price'],
                'description' => !empty($service['description']) ? html_entity_decode($service['description'], ENT_QUOTES, 'UTF-8') : '',
            ];
        }

        wp_send_json_success(['services' => $result]);
    }

    /**
     * Get staff members (for dynamic loading)
     */
    public function ajax_get_staff()
    {
        check_ajax_referer('ontime_booking_nonce', 'nonce');

        $service_id = isset($_POST['service_id']) ? absint($_POST['service_id']) : 0;
        $staff_members = $this->db->get_staff_members(true);
        
        $result = [];
        foreach ($staff_members as $staff) {
            $result[] = [
                'id' => $staff['id'],
                'display_name' => html_entity_decode($staff['display_name'], ENT_QUOTES, 'UTF-8'),
                'bio' => !empty($staff['bio']) ? wp_strip_all_tags($staff['bio']) : '',
                'avatar' => !empty($staff['user_id']) ? get_avatar_url($staff['user_id'], ['size' => 150]) : '',
                'available' => true, // Could check availability based on service
            ];
        }

        wp_send_json_success(['staff' => $result]);
    }

    /**
     * Get available dates for a service and staff combination
     */
    public function ajax_get_available_dates()
    {
        check_ajax_referer('ontime_booking_nonce', 'nonce');

        $service_id = isset($_POST['service_id']) ? absint($_POST['service_id']) : 0;
        $staff_id = isset($_POST['staff_id']) ? absint($_POST['staff_id']) : 0;

        if (empty($service_id) || empty($staff_id)) {
            wp_send_json_error(['message' => __(' service and staff are required', 'ontime')]);
        }

        // Get service duration
        $service = $this->db->get_service($service_id);
        if (empty($service)) {
            wp_send_json_error(['message' => __('Service not found', 'ontime')]);
        }

        $duration = $service['duration_minutes'];
        
        // Get current date and calculate available dates (next 30 days)
        $current_date = $this->calendar->get_current_jalali_date();
        $dates = [];
        
        for ($i = 0; $i < 30; $i++) {
            // Parse current date
            $parts = explode('/', $current_date);
            $year = absint($parts[0]);
            $month = absint($parts[1]);
            $day = absint($parts[2]);
            
            // Add days (simple Jalali date addition - could be improved)
            $new_day = $day + $i;
            $new_month = $month;
            $new_year = $year;
            
            // Handle month overflow
            $jalali_days_in_month = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];
            if ($this->calendar->is_jalali_leap_year($new_year)) {
                $jalali_days_in_month[11] = 30; // Esfand has 30 days in leap year
            }
            
            while ($new_day > $jalali_days_in_month[$new_month - 1]) {
                $new_day -= $jalali_days_in_month[$new_month - 1];
                $new_month++;
                if ($new_month > 12) {
                    $new_month = 1;
                    $new_year++;
                }
            }
            
            $date = sprintf('%04d/%02d/%02d', $new_year, $new_month, $new_day);
            
            // Check if staff works on this day
            $working_hours = $this->calendar->get_staff_working_hours($staff_id, $date);
            $has_slots = !empty($working_hours);
            
            // Check if it's a holiday
            $is_holiday = $this->calendar->is_holiday($date);
            $holiday_name = $is_holiday ? $this->calendar->get_holiday_name($date) : '';
            
            $dates[] = [
                'date' => $date,
                'formatted' => $this->format_jalali_date($date),
                'available' => $has_slots && !$is_holiday,
                'is_holiday' => $is_holiday,
                'holiday_name' => $holiday_name,
                'day_of_week' => $this->calendar->get_jalali_day_of_week($new_year, $new_month, $new_day),
            ];
        }

        wp_send_json_success(['dates' => $dates]);
    }

    /**
     * Get available time slots for a specific date, service, and staff
     */
    public function ajax_get_available_slots()
    {
        check_ajax_referer('ontime_booking_nonce', 'nonce');

        $service_id = isset($_POST['service_id']) ? absint($_POST['service_id']) : 0;
        $staff_id = isset($_POST['staff_id']) ? absint($_POST['staff_id']) : 0;
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';

        if (empty($service_id) || empty($staff_id) || empty($date)) {
            wp_send_json_error(['message' => __('Service, staff, and date are required', 'ontime')]);
        }

        // Get service duration
        $service = $this->db->get_service($service_id);
        if (empty($service)) {
            wp_send_json_error(['message' => __('Service not found', 'ontime')]);
        }

        $duration = $service['duration_minutes'];
        
        // Check if it's a holiday
        if ($this->calendar->is_holiday($date)) {
            $holiday_name = $this->calendar->get_holiday_name($date);
            wp_send_json_error([
                'message' => sprintf(__('تاریخ انتخاب شده تعطیل است: %s', 'ontime'), $holiday_name)
            ]);
        }

        // Check if staff works on this day
        $working_hours = $this->calendar->get_staff_working_hours($staff_id, $date);
        if (empty($working_hours)) {
            wp_send_json_error(['message' => __('This staff member is not available on the selected date', 'ontime')]);
        }

        // Get available slots
        $slots = $this->calendar->get_available_slots($staff_id, $date, $duration);
        
        // Format slots for output
        $formatted_slots = [];
        foreach ($slots as $slot) {
            $formatted_slots[] = [
                'time' => $slot['start'],
                'end_time' => $slot['end'],
                'formatted' => $slot['formatted'],
                'timestamp' => $slot['start_timestamp'],
                'datetime' => $slot['datetime_start'],
            ];
        }

        wp_send_json_success(['slots' => $formatted_slots]);
    }

    /**
     * Validate customer information
     */
    public function ajax_validate_customer_info()
    {
        check_ajax_referer('ontime_booking_nonce', 'nonce');

        $customer_name = isset($_POST['customer_name']) ? sanitize_text_field($_POST['customer_name']) : '';
        $customer_phone = isset($_POST['customer_phone']) ? sanitize_text_field($_POST['customer_phone']) : '';
        $customer_email = isset($_POST['customer_email']) ? sanitize_email($_POST['customer_email']) : '';

        $errors = [];
        
        if (empty($customer_name)) {
            $errors['customer_name'] = __('نام و نام خانوادگی الزامی است', 'ontime');
        }
        
        if (empty($customer_phone)) {
            $errors['customer_phone'] = __('تلفن الزامی است', 'ontime');
        } elseif (!preg_match('/^09[0-9]{9}$/', $customer_phone)) {
            $errors['customer_phone'] = __('فرمت تلفن معتبر نیست', 'ontime');
        }
        
        if (!empty($customer_email) && !is_email($customer_email)) {
            $errors['customer_email'] = __('آدرس ایمیل معتبر نیست', 'ontime');
        }

        if (!empty($errors)) {
            wp_send_json_error(['errors' => $errors]);
        }

        wp_send_json_success(['valid' => true]);
    }

    /**
     * Confirm and create the booking
     */
    public function ajax_confirm_booking()
    {
        check_ajax_referer('ontime_booking_nonce', 'nonce');
        
        // Basic rate limiting - check for recent submissions
        $this->check_rate_limit();

        // Sanitize all inputs
        $service_id = isset($_POST['service_id']) ? absint($_POST['service_id']) : 0;
        $staff_id = isset($_POST['staff_id']) ? absint($_POST['staff_id']) : 0;
        $appointment_date = isset($_POST['appointment_date']) ? sanitize_text_field($_POST['appointment_date']) : '';
        $appointment_time = isset($_POST['appointment_time']) ? sanitize_text_field($_POST['appointment_time']) : '';
        $service_price = isset($_POST['service_price']) ? absint($_POST['service_price']) : 0;
        $service_duration = isset($_POST['service_duration']) ? absint($_POST['service_duration']) : 30;
        $customer_name = isset($_POST['customer_name']) ? sanitize_text_field($_POST['customer_name']) : '';
        $customer_phone = isset($_POST['customer_phone']) ? sanitize_text_field($_POST['customer_phone']) : '';
        $customer_email = isset($_POST['customer_email']) ? sanitize_email($_POST['customer_email']) : '';
        $customer_notes = isset($_POST['customer_notes']) ? sanitize_textarea_field($_POST['customer_notes']) : '';
        $accept_terms = isset($_POST['accept_terms']) ? (bool)$_POST['accept_terms'] : false;

        // Validate required fields
        if (empty($service_id) || empty($staff_id) || empty($appointment_date) || empty($appointment_time)) {
            wp_send_json_error(['message' => __('All fields are required', 'ontime')]);
        }

        if (empty($customer_name)) {
            wp_send_json_error(['message' => __('Name is required', 'ontime')]);
        }

        if (empty($customer_phone)) {
            wp_send_json_error(['message' => __('Phone is required', 'ontime')]);
        }

        if (!$accept_terms) {
            wp_send_json_error(['message' => __('You must accept the terms', 'ontime')]);
        }

        // Get service info
        $service = $this->db->get_service($service_id);
        if (empty($service)) {
            wp_send_json_error(['message' => __('Service not found', 'ontime')]);
        }

        // Parse time
        $time_parts = explode(':', $appointment_time);
        $hour = isset($time_parts[0]) ? absint($time_parts[0]) : 0;
        $minute = isset($time_parts[1]) ? absint($time_parts[1]) : 0;

        // Convert Jalali date to Gregorian
        $gregorian_date = $this->calendar->jalali_to_gregorian($appointment_date);
        if (empty($gregorian_date)) {
            wp_send_json_error(['message' => __('Invalid date format', 'ontime')]);
        }

        $start_datetime = sprintf('%s %02d:%02d:00', $gregorian_date, $hour, $minute);
        $end_datetime = date('Y-m-d H:i:s', strtotime($start_datetime . ' UTC') + ($service_duration * 60));

        // Check if slot is still available (race condition protection)
        $available_slots = $this->calendar->get_available_slots($staff_id, $appointment_date, $service_duration);
        $slot_available = false;
        foreach ($available_slots as $slot) {
            if ($slot['start'] === $appointment_time) {
                $slot_available = true;
                break;
            }
        }

        if (!$slot_available) {
            wp_send_json_error(['message' => __('Selected time slot is no longer available', 'ontime')]);
        }

        // Insert appointment
        $appointment_data = [
            'customer_name' => $customer_name,
            'customer_phone' => $customer_phone,
            'customer_email' => $customer_email,
            'service_id' => $service_id,
            'staff_id' => $staff_id,
            'start_datetime' => $start_datetime,
            'end_datetime' => $end_datetime,
            'status' => 'pending',
            'total_price' => $service_price,
            'notes' => $customer_notes,
        ];

        $appointment_id = $this->db->insert_appointment($appointment_data);

        if (!$appointment_id) {
            wp_send_json_error(['message' => __('Failed to create appointment', 'ontime')]);
        }

        // Format appointment details for response
        $appointment_details = [
            'id' => $appointment_id,
            'service_name' => html_entity_decode($service['name'], ENT_QUOTES, 'UTF-8'),
            'staff_name' => $this->get_staff_name($staff_id),
            'date' => $appointment_date,
            'time' => $appointment_time,
            'duration' => $service_duration,
            'price' => $service_price,
            'customer_name' => $customer_name,
            'customer_phone' => $customer_phone,
            'customer_email' => $customer_email,
        ];

        // Send confirmation email if configured (hook for future implementation)
        do_action('ontime_appointment_confirmed', $appointment_id, $appointment_data);

        wp_send_json_success([
            'message' => __('Appointment successfully booked', 'ontime'),
            'appointment' => $appointment_details
        ]);
    }

    /**
     * Get staff display name by ID
     * 
     * @param int $staff_id Staff ID
     * @return string Staff display name
     */
    private function get_staff_name($staff_id)
    {
        $staff = $this->db->get_staff($staff_id);
        return $staff ? html_entity_decode($staff['display_name'], ENT_QUOTES, 'UTF-8') : __('Unknown', 'ontime');
    }

    /**
     * Format Jalali date for display
     * 
     * @param string $date Jalali date (YYYY/MM/DD)
     * @return string Formatted date
     */
    private function format_jalali_date($date)
    {
        $parts = explode('/', $date);
        if (count($parts) !== 3) {
            return $date;
        }

        $year = absint($parts[0]);
        $month = absint($parts[1]);
        $day = absint($parts[2]);

        $jalali_months = [
            1 => 'فروردین',
            2 => 'اردیبهشت',
            3 => 'خرداد',
            4 => 'تیر',
            5 => 'مرداد',
            6 => 'شهریور',
            7 => 'مهر',
            8 => 'آبان',
            9 => 'آذر',
            10 => 'دی',
            11 => 'بهمن',
            12 => 'اسفند',
        ];

        $month_name = $jalali_months[$month] ?? '';
        $day_name = $this->calendar->get_jalali_day_of_week($year, $month, $day);

        return sprintf('%s %d %s %d', $day_name, $day, $month_name, $year);
    }
}
