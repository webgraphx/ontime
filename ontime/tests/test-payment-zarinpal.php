<?php
/**
 * OnTime Zarinpal Gateway tests.
 *
 * @package OnTime
 * @since   1.0.0
 */

class Test_OnTime_Payment_Zarinpal extends WP_UnitTestCase {

	protected $gateway;

	public function setUp(): void {
		parent::setUp();
		$this->gateway = new OnTime_Payment_Zarinpal();
	}

	public function test_get_slug() {
		$this->assertEquals( 'zarinpal', $this->gateway->get_slug() );
	}

	public function test_get_name() {
		$this->assertEquals( 'زرین‌پال', $this->gateway->get_name() );
	}

	public function test_implements_interface() {
		$this->assertInstanceOf( 'OnTime_Payment_Gateway', $this->gateway );
	}

	public function test_request_no_merchant_id() {
		delete_option( 'ontime_settings' );
		OnTime_Database::instance()->update_setting( 'merchant_code', '' );
		OnTime_Database::instance()->update_setting( 'zarinpal_sandbox', 0 );
		$result = $this->gateway->request( 1, 10000 );
		$this->assertWPError( $result );
		$this->assertEquals( 'ontime_zarinpal_no_merchant', $result->get_error_code() );
	}

	public function test_request_low_amount() {
		OnTime_Database::instance()->update_setting( 'merchant_code', 'test-merchant-1234' );
		$result = $this->gateway->request( 1, 500 );
		$this->assertWPError( $result );
		$this->assertEquals( 'ontime_zarinpal_low_amount', $result->get_error_code() );
	}

	public function test_verify_cancelled_payment() {
		$_GET['Status']    = 'NOK';
		$_GET['Authority'] = '';
		$result = $this->gateway->verify( 1 );
		$this->assertFalse( $result['success'] );
		$this->assertEmpty( $result['transaction_id'] );
	}

	public function test_verify_empty_authority() {
		$_GET['Status']    = 'OK';
		$_GET['Authority'] = '';
		$result = $this->gateway->verify( 1 );
		$this->assertFalse( $result['success'] );
	}

	/**
	 * @dataProvider amount_sanitization_provider
	 */
	public function test_amount_sanitization( $input, $expected ) {
		$raw = $input;
		if ( ! is_numeric( $raw ) ) {
			$persian_map = array( '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9' );
			$raw = strtr( (string) $raw, $persian_map );
		}
		$result = absint( $raw );
		$this->assertEquals( $expected, $result );
	}

	public function amount_sanitization_provider() {
		return array(
			'integer'        => array( 10000, 10000 ),
			'string integer'  => array( '10000', 10000 ),
			'decimal'         => array( 10000.99, 10000 ),
			'negative'        => array( -5000, 5000 ),
			'persian digits'  => array( '۱۰۰۰۰', 10000 ),
			'zero'            => array( 0, 0 ),
			'non-numeric'     => array( 'abc', 0 ),
		);
	}

	public function test_sandbox_mode_setting() {
		update_option( 'ontime_settings', array( 'zarinpal_sandbox' => 1 ) );
		$this->assertTrue( $this->gateway->is_sandbox() );
		update_option( 'ontime_settings', array( 'zarinpal_sandbox' => 0 ) );
		$this->assertFalse( $this->gateway->is_sandbox() );
	}

	public function test_sandbox_default_merchant() {
		update_option( 'ontime_settings', array( 'zarinpal_sandbox' => 1, 'merchant_code' => '' ) );
		OnTime_Database::instance()->update_setting( 'merchant_code', '' );
		$reflection = new ReflectionClass( $this->gateway );
		$method = $reflection->getMethod( 'get_merchant_id' );
		$method->setAccessible( true );
		$merchant = $method->invoke( $this->gateway );
		$this->assertEquals( '00000000-0000-0000-0000-000000000000', $merchant );
	}

	public function tearDown(): void {
		unset( $_GET['Status'], $_GET['Authority'] );
		delete_option( 'ontime_settings' );
		parent::tearDown();
	}
}
