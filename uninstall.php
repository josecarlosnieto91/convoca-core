<?php
/**
 * Uninstall handler for Biodevas Common Utilities.
 *
 * @package Convoca\Core
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Clean up options.
$options = array(
	'bdv_common_db_version',
	'bdv_db_version',
	'bdv_webhooks',
	'bdv_settings',
	'bdv_persistent_salt',
	'bdv_template_version',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// Clean up cron jobs.
wp_clear_scheduled_hook( 'bdv_log_cleanup' );
wp_clear_scheduled_hook( 'bdv_log_purge' );

// Clean up transients.
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_bdv_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_bdv_%'" );

// Clean up custom tables.
$tables_biodevas = array( 'biodevas_logs', 'biodevas_webhook_retries', 'biodevas_locks' );
foreach ( $tables_biodevas as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" );
}

// Also clean legacy bdv_ prefixed tables.
$tables_legacy = array( 'bdv_logs', 'bdv_webhook_retries', 'bdv_locks' );
foreach ( $tables_legacy as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" );
}
