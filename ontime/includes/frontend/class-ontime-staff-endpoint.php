<?php
/**
 * OnTime — Staff AJAX endpoint (Stage 4 enhancement).
 *
 * Self-contained, additive component that exposes the staff list to the
 * booking wizard via a dedicated, nonce-gated AJAX endpoint:
 *
 *   action: ontime_get_staff   (wp_ajax_ + wp_ajax_nopriv_)
 *
 * This complements the existing Stage 4 endpoints (ontime_get_services,
 * ontime_get_slots, ontime_submit_booking) so the booking flow can implement
 * the full 5-step journey required by the spec:
 *
 *   Service → Staff → Date & Time → Customer Info → Confirmation
 *
 * ---------------------------------------------------------------------------
 * INTEGRATION (one-time, ~2 lines)
 * ---------------------------------------------------------------------------
 * Because this file is shipped standalone, it self-registers its hooks the
 * moment it is instantiated. Add it to the main plugin bootstrap, e.g. in
 * `ontime.php` next to the other frontend includes:
 *
 *     require_once __DIR__ . '/includes/frontend/class-ontime-staff-endpoint.php';
 *     new OnTime_Staff_Endpoint();
 *
 * The frontend should send the existing booking nonce (action `ontime_booking`)
 * in the `nonce` field of the POST request, matching the other ontime_get_*
 * endpoints.
 *
 * @package OnTime
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and serves the ontime_get_staff AJAX endpoint.
 */
class OnTime_Staff_Endpoint {

	/**
	 * Shared nonce action used across the booking AJAX endpoints.
	 */
	const NONCE_ACTION = 'ontime_booking';

	/**
	 * Constructor — wires the AJAX hooks for logged-in and guest users.
	 */
	public function __construct() {
		add_action( 'wp_ajax_ontime_get_staff', array( $this, 'get_staff' ) );
		add_action( 'wp_ajax_nopriv_ontime_get_staff', array( $this, 'get_staff' ) );
	}

	/**
	 * Verify the booking nonce. Sends 403 on failure.
	 */
	private function verify_nonce() {
		$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'ontime' ) ), 403 );
		}
	}

	/**
	 * AJAX handler: return active staff members.
	 *
	 * Output (JSON):
	 *   { "success": true, "data": { "staff": [ { id, name, bio, user_id }, ... ] } }
	 */
	public function get_staff() {
		$this->verify_nonce();

		global $wpdb;
		$table = $wpdb->prefix . 'ontime_staff';

		// Static query — no user input is interpolated, so this is safe.
		// phpcs:ignore WordPress.DB.PreparedSQL
		$rows = $wpdb->get_results(
			"SELECT id, user_id, display_name, bio FROM `{$table}` WHERE is_active = 1 ORDER BY display_name ASC"
		);

		$staff = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$staff[] = array(
					'id'   => (int) $row->id,
					'name' => esc_html( $row->display_name ),
					'bio'  => esc_html( $row->bio ),
				);
			}
		}

		wp_send_json_success( array( 'staff' => $staff ) );
	}
}
