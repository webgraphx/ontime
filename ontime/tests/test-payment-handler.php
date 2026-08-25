<?php
/**
 * OnTime Payment Handler tests.
 *
 * @package OnTime
 * @since   1.0.0
 */

class Test_OnTime_Payment_Handler extends WP_UnitTestCase {

	protected $handler;

	public function setUp(): void {
		parent::setUp();
		$this->handler = OnTime_Payment_Handler::instance();
	}

	public function test_is_singleton() {
		$this->assertSame( $this->handler, OnTime_Payment_Handler::instance() );
	}

	public function test_mock_gateway_registered() {
		$this->assertTrue( $this->handler->has_gateway( 'mock' ) );
	}

	public function test_zarinpal_gateway_registered() {
		$this->assertTrue( $this->handler->has_gateway( 'zarinpal' ) );
	}

	public function test_get_gateway() {
		$mock = $this->handler->get_gateway( 'mock' );
		$this->assertInstanceOf( 'OnTime_Payment_Mock', $mock );
		$this->assertInstanceOf( 'OnTime_Payment_Gateway', $mock );
	}

	public function test_get_nonexistent_gateway() {
		$this->assertNull( $this->handler->get_gateway( 'nonexistent' ) );
	}

	public function test_process_payment_mock() {
		OnTime_Database::instance()->update_setting( 'active_gateway', 'mock' );
		$apt_id = $this->factory->post->create();
		$result = $this->handler->process_payment( $apt_id, 10000 );
		$this->assertIsString( $result );
		$this->assertStringContainsString( 'ontime_callback', $result );
	}

	public function test_process_payment_no_gateway() {
		OnTime_Database::instance()->update_setting( 'active_gateway', '' );
		$result = $this->handler->process_payment( 1, 5000 );
		$this->assertWPError( $result );
	}
}
