<?php
/**
 * Bootstrap for OnTime PHPUnit tests.
 *
 * @package OnTime
 * @since   1.0.0
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "WordPress test suite not found. Please install it:\n";
	echo "  bash bin/install-wp-tests.sh\n";
	exit( 1 );
}

require_once $_tests_dir . '/includes/functions.php';

function _manually_activate_ontime_plugin() {
	require_once dirname( dirname( __FILE__ ) ) . '/ontime/ontime.php';
	if ( function_exists( 'ontime' ) ) {
		ontime();
	}
}

tests_add_filter( 'muplugins_loaded', '_manually_activate_ontime_plugin' );

require_once $_tests_dir . '/includes/bootstrap.php';
