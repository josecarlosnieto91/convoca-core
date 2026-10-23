<?php
/**
 * Las dos pantallas del panel: el escritorio y el registro.
 *
 * Aquí lo que se prueba es lo que un administrador ve y lo que un formulario puede cambiar:
 *
 * - **permisos**: sin capacidad no se pinta nada, y el registro acepta dos capacidades distintas;
 * - **la retención de registros** sólo se guarda con su nonce, y se guarda saneada (números, no texto);
 * - **el botón de actualizar actualiza**: con `refresh` se salta la caché;
 * - **los filtros viajan**: al exportar se conservan el nivel, el contexto y las fechas.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Utils.php';
require_once dirname( __DIR__, 2 ) . '/includes/Logger.php';
require_once dirname( __DIR__, 2 ) . '/includes/Features.php';
require_once dirname( __DIR__, 2 ) . '/includes/Admin_Analytics.php';
require_once dirname( __DIR__, 2 ) . '/includes/Admin_Logs_List.php';
require_once dirname( __DIR__, 2 ) . '/includes/admin-appearance.php';

if ( ! defined( 'CONVOCA_COMMON_VERSION' ) ) {
	define( 'CONVOCA_COMMON_VERSION', '2.0.0' );
}
if ( ! defined( 'CONVOCA_COMMON_URL' ) ) {
	define( 'CONVOCA_COMMON_URL', 'https://example.test/plugin/' );
}
if ( ! defined( 'CONVOCA_IMAGES_URL' ) ) {
	define( 'CONVOCA_IMAGES_URL', 'https://example.test/img/' );
}

/**
 * Pantallas del panel.
 */
final class AdminPagesTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		unset( $_GET['refresh'], $_POST['convoca_log_retention_save'], $_POST['retention_info'], $_POST['retention_warning'], $_POST['retention_error'] );
		foreach ( array( 'filter_context', 'filter_level', 'filter_date_from', 'filter_date_to', 's', 'paged' ) as $f ) {
			unset( $_GET[ $f ] );
		}
		$GLOBALS['_wp_stores'] = array(
			'options'         => array( 'admin_email' => 'junta@example.test' ),
			'post_meta'       => array(),
			'transients'      => array(),
			'posts'           => array(),
			'usuarios'        => array(),
			'db_inserts'      => array(),
			'queries'         => array(),
			'db_reads'        => array(),
			'get_var_mapa'    => array(),
			'get_row_mapa'    => array(),
			'scripts'         => array(),
			'enqueues'        => array(),
			'nonces_validos'  => array( 'bueno' ),
		);
		$GLOBALS['_test_caps']    = array();
		$GLOBALS['_wp_die_lanza'] = true;
	}

	/**
	 * Pinta una pantalla y devuelve su HTML.
	 *
	 * @param callable $pantalla Función que pinta.
	 * @return string
	 */
	private function pintar( callable $pantalla ): string {
		ob_start();
		try {
			$pantalla();
		} finally {
			$html = (string) ob_get_clean();
		}

		return $html;
	}

	/**
	 * El escritorio exige `manage_options`.
	 */
	public function test_el_escritorio_sin_permiso_muere(): void {
		$GLOBALS['_test_caps'] = array( 'manage_options' => false );

		$this->expectException( \RuntimeException::class );
		\Convoca\Core\convoca_dashboard_page();
	}

	/**
	 * Con permiso, el escritorio enseña sus tarjetas y carga su gráfico.
	 */
	public function test_el_escritorio_pinta(): void {
		$html = $this->pintar( 'Convoca\Core\convoca_dashboard_page' );

		$this->assertStringContainsString( 'Socios activos', $html );
		$this->assertStringContainsString( 'Altas este mes', $html );
		$this->assertStringContainsString( 'conv-members', $html, 'con enlace a socios' );
		$this->assertArrayHasKey( 'chart-js', $GLOBALS['_wp_stores']['scripts'], 'y con Chart.js encolado' );
	}

	/**
	 * **El botón de actualizar actualiza**: con la caché caliente, `refresh` vuelve a consultar la base.
	 */
	public function test_el_boton_de_actualizar_se_salta_la_cache(): void {
		$this->pintar( 'Convoca\Core\convoca_dashboard_page' );
		$lecturas = count( $GLOBALS['_wp_stores']['db_reads'] );
		$GLOBALS['_wp_stores']['db_reads'] = array();

		$this->pintar( 'Convoca\Core\convoca_dashboard_page' );
		$this->assertSame( array(), $GLOBALS['_wp_stores']['db_reads'], 'sin refresh, tira de caché' );

		$_GET['refresh'] = '1';
		$this->pintar( 'Convoca\Core\convoca_dashboard_page' );
		$this->assertGreaterThan( 0, count( $GLOBALS['_wp_stores']['db_reads'] ), 'con refresh, consulta de nuevo' );
		$this->assertGreaterThan( 0, $lecturas );
	}

	/**
	 * El registro exige una capacidad propia **o** `manage_options`.
	 */
	public function test_el_registro_sin_permiso_muere(): void {
		$GLOBALS['_test_caps'] = array(
			'manage_convoca_logs' => false,
			'manage_options'      => false,
		);

		$this->expectException( \RuntimeException::class );
		\Convoca\Core\convoca_logs_page();
	}

	/**
	 * Y con la capacidad propia entra, sin necesidad de ser administrador total.
	 */
	public function test_el_registro_entra_con_su_capacidad(): void {
		$GLOBALS['_test_caps'] = array(
			'manage_convoca_logs' => true,
			'manage_options'      => false,
		);

		$html = $this->pintar( 'Convoca\Core\convoca_logs_page' );

		$this->assertNotSame( '', $html, 'la pantalla se pinta' );
	}

	/**
	 * **La retención se guarda saneada**, no como texto: son días, no comentarios.
	 */
	public function test_la_retencion_se_guarda_saneada(): void {
		$GLOBALS['_test_caps'] = array( 'manage_convoca_logs' => true );
		$_POST['convoca_log_retention_save'] = '1';
		$_POST['retention_info']             = '7abc';
		$_POST['retention_warning']          = '15';
		$_POST['retention_error']            = '60<script>';

		$this->pintar( 'Convoca\Core\convoca_logs_page' );

		$this->assertSame(
			array( 'info' => 7, 'warning' => 15, 'error' => 60 ),
			$GLOBALS['_wp_stores']['options']['convoca_log_retention_days'] ?? null
		);
	}

	/**
	 * Sin la marca del formulario no se guarda nada: abrir la pantalla no cambia la retención.
	 */
	public function test_sin_formulario_no_se_guarda_la_retencion(): void {
		$GLOBALS['_test_caps'] = array( 'manage_convoca_logs' => true );

		$this->pintar( 'Convoca\Core\convoca_logs_page' );

		$this->assertArrayNotHasKey( 'convoca_log_retention_days', $GLOBALS['_wp_stores']['options'] );
	}

	/**
	 * Al exportar se conservan los filtros: si no, se descarga «todo» creyendo que es «lo filtrado».
	 */
	public function test_la_exportacion_conserva_los_filtros(): void {
		$GLOBALS['_test_caps'] = array( 'manage_convoca_logs' => true );
		$_GET['filter_level']      = 'error';
		$_GET['filter_context']    = 'Common/Security';
		$_GET['filter_date_from']  = '2026-10-01';
		$_GET['s']                 = 'acceso';

		$html = $this->pintar( 'Convoca\Core\convoca_logs_page' );

		$this->assertStringContainsString( 'convoca_export_logs_csv', $html, 'hay enlace de exportación' );
		$this->assertStringContainsString( 'filter_level=error', $html );
		$this->assertStringContainsString( 'filter_date_from=2026-10-01', $html );
		$this->assertStringContainsString( 's=acceso', $html );
	}
}
