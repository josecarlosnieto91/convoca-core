<?php

/**
 * Convoca Core
 *
 * @package    Convoca\Core
 * @subpackage Convoca-core
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 */

/**
 * Uninstall handler for Convoca Common Utilities.
 *
 * @package Convoca\Core
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// ─── Keep data mode ───
// Define CONVOCA_KEEP_DATA_ON_UNINSTALL in wp-config.php to preserve all data
// when uninstalling. Useful for temporary deactivation + reactivation.
if ( defined( 'CONVOCA_KEEP_DATA_ON_UNINSTALL' ) && CONVOCA_KEEP_DATA_ON_UNINSTALL ) {
	return;
}

// Clean up options.
$options = array(
	'convoca_common_db_version',
	'convoca_db_version',
	'convoca_webhooks',
	'convoca_settings',
	'convoca_persistent_salt',
	'convoca_template_version',
	'convoca_capabilities_hash',
	'convoca_webhook_retry_limit',
	'convoca_auto_cleanup_days',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// Clean up cron jobs.
wp_clear_scheduled_hook( 'convoca_log_cleanup' );
wp_clear_scheduled_hook( 'convoca_log_purge' );
wp_clear_scheduled_hook( 'convoca_webhook_retry' );

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
$tables_legacy = array( 'convoca_logs', 'convoca_webhook_retries', 'convoca_locks' );
foreach ( $tables_legacy as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" );
}
