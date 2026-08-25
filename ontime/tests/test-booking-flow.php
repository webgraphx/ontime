<?php
/**
 * OnTime Booking Flow integration tests.
 *
 * @package OnTime
 * @since   1.0.0
 */

class Test_OnTime_Booking_Flow extends WP_UnitTestCase {

	protected $form;

	public function setUp(): void {
		parent::setUp();
		$this->form = OnTime_Frontend_Booking_Form::instance();
	}

	public function test_form_is_singleton() {
		$this->assertSame( $this->form, OnTime_Frontend_Booking_Form::instance() );
	}

	public function test_shortcode_registered() {
		$this->assertTrue( shortcode_exists( 'ontime_booking_form' ) );
	}

	public function test_shortcode_output() {
		$output = do_shortcode( '[ontime_booking_form]' );
		$this->assertStringContainsString( 'ontime-widget', $output );
		$this->assertStringContainsString( 'data-step="1"', $output );
	}

	public function test_invalid_nonce_rejected() {
		$_POST['nonce'] = 'invalid-nonce';
		ob_start();
		try {
			$this->form->ajax_get_services();
		} catch ( WPAjaxDieContinueException $e ) {}
		$output = ob_get_clean();
		$json = json_decode( $output, true );
		$this->assertFalse( $json['success'] );
	}

	public function test_payment_routing_for_paid_service() {
		global $wpdb;
		$table = OnTime_Database::instance()->get_table( 'services' );
		$wpdb->insert( $table, array(
			'name'       => 'Test Paid Service',
			'duration'   => 30,
			'price'      => 50000,
			'is_active'  => 1,
		) );
		$service_id = $wpdb->insert_id;
		OnTime_Database::instance()->update_setting( 'active_gateway', 'mock' );
		$handler = OnTime_Payment_Handler::instance();
		$this->assertTrue( $handler->has_gateway( 'mock' ) );
		$wpdb->delete( $table, array( 'id' => $service_id ) );
	}

	public function test_free_service_no_payment() {
		global $wpdb;
		$table = OnTime_Database::instance()->get_table( 'services' );
		$wpdb->insert( $table, array(
			'name'       => 'Free Service',
			'duration'   => 15,
			'price'      => 0,
			'is_active'  => 1,
		) );
		$service_id = $wpdb->insert_id;
		$service = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $service_id ),
			ARRAY_A
		);
		$this->assertEquals( 0, (float) $service['price'] );
		$wpdb->delete( $table, array( 'id' => $service_id ) );
	}

	public function test_ajax_actions_registered() {
		$this->assertTrue( has_action( 'wp_ajax_ontime_get_services' ) );
		$this->assertTrue( has_action( 'wp_ajax_nopriv_ontime_get_services' ) );
		$this->assertTrue( has_action( 'wp_ajax_ontime_get_slots' ) );
		$this->assertTrue( has_action( 'wp_ajax_nopriv_ontime_get_slots' ) );
		$this->assertTrue( has_action( 'wp_ajax_ontime_submit_booking' ) );
		$this->assertTrue( has_action( 'wp_ajax_nopriv_ontime_submit_booking' ) );
	}
}
