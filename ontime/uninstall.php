<?php
/**
 * Uninstall OnTime.
 *
 * @package OnTime
 * @since   0.1.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$tables = array(
	$wpdb->prefix . 'ontime_appointments',
	$wpdb->prefix . 'ontime_services',
	$wpdb->prefix . 'ontime_settings',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

delete_option( 'ontime_version' );
delete_option( 'ontime_settings' );
wp_clear_scheduled_hook( 'ontime_daily_cleanup' );
