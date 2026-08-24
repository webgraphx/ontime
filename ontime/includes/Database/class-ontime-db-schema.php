<?php
/**
 * OnTime Database Schema
 * 
 * Contains table creation SQL for dbDelta compatibility
 * 
 * @package OnTime
 * @subpackage Database
 * @since 1.0.0
 */

namespace OnTime\Database;

/**
 * Database schema class for OnTime plugin
 */
class Schema
{
    /**
     * Get appointments table creation SQL
     * 
     * @global \wpdb $wpdb
     * @return string SQL statement for dbDelta
     */
    public static function get_appointments_table_sql()
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ontime_appointments';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            customer_name varchar(191) NOT NULL,
            customer_phone varchar(20) DEFAULT NULL,
            customer_email varchar(191) DEFAULT NULL,
            service_id bigint(20) NOT NULL,
            staff_id bigint(20) NOT NULL,
            start_datetime datetime NOT NULL,
            end_datetime datetime NOT NULL,
            status enum('pending','confirmed','cancelled','completed') NOT NULL DEFAULT 'pending',
            total_price decimal(12,2) NOT NULL DEFAULT 0.00,
            transaction_id varchar(100) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY ontime_app_service_id (service_id),
            KEY ontime_app_staff_id (staff_id),
            KEY ontime_app_status (status),
            KEY ontime_app_datetime (start_datetime),
            KEY ontime_app_customer_email (customer_email),
            KEY ontime_app_transaction (transaction_id),
            KEY ontime_app_status_staff (status, staff_id),
            KEY ontime_app_status_service (status, service_id),
            KEY ontime_app_datetime_status (start_datetime, status),
            FULLTEXT KEY ontime_app_customer_search (customer_name, customer_email, customer_phone)
        ) {$charset_collate};";

        return $sql;
    }

    /**
     * Get services table creation SQL
     * 
     * @global \wpdb $wpdb
     * @return string SQL statement for dbDelta
     */
    public static function get_services_table_sql()
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ontime_services';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(191) NOT NULL,
            duration_minutes int(11) NOT NULL DEFAULT 30,
            price decimal(12,2) NOT NULL DEFAULT 0.00,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY ontime_srv_name (name),
            KEY ontime_srv_active (is_active)
        ) {$charset_collate};";

        return $sql;
    }

    /**
     * Get staff table creation SQL
     * 
     * @global \wpdb $wpdb
     * @return string SQL statement for dbDelta
     */
    public static function get_staff_table_sql()
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ontime_staff';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) DEFAULT NULL,
            display_name varchar(191) NOT NULL,
            bio text DEFAULT NULL,
            working_hours_json longtext DEFAULT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY ontime_stf_user_id (user_id),
            KEY ontime_stf_active (is_active)
        ) {$charset_collate};";

        return $sql;
    }

    /**
     * Get all table creation SQL statements
     * 
     * @return array Array of SQL statements
     */
    public static function get_all_tables_sql()
    {
        return [
            self::get_appointments_table_sql(),
            self::get_services_table_sql(),
            self::get_staff_table_sql(),
        ];
    }
}
