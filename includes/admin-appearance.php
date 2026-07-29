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

/* ── Admin Appearance (Prompts 19, 20) ────────────────── */

/**
 * Hide Screen Options tab on Convoca custom admin pages.
 */
function convoca_hide_screen_options( bool $show_screen, \WP_Screen $screen ): bool {
	$convoca_slugs = array( 'conv-', 'convoca-' );
	foreach ( $convoca_slugs as $prefix ) {
		if ( str_contains( $screen->id, $prefix ) || str_contains( $screen->base ?? '', $prefix ) ) {
			return false;
		}
	}
	return $show_screen;
}

/**
 * Custom admin footer text.
 */
function convoca_admin_footer( string $text ): string {
	return sprintf(
		'© %d <a href="%s" target="_blank">' . get_bloginfo('name') . '</a> — %s',
		wp_date( 'Y' ),
		apply_filters( 'convoca_admin_appearance_url', 'https://getconvoca.app' ), 
		__( 'Plataforma de gestión de la asociación.', 'convoca-core' )
	);
}

/**
 * Replace WP version in footer with Convoca version.
 */
function convoca_admin_footer_version( string $version ): string {
	return 'Convoca v' . CONVOCA_COMMON_VERSION;
}

/**
 * Remove the Help tab on Convoca admin pages.
 */
function convoca_remove_help_tab(): void {
	$screen = get_current_screen();
	if ( ! $screen ) {
		return;
	}
	$convoca_slugs = array( 'conv-', 'convoca-' );
	foreach ( $convoca_slugs as $prefix ) {
		if ( str_contains( $screen->id, $prefix ) || str_contains( $screen->base ?? '', $prefix ) ) {
			$screen->remove_help_tabs();
			return;
		}
	}
}

/**
 * Remove the submitdiv metabox from all managed CPTs.
 */
function convoca_remove_submitdiv(): void {
	$cpts = array( 'miembro', 'actividad', 'inscripcion', 'pago', 'centro_turno', 'convoca_evaluacion', 'proyecto', 'registro_hora', 'convoca_documento' );
	foreach ( $cpts as $cpt ) {
		remove_meta_box( 'submitdiv', $cpt, 'side' );
	}
}
add_action( 'admin_head', 'Convoca\\Core\\convoca_remove_submitdiv' );

/**
 * Customize the "New" admin bar menu to point to custom editors.
 */
add_action( 'admin_bar_menu', 'Convoca\\Core\\convoca_customize_new_menu', 999 );

function convoca_customize_new_menu( \WP_Admin_Bar $wp_admin_bar ): void {
	$custom_links = array(
		'new-miembro'       => admin_url( 'admin.php?page=conv-member-editor' ),
		'new-actividad'     => admin_url( 'admin.php?page=convoca-actividad-editor' ),
		'new-proyecto'      => admin_url( 'admin.php?page=conv-proyecto-editor' ),
		'new-registro_hora' => admin_url( 'admin.php?page=conv-horas-editor' ),
		'new-centro_turno'  => admin_url( 'edit.php?post_type=centro_turno&page=convoca_shifts_turno_rapido' ),
	);

	$remove_nodes = array( 'new-inscripcion', 'new-pago', 'new-conv_evaluacion', 'new-convoca_documento' );

	foreach ( $custom_links as $node_id => $url ) {
		$node = $wp_admin_bar->get_node( $node_id );
		if ( $node ) {
			$node->href = $url;
			$wp_admin_bar->add_node( $node );
		}
	}

	foreach ( $remove_nodes as $node_id ) {
		$wp_admin_bar->remove_node( $node_id );
	}
}

/**
 * Add wizard link to admin bar.
 */
function convoca_add_wizard_link( \WP_Admin_Bar $wp_admin_bar ): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$wp_admin_bar->add_node(
		array(
			'id'     => 'conv-wizard',
			'title'  => '🔧 ' . esc_html__( 'Asistente Convoca', 'convoca-core' ),
			'href'   => admin_url( 'admin.php?page=conv-setup-wizard' ),
			'parent' => 'top-secondary',
		)
	);
}

/**
 * Generate and stream a PDF from a list table.
 *
 * @param string $title   Title of the report.
 * @param array  $headers Column headers.
 * @param array  $rows    Data rows (arrays of strings).
 * @param string $filename Output filename (without .pdf).
 */
function convoca_export_pdf( string $title, array $headers, array $rows, string $filename ): void {
	if ( ! class_exists( 'Dompdf\Dompdf' ) ) {
		wp_die( esc_html__( 'La librería Dompdf no está instalada. Contacta con el administrador.', 'convoca-core' ) );
	}

	$html  = '<html><head><meta charset="UTF-8"><style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10pt; color: #333; }
        h1 { font-size: 18pt; color: #320028; margin-bottom: 5px; }
        .subtitle { color: #666; font-size: 9pt; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #320028; color: #fff; padding: 8px 10px; text-align: left; font-size: 9pt; }
        td { padding: 6px 10px; border-bottom: 1px solid #e0e0e0; font-size: 9pt; }
        tr:nth-child(even) td { background: #f8f8f8; }
        .footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 8pt; color: #999; border-top: 1px solid #e0e0e0; padding-top: 5px; }
    </style></head><body>';
	$html .= '<h1>' . esc_html( $title ) . '</h1>';
	$html .= '<div class="subtitle">' . esc_html__( 'Generado el', 'convoca-core' ) . ' ' . wp_date( 'd/m/Y H:i' ) . '</div>';
	$html .= '<table><thead><tr>';
	foreach ( $headers as $h ) {
		$html .= '<th>' . esc_html( $h ) . '</th>';
	}
	$html .= '</tr></thead><tbody>';
	foreach ( $rows as $row ) {
		$html .= '<tr>';
		foreach ( $row as $cell ) {
			$html .= '<td>' . esc_html( $cell ) . '</td>';
		}
		$html .= '</tr>';
	}
	$html .= '</tbody></table>';
	$html .= '<div class="footer">' . get_bloginfo( 'name' ) . ' — ' . get_bloginfo('name') . ' — ' . wp_date( 'Y' ) . '</div>';
	$html .= '</body></html>';

	$dompdf_options = new \Dompdf\Options();
	$dompdf_options->set( 'defaultFont', 'Helvetica' );
	$dompdf_options->set( 'isRemoteEnabled', false );
	$dompdf_options->set( 'isPhpEnabled', false );
	$dompdf_options->set( 'isJavascriptEnabled', false );

	try {
		$dompdf = new \Dompdf\Dompdf( $dompdf_options );
		$dompdf->loadHtml( $html );
		$dompdf->setPaper( 'A4', 'landscape' );
		$dompdf->render();
	} catch ( \Throwable $e ) {
		\Convoca\Core\Logger::error( 'Error generando PDF export: ' . $e->getMessage(), 'System' );
		wp_die( esc_html__( 'Error al generar el PDF. Contacta con el administrador.', 'convoca-core' ) );
	}

	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '-' . wp_date( 'Y-m-d' ) . '.pdf"' );
	header( 'Pragma: no-cache' );
	echo $dompdf->output(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — binary PDF output, escaping would corrupt the file
	exit;
}

/**
 * Admin POST handler: Export payments as PDF.
 */
add_action(
	'admin_post_convoca_export_payments_pdf',
	function () {
		if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'convoca_gateway_export_payments_pdf' ) ) {
			wp_die( esc_html__( 'Nonce inválido.', 'convoca-core' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos.', 'convoca-core' ) );
		}

		$posts   = get_posts(
			array(
				'post_type'      => 'pago',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
			)
		);
		$headers = array( __( 'Order ID', 'convoca-core' ), __( 'Importe', 'convoca-core' ), __( 'Estado', 'convoca-core' ), __( 'Método', 'convoca-core' ), __( 'Fecha', 'convoca-core' ) );
		$rows    = array();

		if ( class_exists( '\\Convoca\\Gateway\\CPT_Pago' ) ) {
			foreach ( $posts as $p ) {
				$m      = \Convoca\Gateway\CPT_Pago::get_meta( $p->ID );
				$rows[] = array(
					$m['order_id'] ?? '',
					\Convoca\Gateway\CPT_Pago::format_amount( (int) ( $m['amount_cents'] ?? 0 ) ),
					$m['status'] ?? '',
					$m['method'] ?? '',
					$m['created_at'] ?? '',
				);
			}
		}

		convoca_export_pdf( __( 'Listado de Pagos', 'convoca-core' ), $headers, $rows, 'pagos-convoca' );
	}
);

/**
 * Centralized Logs page.
 */
function convoca_logs_page(): void {
	if ( ! current_user_can( 'manage_convoca_logs' ) && ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'No tienes permisos.', 'convoca-core' ) );
	}
	// [PSR-4] Admin_Logs_List is autoloaded from includes/Admin_Logs_List.php
	$table = new \Convoca\Core\Admin_Logs_List();
	$table->prepare_items();
	?>
	<div class="wrap">
		<h1>📋 <?php esc_html_e( 'Registros del Sistema', 'convoca-core' ); ?></h1>
		<?php if ( $table->approx_total ) : ?>
			<p class="description"><?php esc_html_e( '⚠️ El contador de registros es aproximado para tablas grandes (>10.000 filas). Usa filtros para obtener un recuento exacto.', 'convoca-core' ); ?></p>
		<?php endif; ?>
		<form method="get">
			<input type="hidden" name="page" value="conv-logs-central">
			<?php $table->search_box( __( 'Buscar en logs', 'convoca-core' ), 'log_search' ); ?>
			<?php $table->display(); ?>
		</form>
	</div>
	<div id="conv-log-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:100000;align-items:center;justify-content:center;" onclick="this.style.display='none'">
		<div style="background:#fff;border-radius:8px;max-width:600px;width:90%;max-height:80vh;overflow:auto;padding:24px;box-shadow:0 4px 24px rgba(0,0,0,0.2);" onclick="event.stopPropagation()">
			<h3 style="margin-top:0;"><?php esc_html_e( 'Detalle del Log', 'convoca-core' ); ?></h3>
			<pre id="conv-log-message-content" style="white-space:pre-wrap;word-break:break-word;background:#f8fafc;padding:12px;border-radius:4px;font-size:13px;line-height:1.5;"></pre>
			<button style="margin-top:12px;" class="button" onclick="document.getElementById('conv-log-modal').style.display='none'"><?php esc_html_e( 'Cerrar', 'convoca-core' ); ?></button>
		</div>
	</div>
	<script>
	(function(){
		var modal = document.getElementById('conv-log-modal');
		var content = document.getElementById('conv-log-message-content');
		document.querySelectorAll('.view-log-detail').forEach(function(el){
			el.style.color = '#3b82f6';
			el.style.cursor = 'pointer';
			el.addEventListener('click', function(e){
				e.preventDefault();
				content.textContent = el.getAttribute('data-log-message');
				modal.style.display = 'flex';
			});
		});
	})();
	</script>
	<?php
}

/**

/**
 * Unified Dashboard page.
 */
function convoca_dashboard_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'No tienes permisos.', 'convoca-core' ) );
	}

	$force = isset( $_GET['refresh'] );
	$data  = \Convoca\Core\Admin_Analytics::get_all( $force );
	$m     = $data['members'];
	$i     = $data['inscriptions'];
	$p     = $data['payments'];
	$t     = $data['turnos'];
	$trend = $data['trends'];

	\Convoca\Core\Admin_Analytics::enqueue_chartjs();

	$cards = array(
		array(
			'icon'  => '👥',
			'label' => __( 'Socios activos', 'convoca-core' ),
			'value' => $m['activos'],
			'total' => $m['total'],
			'url'   => admin_url( 'admin.php?page=conv-members' ),
		),
		array(
			'icon'  => '🆕',
			'label' => __( 'Altas este mes', 'convoca-core' ),
			'value' => $m['nuevos_mes'],
			'total' => '',
			'url'   => admin_url( 'admin.php?page=conv-members' ),
		),
		array(
			'icon'  => '📝',
			'label' => __( 'Inscripciones confirmadas', 'convoca-core' ),
			'value' => $i['confirmadas'],
			'total' => $i['total'],
			'url'   => admin_url( 'admin.php?page=convoca-enroll-inscripciones' ),
		),
		array(
			'icon'  => '⏳',
			'label' => __( 'En lista de espera', 'convoca-core' ),
			'value' => $i['lista_espera'],
			'total' => '',
			'url'   => admin_url( 'admin.php?page=convoca-enroll-inscripciones' ),
		),
		array(
			'icon'  => '💰',
			'label' => __( 'Ingresos este mes', 'convoca-core' ),
			'value' => $p['total_mes_fmt'],
			'total' => '',
			'url'   => admin_url( 'edit.php?post_type=pago' ),
		),
		array(
			'icon'  => '🟡',
			'label' => __( 'Turnos sin cubrir', 'convoca-core' ),
			'value' => $t['disponibles'],
			'total' => $t['total'],
			'url'   => admin_url( 'edit.php?post_type=centro_turno' ),
		),
	);
	?>
	<div class="wrap">
		<h1>📊 <?php esc_html_e( 'Panel de Control Convoca', 'convoca-core' ); ?></h1>
		<p>
			<a href="<?php echo esc_url( add_query_arg( 'refresh', '1' ) ); ?>" class="convoca-btn convoca-btn-outline">🔄 <?php esc_html_e( 'Actualizar datos', 'convoca-core' ); ?></a>
			<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=convoca_generate_memory' ), 'convoca_generate_memory' ) ); ?>" class="convoca-btn convoca-btn-primary" style="margin-left:8px;">📄 <?php esc_html_e( 'Generar memoria PDF', 'convoca-core' ); ?></a>
		</p>

		<div class="conv-analytics-cards">
			<?php foreach ( $cards as $card ) : ?>
				<a href="<?php echo esc_url( $card['url'] ); ?>" class="conv-analytics-card">
					<div class="conv-card-icon"><?php echo esc_html( $card['icon'] ); ?></div>
					<div class="conv-card-value"><?php echo esc_html( $card['value'] ); ?></div>
					<?php
					if ( $card['total'] ) :
						?>
						<div class="conv-card-total">/ <?php echo esc_html( $card['total'] ); ?> total</div><?php endif; ?>
					<div class="conv-card-label"><?php echo esc_html( $card['label'] ); ?></div>
				</a>
			<?php endforeach; ?>
		</div>

		<div class="conv-analytics-charts">
			<div class="conv-chart-card">
				<h3>📈 <?php esc_html_e( 'Tendencia (6 meses)', 'convoca-core' ); ?></h3>
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — chart.js data is numeric, render_chart returns safe HTML with inline JS
				echo \Convoca\Core\Admin_Analytics::render_chart(
					'chartTrends',
					'line',
					$trend['labels'],
					array(
						array(
							'label'  => 'Altas socios',
							'data'   => $trend['members'],
							'color'  => '#320028',
							'border' => '#320028',
						),
						array(
							'label'  => 'Inscripciones',
							'data'   => $trend['inscriptions'],
							'color'  => 'rgba(255,135,0,0.2)',
							'border' => '#FF8700',
						),
					)
				);
				?>
			</div>
			<div class="conv-chart-card">
				<h3>💰 <?php esc_html_e( 'Ingresos mensuales', 'convoca-core' ); ?></h3>
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — chart.js data is numeric, render_chart returns safe HTML with inline JS
				echo \Convoca\Core\Admin_Analytics::render_chart(
					'chartPayments',
					'bar',
					$trend['labels'],
					array(
						array(
							'label'  => 'Ingresos (€)',
							'data'   => $trend['payments'],
							'color'  => '#059669',
							'border' => '#059669',
						),
					)
				);
				?>
			</div>
			<div class="conv-chart-card">
				<h3>📊 <?php esc_html_e( 'Inscripciones por estado', 'convoca-core' ); ?></h3>
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — chart.js data is numeric, render_chart returns safe HTML with inline JS
				echo \Convoca\Core\Admin_Analytics::render_chart(
					'chartInscriptionStates',
					'doughnut',
					array( 'Confirmadas', 'Pendientes', 'Espera', 'Canceladas' ),
					array(
						array(
							'label' => 'Estado',
							'data'  => array( $i['confirmadas'], $i['pendientes'], $i['lista_espera'], $i['canceladas'] ),
							'color' => array( '#059669', '#f59e0b', '#3b82f6', '#ef4444' ),
						),
					)
				);
				?>
			</div>
			<div class="conv-chart-card">
				<h3>💳 <?php esc_html_e( 'Métodos de pago (mes)', 'convoca-core' ); ?></h3>
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — chart.js data is numeric, render_chart returns safe HTML with inline JS
				echo \Convoca\Core\Admin_Analytics::render_chart(
					'chartPayMethods',
					'doughnut',
					array( 'Tarjeta', 'Bizum', 'Otros' ),
					array(
						array(
							'label' => 'Método',
							'data'  => array( $p['methods_pct']['tarjeta'], $p['methods_pct']['bizum'], $p['methods_pct']['otros'] ?? 0 ),
							'color' => array( '#0073aa', '#46b450', '#94a3b8' ),
						),
					)
				);
				?>
			</div>
			<div class="conv-chart-card conv-chart-card--wide">
				<h3>📋 <?php esc_html_e( 'Últimos 7 días — Pagos', 'convoca-core' ); ?></h3>
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — chart.js data is numeric, render_chart returns safe HTML with inline JS
				echo \Convoca\Core\Admin_Analytics::render_chart(
					'chart7days',
					'bar',
					array_column( $p['last_7_days'], 'label' ),
					array(
						array(
							'label'  => 'Importe (€)',
							'data'   => array_map( fn( $d ) => round( $d['total'] / 100, 2 ), $p['last_7_days'] ),
							'color'  => '#FF8700',
							'border' => '#e67a00',
						),
					),
					array( 'height' => '160px' )
				);
				?>
			</div>
		</div>
	</div>

	<style>
	.conv-analytics-cards {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
		gap: 16px;
		margin: 20px 0;
	}
	.conv-analytics-card {
		display: block;
		background: #fff;
		border-radius: 12px;
		padding: 20px;
		box-shadow: 0 2px 8px rgba(0,0,0,.06);
		border: 1px solid #e2e8f0;
		text-decoration: none;
		transition: transform .2s, box-shadow .2s;
	}
	.conv-analytics-card:hover {
		transform: translateY(-3px);
		box-shadow: 0 6px 16px rgba(0,0,0,.1);
	}
	.conv-card-icon { font-size: 28px; margin-bottom: 6px; }
	.conv-card-value { font-size: 26px; font-weight: 800; color: #320028; line-height: 1.2; }
	.conv-card-total { font-size: 13px; color: #94a3b8; }
	.conv-card-label { font-size: 12px; color: #64748b; margin-top: 4px; }

	.conv-analytics-charts {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
		gap: 20px;
		margin-top: 24px;
	}
	.conv-chart-card {
		background: #fff;
		border-radius: 12px;
		padding: 20px;
		box-shadow: 0 2px 8px rgba(0,0,0,.06);
		border: 1px solid #e2e8f0;
	}
	.conv-chart-card h3 {
		margin: 0 0 16px;
		font-size: 15px;
		color: #320028;
		border-bottom: 2px solid #FF8700;
		padding-bottom: 8px;
	}
	.conv-chart-card--wide { grid-column: 1 / -1; }
	@media (max-width: 600px) {
		.conv-analytics-charts { grid-template-columns: 1fr; }
		.conv-analytics-cards { grid-template-columns: repeat(2, 1fr); }
	}
	</style>
	<?php
}

function convoca_build_metrics(): array {
	global $wpdb;
	$metrics = array();

	// Members.
	if ( class_exists( '\Convoca\Members\CPT_Miembro' ) && class_exists( '\Convoca\Members\Estados' ) ) {
		$counts = array();
		foreach ( \Convoca\Members\Estados::LABELS as $slug => $label ) {
			$counts[ $slug ] = ( new \WP_Query(
				array(
					'post_type'      => 'miembro',
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_query'     => array(
						array(
							'key'   => '_convoca_estado_miembro',
							'value' => $slug,
						),
					),
				)
			) )->found_posts;
		}
		$metrics['members_total']   = array_sum( $counts );
		$metrics['members_activos'] = $counts['activo'] ?? 0;
		$metrics['members_url']     = admin_url( 'admin.php?page=conv-members' );
	}

	$metrics['members_new_month'] = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'miembro' AND post_status = 'publish' AND MONTH(post_date) = MONTH(NOW()) AND YEAR(post_date) = YEAR(NOW())"
		)
	);

	if ( class_exists( '\Convoca\Enroll\CPT_Inscripcion' ) ) {
		$pendientes_pago                          = new \WP_Query(
			array(
				'post_type'      => 'inscripcion',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => '_convoca_estado',
						'value' => 'pendiente_pago',
					),
				),
			)
		);
		$metrics['inscripciones_pendientes_pago'] = $pendientes_pago->found_posts;
		$metrics['inscripciones_url']             = admin_url( 'admin.php?page=convoca-core-enroll' );
	}

	if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}postmeta'" ) ) {
		$metrics['payments_month'] = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(meta_value + 0) FROM {$wpdb->postmeta} pm 
             JOIN {$wpdb->posts} p ON p.ID = pm.post_id 
             WHERE pm.meta_key = '_convoca_amount_cents' AND p.post_type = 'pago' 
             AND MONTH(p.post_date) = MONTH(NOW()) AND YEAR(p.post_date) = YEAR(NOW())"
			)
		) / 100;
		$metrics['payments_url']   = admin_url( 'admin.php?page=conv-gateway-payments' );
	}

	$metrics['turnos_sin_cubrir'] = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} pm 
         JOIN {$wpdb->posts} p ON p.ID = pm.post_id 
         WHERE pm.meta_key = '_estado' AND pm.meta_value = 'abierto_disponible' 
         AND p.post_type = 'centro_turno' AND p.post_status = 'publish'
         AND p.post_date >= NOW()"
		)
	);
	$metrics['turnos_url']        = admin_url( 'edit.php?post_type=centro_turno&page=convoca-shifts-turnos-list' );

	return $metrics;
}