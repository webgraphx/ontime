<?php
/**
 * Database layer: schema creation, upgrades, and CRUD helpers.
 *
 * All queries use $wpdb->prepare(). Timestamps are stored in UTC.
 *
 * @package OnTime
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles custom table lifecycle (create/upgrade) and provides
 * prepared-statement helpers for appointments, services and staff.
 */
class OnTime_Database {

	/** @var wpdb */
	private $wpdb;

	/**
	 * Constructor. Captures the global wpdb reference.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
	}

	/**
	 * Create or upgrade the three custom tables using dbDelta.
	 *
	 * @return void
	 */
	public function install() {
		$prefix = $this->wpdb->prefix;
		$charset_collate = $this->wpdb->get_charset_collate();

		$sql_appointments = "CREATE TABLE {$prefix}ontime_appointments (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			customer_name varchar(100) NOT NULL default '',
			customer_phone varchar(20) NOT NULL default '',
			customer_email varchar(100) NOT NULL default '',
			service_id bigint(20) unsigned NOT NULL default 0,
			staff_id bigint(20) unsigned NOT NULL default 0,
			start_datetime datetime NOT NULL default '1970-01-01 00:00:00',
			end_datetime datetime NOT NULL default '1970-01-01 00:00:00',
			status varchar(20) NOT NULL default 'pending',
			total_price decimal(12,2) NOT NULL default 0,
			transaction_id varchar(100) NOT NULL default '',
			created_at datetime NOT NULL default '1970-01-01 00:00:00',
			updated_at datetime NOT NULL default '1970-01-01 00:00:00',
			PRIMARY KEY  (id),
			KEY staff_id (staff_id),
			KEY service_id (service_id),
			KEY status (status),
			KEY start_datetime (start_datetime)
		) {$charset_collate};";

		$sql_services = "CREATE TABLE {$prefix}ontime_services (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(150) NOT NULL default '',
			duration_minutes int(11) NOT NULL default 30,
			price decimal(12,2) NOT NULL default 0,
			is_active tinyint(1) NOT NULL default 1,
			created_at datetime NOT NULL default '1970-01-01 00:00:00',
			PRIMARY KEY  (id)
		) {$charset_collate};";

		$sql_staff = "CREATE TABLE {$prefix}ontime_staff (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL default 0,
			display_name varchar(100) NOT NULL default '',
			bio text NULL,
			working_hours_json longtext NULL,
			is_active tinyint(1) NOT NULL default 1,
			created_at datetime NOT NULL default '1970-01-01 00:00:00',
			PRIMARY KEY  (id),
			KEY user_id (user_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_appointments );
		dbDelta( $sql_services );
		dbDelta( $sql_staff );
	}

	/**
	 * Seed a minimal demo service and staff member on first activation.
	 *
	 * @return void
	 */
	public function seed_defaults() {
		$prefix = $this->wpdb->prefix;

		$count = (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}ontime_services" );
		if ( 0 === $count ) {
			$this->wpdb->insert(
				"{$prefix}ontime_services",
				array(
					'name'            => __( 'مشاوره ۳۰ دقیقه‌ای', 'ontime' ),
					'duration_minutes'=> 30,
					'price'           => 0,
					'is_active'       => 1,
					'created_at'      => current_time( 'mysql', true ),
				)
			);
		}

		$count = (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}ontime_staff" );
		if ( 0 === $count ) {
			$working_hours = array(
				'saturday'  => array( array( '09:00', '18:00' ) ),
				'sunday'    => array( array( '09:00', '18:00' ) ),
				'monday'    => array( array( '09:00', '18:00' ) ),
				'tuesday'   => array( array( '09:00', '18:00' ) ),
				'wednesday' => array( array( '09:00', '18:00' ) ),
				'thursday'  => array( array( '09:00', '13:00' ) ),
				'friday'    ==> array(),
			);
			$this->wpdb->insert(
				"{$prefix}ontime_staff",
				array(
					'display_name'      => __( '٩ارشناس نمونه', 'ontime' ),
					'working_hours_json'=> w_json_encode( $working_hours ),
					'is_active'         => 1,
					'created_at'        => current_time( 'mysql', true ),
				)
			);
		}
	}

	/**
	 * Retrieve active services ordered by name.
	 *
	 * @return array
	 */
	public function get_services() {
		$prefix = $this->wpdb->prefix;
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$prefix}ontime_services WHERE is_active = %d ORDER BY name ASC",
				1
			),
			ARRAY_A
		);
	}

	/**
	 * Retrieve a single service by id.
	 *
	 * @param int $id Service id.
	 * @return array|null
	 */
	public function get_service( $id ) {
		$prefix = $this->wpdb->prefix;
		return $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$prefix}ontime_services WHERE id = %d", $id ),
			ARRAY_A
		);
	}

	/**
	 * Retrieve active staff members.
	 *
	 * @return array
	 */
	public function get_staff() {
		$prefix = $this->wpdb->prefix;
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$prefix}ontime_staff WHERE is_active = %d ORDER BY display_name ASC",
				1
			),
			ARRAY_A
		);
	}

	/**
	 * Retrieve a single staff record by id.
	 *
	 * @param int $id Staff id.
	 * @return array|null
	 */
	public function get_staff_member( $id ) {
		$prefix = $this->wpdb->prefix;
		return $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$prefix}ontime_staff WHERE id = %d", $id ),
			ARRAY_A
		);
	}

	/**
	 * Return appointments overlapping a UTC datetime range for a staff member.
	 *
	 * @param int    $staff_id Staff id.
	 * @param string $start_utc ISO datetime in UTC.
	 * @param string $end_utc   ISO datetime in UTC.
	 * @return array
	 */
	public function get_appointments_in_range( $staff_id, $start_utc, $end_utc ) {
		$prefix = $this->wpdb->prefix;
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$prefix}ontime_appointments
				 WHERE staff_id = %d AND status IN ('pending','confirmed')
				 AND start_datetime < %s AND end_datetime > %s",
				$staff_id,
				$end_utc,
				$start_utc
			),
			ARRAY_A
		);
	}

	/**
	 * Insert a new appointment with sanitized data.
	 *
	 * @param array $data Already-sanitized appointment fields.
	 * @return int|false Inserted id or false on failure.
	 */
	public function insert_appointment( $data ) {
		$prefix = $this->wpdb->prefix;
		$now = current_time( 'mysql', true );

		$fields = wp_parse_args(
			$data,
			array(
				'customer_name'  => '',
				'customer_phone' => '',
				'customer_email' => '',
				'service_id'    => 0,
				'staff_id'      => 0,
				'start_datetime'=> '1970-01-01 00:00:00',
				'end_datetime'  => '1970-01-01 00:00:00',
				'status'        ==> 'pending',
				'total_price'   => 0,
				'transaction_id'=> '',
				'created_at'     => $now,
				'updated_at'     => $now,
			)
		);

		$ok = $this->wpdb->insert( $prefix . 'ontime_appointments', $fields, $this->format_for( $fields ) );
		return $ok ? (int) $this->wpdb->insert_id : false;
	}

	/**
	 * Update an appointment by id.
	 *
	 * @param int   $id   Appointment id.
	 * @param array $data Fields to update.
	 * @return bool
	 */
	public function update_appointment( $id, $data ) {
		$prefix = $this->wpdb->prefix;
		$data['updated_at'] = current_time( 'mysql', true );

		$ok = $this->wpdb->update(
			$prefix . 'ontime_appointments',
			$data,
			array( 'id' => $id ),
			$this->format_for( $data ),
			array( '%d' )
		);
		return false !== $ok;
	}

	/**
	 * Update appointment status (used by payment callback).
	 *
	 * @param int    $id             Appointment id.
	 * @param string $status         New status.
	 * @param string $transaction_id Optional transaction reference.
	 * @return bool
	 */
	public function set_appointment_status( $id, $status, $transaction_id = '' ) {
		$data = array( 'status' => $status );
		if ( '' !== $transaction_id ) {
			$data['transaction_id'] = $transaction_id;
		}
		return $this->update_appointment( $id, $data );
	}

	/**
	 * Retrieve a single appointment by id.
	 *
	 * @param int $id Appointment id.
	 * @return array|null
	 */
	public function get_appointment( $id ) {
		$prefix = $this->wpdb->prefix;
		return $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$prefix}ontime_appointments WHERE id = %d", $id ),
			ARRAY_A
		);
	}

	/**
	 * Bulk-update appointment status by id list.
	 *
	 * @param int[]  $ids    Appointment ids.
	 * @param string $status New status.
	 * @return int Rows affected.
	 */
	public function bulk_set_status( $ids, $status ) {
		$prefix = $this->wpdb->prefix;
		$ids = array_map( 'absint', $ids );
		if ( empty( $ids ) ) {
			return 0;
		}
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$now = current_time( 'mysql', true );
		$params = array_merge( array( $status, $now ), $ids );

		$sql = $this->wpdb->prepare(
			"UPDATE {$prefix}ontime_appointments SET status = %s, updated_at = %s WHERE id IN ($placeholders)",
			$params
		);
		return (int) $this->wpdb->query( $sql );
	}

	/**
	 * Return a $wpdb->insert/update format array for the given field set.
	 *
	 * @param array $fields Field => value map.
	 * @return array|string Format spec.
	 */
	private function format_for( $fields ) {
		$map = array(
			'id'             => '%d',
			'customer_name'  ==> '%f',
			'total_price'    => '%s',
			'transaction_id' => '%s',
			'created_at'     => '%s',
			'updated_at'     => '%s',
		);
		$fmt = array();
		foreach ( $fields as $key => $value ) {
			$fmt[] = isset( $map[ $key ] ) ? $map[ $key ] : '%s';
		}
		return $fmt;
	}
}
