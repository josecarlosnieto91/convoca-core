<?php
/**
 * Las cifras del panel: lo que se enseña cuando hay datos y cuando no.
 *
 * Tres cosas se prueban aquí, y las tres tienen consecuencias:
 *
 * - **sin el plugin de socios no se inventan cifras**: todas las estadísticas vuelven a cero, y un porcentaje sobre
 *   cero socios es `0`, no una división por cero;
 * - **la caché se respeta y se puede forzar**: una segunda lectura no consulta la base, y `force_refresh` sí;
 * - **un gráfico sin datos no se pinta vacío**: Chart.js deja el lienzo en blanco y parece roto, así que sale un
 *   «Sin datos todavía». Y el JSON va escapado: una etiqueta con `</script>` no puede cerrar el script.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use Convoca\Core\Admin_Analytics;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Utils.php';
require_once dirname( __DIR__, 2 ) . '/includes/Features.php';
require_once dirname( __DIR__, 2 ) . '/includes/Admin_Analytics.php';

if ( ! defined( 'CONVOCA_COMMON_URL' ) ) {
	define( 'CONVOCA_COMMON_URL', 'https://example.test/wp-content/plugins/convoca-core/' );
}

/**
 * Estadísticas del panel.
 */
final class AdminAnalyticsTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$GLOBALS['_wp_stores'] = array(
			'options'     => array(),
			'post_meta'   => array(),
			'transients'  => array(),
			'posts'       => array(),
			'usuarios'    => array(),
			'db_inserts'  => array(),
			'queries'     => array(),
			'get_var_mapa' => array(),
			'get_row_mapa' => array(),
			'db_reads'     => array(),
			'scripts'     => array(),
			'enqueues'    => array(),
		);
	}

	/**
	 * Los céntimos se leen como euros, en formato español y con el símbolo pegado.
	 */
	public function test_los_centimos(): void {
		$this->assertSame( '0,00€', Admin_Analytics::format_cents( 0 ) );
		$this->assertSame( '1.234,56€', Admin_Analytics::format_cents( 123456 ) );
		$this->assertSame( '1.000.000,00€', Admin_Analytics::format_cents( 100000000 ) );
		$this->assertSame( '-12,50€', Admin_Analytics::format_cents( -1250 ), 'una devolución se lee con su signo' );
	}

	/**
	 * Sin el plugin de socios no hay nada que contar, y no se inventa: todas las cifras a cero.
	 */
	public function test_sin_socios_las_cifras_son_cero(): void {
		$stats = Admin_Analytics::get_member_stats();

		$this->assertSame( 0, $stats['total'] );
		$this->assertSame( 0, $stats['activos'] );
		$this->assertSame( 0, $stats['vencen_7d'] );
		$this->assertSame( 0, $stats['activos_pct'], 'sin socios el porcentaje es 0, no una división por cero' );
	}

	/**
	 * Sin pasarela, los pagos vuelven a cero **con su forma completa**: quien pinte el panel espera las claves.
	 */
	public function test_sin_pasarela_los_pagos_son_cero(): void {
		$stats = Admin_Analytics::get_payment_stats();

		$this->assertSame( 0, $stats['total_mes'] );
		$this->assertSame( '0,00€', $stats['total_mes_fmt'] );
		$this->assertSame( array( 'tarjeta' => 0, 'bizum' => 0 ), $stats['methods'] );
		$this->assertSame( array( 'tarjeta' => 0, 'bizum' => 0, 'otros' => 0 ), $stats['methods_pct'] );
		$this->assertSame( array(), $stats['last_7_days'] );
	}

	/**
	 * Los turnos se leen de su agregado: total, cerrados, disponibles y ocupados.
	 */
	public function test_los_turnos(): void {
		$GLOBALS['_wp_stores']['get_row_mapa']['_estado'] = (object) array(
			'total'       => 12,
			'cerrados'    => 5,
			'disponibles' => 4,
			'ocupados'    => 3,
		);

		$stats = Admin_Analytics::get_turno_stats();

		$this->assertSame( 12, $stats['total'] );
		$this->assertSame( 5, $stats['cerrados'] );
		$this->assertSame( 4, $stats['disponibles'] );
		$this->assertSame( 3, $stats['ocupados'] );
	}

	/**
	 * Sin turnos, ceros (no un aviso por un agregado que devuelve `null`).
	 */
	public function test_sin_turnos_cero(): void {
		$stats = Admin_Analytics::get_turno_stats();

		$this->assertSame( array( 'total' => 0, 'cerrados' => 0, 'disponibles' => 0, 'ocupados' => 0 ), $stats );
	}

	/**
	 * Las tendencias: seis meses, del más antiguo al actual, con sus valores.
	 */
	public function test_las_tendencias(): void {
		$GLOBALS['_wp_stores']['get_var_mapa'] = array(
			"'miembro'"     => 3,
			"'inscripcion'" => 2,
		);

		$t = Admin_Analytics::get_trends();

		$this->assertCount( 6, $t['labels'], 'seis meses' );
		$this->assertSame( wp_date( 'M', strtotime( '-5 months' ) ), $t['labels'][0], 'del más antiguo' );
		$this->assertSame( wp_date( 'M' ), $t['labels'][5], 'al actual' );
		$this->assertSame( array( 3, 3, 3, 3, 3, 3 ), $t['members'] );
		$this->assertSame( array( 2, 2, 2, 2, 2, 2 ), $t['inscriptions'] );
	}

	/**
	 * Todo junto se guarda en caché: la segunda lectura no vuelve a consultar la base.
	 */
	public function test_se_cachea(): void {
		$GLOBALS['_wp_stores']['get_row_mapa']['_estado'] = (object) array( 'total' => 1 );

		$primera = Admin_Analytics::get_all();
		$lecturas = count( $GLOBALS['_wp_stores']['db_reads'] );
		$segunda  = Admin_Analytics::get_all();

		$this->assertSame( $primera['turnos']['total'], $segunda['turnos']['total'] );
		$this->assertGreaterThan( 0, $lecturas, 'la primera sí consulta' );
		$this->assertSame( $lecturas, count( $GLOBALS['_wp_stores']['db_reads'] ), 'la segunda no consulta' );
		$this->assertArrayHasKey( 'timestamp', $primera, 'y se sabe de cuándo son los datos' );
	}

	/**
	 * Y `force_refresh` sí vuelve a consultar: el botón de actualizar tiene que actualizar.
	 */
	public function test_se_puede_forzar(): void {
		Admin_Analytics::get_all();
		$antes = count( $GLOBALS['_wp_stores']['db_reads'] );

		Admin_Analytics::get_all( true );

		$this->assertGreaterThan( $antes, count( $GLOBALS['_wp_stores']['db_reads'] ), 'vuelve a preguntar' );
	}

	/**
	 * **Sin datos no se pinta un lienzo en blanco**: sale el aviso.
	 */
	public function test_grafico_sin_datos(): void {
		$html = Admin_Analytics::render_chart(
			'grafico-1',
			'line',
			array( 'Ene', 'Feb' ),
			array(
				array(
					'label' => 'Socios',
					'data'  => array( 0, 0 ),
				),
			)
		);

		$this->assertStringContainsString( 'Sin datos todavía', $html );
		$this->assertStringNotContainsString( '<canvas', $html, 'ni un lienzo vacío' );
	}

	/**
	 * Un gráfico de líneas: relleno, tensión y sus colores.
	 */
	public function test_grafico_de_lineas(): void {
		$html = Admin_Analytics::render_chart(
			'grafico-2',
			'line',
			array( 'Ene', 'Feb' ),
			array(
				array(
					'label' => 'Socios',
					'data'  => array( 1, 2 ),
				),
			)
		);

		$this->assertStringContainsString( 'id="grafico-2"', $html );
		$this->assertStringContainsString( '"fill":true', $html );
		$this->assertStringContainsString( '"tension":0.4', $html );
		$this->assertStringContainsString( '"pointRadius":4', $html );
		$this->assertStringContainsString( 'rgba(50,0,40,0.1)', $html, 'el fondo de una línea' );
	}

	/**
	 * Un gráfico de barras usa el naranja de relleno y no lleva relleno ni puntos.
	 */
	public function test_grafico_de_barras(): void {
		$html = Admin_Analytics::render_chart(
			'grafico-3',
			'bar',
			array( 'Ene' ),
			array(
				array(
					'label' => 'Altas',
					'data'  => array( 5 ),
				),
			)
		);

		$this->assertStringContainsString( '#FF8700', $html );
		$this->assertStringContainsString( '"fill":false', $html );
		$this->assertStringNotContainsString( '"pointRadius"', $html, 'las barras no llevan puntos' );
	}

	/**
	 * Un tarta no lleva ejes; una línea sí. Sin esta distinción Chart.js pinta un eje sobre un círculo.
	 */
	public function test_los_ejes_segun_el_tipo(): void {
		$pie = Admin_Analytics::render_chart(
			'grafico-4',
			'pie',
			array( 'Sí', 'No' ),
			array(
				array(
					'label' => 'Respuestas',
					'data'  => array( 3, 1 ),
				),
			)
		);

		$barras = Admin_Analytics::render_chart(
			'grafico-5',
			'bar',
			array( 'Ene' ),
			array(
				array(
					'label' => 'Altas',
					'data'  => array( 5 ),
				),
			)
		);

		$this->assertStringContainsString( '"scales":{}', $pie, 'la tarta lleva los ejes vacíos, no un eje' );
		$this->assertStringContainsString( '"scales":{"y":{"beginAtZero":true}}', $barras, 'las barras empiezan en cero' );
	}

	/**
	 * La leyenda: con un solo conjunto no hace falta; con dos, sí; en una tarta, siempre.
	 */
	public function test_la_leyenda(): void {
		$uno = Admin_Analytics::render_chart( 'a', 'bar', array( 'x' ), array( array( 'label' => 'Uno', 'data' => array( 1 ) ) ) );
		$dos = Admin_Analytics::render_chart( 'b', 'bar', array( 'x' ), array(
			array( 'label' => 'Uno', 'data' => array( 1 ) ),
			array( 'label' => 'Dos', 'data' => array( 2 ) ),
		) );
		$pie = Admin_Analytics::render_chart( 'c', 'pie', array( 'x' ), array( array( 'label' => 'Uno', 'data' => array( 1 ) ) ) );

		$this->assertStringContainsString( '"legend":{"display":false}', $uno );
		$this->assertStringContainsString( '"legend":{"display":true}', $dos );
		$this->assertStringContainsString( '"legend":{"display":true}', $pie );
	}

	/**
	 * **Una etiqueta con `</script>` no puede cerrar el bloque**: se escapa, y el gráfico sigue siendo el gráfico.
	 */
	public function test_una_etiqueta_no_cierra_el_script(): void {
		$html = Admin_Analytics::render_chart(
			'grafico-6',
			'bar',
			array( '</script><img src=x onerror=alert(1)>' ),
			array(
				array(
					'label' => 'Altas',
					'data'  => array( 1 ),
				),
			)
		);

		$this->assertSame( 1, substr_count( $html, '</script>' ), 'un solo cierre, el del bloque' );
		$this->assertStringNotContainsString( '<img', $html );
	}

	/**
	 * Chart.js se sirve del propio sitio (wp.org prohíbe cargarlo de una CDN) y una sola vez.
	 */
	public function test_chartjs_local_y_una_sola_vez(): void {
		Admin_Analytics::enqueue_chartjs();
		Admin_Analytics::enqueue_chartjs();

		$scripts = $GLOBALS['_wp_stores']['scripts'];
		$this->assertArrayHasKey( 'chart-js', $scripts );
		$this->assertStringContainsString( 'assets/js/chart.umd.min.js', $scripts['chart-js']['src'], 'desde el plugin' );
		$this->assertStringStartsWith( 'https://example.test/', $scripts['chart-js']['src'], 'no de un CDN' );
		$this->assertSame( array( 'chart-js' ), $GLOBALS['_wp_stores']['enqueues'], 'encolado una sola vez' );
		$this->assertTrue( $scripts['chart-js']['footer'], 'en el pie' );
	}
}
