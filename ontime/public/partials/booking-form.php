<?php
/**
 * OnTime Booking Form Partial
 * 
 * The main booking form partial (can be used for custom templates)
 * 
 * @package OnTime
 */

if (!defined('ABSPATH')) {
    exit;
}

$db = OnTime\Database\Database::get_instance();
$services = $db->get_services(true);
$staff = $db->get_staff_members(true);

if (empty($services)) {
    echo '<p class="ontime-notice ontime-notice-error">' . esc_html__('هیچ سرویسی یافت نشد. لطفا ابتدا خدمات را تعریف کنید.', 'ontime') . '</p>';
    return;
}

?>
<div class="ontime-booking-form-wrapper">
    <form id="ontime-booking-form" class="ontime-booking-form" method="post">
        <!-- Customer Information -->
        <div class="ontime-form-section">
            <h4><?php esc_html_e('اطلاعات مشتری', 'ontime'); ?></h4>
            
            <div class="ontime-form-group">
                <label for="ontime_customer_name">
                    <?php esc_html_e('نام و نام خانوادگی', 'ontime'); ?>
                    <span class="required">*</span>
                </label>
                <input type="text" id="ontime_customer_name" name="customer_name" required 
                       placeholder="<?php esc_attr_e('مثال: علی محمدی', 'ontime'); ?>">
            </div>
            
            <div class="ontime-form-group">
                <label for="ontime_customer_phone"><?php esc_html_e('تلفن همراه', 'ontime'); ?></label>
                <input type="tel" id="ontime_customer_phone" name="customer_phone" 
                       placeholder="<?php esc_attr_e('مثال: 09123456789', 'ontime'); ?>">
            </div>
            
            <div class="ontime-form-group">
                <label for="ontime_customer_email"><?php esc_html_e('آدرس ایمیل', 'ontime'); ?></label>
                <input type="email" id="ontime_customer_email" name="customer_email" 
                       placeholder="<?php esc_attr_e('مثال: email@example.com', 'ontime'); ?>">
            </div>
        </div>

        <!-- Appointment Details -->
        <div class="ontime-form-section">
            <h4><?php esc_html_e('جزئیات نوبت', 'ontime'); ?></h4>
            
            <div class="ontime-form-group">
                <label for="ontime_service_id">
                    <?php esc_html_e('نوع سرویس', 'ontime'); ?>
                    <span class="required">*</span>
                </label>
                <select id="ontime_service_id" name="service_id" required>
                    <option value=""><?php esc_html_e('انتخاب کنید...', 'ontime'); ?></option>
                    <?php foreach ($services as $service) : ?>
                        <option value="<?php echo esc_attr($service['id']); ?>" 
                                data-price="<?php echo esc_attr($service['price']); ?>"
                                data-duration="<?php echo esc_attr($service['duration_minutes']); ?>">
                            <?php echo esc_html($service['name']); ?> 
                            (<?php echo esc_html(number_format($service['price'])); ?> <?php esc_html_e('تومان', 'ontime'); ?> - 
                            <?php echo esc_html($service['duration_minutes']); ?> <?php esc_html_e('دقیقه', 'ontime'); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="ontime-form-group">
                <label for="ontime_staff_id">
                    <?php esc_html_e('پرسنل', 'ontime'); ?>
                    <span class="required">*</span>
                </label>
                <select id="ontime_staff_id" name="staff_id" required>
                    <option value=""><?php esc_html_e('انتخاب کنید...', 'ontime'); ?></option>
                    <?php foreach ($staff as $member) : ?>
                        <option value="<?php echo esc_attr($member['id']); ?>">
                            <?php echo esc_html($member['display_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="ontime-form-group">
                <label for="ontime_date">
                    <?php esc_html_e('تاریخ', 'ontime'); ?>
                    <span class="required">*</span>
                </label>
                <input type="text" id="ontime_date" name="date" class="ontime-datepicker" 
                       placeholder="<?php esc_attr_e('مثال: 1403/05/15', 'ontime'); ?>" required>
            </div>
            
            <div class="ontime-form-group">
                <label><?php esc_html_e('ساعت آزاد', 'ontime'); ?></label>
                <div id="ontime_slots_container">
                    <p class="ontime-hint"><?php esc_html_e('ابتدا سرویس، پرسنل و تاریخ را انتخاب کنید', 'ontime'); ?></p>
                </div>
            </div>
        </div>

        <!-- Summary -->
        <div class="ontime-form-section ontime-summary">
            <h4><?php esc_html_e('خلاصه نوبت', 'ontime'); ?></h4>
            <div id="ontime-summary-content">
                <p><?php esc_html_e('اطلاعات نوبت در اینجا نمایش داده خواهد شد', 'ontime'); ?></p>
            </div>
        </div>

        <!-- Submit Button -->
        <button type="submit" class="ontime-btn ontime-btn-primary ontime-btn-large">
            <?php esc_html_e('رزرو نوبت', 'ontime'); ?>
        </button>
        
        <!-- Hidden fields -->
        <input type="hidden" name="action" value="ontime_create_appointment">
        <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('ontime_public_nonce')); ?>">
    </form>
    
    <!-- Success message container -->
    <div id="ontime-success-message" class="ontime-notice ontime-notice-success" style="display: none;"></div>
</div>

<style>
.ontime-form-section { margin: 25px 0; padding: 20px; background: #f9f9f9; border-radius: 8px; }
.ontime-form-section h4 { margin: 0 0 15px 0; color: #1d2327; }
.required { color: #cc1818; }
.ontime-hint { color: #8c8f94; font-style: italic; }
.ontime-summary { background: #e9f3fc; }
.ontime-btn-large { padding: 14px 28px; font-size: 16px; }
</style>
