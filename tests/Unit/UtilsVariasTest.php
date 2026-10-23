<?php
/**
 * Los ayudantes que usa todo el ecosistema: assets, fechas, marca, sal y caché.
 *
 * `Utils` es la caja de herramientas común, y aquí se prueban las piezas que deciden cosas con consecuencias:
 *
 * - **qué assets se encolan**: si una página no lleva nada de Convoca, no se le mete el CSS del ecosistema;
 * - **la sal persistente**: se genera una vez y no cambia nunca, porque de ella dependen los nonces ya emitidos;
 * - **la caché REST**: se guarda, se reutiliza y se puede invalidar;
 * - **el branding**: el del sitio, nunca uno del plugin (no puede aparecer el logo de otra asociación).
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use Convoca\Core\Utils;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Utils.php';
require_once dirname( __DIR__, 2 ) . '/includes/Features.php';

/**
 * Ayudantes comunes.
 */
final class UtilsVariasTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$GLOBALS['_wp_filters'] = array();
		$GLOBALS['_wp_stores']  = array(
			'options'        => array(),
			'post_meta'      => array(),
			'transients'     => array(),
			'posts'          => array(),
			'usuarios'       => array(),
			'db_inserts'     => array(),
			'queries'        => array(),
			'theme_mods'     => array(),
			'attachments'    => array(),
			'active_plugins' => array(),
			'plugin_data'    => array(),
		);
		$this->limpiar_fired();
	}

	/**
	 * Limpia el registro de hooks disparados.
	 */
	private function limpiar_fired(): void {
		unset( $GLOBALS['_wp_stores']['options']['convoca_utils_fired'] );
	}

	/**
	 * Los assets comunes se encolan si el contenido usa algo de Convoca, y sólo entonces.
	 */
	public function test_cuando_hacen_falta_los_assets(): void {
		$this->assertFalse( Utils::content_needs_common_assets( '' ), 'vacío' );
		$this->assertFalse( Utils::content_needs_common_assets( '<p>Una entrada normal</p>' ), 'texto sin Convoca' );
		$this->assertTrue( Utils::content_needs_common_assets( '<p>[convoca_alta_socio]</p>' ), 'shortcode' );
		$this->assertTrue( Utils::content_needs_common_assets( '<!-- wp:convoca-common/post-meta-field /-->' ), 'bloque de Convoca' );
		$this->assertTrue( Utils::content_needs_common_assets( '<!-- wp:centro-social/turnos /-->' ), 'bloque del centro social' );
	}

	/**
	 * La fecha: vacía no es un error, se devuelve vacía; y una fecha ilegible se deja tal cual.
	 */
	public function test_la_fecha(): void {
		$this->assertSame( '', Utils::format_date( '' ) );
		$this->assertSame( '07/11/2026', Utils::format_date( '2026-11-07' ) );
		$this->assertSame( '2026-11-07', Utils::format_date( '2026-11-07', 'Y-m-d' ), 'otro formato' );
		$this->assertSame( 'no es una fecha', Utils::format_date( 'no es una fecha' ), 'sin inventar' );
	}

	/**
	 * Los nombres propios se capitalizan, con la ñ y los acentos bien.
	 */
	public function test_los_nombres(): void {
		$this->assertSame( 'José María Núñez', Utils::format_nombre( '  JOSÉ MARÍA NÚÑEZ  ' ) );
		$this->assertSame( 'Ana', Utils::format_nombre( 'ana' ) );
	}

	/**
	 * `Utils::do_action()` dispara el hook nuevo y deja constancia; el viejo sólo si alguien lo escucha.
	 */
	public function test_los_hooks_con_su_antiguo(): void {
		$recibido = null;
		add_action(
			'convoca_nuevo',
			static function ( $dato ) use ( &$recibido ): void {
				$recibido = $dato;
			}
		);

		Utils::do_action( 'convoca_nuevo', '', 'hola' );

		$this->assertSame( 'hola', $recibido, 'el nuevo se dispara' );
		$this->assertArrayHasKey( 'convoca_nuevo', Utils::get_fired(), 'y queda registrado' );
	}

	/**
	 * El hook antiguo también se dispara cuando hay quien lo escucha: no se rompe una integración existente.
	 */
	public function test_el_hook_antiguo_se_respeta(): void {
		$viejo = null;
		add_action(
			'convoca_viejo',
			static function ( $dato ) use ( &$viejo ): void {
				$viejo = $dato;
			}
		);

		Utils::do_action( 'convoca_nuevo', 'convoca_viejo', 'dato' );

		$this->assertSame( 'dato', $viejo );
	}

	/**
	 * Y sin nadie escuchando el antiguo, no se dispara (no hay avisos de hook obsoleto en cada petición).
	 */
	public function test_sin_escucha_no_hay_antiguo(): void {
		$this->assertFalse( has_action( 'convoca_viejo_inexistente' ) );

		Utils::do_action( 'convoca_nuevo', 'convoca_viejo_inexistente', 'dato' );

		$this->assertArrayHasKey( 'convoca_nuevo', Utils::get_fired() );
	}

	/**
	 * El filtro antiguo puede cambiar el valor: una integración que filtra por el nombre viejo sigue funcionando.
	 */
	public function test_el_filtro_antiguo_puede_cambiar_el_valor(): void {
		add_filter( 'convoca_filtro_viejo', static fn( $v ): string => $v . '-viejo' );

		$resultado = Utils::apply_filters( 'convoca_filtro_nuevo', 'convoca_filtro_viejo', 'base' );

		$this->assertSame( 'base-viejo', $resultado );
	}

	/**
	 * El logo es el del sitio y es filtrable; sin logo, la marca es el nombre del sitio.
	 */
	public function test_el_branding(): void {
		$this->assertSame( '', Utils::get_logo_url(), 'sin logo no se inventa uno' );
		$this->assertStringContainsString( '<h1', Utils::get_branding_html(), 'sin logo, el nombre del sitio' );
		$this->assertStringContainsString( get_bloginfo( 'name' ), Utils::get_branding_html() );

		$GLOBALS['_wp_stores']['theme_mods']['custom_logo']         = 12;
		$GLOBALS['_wp_stores']['attachments'][12]                   = array( 'https://example.test/logo.png', 100, 100 );
		$html = Utils::get_branding_html( 'common', 'mi-clase' );

		$this->assertStringContainsString( '<img', $html );
		$this->assertStringContainsString( 'https://example.test/logo.png', $html );
		$this->assertStringContainsString( 'mi-clase', $html );
		$this->assertStringContainsString( 'alt="' . get_bloginfo( 'name' ) . '"', $html, 'con texto alternativo' );
	}

	/**
	 * El tema del documento: claro por defecto, oscuro si se pide, y lo raro vuelve a claro.
	 */
	public function test_el_tema_del_documento(): void {
		$this->assertSame( 'light', Utils::get_document_theme() );
		$GLOBALS['_wp_stores']['options']['convoca_document_theme'] = 'dark';
		$this->assertSame( 'dark', Utils::get_document_theme() );
		$GLOBALS['_wp_stores']['options']['convoca_document_theme'] = 'fucsia';
		$this->assertSame( 'light', Utils::get_document_theme(), 'un valor inválido no se cuela' );
	}

	/**
	 * **La sal persistente se genera una vez y no cambia**: de ella cuelgan los nonces ya emitidos.
	 */
	public function test_la_sal_persistente(): void {
		$primera = Utils::get_persistent_salt();
		$segunda = Utils::get_persistent_salt();

		$this->assertSame( 64, strlen( $primera ), 'suficientemente larga' );
		$this->assertSame( $primera, $segunda, 'y estable entre llamadas' );
		$this->assertSame( $primera, $GLOBALS['_wp_stores']['options']['convoca_persistent_salt'] );
	}

	/**
	 * Los colores del nivel, con su caso vecino: lo desconocido no revienta.
	 */
	public function test_los_colores_del_nivel(): void {
		$this->assertSame( '#d63638', Utils::get_log_level_color( 'error' ) );
		$this->assertSame( '#ffb900', Utils::get_log_level_color( 'warning' ) );
		$this->assertNotSame( '', Utils::get_log_level_color( 'lo-que-sea' ) );
	}

	/**
	 * La caché REST: el callback se ejecuta una vez y la invalidación obliga a repetirlo.
	 */
	public function test_la_cache_rest(): void {
		$llamadas = 0;
		$callback = static function () use ( &$llamadas ): array {
			++$llamadas;

			return array( 'valor' => $llamadas );
		};

		$primera = Utils::rest_cache_get( 'listado', 60, $callback );
		$segunda = Utils::rest_cache_get( 'listado', 60, $callback );
		$this->assertSame( 1, $llamadas, 'la segunda sale de caché' );
		$this->assertSame( $primera, $segunda );

		Utils::rest_cache_invalidate( 'listado' );
		Utils::rest_cache_get( 'listado', 60, $callback );
		$this->assertSame( 2, $llamadas, 'tras invalidar, se vuelve a calcular' );
	}

	/**
	 * Las plantillas PDF se leen de su opción, y una que no está devuelve `null` (no un array vacío).
	 */
	public function test_las_plantillas_pdf(): void {
		$this->assertNull( Utils::get_pdf_template( 'certificado' ), 'sin opción no hay plantilla' );

		$GLOBALS['_wp_stores']['options']['convoca_pdf_templates'] = array(
			'certificado' => array( 'name' => 'Certificado', 'content' => '<p>Hola</p>' ),
		);

		$this->assertSame( 'Certificado', Utils::get_pdf_template( 'certificado' )['name'] );
		$this->assertArrayHasKey( 'certificado', Utils::get_pdf_templates() );
	}

	/**
	 * Las etiquetas de «aportación» según el contexto, y el plural no es el singular.
	 */
	public function test_las_etiquetas_de_aportacion(): void {
		$this->assertSame( 'Aportación', Utils::get_aportacion_label() );
		$this->assertSame( 'Aportaciones', Utils::get_aportacion_label( 'plural' ) );
		$this->assertStringContainsString( 'Trasgu', Utils::get_aportacion_label( 'trasgu' ) );
		$this->assertStringContainsString( 'socios', Utils::get_aportacion_label( 'sugerida_socio' ) );
	}

	/**
	 * `is_plugin_active_safe` responde según la lista de plugins activos.
	 */
	public function test_si_un_plugin_esta_activo(): void {
		$this->assertFalse( Utils::is_plugin_active_safe( 'convoca-members/convoca-members.php' ) );

		$GLOBALS['_wp_stores']['active_plugins'] = array( 'convoca-members/convoca-members.php' );
		$this->assertTrue( Utils::is_plugin_active_safe( 'convoca-members/convoca-members.php' ) );
	}
}
