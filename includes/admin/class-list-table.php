<?php
/**
 * Appointments List Table.
 *
 * @package OnTime
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Custom WP_List_Table for OnTime appointments. Supports bulk actions
 * (Confirm, Cancel, Mark Completed), search and date-range / staff filters.
 */
class OnTime_List_Table extends WP_List_Table {

	/** @var OnTime_Database */
	private $db;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( array(
			'singular' => __( 'نوبت', 'ontime' ),
			'plural'   => __( 'نوبت‌ها', 'ontime' ),
			'ajax'     => false,
			'screen'   => isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'ontime-appointments',
		) );
		$this->db = new OnTime_Database();
	}

	/**
	 * Define table columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'             => '<input type="checkbox" />',
			'customer_name'  => __( 'مشتری', 'ontime' ),
			'service_id'     => __( 'خدمت', 'ontime' ),
			'staff_id'       => __( 'کارشناس', 'ontime' ),
			'start_datetime' => __( 'تاریخ و ساعت (جلالی)', 'ontime' ),
			'status'         => __( 'وضعیت', 'ontime' ),
			'total_price'    => __( 'مبلغ', 'ontime' ),
		);
	}

	/**
	 * Define sortable columns.
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'start_datetime' => array( 'start_datetime', true ),
			'status'         => array( 'status', false ),
			'total_price'    => array( 'total_price', false ),
		);
	}

	/**
	 * Define bulk actions.
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		return array(
			'confirm'  => __( 'تأیید', 'ontime' ),
			'complete' => __( 'تکمیل‌شده', 'ontime' ),
			'cancel'   => __( 'لغو', 'ontime' ),
		);
	}

	/**
	 * Render the checkbox column.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="appointment[]" value="%d" />', (int) $item['id'] );
	}

	/**
	 * Default column renderer.
	 *
	 * @param array  $item        Row data.
	 * @param string $column_name Column name.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		$engine = new OnTime_Calendar_Engine();
		switch ( $column_name ) {
			case 'customer_name':
				$name  = esc_html( $item['customer_name'] );
				$phone = esc_html( $item['customer_phone'] );
				return sprintf( '<strong>%s</strong><br><span class="ontime-muted">%s</span>', $name, $phone );
			case 'service_id':
				$s = $this->db->get_service( (int) $item['service_id'] );
				return $s ? esc_html( $s['name'] ) : '—';
			case 'staff_id':
				$staff = $this->db->get_staff_member( (int) $item['staff_id'] );
				return $staff ? esc_html( $staff['display_name'] ) : '—';
			case 'start_datetime':
				return esc_html( $engine->to_jalali_display( $item['start_datetime'] ) );
			case 'status':
				return esc_html( $this->status_label( $item['status'] ) );
			case 'total_price':
				return esc_html( number_format_i18n( (float) $item['total_price'], 0 ) );
			default:
				return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
		}
	}

	/**
	 * Translate status enum to a localized label.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	private function status_label( $status ) {
		$labels = array(
			'pending'   => __( 'در انتظار', 'ontime' ),
			'confirmed' => __( 'تأییدشده', 'ontime' ),
			'cancelled' => __( 'لغوشده', 'ontime' ),
			'completed' => __( 'تکمیل‌شده', 'ontime' ),
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	/**
	 * Fetch, filter and paginate rows for the current view.
	 */
	public function prepare_items() {
		global $wpdb;
		$prefix = $wpdb->prefix;

		$per_page = 20;
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		$where  = array( '1=1' );
		$params = array();

		// Search by customer name/phone.
		$search = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		if ( '' !== $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '(customer_name LIKE %s OR customer_phone LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}

		// Staff filter.
		$staff_id = isset( $_REQUEST['staff_filter'] ) ? absint( $_REQUEST['staff_filter'] ) : 0;
		if ( $staff_id ) {
			$where[]  = 'staff_id = %d';
			$params[] = $staff_id;
		}

		// Status filter.
		$status_filter = isset( $_REQUEST['status_filter'] ) ? sanitize_key( wp_unslash( $_REQUEST['status_filter'] ) ) : '';
		$statuses = array( 'pending', 'confirmed', 'cancelled', 'completed' );
		if ( '' !== $status_filter && in_array( $status_filter, $statuses, true ) ) {
			$where[]  = 'status = %s';
			$params[] = $status_filter;
		}

		$where_sql = implode( ' AND ', $where );

		// Sorting.
		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'start_datetime';
		$allowed = array( 'start_datetime', 'status', 'total_price' );
		if ( ! in_array( $orderby, $allowed, true ) ) {
			$orderby = 'start_datetime';
		}
		$order = isset( $_REQUEST['order'] ) && 'asc' === strtolower( wp_unslash( $_REQUEST['order'] ) ) ? 'ASC' : 'DESC';

		$paged  = isset( $_REQUEST['paged'] ) ? absint( $_REQUEST['paged'] ) : 1;
		$offset = ( $paged - 1 ) * $per_page;

		$params_count = $params;
		$count_sql = "SELECT COUNT(*) FROM {$prefix}ontime_appointments WHERE {$where_sql}";
		$total_items = (int) $wpdb->get_var( count( $params_count ) ? $wpdb->prepare( $count_sql, $params_count ) : $count_sql );

		$sql = "SELECT * FROM {$prefix}ontime_appointments WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$params[] = $per_page;
		$params[] = $offset;
		$this->items = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'     => $per_page,
			'total_pages'  => (int) ceil( $total_items / $per_page ),
		) );
	}

	/**
	 * Render extra controls (staff & status filters) above the table.
	 *
	 * @param string $which 'top' or 'bottom'.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		$staff = ( new OnTime_Database() )->get_staff();
		$current_staff = isset( $_REQUEST['staff_filter'] ) ? absint( $_REQUEST['staff_filter'] ) : 0;
		$current_status = isset( $_REQUEST['status_filter'] ) ? sanitize_key( wp_unslash( $_REQUEST['status_filter'] ) ) : '';

		echo '<div class="alignleft actions">';
		echo '<select name="staff_filter">';
		printf( '<option value="0">%s</option>', esc_html__( 'همه کارشناسان', 'ontime' ) );
		foreach ( $staff as $row ) {
			printf( '<option value="%d" %s>%s</option>', (int) $row['id'], selected( $current_staff, (int) $row['id'], false ), esc_html( $row['display_name'] ) );
		}
		echo '</select> ';

		echo '<select name="status_filter">';
		printf( '<option value="">%s</option>', esc_html__( 'همه وضعیت‌ها', 'ontime' ) );
		$statuses = array( 'pending', 'confirmed', 'cancelled', 'completed' );
		$labels = array(
			'pending'   => __( 'در انتظار', 'ontime' ),
			'confirmed' => __( 'تأییدشده', 'ontime' ),
			'cancelled' => __( 'لغوشده', 'ontime' ),
			'completed' => __( 'تکمیل‌شده', 'ontime' ),
		);
		foreach ( $statuses as $s ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $s ), selected( $current_status, $s, false ), esc_html( $labels[ $s ] ) );
		}
		echo '</select> ';

		submit_button( __( 'فیلتر', 'ontime' ), '', 'filter_action', false );
		echo '</div>';
	}

	/**
	 * Override the bulk-actions form target to our secure admin-post handler.
	 *
	 * @return array
	 */
	protected function get_bulk_actions_map() {
		return array(
			'confirm'  => 'confirmed',
			'complete' => 'completed',
			'cancel'   => 'cancelled',
		);
	}

	/**
	 * Output the wrapping form so bulk actions route through admin-post with a nonce.
	 *
	 * @return void
	 */
	public function display() {
		$url = admin_url( 'admin-post.php' );
		echo '<form action="' . esc_url( $url ) . '" method="post">';
		wp_nonce_field( 'ontime_bulk' );
		echo '<input type="hidden" name="action" value="ontime_bulk_action" />';
		parent::display();
		echo '</form>';
	}
}
