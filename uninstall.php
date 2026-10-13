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

// ─── Modo conservar datos ───
// Dos formas de pedirlo, y este fichero solo miraba una:
//   1. La constante CONVOCA_KEEP_DATA_ON_UNINSTALL en wp-config.php (despliegues).
//   2. La opción de la interfaz, convoca_uninstall_keep_data.
// La segunda es la que ofrece includes/Uninstall_Settings.php, que dice de sí misma que «todos los
// plugins leen esta misma opción»: los otros seis la respetan y este no, así que con la casilla
// marcada el núcleo borraba sus tablas igualmente (medido en un WordPress limpio el 13/10/2026).
if (
	( defined( 'CONVOCA_KEEP_DATA_ON_UNINSTALL' ) && CONVOCA_KEEP_DATA_ON_UNINSTALL )
	|| 1 === (int) get_option( 'convoca_uninstall_keep_data', 0 )
) {
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
// Faltaba: se programa en convoca-core.php y seguía disparándose tras desinstalar.
wp_clear_scheduled_hook( 'convoca_security_digest' );
// Y estos dos, del módulo de licencias/accesos: los encontró la prueba de contrato (13/10/2026).
wp_clear_scheduled_hook( 'convoca_continue_access_codes' );
wp_clear_scheduled_hook( 'convoca_license_validate' );

// Clean up transients.
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_conv_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_conv_%'" );

// Clean up custom tables.
$tables_convoca = array( 'convoca_logs', 'convoca_webhook_retries', 'convoca_locks' );
foreach ( $tables_convoca as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" );
}

// Tablas de la etapa anterior al cambio de nombre del producto (prefijo `conv_`).
// El array anterior traía los mismos nombres `convoca_*`, así que este bucle no limpiaba nada
// distinto y el comentario prometía algo que no ocurría. Comprobado el 13/10/2026 en las bases
// de los tres sitios: no hay ninguna tabla `conv_*`, así que hoy no encuentra nada; se deja como
// red por si quedara una instalación antigua.
$tables_legacy = array( 'logs', 'webhook_retries', 'locks' );
foreach ( $tables_legacy as $sufijo ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}conv_{$sufijo}" );
}
