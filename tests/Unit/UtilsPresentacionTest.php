<?php
/**
 * Lo que se pinta: avisos, insignias de nivel, panel de diagnóstico y plantillas de PDF.
 *
 * Es la capa que ve quien administra el sitio. Dos cosas no pueden fallar aquí: un mensaje no puede ser la vía de
 * entrada de un `<script>`, y un aviso guardado no puede repetirse en cada carga (se guarda para una vez y se borra).
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use Convoca\Core\Utils;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Utils.php';
require_once dirname( __DIR__, 2 ) . '/includes/Logger.php';
require_once dirname( __DIR__, 2 ) . '/includes/Features.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/convoca-abspath/' );
}
if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
	// Sin barra final, como WordPress: el producto concatena `/` y una doble barra hacía que el fichero
	// «no existiera» (y la prueba habría culpado al producto).
	define( 'WP_PLUGIN_DIR', sys_get_temp_dir() . '/convoca-plugins' );
}

/**
 * Presentación y utilidades de panel.
 */
final class UtilsPresentacionTest extends TestCase {

	/**
	 * Estado global previo.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->previo              = $GLOBALS['_wp_stores'] ?? array();
		$GLOBALS['_wp_filters']    = array();
		$GLOBALS['_wp_stores']     = array(
			'options'        => array(),
			'transients'     => array(),
			'post_meta'      => array(),
			'posts'          => array(),
			'usuarios'       => array(),
			'db_inserts'     => array(),
			'queries'        => array(),
			'active_plugins' => array(),
			'plugin_data'    => array(),
		);
		$GLOBALS['_test_user_id'] = 3;
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores'] = $this->previo;
		unset( $GLOBALS['_test_user_id'] );
	}

	/**
	 * Pinta un método que escribe.
	 *
	 * @param string $metodo Método.
	 * @param mixed  ...$args Argumentos.
	 * @return string
	 */
	private function pinta( string $metodo, ...$args ): string {
		ob_start();
		try {
			Utils::$metodo( ...$args );
		} finally {
			$html = (string) ob_get_clean();
		}

		return $html;
	}

	/**
	 * El panel de diagnóstico: el peor estado manda (un error tapa los avisos).
	 */
	public function test_el_panel_de_diagnostico(): void {
		$error = $this->pinta(
			'render_diagnostic_panel',
			array(
				array(
					'title'   => 'Conexión',
					'message' => 'No responde',
					'status'  => 'error',
					'fix'     => 'Revisa el puerto',
					'fix_url' => 'https://example.test/fix',
				),
				array(
					'title'   => 'Caché',
					'message' => 'Lenta',
					'status'  => 'warning',
				),
			)
		);
		$this->assertStringContainsString( 'Estado: Errores detectados', $error, 'el error manda' );
		$this->assertStringContainsString( 'convoca-diagnostic-header--error', $error );
		$this->assertStringContainsString( 'Revisa el puerto', $error );
		$this->assertStringContainsString( 'href="https://example.test/fix"', $error );
		$this->assertStringContainsString( 'Reparar', $error );
		$this->assertStringContainsString( '✗', $error );
		$this->assertStringContainsString( '⚠', $error, 'el aviso sigue visible en su fila' );

		$aviso = $this->pinta(
			'render_diagnostic_panel',
			array(
				array(
					'title'   => 'Caché',
					'message' => 'Lenta',
					'status'  => 'warning',
				),
			)
		);
		$this->assertStringContainsString( 'Estado: Advertencias', $aviso );
		$this->assertStringContainsString( 'convoca-diagnostic-header--warning', $aviso );

		$bien = $this->pinta(
			'render_diagnostic_panel',
			array( array( 'title' => 'Todo', 'message' => 'Bien', 'status' => 'ok' ) ),
			'Mi título'
		);
		$this->assertStringContainsString( 'Mi título', $bien, 'el título propio se respeta' );
		$this->assertStringContainsString( 'convoca-diagnostic-header--success', $bien );
		$this->assertStringContainsString( '✓', $bien );
		$this->assertStringNotContainsString( 'Reparar', $bien, 'sin `fix_url` no hay botón' );
	}

	/**
	 * Los colores de nivel y sus insignias.
	 */
	public function test_los_niveles_del_registro(): void {
		$this->assertSame( '#d63638', Utils::get_log_level_color( 'error' ) );
		$this->assertSame( '#ffb900', Utils::get_log_level_color( 'warning' ) );
		$this->assertSame( '#72aee6', Utils::get_log_level_color( 'info' ) );
		$this->assertSame( '#00a32a', Utils::get_log_level_color( 'success' ) );
		$this->assertSame( '#646970', Utils::get_log_level_color( 'lo-que-sea' ) );

		$this->assertSame( '<span class="convoca-badge convoca-badge--error">Error</span>', Utils::render_log_level_badge( 'error' ) );
		$this->assertStringContainsString( 'convoca-badge--warning', Utils::render_log_level_badge( 'warning' ) );
		$this->assertStringContainsString( 'convoca-badge--success', Utils::render_log_level_badge( 'success' ) );
		$this->assertStringContainsString( 'convoca-badge--info', Utils::render_log_level_badge( 'vaya' ), 'lo desconocido cae a info' );
	}

	/**
	 * Un aviso inmediato: el tipo va en la clase y el mensaje **no** puede traer un script.
	 */
	public function test_el_aviso_inmediato(): void {
		$html = $this->pinta( 'admin_notice', 'Guardado <script>alert(1)</script>', 'danger' );

		$this->assertStringContainsString( 'convoca-alert--danger', $html );
		$this->assertStringContainsString( 'role="alert"', $html );
		$this->assertStringNotContainsString( '<script>', $html, 'el mensaje no es vía de entrada' );
		$this->assertStringContainsString( 'Guardado', $html );
	}

	/**
	 * Un aviso guardado: se pinta la próxima vez y **se borra** (no se repite en cada carga).
	 */
	public function test_el_aviso_guardado(): void {
		Utils::set_admin_notice( 'Primero', 'success' );
		Utils::set_admin_notice( 'Segundo', 'warning' );

		$this->assertCount( 2, get_transient( 'convoca_notice_3' ) );

		$html = $this->pinta( 'render_stored_notices' );
		$this->assertStringContainsString( 'Primero', $html );
		$this->assertStringContainsString( 'Segundo', $html );
		$this->assertStringContainsString( 'convoca-alert--warning', $html );
		$this->assertFalse( get_transient( 'convoca_notice_3' ), 'se borra al pintarlo' );

		$this->assertSame( '', $this->pinta( 'render_stored_notices' ), 'la segunda vez no hay nada' );
	}

	/**
	 * Plantillas de PDF: por clave, y `null` si no está.
	 */
	public function test_las_plantillas_de_pdf(): void {
		$GLOBALS['_wp_stores']['options']['convoca_pdf_templates'] = array(
			'certificado' => array(
				'name'    => 'Certificado',
				'content' => '<p>Hola</p>',
			),
		);

		$this->assertSame( 'Certificado', Utils::get_pdf_template( 'certificado' )['name'] );
		$this->assertNull( Utils::get_pdf_template( 'no-existe' ) );
		$this->assertCount( 1, Utils::get_pdf_templates() );
	}

	/**
	 * La versión de un plugin se lee de su cabecera; si el fichero no está, no se inventa nada.
	 */
	public function test_la_version_de_un_plugin(): void {
		$dir = WP_PLUGIN_DIR . '/convoca-prueba';
		if ( ! is_dir( $dir ) ) {
			mkdir( $dir, 0777, true );
		}
		$fichero = $dir . '/convoca-prueba.php';
		file_put_contents( $fichero, "<?php\n/* Plugin Name: Prueba */\n" );

		$GLOBALS['_wp_stores']['plugin_data'][ $fichero ] = array( 'Version' => '2.5.0' );

		$this->assertSame( '2.5.0', Utils::get_plugin_version( 'convoca-prueba/convoca-prueba.php' ) );
		$this->assertSame( '', Utils::get_plugin_version( 'no-existe/no-existe.php' ), 'sin fichero, vacío' );

		@unlink( $fichero );
		@rmdir( $dir );
	}

	/**
	 * Las dos preguntas delegadas: ¿está activo? ¿tiene tal capacidad?
	 */
	public function test_las_preguntas_delegadas(): void {
		$GLOBALS['_wp_stores']['active_plugins'] = array( 'convoca-members/convoca-members.php' );

		$this->assertTrue( Utils::is_plugin_active_safe( 'convoca-members/convoca-members.php' ) );
		$this->assertFalse( Utils::is_plugin_active_safe( 'convoca-enroll/convoca-enroll.php' ) );

		$this->assertIsBool( Utils::has_feature( 'members' ) );
	}
}
