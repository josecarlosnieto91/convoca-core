<?php

/**
 * Convoca Core
 *
 * @package    Convoca\Core
 * @subpackage Includes
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 */

namespace Convoca\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ── Admin Dashboard ─────────────────────────────────── */
add_action( 'admin_menu', 'Convoca\Core\convoca_register_global_menu' );
function convoca_register_global_menu(): void {
	add_menu_page(
		'Convoca',
		'Convoca',
		'manage_options',
		'convoca-core',
		'Convoca\Core\convoca_health_page',
		'dashicons-admin-site-alt3',
		3
	);

	add_submenu_page(
		'convoca-core',
		__( 'Salud del Sistema', 'convoca-core' ),
		__( 'Salud del Sistema', 'convoca-core' ),
		'manage_options',
		'convoca-core',
		'Convoca\Core\convoca_health_page'
	);

	// El submenu 'conv-setup-wizard' se registra desde Admin_Setup_Wizard.
	add_submenu_page(
		'convoca-core',
		__( 'Panel de Control', 'convoca-core' ),
		__( 'Panel de Control', 'convoca-core' ),
		'manage_options',
		'conv-dashboard',
		'Convoca\Core\convoca_dashboard_page'
	);

	add_submenu_page(
		'convoca-core',
		__( 'Registros del Sistema', 'convoca-core' ),
		__( 'Registros', 'convoca-core' ),
		'common_view_logs',
		'conv-logs-central',
		'Convoca\Core\convoca_logs_page'
	);
}


/**
 * Consolidated System Health page.
 */
function convoca_health_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'No tienes permisos.', 'convoca-core' ) );
	}

	$force = isset( $_GET['force'] ) && $_GET['force'] === '1';

	echo '<div class="wrap">';
	echo '<h1>🔍 ' . esc_html__( 'Salud del Sistema Convoca', 'convoca-core' ) . '</h1>';
	echo '<p>' . esc_html__( 'Diagnóstico consolidado de todos los plugins del ecosistema.', 'convoca-core' ) . '</p>';
	echo '<p><a href="' . esc_url( add_query_arg( 'force', '1' ) ) . '" class="convoca-btn convoca-btn-outline">🔄 ' . esc_html__( 'Forzar comprobación', 'convoca-core' ) . '</a></p>';
	echo '<hr>';

	$all_checks   = array();
	$plugin_names = array();

	// ── Members ──
	if ( class_exists( '\\Convoca\\Members\\Admin_Settings' ) ) {
		try {
			$ref = new \ReflectionMethod( '\\Convoca\\Members\\Admin_Settings', 'get_system_checks' );
			$ref->setAccessible( true );
			$settings = new \Convoca\Members\Admin_Settings();
			$checks   = $ref->invoke( $settings, $force );
			if ( is_array( $checks ) ) {
				$all_checks              = array_merge( $all_checks, $checks );
				$plugin_names['members'] = __( 'Convoca Members', 'convoca-core' );
			}
		} catch ( \Exception $e ) {
			$all_checks[] = array(
				'title'   => 'Convoca Members',
				'status'  => 'error',
				'message' => $e->getMessage(),
			);
		}
	} else {
		$all_checks[] = array(
			'title'   => 'Convoca Members',
			'status'  => 'warning',
			'message' => __( 'Plugin no activo.', 'convoca-core' ),
		);
	}

	// ── Enroll ──
	if ( class_exists( '\\Convoca\\Enroll\\Admin_Settings' ) ) {
		try {
			$ref = new \ReflectionMethod( '\\Convoca\\Enroll\\Admin_Settings', 'get_system_checks' );
			$ref->setAccessible( true );
			$checks = $ref->invoke( null, $force );
			if ( is_array( $checks ) ) {
				$all_checks             = array_merge( $all_checks, $checks );
				$plugin_names['enroll'] = __( 'Convoca Enroll', 'convoca-core' );
			}
		} catch ( \Exception $e ) {
			$all_checks[] = array(
				'title'   => 'Convoca Enroll',
				'status'  => 'error',
				'message' => $e->getMessage(),
			);
		}
	} else {
		$all_checks[] = array(
			'title'   => 'Convoca Enroll',
			'status'  => 'warning',
			'message' => __( 'Plugin no activo.', 'convoca-core' ),
		);
	}

	// ── Gateway ──
	if ( class_exists( '\\Convoca\\Gateway\\Diagnostic' ) ) {
		$results = \Convoca\Gateway\Diagnostic::run_all( $force );
		foreach ( $results as $r ) {
			$all_checks[] = array(
				'title'   => ( $plugin_names['gateway'] ?? 'Gateway' ) . ': ' . $r['title'],
				'status'  => $r['severity'] === 'ok' ? 'ok' : $r['severity'],
				'message' => $r['message'],
				'fix'     => $r['fix'] ?? '',
			);
		}
		$plugin_names['gateway'] = __( 'Convoca Gateway', 'convoca-core' );
	} else {
		$all_checks[] = array(
			'title'   => 'Convoca Gateway',
			'status'  => 'warning',
			'message' => __( 'Plugin no activo.', 'convoca-core' ),
		);
	}

	// ── Turnos ──
	if ( function_exists( 'convoca_shifts_get_system_checks' ) ) {
		$checks = convoca_shifts_get_system_checks( $force );
		if ( is_array( $checks ) ) {
			foreach ( $checks as $c ) {
				$all_checks[] = array(
					'title'   => 'Turnos: ' . $c['title'],
					'status'  => $c['status'],
					'message' => $c['message'],
					'fix'     => $c['fix'] ?? '',
				);
			}
			$plugin_names['turnos'] = __( 'Convoca Shifts', 'convoca-core' );
		}
	} else {
		$all_checks[] = array(
			'title'   => 'Convoca Shifts',
			'status'  => 'warning',
			'message' => __( 'Plugin no activo o sin función de diagnóstico.', 'convoca-core' ),
		);
	}

	// ── Common self-check ──
	$all_checks[] = array(
		'title'   => 'Convoca Common — Versión',
		'status'  => 'ok',
		'message' => 'v' . CONVOCA_COMMON_VERSION,
	);
	global $wpdb;
	$tables = array( 'convoca_logs', 'convoca_locks', 'convoca_webhook_retries' );
	foreach ( $tables as $t ) {
		$exists       = $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}{$t}'" ) === $wpdb->prefix . $t;
		/* translators: %s: database table name */
		$all_checks[] = array(
			'title'   => sprintf( __( 'Tabla %s', 'convoca-core' ), $t ),
			'status'  => $exists ? 'ok' : 'error',
			'message' => $exists ? __( 'OK', 'convoca-core' ) : __( 'No encontrada. Desactiva y reactiva Convoca Common.', 'convoca-core' ),
		);
	}

	\Convoca\Core\Utils::render_diagnostic_panel( $all_checks, __( 'Diagnóstico completo del ecosistema', 'convoca-core' ) );

	echo '</div>';
}

// Notifications list page.
add_action( 'admin_menu', 'Convoca\Core\convoca_notifications_menu' );
function convoca_notifications_menu(): void {
	add_submenu_page(
		null,
		__( 'Notificaciones', 'convoca-core' ),
		__( 'Notificaciones', 'convoca-core' ),
		'manage_options',
		'conv-notificaciones',
		'Convoca\Core\convoca_notifications_page'
	);
}

function convoca_notifications_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'Access denied.', 'convoca-core' ) );
	}
	$all      = get_user_meta( get_current_user_id(), Notifications::META_KEY, true ) ?: array();
	$paged    = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
	$per_page = 20;
	$total    = count( $all );
	$items    = array_slice( $all, ( $paged - 1 ) * $per_page, $per_page );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Notificaciones', 'convoca-core' ); ?></h1>
		<p><?php printf( esc_html__( 'Total: %d notificaciones', 'convoca-core' ), $total ); ?></p>
		<table class="wp-list-table widefat fixed striped">
			<thead><tr><th><?php esc_html_e( 'Tipo', 'convoca-core' ); ?></th><th><?php esc_html_e( 'Mensaje', 'convoca-core' ); ?></th><th><?php esc_html_e( 'Fecha', 'convoca-core' ); ?></th><th><?php esc_html_e( 'Estado', 'convoca-core' ); ?></th></tr></thead>
			<tbody>
				<?php if ( empty( $items ) ) : ?>
				<tr><td colspan="4"><?php esc_html_e( 'No hay notificaciones.', 'convoca-core' ); ?></td></tr>
					<?php
				else :
					foreach ( $items as $n ) :
						$icon = match ( $n['type'] ?? 'info' ) {
							'success' => '✅', 'warning' => '⚠️', 'error' => '❌', default => 'ℹ️' };
						?>
				<tr>
					<td><?php echo esc_html( $icon ); ?></td>
					<td><a href="<?php echo esc_url( $n['url'] ); ?>"><?php echo esc_html( $n['title'] ); ?></a></td>
					<td><?php echo esc_html( $n['time'] ); ?></td>
					<td><?php echo empty( $n['read'] ) ? '<span style="color:#d63638;font-weight:bold;">' . esc_html__( 'No leída', 'convoca-core' ) . '</span>' : '<span style="color:#46b450;">' . esc_html__( 'Leída', 'convoca-core' ) . '</span>'; ?></td>
				</tr>
									<?php
				endforeach;
endif;
				?>
			</tbody>
		</table>
		<?php if ( $total > $per_page ) : ?>
		<div class="tablenav"><div class="tablenav-pages">
			<?php
			echo wp_kses_post(
				paginate_links(
				array(
					'base'    => add_query_arg( 'paged', '%#%' ),
					'format'  => '',
					'total'   => ceil( $total / $per_page ),
					'current' => $paged,
				)
			) );
			?>
		</div></div>
		<?php endif; ?>
	</div>
	<?php
}

// Enqueue common assets (Public).
function convoca_common_enqueue_assets(): void {
	wp_enqueue_style(
		'convoca-core',
		CONVOCA_COMMON_URL . 'assets/css/convoca-common.css',
		array(),
		CONVOCA_COMMON_VERSION
	);

	wp_enqueue_script(
		'convoca-common-js',
		CONVOCA_COMMON_URL . 'assets/js/convoca-common.js',
		array(),
		CONVOCA_COMMON_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'Convoca\\Core\\convoca_common_enqueue_assets' );

// Enqueue common assets (Admin).
function convoca_common_enqueue_admin_assets(): void {
	// Also load the CSS on admin.
	convoca_common_enqueue_assets();

	wp_enqueue_script(
		'convoca-common-admin-js',
		CONVOCA_COMMON_URL . 'assets/js/convoca-common-admin.js',
		array(),
		CONVOCA_COMMON_VERSION,
		true
	);
}
add_action( 'admin_enqueue_scripts', 'Convoca\\Core\\convoca_common_enqueue_admin_assets' );