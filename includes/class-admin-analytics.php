<?php
/**
 * Admin Analytics — panel de control unificado con gráficas.
 *
 * Proporciona datos agregados de todos los plugins del ecosistema
 * (miembros, inscripciones, pagos, turnos) y helpers para generar
 * gráficas Chart.js.
 *
 * @package Convoca\Core
 */

namespace Convoca\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Analytics {

	/** Cache TTL (seconds). */
	const CACHE_TTL = 300;

	/** Metric groups (used for partial invalidation). */
	const CACHE_PREFIX = 'bdv_analytics_';

	/**
	 * Get all metrics aggregated across plugins.
	 *
	 * @param bool $force_refresh Skip transient cache.
	 * @return array
	 */
	public static function get_all( bool $force_refresh = false ): array {
		$cache_key = self::CACHE_PREFIX . 'all';
		if ( ! $force_refresh ) {
			$data = get_transient( $cache_key );
			if ( false !== $data ) {
				return $data;
			}
		}

		$data = array(
			'members'      => self::get_member_stats(),
			'inscriptions' => self::get_inscription_stats(),
			'payments'     => self::get_payment_stats(),
			'turnos'       => self::get_turno_stats(),
			'trends'       => self::get_trends(),
			'timestamp'    => current_time( 'mysql' ),
		);

		set_transient( $cache_key, $data, self::CACHE_TTL );
		return $data;
	}

	/* ── Members ──────────────────────────────────── */

	/**
	 * Member statistics: counts by estado, new this month, expiring soon.
	 */
	public static function get_member_stats(): array {
		global $wpdb;

		$default = array(
			'total'           => 0,
			'activos'         => 0,
			'pendientes_pago' => 0,
			'pendientes_doc'  => 0,
			'nuevos_mes'      => 0,
			'vencen_7d'       => 0,
		);

		if ( ! class_exists( '\\Convoca\\Members\\CPT_Miembro' ) ) {
			return $default;
		}

		$campo = "{$wpdb->posts}.post_type = 'miembro' AND {$wpdb->posts}.post_status = 'publish'";

		// Active.
		$counts = $wpdb->get_row(
			"SELECT
                SUM(IF(pm.meta_value = 'activo', 1, 0)) AS activos,
                SUM(IF(pm.meta_value = 'pendiente_pago', 1, 0)) AS pendientes_pago,
                SUM(IF(pm.meta_value = 'pendiente_documentacion', 1, 0)) AS pendientes_doc
             FROM {$wpdb->postmeta} pm
             JOIN {$wpdb->posts} ON {$wpdb->posts}.ID = pm.post_id AND $campo
             WHERE pm.meta_key = '_bdv_estado_miembro"
		);

		$total = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'miembro' AND post_status = 'publish'"
		);

		$nuevos_mes = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_type = 'miembro' AND post_status = 'publish'
             AND MONTH(post_date) = MONTH(NOW()) AND YEAR(post_date) = YEAR(NOW())"
		);

		$hoy       = wp_date( 'Y-m-d' );
		$semana    = wp_date( 'Y-m-d', strtotime( '+7 days' ) );
		$vencen_7d = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} pe ON p.ID = pe.post_id AND pe.meta_key = '_bdv_estado_miembro' AND pe.meta_value = 'activo'
             JOIN {$wpdb->postmeta} pr ON p.ID = pr.post_id AND pr.meta_key = '_bdv_fecha_renovacion'
             WHERE p.post_type = 'miembro' AND p.post_status = 'publish'
             AND CAST(pr.meta_value AS DATE) BETWEEN %s AND %s",
				$hoy,
				$semana
			)
		);

		return array(
			'total'           => $total,
			'activos'         => (int) ( $counts->activos ?? 0 ),
			'pendientes_pago' => (int) ( $counts->pendientes_pago ?? 0 ),
			'pendientes_doc'  => (int) ( $counts->pendientes_doc ?? 0 ),
			'nuevos_mes'      => $nuevos_mes,
			'vencen_7d'       => $vencen_7d,
			'activos_pct'     => $total > 0 ? round( ( ( $counts->activos ?? 0 ) / $total ) * 100 ) : 0,
		);
	}

	/* ── Inscriptions ─────────────────────────────── */

	/**
	 * Inscription statistics: by status, occupation %, waitlist.
	 */
	public static function get_inscription_stats(): array {
		global $wpdb;

		$default = array(
			'total'        => 0,
			'confirmadas'  => 0,
			'pendientes'   => 0,
			'lista_espera' => 0,
			'canceladas'   => 0,
			'actividades'  => 0,
			'proximas'     => 0,
		);

		if ( ! class_exists( '\\Convoca\\Enroll\\CPT_Inscripcion' ) ) {
			return $default;
		}

		$stats = $wpdb->get_row(
			"SELECT
                COUNT(*) AS total,
                SUM(IF(pm.meta_value = 'confirmada', 1, 0)) AS confirmadas,
                SUM(IF(pm.meta_value = 'pendiente_pago', 1, 0)) AS pendientes,
                SUM(IF(pm.meta_value = 'lista_espera', 1, 0)) AS lista_espera,
                SUM(IF(pm.meta_value = 'cancelada', 1, 0)) AS canceladas
             FROM {$wpdb->postmeta} pm
             JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = '_bde_estado'
               AND p.post_type = 'inscripcion'
               AND p.post_status = 'publish'"
		);

		// Active activities.
		$actividades = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_type = 'actividad' AND post_status = 'publish'"
		);

		// Upcoming activities (future).
		$proximas = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_bde_fecha_inicio'
             WHERE p.post_type = 'actividad' AND p.post_status IN ('publish','future')
             AND pm.meta_value > NOW()"
		);

		return array(
			'total'        => (int) ( $stats->total ?? 0 ),
			'confirmadas'  => (int) ( $stats->confirmadas ?? 0 ),
			'pendientes'   => (int) ( $stats->pendientes ?? 0 ),
			'lista_espera' => (int) ( $stats->lista_espera ?? 0 ),
			'canceladas'   => (int) ( $stats->canceladas ?? 0 ),
			'actividades'  => $actividades,
			'proximas'     => $proximas,
		);
	}

	/* ── Payments ─────────────────────────────────── */

	/**
	 * Payment statistics: month totals, method breakdown, 7-day history.
	 */
	public static function get_payment_stats(): array {
		global $wpdb;

		$default = array(
			'total_mes'     => 0,
			'total_mes_fmt' => '0,00€',
			'cantidad_mes'  => 0,
			'hoy'           => 0,
			'methods'       => array(
				'tarjeta' => 0,
				'bizum'   => 0,
			),
			'methods_pct'   => array(
				'tarjeta' => 0,
				'bizum'   => 0,
				'otros'   => 0,
			),
			'last_7_days'   => array(),
		);

		if ( ! class_exists( '\\Convoca\\Gateway\\CPT_Pago' ) ) {
			return $default;
		}

		$mes_inicio = strtotime( 'first day of this month' );
		$hoy_inicio = strtotime( 'today' );

		// Paid payments this month.
		$pagos = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, pm2.meta_value AS amount, pm3.meta_value AS method, pm4.meta_value AS paid_at
             FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_bdg_status' AND pm1.meta_value = 'paid'
             JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_bdg_amount_cents'
             LEFT JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = '_bdg_method'
             LEFT JOIN {$wpdb->postmeta} pm4 ON p.ID = pm4.post_id AND pm4.meta_key = '_bdg_paid_at'
             WHERE p.post_type = 'pago' AND p.post_status = 'publish'
               AND p.post_date >= %s",
				wp_date( 'Y-m-d H:i:s', $mes_inicio )
			)
		);

		if ( empty( $pagos ) ) {
			return $default;
		}

		$total_mes = 0;
		$hoy       = 0;
		$methods   = array(
			'tarjeta' => 0,
			'bizum'   => 0,
		);

		foreach ( $pagos as $p ) {
			$amount     = (int) ( $p->amount ?? 0 );
			$total_mes += $amount;
			$method     = $p->method ?? 'otros';
			if ( ! isset( $methods[ $method ] ) ) {
				$methods[ $method ] = 0;
			}
			++$methods[ $method ];

			if ( $p->paid_at && strtotime( $p->paid_at ) >= $hoy_inicio ) {
				++$hoy;
			}
		}

		$total_methods = array_sum( $methods );
		$methods_pct   = array();
		foreach ( $methods as $k => $v ) {
			$methods_pct[ $k ] = $total_methods > 0 ? round( ( $v / $total_methods ) * 100 ) : 0;
		}

		// 7-day history
		$semana_inicio = $hoy_inicio - 6 * DAY_IN_SECONDS;
		$dias          = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(p.post_date) AS day,
                    COUNT(*) AS cnt,
                    COALESCE(SUM(CAST(pm.meta_value AS UNSIGNED)), 0) AS total
             FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} ms ON p.ID = ms.post_id AND ms.meta_key = '_bdg_status' AND ms.meta_value = 'paid'
             JOIN {$wpdb->postmeta} ma ON p.ID = ma.post_id AND ma.meta_key = '_bdg_amount_cents'
             WHERE p.post_type = 'pago' AND p.post_status = 'publish'
               AND p.post_date >= %s
             GROUP BY DATE(p.post_date) ORDER BY day ASC",
				wp_date( 'Y-m-d H:i:s', $semana_inicio )
			)
		);

		$map = array();
		foreach ( $dias as $r ) {
			$map[ $r->day ] = array(
				'count' => (int) $r->cnt,
				'total' => (int) $r->total,
			);
		}

		$semana = array();
		for ( $i = 6; $i >= 0; $i-- ) {
			$ts       = strtotime( "-{$i} days", $hoy_inicio );
			$key      = wp_date( 'Y-m-d', $ts );
			$semana[] = array(
				'label'     => wp_date( 'd M', $ts ),
				'count'     => $map[ $key ]['count'] ?? 0,
				'total'     => $map[ $key ]['total'] ?? 0,
				'total_fmt' => self::format_cents( $map[ $key ]['total'] ?? 0 ),
			);
		}

		return array(
			'total_mes'     => $total_mes,
			'total_mes_fmt' => self::format_cents( $total_mes ),
			'cantidad_mes'  => count( $pagos ),
			'hoy'           => $hoy,
			'methods'       => $methods,
			'methods_pct'   => $methods_pct,
			'last_7_days'   => $semana,
		);
	}

	/* ── Turnos (centro-social) ───────────────────── */

	public static function get_turno_stats(): array {
		global $wpdb;

		$stats = $wpdb->get_row(
			"SELECT
                COUNT(*) AS total,
                SUM(IF(pm.meta_value = 'cerrado', 1, 0)) AS cerrados,
                SUM(IF(pm.meta_value = 'abierto_disponible', 1, 0)) AS disponibles,
                SUM(IF(pm.meta_value = 'abierto_ocupado', 1, 0)) AS ocupados
             FROM {$wpdb->postmeta} pm
             JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = '_estado'
               AND p.post_type = 'centro_turno'
               AND p.post_status = 'publish'"
		);

		return array(
			'total'       => (int) ( $stats->total ?? 0 ),
			'cerrados'    => (int) ( $stats->cerrados ?? 0 ),
			'disponibles' => (int) ( $stats->disponibles ?? 0 ),
			'ocupados'    => (int) ( $stats->ocupados ?? 0 ),
		);
	}

	/* ── Trends (6-month) ─────────────────────────── */

	/**
	 * 6-month trends: members, inscriptions, payments.
	 */
	public static function get_trends(): array {
		global $wpdb;

		$months = array();
		for ( $i = 5; $i >= 0; $i-- ) {
			$ts       = strtotime( "-{$i} months" );
			$months[] = array(
				'label'      => wp_date( 'M', $ts ),
				'year_month' => wp_date( 'Y-m', $ts ),
			);
		}

		$members_trend      = array();
		$inscriptions_trend = array();
		$payments_trend     = array();
		$labels             = array();

		foreach ( $months as $m ) {
			$labels[] = $m['label'];
			$ym       = $m['year_month'];

			// Members created that month.
			$members_trend[] = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts}
                 WHERE post_type = 'miembro' AND post_status = 'publish'
                 AND DATE_FORMAT(post_date, '%%Y-%%m') = %s",
					$ym
				)
			);

			// Inscriptions created that month.
			$inscriptions_trend[] = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts}
                 WHERE post_type = 'inscripcion' AND post_status = 'publish'
                 AND DATE_FORMAT(post_date, '%%Y-%%m') = %s",
					$ym
				)
			);

			// Payment amount that month.
			$payments_trend[] = (float) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COALESCE(SUM(CAST(pm.meta_value AS UNSIGNED)), 0) / 100
                 FROM {$wpdb->posts} p
                 JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_bdg_amount_cents'
                 JOIN {$wpdb->postmeta} ps ON p.ID = ps.post_id AND ps.meta_key = '_bdg_status' AND ps.meta_value = 'paid'
                 WHERE p.post_type = 'pago' AND p.post_status = 'publish'
                 AND DATE_FORMAT(p.post_date, '%%Y-%%m') = %s",
					$ym
				)
			);
		}

		return array(
			'labels'       => $labels,
			'members'      => $members_trend,
			'inscriptions' => $inscriptions_trend,
			'payments'     => $payments_trend,
		);
	}

	/* ── Helpers ──────────────────────────────────── */

	/**
	 * Format cents to € string.
	 */
	public static function format_cents( int $cents ): string {
		return number_format( $cents / 100, 2, ',', '.' ) . '€';
	}

	/**
	 * Render Chart.js script tag if not already enqueued.
	 */
	public static function enqueue_chartjs(): void {
		if ( wp_script_is( 'chart-js', 'registered' ) ) {
			return;
		}
		wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.1', true ); .
	}

	/**
	 * Render inline chart HTML + JS for a line/bar chart.
	 *
	 * @param string $canvas_id DOM id.
	 * @param string $type      'line'|'bar'|'pie'|'doughnut'.
	 * @param array  $labels    X-axis / segment labels.
	 * @param array  $datasets  Array of ['label','data','color'].
	 * @param array  $opts      Extra Chart.js options.
	 * @return string HTML with <canvas> + inline <script>.
	 */
	public static function render_chart( string $canvas_id, string $type, array $labels, array $datasets, array $opts = array() ): string {
		$json_data = array();
		foreach ( $datasets as $ds ) {
			$entry = array(
				'label'           => $ds['label'] ?? '',
				'data'            => $ds['data'] ?? array(),
				'backgroundColor' => $ds['color'] ?? ( $type === 'line' ? 'rgba(50,0,40,0.1)' : '#FF8700' ),
				'borderColor'     => $ds['border'] ?? ( $type === 'line' ? '#320028' : '#FF8700' ),
				'fill'            => $type === 'line',
				'tension'         => 0.4,
			);
			if ( $type === 'line' ) {
				$entry['pointRadius']      = 4;
				$entry['pointHoverRadius'] = 6;
			}
			$json_data[] = $entry;
		}

		$chart_opts = json_encode(
			array(
				'responsive'          => true,
				'maintainAspectRatio' => true,
				'plugins'             => array(
					'legend' => array( 'display' => count( $datasets ) > 1 || $type === 'pie' ),
				),
				'scales'              => ( $type !== 'pie' && $type !== 'doughnut' ) ? array(
					'y' => array( 'beginAtZero' => true ),
				) : new \stdClass(),
			),
			JSON_THROW_ON_ERROR
		);

		$labels_json = json_encode( $labels, JSON_THROW_ON_ERROR );
		$data_json   = json_encode( $json_data, JSON_THROW_ON_ERROR );

		$extra_css = $opts['css'] ?? '';
		$height    = $opts['height'] ?? '200px';

		return <<<HTML
<canvas id="{$canvas_id}" style="height:{$height};width:100%;{$extra_css}"></canvas>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var ctx = document.getElementById('{$canvas_id}');
    if (!ctx) return;
    new Chart(ctx, {
        type: '{$type}',
        data: { labels: {$labels_json}, datasets: {$data_json} },
        options: {$chart_opts}
    });
});
</script>
HTML;
	}
}
