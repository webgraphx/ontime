<?php
/**
 * OnTime Database Handler
 * 
 * Main database class using Singleton pattern with prepared statements only
 * 
 * @package OnTime
 * @subpackage Database
 * @since 1.0.0
 */

namespace OnTime\Database;

use wpdb;

/**
 * Database class for OnTime plugin
 * 
 * Handles all database operations with prepared statements for security
 */
final class Database
{
    /**
     * Singleton instance
     * @var Database|null
     */
    private static $instance = null;

    /**
     * WordPress database object
     * @var wpdb
     */
    private $wpdb;

    /**
     * Table names
     * @var array
     */
    private $tables = [];

    /**
     * Cache for frequently accessed data
     * @var array
     */
    private $cache = [];

    /**
     * Constructor - Private for Singleton pattern
     * 
     * @param wpdb|null $wpdb WordPress database object
     */
    private function __construct($wpdb = null)
    {
        global $wpdb;
        
        $this->wpdb = $wpdb ?: $wpdb;
        $this->init_table_names();
    }

    /**
     * Initialize table names
     */
    private function init_table_names()
    {
        $prefix = $this->wpdb->prefix . 'ontime_';
        
        $this->tables = [
            'appointments' => $prefix . 'appointments',
            'services' => $prefix . 'services',
            'staff' => $prefix . 'staff',
        ];
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
     * @return Database
     */
    public static function get_instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Log database errors
     * 
     * @param string $message Error message
     * @param array $context Additional context
     */
    private function log_error($message, $context = [])
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[OnTime DB Error] ' . $message);
            if (!empty($context)) {
                error_log('[OnTime Context] ' . print_r($context, true));
            }
        }
    }

    /**
     * Clear internal cache
     * 
     * @param string|null $key Specific key to clear, or null for all
     */
    public function clear_cache($key = null)
    {
        if ($key === null) {
            $this->cache = [];
        } elseif (isset($this->cache[$key])) {
            unset($this->cache[$key]);
        }
    }

    /**
     * Get table name by key
     * 
     * @param string $key Table key (appointments, services, staff)
     * @return string Full table name with prefix
     */
    public function get_table($key)
    {
        return $this->tables[$key] ?? '';
    }

    /**
     * Create all plugin tables using dbDelta
     * 
     * @return bool True if tables created successfully
     */
    public function create_tables()
    {
        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $sql_statements = Schema::get_all_tables_sql();
        
        foreach ($sql_statements as $sql) {
            // dbDelta returns an array with queries and errors
            // We just need to execute it
            dbDelta($sql);
        }

        // Update option to track plugin version
        update_option('ontime_db_version', ONTIME_VERSION);
        
        return true;
    }

    /**
     * Drop all plugin tables
     * 
     * @return bool True if tables dropped successfully
     */
    public function drop_tables()
    {
        foreach ($this->tables as $table) {
            // Sanitize table name to prevent SQL injection
            $safe_table = $this->wpdb->_escape($table);
            $this->wpdb->query($this->wpdb->prepare("DROP TABLE IF EXISTS %s", $safe_table));
        }
        
        delete_option('ontime_db_version');
        
        return true;
    }

    /**
     * Check if table exists
     * 
     * @param string $key Table key
     * @return bool
     */
    public function table_exists($key)
    {
        $table = $this->get_table($key);
        if (empty($table)) {
            return false;
        }
        
        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table
            )
        );
        
        return !empty($result);
    }

    // ========================================================================
    // APPOINTMENTS TABLE METHODS
    // ========================================================================

    /**
     * Insert new appointment
     * 
     * @param array $data Appointment data
     * @return int|false Inserted ID or false on error
     */
    public function insert_appointment($data)
    {
        $defaults = [
            'customer_name' => '',
            'customer_phone' => null,
            'customer_email' => null,
            'service_id' => 0,
            'staff_id' => 0,
            'start_datetime' => current_time('mysql'),
            'end_datetime' => current_time('mysql'),
            'status' => 'pending',
            'total_price' => 0.00,
            'transaction_id' => null,
        ];
        
        $data = wp_parse_args($data, $defaults);
        
        // Validate required fields
        if (empty($data['customer_name']) || empty($data['service_id']) || empty($data['staff_id'])) {
            return false;
        }

        $result = $this->wpdb->insert(
            $this->tables['appointments'],
            [
                'customer_name' => sanitize_text_field($data['customer_name']),
                'customer_phone' => $data['customer_phone'] ? sanitize_text_field($data['customer_phone']) : null,
                'customer_email' => $data['customer_email'] ? sanitize_email($data['customer_email']) : null,
                'service_id' => absint($data['service_id']),
                'staff_id' => absint($data['staff_id']),
                'start_datetime' => sanitize_text_field($data['start_datetime']),
                'end_datetime' => sanitize_text_field($data['end_datetime']),
                'status' => in_array($data['status'], ['pending', 'confirmed', 'cancelled', 'completed']) ? $data['status'] : 'pending',
                'total_price' => number_format((float) $data['total_price'], 2, '.', ''),
                'transaction_id' => $data['transaction_id'] ? sanitize_text_field($data['transaction_id']) : null,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Update appointment
     * 
     * @param int $id Appointment ID
     * @param array $data Data to update
     * @return bool True on success
     */
    public function update_appointment($id, $data)
    {
        $id = absint($id);
        
        if (empty($id)) {
            return false;
        }

        $update_data = [];
        $format = [];

        $allowed_fields = [
            'customer_name', 'customer_phone', 'customer_email',
            'service_id', 'staff_id', 'start_datetime', 'end_datetime',
            'status', 'total_price', 'transaction_id'
        ];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowed_fields)) {
                switch ($key) {
                    case 'customer_name':
                        $update_data[$key] = sanitize_text_field($value);
                        $format[] = '%s';
                        break;
                    case 'customer_phone':
                        $update_data[$key] = $value ? sanitize_text_field($value) : null;
                        $format[] = '%s';
                        break;
                    case 'customer_email':
                        $update_data[$key] = $value ? sanitize_email($value) : null;
                        $format[] = '%s';
                        break;
                    case 'service_id':
                    case 'staff_id':
                        $update_data[$key] = absint($value);
                        $format[] = '%d';
                        break;
                    case 'start_datetime':
                    case 'end_datetime':
                        $update_data[$key] = sanitize_text_field($value);
                        $format[] = '%s';
                        break;
                    case 'status':
                        $update_data[$key] = in_array($value, ['pending', 'confirmed', 'cancelled', 'completed']) ? $value : 'pending';
                        $format[] = '%s';
                        break;
                    case 'total_price':
                        $update_data[$key] = number_format((float) $value, 2, '.', '');
                        $format[] = '%s';
                        break;
                    case 'transaction_id':
                        $update_data[$key] = $value ? sanitize_text_field($value) : null;
                        $format[] = '%s';
                        break;
                }
            }
        }

        if (empty($update_data)) {
            return false;
        }

        $update_data['updated_at'] = current_time('mysql');
        $format[] = '%s';

        $result = $this->wpdb->update(
            $this->tables['appointments'],
            $update_data,
            ['id' => $id],
            $format,
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Get appointment by ID
     * 
     * @param int $id Appointment ID
     * @return array|null Appointment data or null
     */
    public function get_appointment($id)
    {
        $id = absint($id);
        
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->tables['appointments']} WHERE id = %d LIMIT 1",
                $id
            ),
            ARRAY_A
        );
    }

    /**
     * Get appointments with filters
     * 
     * @param array $args Filter arguments
     * @return array Appointments
     */
    public function get_appointments($args = [])
    {
        $defaults = [
            'status' => null,
            'service_id' => null,
            'staff_id' => null,
            'customer_email' => null,
            'date_from' => null,
            'date_to' => null,
            'limit' => 10,
            'offset' => 0,
            'orderby' => 'start_datetime',
            'order' => 'DESC',
        ];
        
        $args = wp_parse_args($args, $defaults);

        $where = [];
        $params = [];

        if (!empty($args['status'])) {
            $where[] = "status = %s";
            $params[] = sanitize_text_field($args['status']);
        }

        if (!empty($args['service_id'])) {
            $where[] = "service_id = %d";
            $params[] = absint($args['service_id']);
        }

        if (!empty($args['staff_id'])) {
            $where[] = "staff_id = %d";
            $params[] = absint($args['staff_id']);
        }

        if (!empty($args['customer_email'])) {
            $where[] = "customer_email = %s";
            $params[] = sanitize_email($args['customer_email']);
        }

        if (!empty($args['date_from'])) {
            $where[] = "start_datetime >= %s";
            $params[] = sanitize_text_field($args['date_from']);
        }

        if (!empty($args['date_to'])) {
            $where[] = "start_datetime <= %s";
            $params[] = sanitize_text_field($args['date_to']);
        }

        $orderby = sanitize_sql_orderby($args['orderby']);
        $order = in_array(strtoupper($args['order']), ['ASC', 'DESC']) ? strtoupper($args['order']) : 'DESC';

        $where_clause = !empty($where) ? "WHERE " . implode(' AND ', $where) : '';
        
        // Build the SQL with proper placeholders
        $sql = "SELECT * FROM {$this->tables['appointments']} {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        
        // Prepare where clause params and main query params separately
        $where_params = $params;
        $limit = absint($args['limit']);
        $offset = absint($args['offset']);

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, array_merge($where_params, [$limit, $offset])),
            ARRAY_A
        );
    }

    /**
     * Delete appointment
     * 
     * @param int $id Appointment ID
     * @return bool True on success
     */
    public function delete_appointment($id)
    {
        $id = absint($id);
        
        return $this->wpdb->delete(
            $this->tables['appointments'],
            ['id' => $id],
            ['%d']
        ) !== false;
    }

    /**
     * Count appointments with filters
     * 
     * @param array $args Filter arguments
     * @return int Count
     */
    public function count_appointments($args = [])
    {
        $args = wp_parse_args($args, [
            'status' => null,
            'service_id' => null,
            'staff_id' => null,
            'date_from' => null,
            'date_to' => null,
        ]);

        $where = [];
        $params = [];

        if (!empty($args['status'])) {
            $where[] = "status = %s";
            $params[] = sanitize_text_field($args['status']);
        }

        if (!empty($args['service_id'])) {
            $where[] = "service_id = %d";
            $params[] = absint($args['service_id']);
        }

        if (!empty($args['staff_id'])) {
            $where[] = "staff_id = %d";
            $params[] = absint($args['staff_id']);
        }

        if (!empty($args['date_from'])) {
            $where[] = "start_datetime >= %s";
            $params[] = sanitize_text_field($args['date_from']);
        }

        if (!empty($args['date_to'])) {
            $where[] = "start_datetime <= %s";
            $params[] = sanitize_text_field($args['date_to']);
        }

        $where_clause = !empty($where) ? "WHERE " . implode(' AND ', $where) : '';
        
        $sql = "SELECT COUNT(*) FROM {$this->tables['appointments']} {$where_clause}";
        
        return (int) $this->wpdb->get_var(
            $this->wpdb->prepare($sql, $params)
        );
    }

    // ========================================================================
    // SERVICES TABLE METHODS
    // ========================================================================

    /**
     * Insert new service
     * 
     * @param array $data Service data
     * @return int|false Inserted ID or false on error
     */
    public function insert_service($data)
    {
        $defaults = [
            'name' => '',
            'duration_minutes' => 30,
            'price' => 0.00,
            'is_active' => 1,
        ];
        
        $data = wp_parse_args($data, $defaults);
        
        if (empty($data['name'])) {
            return false;
        }

        $result = $this->wpdb->insert(
            $this->tables['services'],
            [
                'name' => sanitize_text_field($data['name']),
                'duration_minutes' => absint($data['duration_minutes']),
                'price' => number_format((float) $data['price'], 2, '.', ''),
                'is_active' => absint($data['is_active']),
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%d', '%s', '%d', '%s']
        );

        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Update service
     * 
     * @param int $id Service ID
     * @param array $data Data to update
     * @return bool True on success
     */
    public function update_service($id, $data)
    {
        $id = absint($id);
        
        if (empty($id)) {
            return false;
        }

        $update_data = [];
        $format = [];

        $allowed_fields = ['name', 'duration_minutes', 'price', 'is_active'];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowed_fields)) {
                switch ($key) {
                    case 'name':
                        $update_data[$key] = sanitize_text_field($value);
                        $format[] = '%s';
                        break;
                    case 'duration_minutes':
                        $update_data[$key] = absint($value);
                        $format[] = '%d';
                        break;
                    case 'price':
                        $update_data[$key] = number_format((float) $value, 2, '.', '');
                        $format[] = '%s';
                        break;
                    case 'is_active':
                        $update_data[$key] = absint($value);
                        $format[] = '%d';
                        break;
                }
            }
        }

        if (empty($update_data)) {
            return false;
        }

        return $this->wpdb->update(
            $this->tables['services'],
            $update_data,
            ['id' => $id],
            $format,
            ['%d']
        ) !== false;
    }

    /**
     * Get service by ID
     * 
     * @param int $id Service ID
     * @return array|null Service data or null
     */
    public function get_service($id)
    {
        $id = absint($id);
        
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->tables['services']} WHERE id = %d LIMIT 1",
                $id
            ),
            ARRAY_A
        );
    }

    /**
     * Get all active services with caching
     * 
     * @param bool $active_only Only return active services
     * @param bool $use_cache Use cached results if available
     * @return array Services
     */
    public function get_services($active_only = true, $use_cache = true)
    {
        $cache_key = 'services_' . ($active_only ? 'active' : 'all');
        
        // Check cache first
        if ($use_cache && isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }
        
        $where = $active_only ? "WHERE is_active = 1" : "";
        
        $services = $this->wpdb->get_results(
            "SELECT * FROM {$this->tables['services']} {$where} ORDER BY name ASC",
            ARRAY_A
        );
        
        // Cache the results
        if ($use_cache) {
            $this->cache[$cache_key] = $services;
        }
        
        return $services;
    }

    /**
     * Delete service
     * 
     * @param int $id Service ID
     * @return bool True on success
     */
    public function delete_service($id)
    {
        $id = absint($id);
        
        return $this->wpdb->delete(
            $this->tables['services'],
            ['id' => $id],
            ['%d']
        ) !== false;
    }

    // ========================================================================
    // STAFF TABLE METHODS
    // ========================================================================

    /**
     * Insert new staff member
     * 
     * @param array $data Staff data
     * @return int|false Inserted ID or false on error
     */
    public function insert_staff($data)
    {
        $defaults = [
            'user_id' => null,
            'display_name' => '',
            'bio' => null,
            'working_hours_json' => null,
            'is_active' => 1,
        ];
        
        $data = wp_parse_args($data, $defaults);
        
        if (empty($data['display_name'])) {
            return false;
        }

        $result = $this->wpdb->insert(
            $this->tables['staff'],
            [
                'user_id' => $data['user_id'] ? absint($data['user_id']) : null,
                'display_name' => sanitize_text_field($data['display_name']),
                'bio' => $data['bio'] ? wp_kses_post($data['bio']) : null,
                'working_hours_json' => $data['working_hours_json'] ? $this->sanitize_json($data['working_hours_json']) : null,
                'is_active' => absint($data['is_active']),
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%d', '%s']
        );

        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Update staff member
     * 
     * @param int $id Staff ID
     * @param array $data Data to update
     * @return bool True on success
     */
    public function update_staff($id, $data)
    {
        $id = absint($id);
        
        if (empty($id)) {
            return false;
        }

        $update_data = [];
        $format = [];

        $allowed_fields = ['user_id', 'display_name', 'bio', 'working_hours_json', 'is_active'];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowed_fields)) {
                switch ($key) {
                    case 'user_id':
                        $update_data[$key] = $value ? absint($value) : null;
                        $format[] = '%d';
                        break;
                    case 'display_name':
                        $update_data[$key] = sanitize_text_field($value);
                        $format[] = '%s';
                        break;
                    case 'bio':
                        $update_data[$key] = $value ? wp_kses_post($value) : null;
                        $format[] = '%s';
                        break;
                    case 'working_hours_json':
                        $update_data[$key] = $value ? $this->sanitize_json($value) : null;
                        $format[] = '%s';
                        break;
                    case 'is_active':
                        $update_data[$key] = absint($value);
                        $format[] = '%d';
                        break;
                }
            }
        }

        if (empty($update_data)) {
            return false;
        }

        return $this->wpdb->update(
            $this->tables['staff'],
            $update_data,
            ['id' => $id],
            $format,
            ['%d']
        ) !== false;
    }

    /**
     * Get staff by ID
     * 
     * @param int $id Staff ID
     * @return array|null Staff data or null
     */
    public function get_staff($id)
    {
        $id = absint($id);
        
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->tables['staff']} WHERE id = %d LIMIT 1",
                $id
            ),
            ARRAY_A
        );
    }

    /**
     * Get all active staff members with caching
     * 
     * @param bool $active_only Only return active staff
     * @param bool $use_cache Use cached results if available
     * @return array Staff members
     */
    public function get_staff_members($active_only = true, $use_cache = true)
    {
        $cache_key = 'staff_' . ($active_only ? 'active' : 'all');
        
        // Check cache first
        if ($use_cache && isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }
        
        $where = $active_only ? "WHERE is_active = 1" : "";
        
        $staff = $this->wpdb->get_results(
            "SELECT * FROM {$this->tables['staff']} {$where} ORDER BY display_name ASC",
            ARRAY_A
        );
        
        // Cache the results
        if ($use_cache) {
            $this->cache[$cache_key] = $staff;
        }
        
        return $staff;
    }

    /**
     * Delete staff member
     * 
     * @param int $id Staff ID
     * @return bool True on success
     */
    public function delete_staff($id)
    {
        $id = absint($id);
        
        return $this->wpdb->delete(
            $this->tables['staff'],
            ['id' => $id],
            ['%d']
        ) !== false;
    }

    /**
     * Sanitize JSON string
     * 
     * @param string $json JSON string
     * @return string|null Sanitized JSON or null
     */
    private function sanitize_json($json)
    {
        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        
        // Re-encode to ensure valid JSON
        return wp_json_encode($decoded);
    }
}
