<?php
/**
 * Appointments list table for the OnTime admin.
 *
 * Extends WP_List_Table to render the appointments table with search,
 * status/service/date filters, sortable columns, pagination, bulk
 * actions and row actions. All state-changing operations are protected
 * by `check_admin_referer()` and require the OnTime capability.
 *
 * @package OnTime
 * @since   0.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// WP_List_Table is not autoloaded by default.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class OnTime_Admin_List_Table
 */
final class OnTime_Admin_List_Table extends WP_List_Table {

	/**
	 * Valid appointment statuses.
	 * @since 0.4.0
	 * @var array
	 */
	private $statuses = array( 'pending', 'confirmed', 'cancelled', 'completed' );

	/**
	 * Capability required for row/bulk actions.
	 * @since 0.4.0
	 * @var string
	 */
	private $cap = 'manage_options';

	/**
	 * Cached services map (id => name) for the filter and column.
	 * @since 0.4.0
	 * @var array
	 */
	private $services = array();

	/**
	 * Constructor.
	 *
	 * @since 0.4.0
	 */
	public function __construct() {
		parent::__construct( array(
			'singular' => 'appointment',
			'plural'   => 'appointments',
			'ajax'     => false,
			'screen'   => 'ontime',
		) );

		$cap = 'manage_ontime';
		if ( ! current_user_can( $cap ) ) {
			$cap = 'manage_options';
		}
		$this->cap = $cap;

		$this->load_services();
	}

	/**
	 * Load services into an id => name map.
	 *
	 * @since 0.4.0
	 * @return void
	 */
	private function load_services() {
		global $wpdb;
		$table = OnTime_Database::instance()->get_table( 'services' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT id, name FROM {$table}", ARRAY_A );
		if ( is_array( $rows ) ) {
			foreach ( $rows as $r ) {
				$this->services[ (int) $r['id'] ] = $r['name'];
			}
		}
	}

	/**
	 * Define table columns.
	 *
	 * @since 0.4.0
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'                => '<input type="checkbox" />',
			'id'                => __( '#', 'ontime' ),
			'service'           => __( 'سرویس', 'ontime' ),
			'customer'          => __( 'مشتری', 'ontime' ),
			'ontime_jalali'     => __( 'تاریخ و زمان', 'ontime' ),
			'status'            => __( 'وضعیت', 'ontime' ),
			'payment_status'    => __( 'پرداخت', 'ontime' ),
		);
	}

	/**
	 * Define sortable columns.
	 *
	 * @since 0.4.0
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'id'             => array( 'id', true ),
			'ontime_jalali'  => array( 'start_time', true ),
			'status'         => array( 'status', false ),
		);
	}

	/**
	 * Define bulk actions.
	 *
	 * @since 0.4.0
	 * @return array
	 */
	protected function get_bulk_actions() {
		return array(
			'confirm'   => __( 'تأیید نوبت‌ها', 'ontime' ),
			'cancel'    => __( 'لغو نوبت‌ها', 'ontime' ),
			'complete'  => __( 'تکمیل نوبت‌ها', 'ontime' ),
			'delete'    => __( 'حذف نوبت‌ها', 'ontime' ),
		);
	}

	/**
	 * Prepare items for display: query, filter, sort, paginate.
	 *
	 * @since 0.4.0
	 * @return void
	 */
	public function prepare_items() {
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		$per_page = 20;
		$current  = $this->get_pagenum();

		global $wpdb;
		$table = OnTime_Database::instance()->get_table( 'appointments' );

		$where = array( '1=1' );

		// Search by customer name / phone / email.
		$s = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		if ( '' !== $s ) {
			$like     = '%' . $wpdb->esc_like( $s ) . '%';
			$where[]  = $wpdb->prepare( '(customer_name LIKE %s OR customer_phone LIKE %s OR customer_email LIKE %s)', $like, $like, $like );
		}

		// Filter by status.
		$status = isset( $_GET['ontime_status'] ) ? sanitize_key( $_GET['ontime_status'] ) : '';
		if ( '' !== $status && in_array( $status, $this->statuses, true ) ) {
			$where[] = $wpdb->prepare( 'status = %s', $status );
		}

		// Filter by service.
		$service_id = isset( $_GET['ontime_service'] ) ? (int) $_GET['ontime_service'] : 0;
		if ( $service_id > 0 ) {
			$where[] = $wpdb->prepare( 'service_id = %d', $service_id );
		}

		// Filter by Jalali date range (converted to UTC).
		$calendar = OnTime_Calendar_Engine::instance();
		$j_from   = isset( $_GET['ontime_jfrom'] ) ? sanitize_text_field( $_GET['ontime_jfrom'] ) : '';
		$j_to     = isset( $_GET['ontime_jto'] ) ? sanitize_text_field( $_GET['ontime_jto'] ) : '';
		if ( '' !== $j_from && preg_match( '/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $j_from, $m ) ) {
			$ts = $calendar->jalali_to_timestamp( (int) $m[1], (int) $m[2], (int) $m[3], 0, 0 );
			$where[] = $wpdb->prepare( 'start_time >= %s', gmdate( 'Y-m-d H:i:s', $ts ) );
		}
		if ( '' !== $j_to && preg_match( '/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $j_to, $m ) ) {
			$ts = $calendar->jalali_to_timestamp( (int) $m[1], (int) $m[2], (int) $m[3], 23, 59 );
			$where[] = $wpdb->prepare( 'start_time <= %s', gmdate( 'Y-m-d H:i:s', $ts ) );
		}

		$where_sql = implode( ' AND ', $where );

		// Sorting.
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'start_time';
		$order   = isset( $_GET['order'] ) && 'asc' === strtolower( $_GET['order'] ) ? 'ASC' : 'DESC';
		$allowed_orderby = array( 'id' => 'id', 'start_time' => 'start_time', 'status' => 'status' );
		$orderby_sql     = isset( $allowed_orderby[ $orderby ] ) ? $allowed_orderby[ $orderby ] : 'start_time';

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}" );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby_sql} {$order} LIMIT %d OFFSET %d",
				$per_page,
				( $current - 1 ) * $per_page
			),
			ARRAY_A
		);

		$this->items = is_array( $items ) ? $items : array();

		$this->set_pagination_args( array(
			'total_items' => $total,
			'per_page'    => $per_page,
			'total_pages' => max( 1, (int) ceil( $total / $per_page ) ),
		) );
	}

	/**
	 * Render the default column fallback.
	 *
	 * @since 0.4.0
	 *
	 * @param array  $item        Row data.
	 * @param string $column_name Column name.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		return esc_html( $item[ $column_name ] ?? '' );
	}

	/**
	 * Render the checkbox column.
	 *
	 * @since 0.4.0
	 * @param array $item Row data.
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="appointment[]" value="%d" />', (int) $item['id'] );
	}

	/**
	 * Render the id column with row actions.
	 *
	 * @since 0.4.0
	 * @param array $item Row data.
	 * @return string
	 */
	public function column_id( $item ) {
		$id      = (int) $item['id'];
		$actions = array();

		if ( current_user_can( $this->cap ) ) {
			$nonce      = wp_create_nonce( 'ontime_row_' . $id );
			$base       = admin_url( 'admin.php?page=ontime' );
			$actions['view'] = sprintf( '<a href="%s&action=view&appointment=%d&paged=1">%s</a>', esc_url( $base ), $id, esc_html__( 'مشاهده', 'ontime' ) );
			$actions['delete'] = sprintf( '<a href="%s&action=delete&appointment=%d&_wpnonce=%s" style="color:#a00;">%s</a>', esc_url( $base ), $id, $nonce, esc_html__( 'حذف', 'ontime' ) );
		}

		return esc_html( $id ) . $this->row_actions( $actions );
	}

	/**
	 * Render the service column.
	 *
	 * @since 0.4.0
	 * @param array $item Row data.
	 * @return string
	 */
	public function column_service( $item ) {
		$sid = (int) $item['service_id'];
		return esc_html( $this->services[ $sid ] ?? sprintf( '#%d', $sid ) );
	}

	/**
	 * Render the customer column (name + phone).
	 *
	 * @since 0.4.0
	 * @param array $item Row data.
	 * @return string
	 */
	public function column_customer( $item ) {
		$name  = esc_html( $item['customer_name'] ?? '' );
		$phone = esc_html( $item['customer_phone'] ?? '' );
		$email = isset( $item['customer_email'] ) && '' !== $item['customer_email'] ? '<br><span style="color:#666;">' . esc_html( $item['customer_email'] ) . '</span>' : '';
		return "{$name}<br><span style="color:#666;">{$phone}</span>{$email}";
	}

	/**
	 * Render the Jalali date/time column.
	 *
	 * @since 0.4.0
	 * @param array $item Row data.
	 * @return string
	 */
	public function column_ontime_jalali( $item ) {
		$ts = strtotime( ( $item['start_time'] ?? '' ) . ' UTC' );
		if ( ! $ts ) {
			return '';
		}
		return esc_html( OnTime_Calendar_Engine::instance()->format_jalali( $ts, 'j F Y، H:i' ) );
	}

	/**
	 * Render the status column with a colored badge.
	 *
	 * @since 0.4.0
	 * @param array $item Row data.
	 * @return string
	 */
	public function column_status( $item ) {
		$status = $item['status'] ?? 'pending';
		$labels = array(
			'pending'   => __( 'در انتظار', 'ontime' ),
			'confirmed' => __( 'تأییدشده', 'ontime' ),
			'cancelled' => __( 'لغوشده', 'ontime' ),
			'completed' => __( 'تکمیل‌شده', 'ontime' ),
		);
		$label = $labels[ $status ] ?? $status;
		return sprintf( '<span class="ontime-status ontime-status-%s">%s</span>', esc_attr( $status ), esc_html( $label ) );
	}

	/**
	 * Render the payment status column.
	 *
	 * @since 0.4.0
	 * @param array $item Row data.
	 * @return string
	 */
	public function column_payment_status( $item ) {
		$status = $item['payment_status'] ?? 'unpaid';
		$labels = array(
			'unpaid'   => __( 'پرداخت‌نشده', 'ontime' ),
			'paid'     => __( 'پرداخت‌شده', 'ontime' ),
			'refunded' => __( 'بازگشت‌داده‌شده', 'ontime' ),
		);
		return esc_html( $labels[ $status ] ?? $status );
	}

	/**
	 * Display extra filter controls above the table.
	 *
	 * @since 0.4.0
	 * @param string $which top or bottom.
	 * @return void
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		$status   = isset( $_GET['ontime_status'] ) ? sanitize_key( $_GET['ontime_status'] ) : '';
		$service  = isset( $_GET['ontime_service'] ) ? (int) $_GET['ontime_service'] : 0;
		$jfrom    = isset( $_GET['ontime_jfrom'] ) ? sanitize_text_field( $_GET['ontime_jfrom'] ) : '';
		$jto      = isset( $_GET['ontime_jto'] ) ? sanitize_text_field( $_GET['ontime_jto'] ) : '';

		echo '<div class="alignleft actions">';
		// Status filter.
		echo '<select name="ontime_status">';
		echo '<option value="">' . esc_html__( 'همه وضعیت‌ها', 'ontime' ) . '</option>';
		foreach ( array( 'pending' => __( 'در انتظار', 'ontime' ), 'confirmed' => __( 'تأییدشده', 'ontime' ), 'cancelled' => __( 'لغوشده', 'ontime' ), 'completed' => __( 'تکمیل‌شده', 'ontime' ) ) as $v => $l ) {
			echo '<option value="' . esc_attr( $v ) . '" ' . selected( $status, $v, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select>';

		// Service filter.
		echo '<select name="ontime_service">';
		echo '<option value="0">' . esc_html__( 'همه سرویس‌ها', 'ontime' ) . '</option>';
		foreach ( $this->services as $sid => $name ) {
			echo '<option value="' . esc_attr( (string) $sid ) . '" ' . selected( $service, $sid, false ) . '>' . esc_html( $name ) . '</option>';
		}
		echo '</select>';

		// Jalali date range.
		echo '<input type="text" name="ontime_jfrom" placeholder="' . esc_attr__( 'از (۱۴۰۳/۰۱/۰۱)', 'ontime' ) . '" value="' . esc_attr( $jfrom ) . '" style="width:120px;" />';
		echo '<input type="text" name="ontime_jto" placeholder="' . esc_attr__( 'تا (۱۴۰۳/۰۱/۳۱)', 'ontime' ) . '" value="' . esc_attr( $jto ) . '" style="width:120px;" />';

		submit_button( __( 'فیلتر', 'ontime' ), '', 'filter_action', false );
		echo '</div>';
	}

	/**
	 * Process row and bulk actions (called from the page handler).
	 *
	 * @since 0.4.0
	 * @return void
	 */
	public function process_actions() {
		global $wpdb;
		$table = OnTime_Database::instance()->get_table( 'appointments' );

		// Row delete (single).
		$row_id = isset( $_GET['appointment'] ) ? (int) $_GET['appointment'] : 0;
		$row_action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';

		if ( 'delete' === $row_action && $row_id > 0 ) {
			check_admin_referer( 'ontime_row_' . $row_id );
			if ( ! current_user_can( $this->cap ) ) {
				wp_die( esc_html__( 'دسترسی غیرمجاز.', 'ontime' ) );
			}
			$wpdb->delete( $table, array( 'id' => $row_id ), array( '%d' ) );
			return;
		}

		// Bulk action.
		$action = $this->current_action();
		$ids    = isset( $_POST['appointment'] ) ? array_map( 'intval', (array) $_POST['appointment'] ) : array();
		if ( '' === $action || empty( $ids ) ) {
			return;
		}

		check_admin_referer( 'bulk-' . $this->_args['plural'] );
		if ( ! current_user_can( $this->cap ) ) {
			wp_die( esc_html__( 'دسترسی غیرمجاز.', 'ontime' ) );
		}

		$ids_csv = implode( ',', array_filter( $ids ) );

		switch ( $action ) {
			case 'confirm':
				$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = 'confirmed' WHERE id IN ({$ids_csv})" ) );
				break;
			case 'cancel':
				$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = 'cancelled' WHERE id IN ({$ids_csv})" ) );
				break;
			case 'complete':
				$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = 'completed' WHERE id IN ({$ids_csv})" ) );
				break;
			case 'delete':
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ({$ids_csv})" ) );
				break;
		}
	}
}
