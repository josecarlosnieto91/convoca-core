<?php
/**
 * Valores del sitio: de la pantalla a los filtros del tema.
 *
 * Es el contrato que sustituye al mu-plugin: el sitio guarda sus enlaces, etiquetas y CTA en una opción
 * y los filtros de siempre los leen. Si la opción está vacía, el filtro devuelve lo que traía; si un
 * mu-plugin o el tema ponen un valor por código, mandan ellos.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use Convoca\Core\Site_Settings;
use PHPUnit\Framework\TestCase;

/**
 * Ajustes del sitio.
 */
final class SiteSettingsTest extends TestCase {

	/**
	 * Carga la clase y limpia opciones y filtros.
	 */
	protected function setUp(): void {
		$GLOBALS['_wp_filters']                    = array();
		$GLOBALS['_wp_stores']['options']          = array();
		$GLOBALS['_wp_stores']['options']['convoca_site_settings'] = array();

		require_once dirname( __DIR__, 2 ) . '/includes/Site_Settings.php';
		Site_Settings::aplicar_filtros();
	}

	/**
	 * Limpia.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_filters']           = array();
		$GLOBALS['_wp_stores']['options'] = array();
	}

	/**
	 * El formulario se convierte en mapas, y lo incompleto se descarta.
	 */
	public function test_sanea_enlaces_y_etiquetas(): void {
		$datos = Site_Settings::sanitize(
			array(
				'site_links'      => "facebook | https://redes.test/entidad\ninstagram | https://redes.test/otra\nsin separador\nmalo | javascript:alert(1)",
				'link_labels'     => "facebook | Facebook\ninstagram | Instagram\nroto",
				'cta_url'         => 'https://entidad.test/unete/',
				'cta_label'       => 'Únete',
				'cta_heading'     => 'Hola',
				'cta_text'        => 'Texto',
				'volunteer_email' => 'voluntarios@entidad.test',
				'copyright_extra' => 'Hecho con cuidado',
			)
		);

		$this->assertSame( 'https://redes.test/entidad', $datos['site_links']['facebook'] );
		$this->assertArrayNotHasKey( 'malo', $datos['site_links'], 'una URL inválida no entra' );
		$this->assertSame( 'Facebook', $datos['link_labels']['facebook'] );
		$this->assertSame( 'https://entidad.test/unete/', $datos['cta_url'] );
		$this->assertSame( 'voluntarios@entidad.test', $datos['volunteer_email'] );
	}

	/**
	 * Con la opción guardada, el filtro del tema devuelve el mapa.
	 */
	public function test_el_filtro_devuelve_lo_guardado(): void {
		$GLOBALS['_wp_stores']['options']['convoca_site_settings'] = array(
			'site_links' => array( 'facebook' => 'https://redes.test/entidad' ),
		);

		$this->assertSame(
			array( 'facebook' => 'https://redes.test/entidad' ),
			apply_filters( 'convoca_theme_site_links', array() )
		);
	}

	/**
	 * Sin opción, el filtro no pisa lo que traía: el tema sigue con sus valores por defecto.
	 */
	public function test_sin_opcion_el_filtro_no_pisa_el_valor(): void {
		$this->assertSame( array( 'del_tema' ), apply_filters( 'convoca_theme_site_links', array( 'del_tema' ) ) );
	}

	/**
	 * Un filtro del sitio añadido después manda sobre la opción.
	 */
	public function test_un_filtro_del_sitio_manda(): void {
		$GLOBALS['_wp_stores']['options']['convoca_site_settings'] = array(
			'cta_label' => 'De la opción',
		);
		add_filter(
			'convoca_theme_cta_label',
			static function () {
				return 'Del código';
			}
		);

		$this->assertSame( 'Del código', apply_filters( 'convoca_theme_cta_label', 'Por defecto' ) );
	}

	/**
	 * Las claves heredadas también salen de la opción.
	 */
	public function test_las_claves_heredadas_salen_de_la_opcion(): void {
		$GLOBALS['_wp_stores']['options']['convoca_site_settings'] = array(
			'legacy_keys' => array( '_convoca_event_start_date' => '_viejo_start' ),
		);

		$this->assertSame(
			array( '_convoca_event_start_date' => '_viejo_start' ),
			apply_filters( 'convoca_event_meta_legacy_keys', array() )
		);
	}

	/**
	 * Los colores del sitio se sanean y se imprimen como tokens (#65).
	 *
	 * Sin este ajuste, un sitio con un tema ajeno no tenía forma de poner su acento sin tocar CSS.
	 */
	public function test_los_colores_se_sanean_y_se_imprimen(): void {
		$datos = \Convoca\Core\Site_Settings::sanitize(
			array(
				'color_acento'        => '#FF8700',
				'color_acento_fuerte' => 'no-es-un-color',
			)
		);

		$this->assertSame( '#FF8700', $datos['color_acento'], 'el hex válido se guarda' );
		$this->assertSame( '', $datos['color_acento_fuerte'], 'lo que no es un color se descarta' );

		$GLOBALS['_wp_stores']['options'][ \Convoca\Core\Site_Settings::OPTION ] = $datos;

		ob_start();
		\Convoca\Core\Site_Settings::imprimir_colores();
		$css = (string) ob_get_clean();

		$this->assertStringContainsString( '--conv-accent: #FF8700', $css );
		$this->assertStringContainsString( ':root:root{', $css, 'tiene que ganar al :root de las hojas del plugin' );
		$this->assertStringNotContainsString( 'accent-strong', $css, 'sin valor no se imprime el token' );
	}

	/**
	 * Sin colores configurados no se imprime nada.
	 */
	public function test_sin_colores_no_se_imprime_css(): void {
		$GLOBALS['_wp_stores']['options'][ \Convoca\Core\Site_Settings::OPTION ] = array();

		ob_start();
		\Convoca\Core\Site_Settings::imprimir_colores();
		$css = (string) ob_get_clean();

		$this->assertSame( '', $css );
	}

	/**
	 * La casilla devuelve el color al valor neutro.
	 */
	public function test_la_casilla_limpia_el_color(): void {
		$datos = \Convoca\Core\Site_Settings::sanitize(
			array(
				'color_acento'         => '#123456',
				'limpiar_color_acento' => '1',
			)
		);

		$this->assertSame( '', $datos['color_acento'] );
	}

	/**
	 * El comando de claves queda registrado: si desaparece, el sitio vuelve a necesitar el mu-plugin.
	 */
	public function test_el_comando_de_claves_esta_registrado(): void {
		$raiz = dirname( __DIR__, 2 );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- lectura de un fichero del propio plugin.
		$plugin = (string) file_get_contents( $raiz . '/convoca-core.php' );

		$this->assertStringContainsString( "'convoca-core sitio'", $plugin );
		$this->assertFileExists( $raiz . '/includes/CLI_Site.php' );
	}
}
