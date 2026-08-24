<?php
/**
 * Database schema and migration handler (Singleton).
 *
 * Creates and manages the three OnTime custom tables:
 *  - {prefix}ontime_services
 *  - {prefix}ontime_appointments
 *  - {prefix}ontime_settings
 *
 * All timestamps are stored in UTC. Jalali conversion happens only at the
 * presentation layer (Stage 2).
 *
 * @package OnTime
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class OnTime_Database {

	private static $instance = null;
	private $tables = array();

	private $default_settings = array(
		'timezone'        => 'Asia/Tehran',
		'work_start'      => '09:00',
		'work_end'        => '18:00',
		'slot_length'     => 30,
		'weekend_days'    => '5',     // Friday in PHP \`w\` (Sun=0 ... Sat=6).
		'buffer_minutes'  => 0,      // Gap between appointments.
		'min_lead_hours'  => 2,      // Minimum hours before a slot is bookable.
		'max_future_days' => 30,     // How far ahead slots are offered.
		'persian_digits'  => 1,      // Display Persian numerals.
		'date_format'     => 'j F Y',
		'payment_gateway' => 'mock', // Default gateway slug.
	);

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}
	private function __clone() {}

	public function __wakeup() {
		_doing_it_wrong( __METHOD__, esc_html__( 'Cannot unserialize a singleton.', 'ontime' ), '0.2.0' );
	}

	public function get_table( $name ) {
		global $wpdb;
		if ( isset( $this->tables[ $name ] ) ) {
			return $this->tables[ $name ];
		}
		$valid = array( 'services', 'appointments', 'settings' );
		if ( ! in_array( $name, $valid, true ) ) {
			return '';
		}
		$this->tables[ $name ] = $wpdb->prefix . 'ontime_' . $name;
		return $this->tables[ $name ];
	}

	public function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $wpdb->get_charset_collate();

		$table_services     = $this->get_table( 'services' );
		$table_appointments = $this->get_table( 'appointments' );
		$table_settings     = $this->get_table( 'settings' );

		// IMPORTANT: dbDelta requires exactly TWO spaces after "CREATE TABLE".
		$sql_services = "CREATE TABLE  {$table_services} (
			id bigint(20) unsigned NOT NULL auto_increment,
			name varchar(255) NOT NULL default '',
			description text NULL,
			duration smallint(5) unsigned NOT NULL default 30,
			price decimal(12,2) NOT NULL default 0,
			is_active tinyint(1) NOT NULL default 1,
			created_at datetime NOT NULL default CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL default CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY is_active (is_active)
		) {$charset_collate};";

		$sql_appointments = "CREATE TABLE  {$table_appointments} (
			id bigint(20) unsigned NOT NULL auto_increment,
			service_id bigint(20) unsigned NOT NULL default 0,
			customer_name varchar(255) NOT NULL default '',
			customer_phone varchar(32) NOT NULL default '',
			customer_email varchar(255) NULL,
			start_time datetime NOT NULL default '0000-00-00 00:00:00',
			end_time datetime NOT NULL default '0000-00-00 00:00:00',
			status varchar(20) NOT NULL default 'pending',
			payment_status varchar(20) NOT NULL default 'unpaid',
			transaction_id varchar(100) NULL,
			notes text NULL,
			created_at datetime NOT NULL default CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL default CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY uk_slot (service_id, start_time),
			KEY status (status),
			KEY start_time (start_time)
		) {$charset_collate};";

		$sql_settings = "CREATE TABLE  {$table_settings} (
			id bigint(20) unsigned NOT NULL auto_increment,
			setting_key varchar(100) NOT NULL default '',
			setting_value longtext NULL,
			autoload tinyint(1) NOT NULL default 0,
			created_at datetime NOT NULL default CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY setting_key (setting_key)
		) {$charset_collate};";

		dbDelta( $sql_services );
		dbDelta( $sql_appointments );
		dbDelta( $sql_settings );
	}

	public function seed_defaults() {
		global $wpdb;
		$table_services = $this->get_table( 'services' );
		$table_settings  = $this->get_table( 'settings' );

		$has_services = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_services}" );
		if ( 0 === $has_services ) {
			$wpdb->insert(
				$table_services,
				array(
					'name'        => __( 'مشاوره عمومی', 'ontime' ),
					'description' => __( 'سرویس پیش‌فرض نوبت‌دهی', 'ontime' ),
					'duration'    => 30,
					'price'       => 0,
					'is_active'   => 1,
				),
				array( '%s', '%s', '%d', '%f', '%d' )
			);
		}

		foreach ( $this->default_settings as $key => $value ) {
			$serialized = maybe_serialize( $value );
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$table_settings} (setting_key, setting_value, autoload) VALUES (%s, %s, 0) ON DUPLICATE KEY UPDATE setting_value = setting_value",
					$key,
					$serialized
				)
			);
		}
	}

	public function get_setting( $key, $default = null ) {
		global $wpdb;
		$table_settings = $this->get_table( 'settings' );
		$value = $wpdb->get_var( $wpdb->prepare( "SELECT setting_value FROM {$table_settings} WHERE setting_key = %s LIMIT 1", $key ) );
		if ( null === $value ) {
			return $default;
		}
		return maybe_unserialize( $value );
	}

	public function update_setting( $key, $value ) {
		global $wpdb;
		$table_settings = $this->get_table( 'settings' );
		$serialized = maybe_serialize( $value );
		$affected = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table_settings} (setting_key, setting_value, autoload) VALUES (%s, %s, 0) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
				$key,
				$serialized
			)
		);
		return false !== $affected;
	}

	public function get_all_settings() {
		global $wpdb;
		$table_settings = $this->get_table( 'settings' );
		$rows = $wpdb->get_results( "SELECT setting_key, setting_value FROM {$table_settings}", ARRAY_A );
		$settings = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$settings[ $row['setting_key'] ] = maybe_unserialize( $row['setting_value'] );
			}
		}
		return $settings;
	}
}
