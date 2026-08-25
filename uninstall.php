<?php
/**
 * Uninstall routine for OnTime.
 *
 * Removes plugin options only. Custom tables and appointment data are
 * preserved by default to avoid accidental data loss; site owners who
 * want a full wipe can set the constant ONTIME_PURGE_ALL in wp-config.php.
 *
 * @package OnTime
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$options = array(
	'ontime_db_version',
	'ontime_settings',
);

foreach ( $options as $opt ) {
	delete_option( $opt );
}

if ( defined( 'ONTIME_PURGE_ALL' ) && ONTIME_PURGE_ALL ) {
	$tables = array(
		$wpdb->prefix . 'ontime_appointments',
		$wpdb->prefix . 'ontime_services',
		$wpdb->prefix . 'ontime_staff',
	);
	foreach ( $tables as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS $table" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is constructed from whitelisted prefix.
	}
}
