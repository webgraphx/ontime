<?php
/**
 * OnTime Appointments List Table
 * 
 * WP_List_Table implementation for displaying appointments
 * 
 * @package OnTime
 * @subpackage Admin
 * @since 1.0.0
 */

namespace OnTime\Admin;

use OnTime\Database\Database;
use OnTime\Calendar\Calendar_Engine;

/**
 * Appointments list table class
 * 
 * Extends WP_List_Table to display appointments with custom columns and actions
 */
class Appointments_List_Table extends \WP_List_Table
{
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
     * Constructor
     */
    public function __construct()
    {
        parent::__construct([
            'singular' => 'appointment',
            'plural' => 'appointments',
            'ajax' => false,
        ]);

        $this->db = Database::get_instance();
        $this->calendar = Calendar_Engine::get_instance();
    }

    /**
     * Prepare items for display
     */
    public function prepare_items()
    {
        // Check capability
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('شما اجازه دسترسی به این صفحه را ندارید.', 'ontime'));
        }

        // Get filter parameters
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $service_filter = isset($_GET['service_id']) ? absint($_GET['service_id']) : 0;
        $staff_filter = isset($_GET['staff_id']) ? absint($_GET['staff_id']) : 0;
        $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
        $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

        // Get current page
        $page = $this->get_pagenum();
        $per_page = $this->get_items_per_page('appointments_per_page', 20);

        // Build filter arguments
        $filter_args = [];

        if (!empty($status_filter)) {
            $filter_args['status'] = $status_filter;
        }

        if (!empty($service_filter)) {
            $filter_args['service_id'] = $service_filter;
        }

        if (!empty($staff_filter)) {
            $filter_args['staff_id'] = $staff_filter;
        }

        // Convert Jalali dates to Gregorian for database query
        if (!empty($date_from)) {
            $filter_args['date_from'] = $this->calendar->jalali_to_gregorian($date_from) . ' 00:00:00';
        }

        if (!empty($date_to)) {
            $filter_args['date_to'] = $this->calendar->jalali_to_gregorian($date_to) . ' 23:59:59';
        }

        // Get appointments
        $args = array_merge($filter_args, [
            'limit' => $per_page,
            'offset' => ($page - 1) * $per_page,
            'orderby' => isset($_GET['orderby']) ? sanitize_sql_orderby($_GET['orderby']) : 'start_datetime',
            'order' => isset($_GET['order']) ? strtoupper($_GET['order']) : 'DESC',
        ]);

        $appointments = $this->db->get_appointments($args);

        // Get total count for pagination
        $total_items = $this->db->count_appointments($filter_args);

        // Set pagination
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ]);

        // Process items for display
        $this->items = $this->prepare_items_for_display($appointments);

        // Set column headers
        $this->set_column_headers();
    }

    /**
     * Prepare items for display
     * 
     * @param array $appointments Raw appointment data
     * @return array Prepared items
     */
    private function prepare_items_for_display($appointments)
    {
        $prepared = [];
        
        foreach ($appointments as $appointment) {
            $item = $appointment;
            
            // Get service info
            $service = $this->db->get_service($appointment['service_id']);
            $item['service_name'] = $service ? $service['name'] : __('حذف شده', 'ontime');
            $item['service_price'] = $service ? $service['price'] : 0;

            // Get staff info
            $staff = $this->db->get_staff($appointment['staff_id']);
            $item['staff_name'] = $staff ? $staff['display_name'] : __('حذف شده', 'ontime');

            // Convert UTC datetime to Jalali for display
            $item['jalali_date'] = $this->calendar->timestamp_to_jalali(strtotime($appointment['start_datetime']));
            $item['jalali_datetime'] = $this->calendar->timestamp_to_jalali_datetime(strtotime($appointment['start_datetime']));
            $item['formatted_time'] = date_i18n('H:i', strtotime($appointment['start_datetime']));

            // Format price
            $item['formatted_price'] = number_format($appointment['total_price']) . ' ' . __('تومان', 'ontime');

            // Status badge
            $item['status_badge'] = $this->get_status_badge($appointment['status']);

            // Created date in Jalali
            $item['jalali_created'] = $this->calendar->timestamp_to_jalali(strtotime($appointment['created_at']));

            $prepared[] = $item;
        }

        return $prepared;
    }

    /**
     * Set column headers
     */
    private function set_column_headers()
    {
        $columns = $this->get_columns();
        $hidden = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = [$columns, $hidden, $sortable];
    }

    /**
     * Get columns
     * 
     * @return array Column definitions
     */
    public function get_columns()
    {
        return [
            'cb' => '<input type="checkbox" />',
            'id' => __('شناسه', 'ontime'),
            'customer_name' => __('مشتری', 'ontime'),
            'service' => __('سرویس', 'ontime'),
            'staff' => __('پرسنل', 'ontime'),
            'datetime' => __('تاریخ/ساعت', 'ontime'),
            'status' => __('وضعیت', 'ontime'),
            'price' => __('قیمت', 'ontime'),
            'created_at' => __('تاریخ ایجاد', 'ontime'),
        ];
    }

    /**
     * Get hidden columns
     * 
     * @return array Hidden column names
     */
    public function get_hidden_columns()
    {
        return [];
    }

    /**
     * Get sortable columns
     * 
     * @return array Sortable columns
     */
    public function get_sortable_columns()
    {
        return [
            'id' => ['id', false],
            'customer_name' => ['customer_name', false],
            'start_datetime' => ['start_datetime', false],
            'status' => ['status', false],
            'created_at' => ['created_at', false],
        ];
    }

    /**
     * Get bulk actions
     * 
     * @return array Bulk actions
     */
    public function get_bulk_actions()
    {
        return [
            'confirm' => __('تایید', 'ontime'),
            'cancel' => __('لغو', 'ontime'),
            'complete' => __('تکمیل', 'ontime'),
            'delete' => __('حذف', 'ontime'),
        ];
    }

    /**
     * Process bulk action
     */
    public function process_bulk_action()
    {
        // Check capability
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('شما اجازه انجام این عمل را ندارید.', 'ontime'));
        }

        // Check nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'bulk-' . $this->_args['plural'])) {
            return;
        }

        // Check action
        $action = $this->current_action();
        
        if (!in_array($action, ['confirm', 'cancel', 'complete', 'delete'])) {
            return;
        }

        // Check if items are selected
        if (empty($_POST['appointment_ids'])) {
            return;
        }

        // Sanitize IDs
        $appointment_ids = array_map('absint', $_POST['appointment_ids']);

        // Process each appointment
        foreach ($appointment_ids as $id) {
            $this->process_single_appointment_action($id, $action);
        }

        // Redirect to prevent form resubmission
        $redirect = add_query_arg([
            'page' => 'ontime-appointments',
            'action' => $action . 'd',
            'count' => count($appointment_ids),
        ], admin_url('admin.php'));

        wp_redirect($redirect);
        exit;
    }

    /**
     * Process single appointment action
     * 
     * @param int $id Appointment ID
     * @param string $action Action to perform
     */
    private function process_single_appointment_action($id, $action)
    {
        $valid_actions = ['confirm', 'cancel', 'complete', 'delete'];
        
        if (!in_array($action, $valid_actions)) {
            return;
        }

        switch ($action) {
            case 'confirm':
                $this->db->update_appointment($id, ['status' => 'confirmed']);
                break;

            case 'cancel':
                $this->db->update_appointment($id, ['status' => 'cancelled']);
                break;

            case 'complete':
                $this->db->update_appointment($id, ['status' => 'completed']);
                break;

            case 'delete':
                $this->db->delete_appointment($id);
                break;
        }
    }

    /**
     * Get status badge HTML
     * 
     * @param string $status Status
     * @return string HTML badge
     */
    private function get_status_badge($status)
    {
        $statuses = [
            'pending' => ['label' => __('در انتظار', 'ontime'), 'class' => 'ontime-status-pending'],
            'confirmed' => ['label' => __('تایید شده', 'ontime'), 'class' => 'ontime-status-confirmed'],
            'cancelled' => ['label' => __('لغو شده', 'ontime'), 'class' => 'ontime-status-cancelled'],
            'completed' => ['label' => __('اتمام یافته', 'ontime'), 'class' => 'ontime-status-completed'],
        ];

        $status_data = $statuses[$status] ?? ['label' => $status, 'class' => 'ontime-status-pending'];

        return '<span class="ontime-status ' . esc_attr($status_data['class']) . '">' . esc_html($status_data['label']) . '</span>';
    }

    /**
     * Display checkbox column
     * 
     * @param array $item Item data
     * @return string Column HTML
     */
    public function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="appointment_ids[]" value="%s" />',
            esc_attr($item['id'])
        );
    }

    /**
     * Display ID column
     * 
     * @param array $item Item data
     * @return string Column HTML
     */
    public function column_id($item)
    {
        $actions = [
            'view' => sprintf(
                '<a href="?page=ontime-appointments&action=view&id=%d">%s</a>',
                absint($item['id']),
                __('مشاهده', 'ontime')
            ),
            'edit' => sprintf(
                '<a href="?page=ontime-appointments&action=edit&id=%d">%s</a>',
                absint($item['id']),
                __('ویرایش', 'ontime')
            ),
        ];

        return sprintf(
            '%d %s',
            esc_html($item['id']),
            $this->row_actions($actions)
        );
    }

    /**
     * Display customer name column
     * 
     * @param array $item Item data
     * @return string Column HTML
     */
    public function column_customer_name($item)
    {
        $output = '<strong>' . esc_html($item['customer_name']) . '</strong>';
        
        if (!empty($item['customer_phone'])) {
            $output .= '<br><small>' . esc_html__('تلفن:', 'ontime') . ' ' . esc_html($item['customer_phone']) . '</small>';
        }
        
        if (!empty($item['customer_email'])) {
            $output .= '<br><small>' . esc_html__('ایمیل:', 'ontime') . ' ' . esc_html($item['customer_email']) . '</small>';
        }

        return $output;
    }

    /**
     * Display service column
     * 
     * @param array $item Item data
     * @return string Column HTML
     */
    public function column_service($item)
    {
        return esc_html($item['service_name']);
    }

    /**
     * Display staff column
     * 
     * @param array $item Item data
     * @return string Column HTML
     */
    public function column_staff($item)
    {
        return esc_html($item['staff_name']);
    }

    /**
     * Display datetime column
     * 
     * @param array $item Item data
     * @return string Column HTML
     */
    public function column_datetime($item)
    {
        return sprintf(
            '%s<br><small>%s</small>',
            esc_html($item['jalali_date']),
            esc_html($item['formatted_time'])
        );
    }

    /**
     * Display status column
     * 
     * @param array $item Item data
     * @return string Column HTML
     */
    public function column_status($item)
    {
        return $item['status_badge'];
    }

    /**
     * Display price column
     * 
     * @param array $item Item data
     * @return string Column HTML
     */
    public function column_price($item)
    {
        return esc_html($item['formatted_price']);
    }

    /**
     * Display created_at column
     * 
     * @param array $item Item data
     * @return string Column HTML
     */
    public function column_created_at($item)
    {
        return esc_html($item['jalali_created']);
    }

    /**
     * Extra table navigation (filters)
     * 
     * @param string $which Position (top or bottom)
     */
    public function extra_tablenav($which)
    {
        if ($which !== 'top') {
            return;
        }

        // Get filter options
        $services = $this->db->get_services(false);
        $staff = $this->db->get_staff_members(false);

        // Current filter values
        $status_filter = isset($_GET['status']) ? $_GET['status'] : '';
        $service_filter = isset($_GET['service_id']) ? $_GET['service_id'] : 0;
        $staff_filter = isset($_GET['staff_id']) ? $_GET['staff_id'] : 0;
        $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
        $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

        echo '<div class="alignleft actions ontime-filters">';

        // Status filter
        echo '<select name="status" id="ontime-filter-status">';
        echo '<option value="">' . esc_html__('همه وضعیت‌ها', 'ontime') . '</option>';
        echo '<option value="pending" ' . selected($status_filter, 'pending', false) . '>' . esc_html__('در انتظار', 'ontime') . '</option>';
        echo '<option value="confirmed" ' . selected($status_filter, 'confirmed', false) . '>' . esc_html__('تایید شده', 'ontime') . '</option>';
        echo '<option value="cancelled" ' . selected($status_filter, 'cancelled', false) . '>' . esc_html__('لغو شده', 'ontime') . '</option>';
        echo '<option value="completed" ' . selected($status_filter, 'completed', false) . '>' . esc_html__('اتمام یافته', 'ontime') . '</option>';
        echo '</select>';

        // Service filter
        echo '<select name="service_id" id="ontime-filter-service">';
        echo '<option value="">' . esc_html__('همه خدمات', 'ontime') . '</option>';
        foreach ($services as $service) {
            echo '<option value="' . esc_attr($service['id']) . '" ' . selected($service_filter, $service['id'], false) . '>' . esc_html($service['name']) . '</option>';
        }
        echo '</select>';

        // Staff filter
        echo '<select name="staff_id" id="ontime-filter-staff">';
        echo '<option value="">' . esc_html__('همه پرسنل', 'ontime') . '</option>';
        foreach ($staff as $member) {
            echo '<option value="' . esc_attr($member['id']) . '" ' . selected($staff_filter, $member['id'], false) . '>' . esc_html($member['display_name']) . '</option>';
        }
        echo '</select>';

        // Date range filter
        echo '<input type="text" name="date_from" id="ontime-filter-date-from" class="ontime-datepicker" value="' . esc_attr($date_from) . '" placeholder="' . esc_attr__('از تاریخ', 'ontime') . '">';
        echo '<input type="text" name="date_to" id="ontime-filter-date-to" class="ontime-datepicker" value="' . esc_attr($date_to) . '" placeholder="' . esc_attr__('تا تاریخ', 'ontime') . '">';

        // Submit button
        submit_button(__('فیلتر', 'ontime'), 'secondary', 'filter', false);

        // Clear filters link
        $clear_url = add_query_arg([
            'page' => 'ontime-appointments',
            'paged' => false,
            'status' => false,
            'service_id' => false,
            'staff_id' => false,
            'date_from' => false,
            'date_to' => false,
            's' => false,
        ], admin_url('admin.php'));

        echo '<a href="' . esc_url($clear_url) . '" class="button">' . esc_html__('پاک کردن فیلترها', 'ontime') . '</a>';

        echo '</div>';
    }

    /**
     * Get default primary column
     * 
     * @return string Column name
     */
    protected function get_default_primary_column_name()
    {
        return 'customer_name';
    }

    /**
     * Display no items message
     */
    public function no_items()
    {
        echo '<tr><td colspan="8">' . esc_html__('هیچ نوبتی یافت نشد.', 'ontime') . '</td></tr>';
    }
}
