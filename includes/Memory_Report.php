<?php
/**
 * Monthly/Annual PDF Memory Report Generator.
 *
 * Generates a downloadable PDF with the association's activity summary
 * (members, inscriptions, payments, volunteer hours) using Dompdf.
 * Hooked into the weekly cron and accessible from admin.
 *
 * @package Convoca\Core
 */

namespace Convoca\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Memory_Report {

	/**
	 * Initialize hooks.
	 */
	public static function init(): void {
		// Weekly cron: auto-generate monthly report.
		add_action( 'conv_weekly_event', array( __CLASS__, 'auto_generate' ) );
		// Admin: manual generation.
		add_action( 'admin_post_conv_generate_memory', array( __CLASS__, 'handle_admin_generate' ) );
	}

	/**
	 * Auto-generate on the 1st week of each month.
	 */
	public static function auto_generate(): void {
		$today = (int) wp_date( 'j' );
		if ( $today > 7 ) {
			return; // Only run during the first week.
		}

		$last_month = strtotime( 'first day of last month' );
		$label      = wp_date( 'F Y', $last_month );

		// Check if already generated this month.
		$cache_key = 'conv_memory_' . wp_date( 'Y_m', $last_month );
		if ( get_transient( $cache_key ) ) {
			return;
		}

		$pdf = self::generate_pdf( $last_month );
		if ( $pdf ) {
			// Store for admin download.
			$upload_dir = wp_upload_dir();
			$dir        = $upload_dir['basedir'] . '/convoca-memorias';
			if ( ! is_dir( $dir ) ) {
				wp_mkdir_p( $dir );
			}
			$path = $dir . '/memoria-' . sanitize_file_name( $label ) . '.pdf';
			file_put_contents( $path, $pdf );

			// Notify admin.
			$admin_email = get_option( 'admin_email' );
			$subject     = '📊 Memoria mensual de actividades — ' . $label;
			$body        = 'La memoria mensual de ' . $label . ' ha sido generada automáticamente.';
			$body       .= "\n\nPuedes descargarla en el Panel de Control de " . esc_html(get_bloginfo("name")) . ".";
			wp_mail( $admin_email, $subject, $body );

			set_transient( $cache_key, true, MONTH_IN_SECONDS );
			Logger::info( "Memoria mensual generada: {$label}", 'Common/Memory' );
		}
	}

	/**
	 * Handle admin manual generation.
	 */
	public static function handle_admin_generate(): void {
		if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'conv_generate_memory' ) ) {
			wp_die( 'Nonce inválido.' );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'No tienes permisos.' );
		}

		$month     = isset( $_GET['month'] ) ? (int) $_GET['month'] : 0;
		$year      = isset( $_GET['year'] ) ? (int) $_GET['year'] : (int) wp_date( 'Y' );
		$timestamp = $month ? mktime( 0, 0, 0, $month, 1, $year ) : strtotime( 'first day of last month' );

		$pdf = self::generate_pdf( $timestamp );
		if ( ! $pdf ) {
			wp_die( 'Error al generar la memoria.' );
		}

		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="memoria-' . wp_date( 'F-Y', $timestamp ) . '.pdf"' );
		header( 'Content-Length: ' . strlen( $pdf ) );
		echo $pdf;
		exit;
	}

	/**
	 * Generate PDF memory report for a given month.
	 *
	 * @param int $timestamp Unix timestamp (1st of the month).
	 * @return string|null PDF content or null on failure.
	 */
	public static function generate_pdf( int $timestamp ): ?string {
		if ( ! class_exists( '\\Dompdf\\Dompdf' ) ) {
			Logger::error( 'Dompdf no está disponible para generar la memoria', 'Common/Memory' );
			return null;
		}

		$label       = ucfirst( wp_date( 'F \d\e Y', $timestamp ) );
		$year        = wp_date( 'Y', $timestamp );
		$month_num   = wp_date( 'm', $timestamp );
		$month_start = $timestamp;
		$month_end   = strtotime( 'last day of this month', $timestamp );

		// Fetch data.
		$analytics = Admin_Analytics::get_all( true );
		$m         = $analytics['members'];
		$i         = $analytics['inscriptions'];
		$p         = $analytics['payments'];
		$t         = $analytics['turnos'];
		$trend     = $analytics['trends'];

		// Monthly-specific queries.
		global $wpdb;
		$altas_mes    = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_type = 'miembro' AND post_status = 'publish'
             AND post_date >= %s AND post_date < %s",
				wp_date( 'Y-m-d', $month_start ),
				wp_date( 'Y-m-d', $month_end )
			)
		);
		$insc_mes     = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_type = 'inscripcion' AND post_status = 'publish'
             AND post_date >= %s AND post_date < %s",
				wp_date( 'Y-m-d', $month_start ),
				wp_date( 'Y-m-d', $month_end )
			)
		);
		$ingresos_mes = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(CAST(pm.meta_value AS UNSIGNED)), 0) / 100
             FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_conv_amount_cents'
             JOIN {$wpdb->postmeta} ps ON p.ID = ps.post_id AND ps.meta_key = '_conv_status' AND ps.meta_value = 'paid'
             WHERE p.post_type = 'pago' AND p.post_status = 'publish'
             AND p.post_date >= %s AND p.post_date < %s",
				wp_date( 'Y-m-d', $month_start ),
				wp_date( 'Y-m-d', $month_end )
			)
		);

		$logo_url  = Utils::get_logo_url( 'memory' );
		$logo_html = $logo_url ? '<img src="' . $logo_url . '" style="height:50px;" alt="' . esc_attr(get_bloginfo('name')) . '" />' : '';

		$html = '
        <!DOCTYPE html>
        <html>
        <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: "Helvetica", "Arial", sans-serif; font-size: 12px; color: #333; line-height: 1.6; }
            .header { text-align: center; padding: 30px 0; border-bottom: 3px solid #FF8700; }
            .header h1 { color: #320028; font-size: 24px; margin: 10px 0 0; }
            .header p { color: #666; font-size: 14px; margin: 5px 0 0; }
            .section { margin: 20px 0; padding: 15px; border: 1px solid #e0e0e0; border-radius: 8px; }
            .section h2 { color: #FF8700; font-size: 16px; margin: 0 0 15px; border-bottom: 2px solid #FF8700; padding-bottom: 5px; }
            table { width: 100%; border-collapse: collapse; }
            th { background: #320028; color: #fff; padding: 8px; text-align: left; font-size: 11px; }
            td { padding: 6px 8px; border-bottom: 1px solid #e0e0e0; }
            tr:nth-child(even) { background: #f9f9f9; }
            .stat { display: inline-block; width: 45%; margin: 5px; padding: 10px; background: #f5f5f5; border-radius: 6px; }
            .stat-value { font-size: 22px; font-weight: 700; color: #320028; }
            .stat-label { font-size: 11px; color: #666; }
            .footer { text-align: center; padding: 20px; color: #999; font-size: 10px; border-top: 1px solid #e0e0e0; margin-top: 30px; }
            .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: 600; }
            .badge-green { background: #d1fae5; color: #065f46; }
            .badge-blue { background: #dbeafe; color: #1e40af; }
            .badge-yellow { background: #fef3c7; color: #92400e; }
            .badge-red { background: #fee2e2; color: #991b1b; }
        </style>
        </head>
        <body>
            <div class="header">'
				. $logo_html . '
                <h1>Memoria de Actividades</h1>
                <p>' . $label . '</p>
            </div>

            <div class="section">
                <h2>📊 Datos generales</h2>
                <table>
                    <tr><th>Indicador</th><th>Valor</th></tr>
                    <tr><td>Socios activos</td><td><strong>' . $m['activos'] . '</strong> de ' . $m['total'] . ' totales</td></tr>
                    <tr><td>Altas este mes</td><td><strong>' . $altas_mes . '</strong> nuevos socios</td></tr>
                    <tr><td>Próximos a vencer (7d)</td><td>' . $m['vencen_7d'] . ' membresías</td></tr>
                    <tr><td>Inscripciones totales</td><td><strong>' . $i['confirmadas'] . '</strong> confirmadas de ' . $i['total'] . ' totales</td></tr>
                    <tr><td>Inscripciones este mes</td><td><strong>' . $insc_mes . '</strong> nuevas inscripciones</td></tr>
                    <tr><td>Lista de espera</td><td>' . $i['lista_espera'] . ' personas</td></tr>
                    <tr><td>Actividades activas</td><td>' . $i['actividades'] . ' (' . $i['proximas'] . ' próximas)</td></tr>
                    <tr><td>Ingresos este mes</td><td><strong>' . number_format( $ingresos_mes, 2, ',', '.' ) . '€</strong></td></tr>
                    <tr><td>Turnos centro social</td><td>' . $t['disponibles'] . ' libres / ' . $t['ocupados'] . ' ocupados / ' . $t['cerrados'] . ' cerrados</td></tr>
                </table>
            </div>

            <div class="section">
                <h2>📈 Evolución últimos 6 meses</h2>
                <table>
                    <tr><th>Mes</th><th>Altas socios</th><th>Inscripciones</th><th>Ingresos</th></tr>';

		for ( $i = 0; $i < count( $trend['labels'] ); $i++ ) {
			$html .= '<tr>'
				. '<td>' . $trend['labels'][ $i ] . '</td>'
				. '<td>' . ( $trend['members'][ $i ] ?? 0 ) . '</td>'
				. '<td>' . ( $trend['inscriptions'][ $i ] ?? 0 ) . '</td>'
				. '<td>' . number_format( $trend['payments'][ $i ] ?? 0, 2, ',', '.' ) . '€</td>'
				. '</tr>';
		}

		$html .= '
                </table>
            </div>

            <div class="section">
                <h2>💳 Métodos de pago</h2>
                <table>
                    <tr><th>Método</th><th>Porcentaje</th></tr>
                    <tr><td>💳 Tarjeta</td><td>' . ( $p['methods_pct']['tarjeta'] ?? 0 ) . '%</td></tr>
                    <tr><td>📱 Bizum</td><td>' . ( $p['methods_pct']['bizum'] ?? 0 ) . '%</td></tr>
                </table>
            </div>

            <div class="footer">
                <p>Generado automáticamente por " . esc_html(get_bloginfo("name")) . " — ' . wp_date( 'd/m/Y H:i' ) . '</p>
                <p>" . esc_html(get_bloginfo("name")) . "</p>
            </div>
        </body>
        </html>';

		$dompdf = new \Dompdf\Dompdf();
		$dompdf->setPaper( 'A4', 'portrait' );
		$dompdf->loadHtml( $html );
		$dompdf->render();

		return $dompdf->output();
	}
}
