<?php
/**
 * OnTime Mock Gateway tests.
 *
 * @package OnTime
 * @since   1.0.0
 */

class Test_OnTime_Payment_Mock extends WP_UnitTestCase {

	protected $gateway;

	public function setUp(): void {
		parent::setUp();
		$this->gateway = new OnTime_Payment_Mock();
	}

	public function test_get_slug() {
		$this->assertEquals( 'mock', $this->gateway->get_slug() );
	}

	public function test_get_name() {
		$this->assertNotEmpty( $this->gateway->get_name() );
	}

	public function test_request_returns_url() {
		$url = $this->gateway->request( 42, 5000 );
		$this->assertIsString( $url );
		$this->assertStringContainsString( 'ontime_callback=1', $url );
		$this->assertStringContainsString( 'appointment_id=42', $url );
		$this->assertStringContainsString( 'mock_status=success', $url );
	}

	public function test_verify_returns_success() {
		$result = $this->gateway->verify( 42 );
		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );
		$this->assertNotEmpty( $result['transaction_id'] );
		$this->assertStringContainsString( 'MOCK-', $result['transaction_id'] );
	}

	public function test_transaction_id_includes_appointment_id() {
		$result = $this->gateway->verify( 7 );
		$this->assertStringContainsString( '000007', $result['transaction_id'] );
	}

	public function test_implements_interface() {
		$this->assertInstanceOf( 'OnTime_Payment_Gateway', $this->gateway );
	}
}
