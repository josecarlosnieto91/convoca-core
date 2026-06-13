<?php
/**
 * Uninstall handler for Convoca Common Utilities.
 *
 * @package Convoca\Core
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Clean up options.
$options = array(
	'conv_common_db_version',
	'conv_db_version',
	'conv_webhooks',
	'conv_settings',
	'conv_persistent_salt',
	'conv_template_version',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// Clean up cron jobs.
wp_clear_scheduled_hook( 'conv_log_cleanup' );
wp_clear_scheduled_hook( 'conv_log_purge' );

// Clean up transients.
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_conv_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_conv_%'" );

// Clean up custom tables.
$tables_convoca = array( 'convoca_logs', 'convoca_webhook_retries', 'convoca_locks' );
foreach ( $tables_convoca as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" );
}

// Also clean legacy conv_ prefixed tables.
$tables_legacy = array( 'conv_logs', 'conv_webhook_retries', 'conv_locks' );
foreach ( $tables_legacy as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" );
}
