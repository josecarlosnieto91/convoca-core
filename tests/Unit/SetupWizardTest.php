<?php
/**
 * El asistente de instalación: las páginas del sistema.
 *
 * Un sitio recién instalado necesita cinco páginas con sus shortcodes. El asistente tiene tres formas de
 * equivocarse, y las tres se prueban aquí:
 *
 * - **duplicar**: crear una segunda página con el mismo shortcode porque la que existe se llama de otra forma
 *   (issue #14). Se busca por slug **y** por shortcode;
 * - **pisar el texto**: al reparar una página que existe sin el shortcode, se añade al final; no se borra lo que
 *   escribió quien la editó;
 * - **publicar una página rota en silencio**: si el shortcode no lo registra nadie, se anota en el registro.
 *
 * Además, lo que no ve un visitante no cuenta: una página en borrador con el shortcode correcto se considera
 * ausente y se crea publicada.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use Convoca\Core\Admin_Setup_Wizard;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Utils.php';
require_once dirname( __DIR__, 2 ) . '/includes/Logger.php';
require_once dirname( __DIR__, 2 ) . '/includes/Admin_Setup_Wizard.php';

/**
 * Asistente de instalación.
 */
final class SetupWizardTest extends TestCase {

	/**
	 * Asistente bajo prueba.
	 *
	 * @var Admin_Setup_Wizard
	 */
	private Admin_Setup_Wizard $wizard;

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		unset( $GLOBALS['_wp_filters'], $GLOBALS['_wp_die_lanza'], $_REQUEST['_wpnonce'] );
		$GLOBALS['_wp_stores'] = array(
			'options'     => array(),
			'post_meta'   => array(),
			'transients'  => array( 'convoca_logger_table_exists' => 1 ),
			'posts'       => array(),
			'usuarios'    => array(),
			'db_inserts'  => array(),
			'queries'     => array(),
			'db_reads'    => array(),
			'locks'       => array(),
			'shortcodes'  => array(),
		);
		$GLOBALS['_test_caps'] = array();
		$this->wizard          = new Admin_Setup_Wizard();
	}

	/**
	 * Siembra una página con su slug, estado y contenido.
	 *
	 * @param int    $id      Id.
	 * @param string $slug    Slug.
	 * @param string $content Contenido.
	 * @param string $estado  Estado.
	 */
	private function pagina( int $id, string $slug, string $content, string $estado = 'publish' ): void {
		// `WP_Post` de verdad: el producto comprueba `instanceof \WP_Post`, así que un objeto plano se lee como
		// «la página no existe» y el asistente la volvería a crear.
		$pagina               = new \WP_Post();
		$pagina->ID           = $id;
		$pagina->post_type    = 'page';
		$pagina->post_status  = $estado;
		$pagina->post_title   = ucfirst( $slug );
		$pagina->post_name    = $slug;
		$pagina->post_content = $content;
		$pagina->post_date    = '2026-01-01 10:00:00';
		$GLOBALS['_wp_stores']['posts'][ $id ] = $pagina;
	}

	/**
	 * Siembra las cuatro áreas que no son «panel-socio» con su shortcode correcto.
	 */
	private function sembrar_las_otras_cuatro(): void {
		$this->pagina( 1, 'alta-socios', '[convoca_alta_socio]' );
		$this->pagina( 3, 'pago', '[convoca_pago]' );
		$this->pagina( 4, 'verificar-socio', '[convoca_verificar_socio]' );
		$this->pagina( 5, 'verificar-certificado', '[convoca_verificar_certificado]' );
	}

	/**
	 * Crea ($id > 0) o devuelve la primera página publicada que contiene el shortcode.
	 *
	 * @param string $tag Shortcode sin corchetes.
	 * @return object|null
	 */
	private function pagina_con_shortcode( string $tag ) {
		foreach ( (array) $GLOBALS['_wp_stores']['posts'] as $p ) {
			if ( 'page' === ( $p->post_type ?? '' ) && 'publish' === ( $p->post_status ?? '' )
				&& false !== strpos( (string) ( $p->post_content ?? '' ), '[' . $tag . ']' ) ) {
				return $p;
			}
		}

		return null;
	}

	/**
	 * Las cinco páginas del sistema, con sus tres obligatorias.
	 */
	public function test_las_paginas_del_sistema(): void {
		$paginas = Admin_Setup_Wizard::system_pages();

		$this->assertSame(
			array( 'alta-socios', 'panel-socio', 'pago', 'verificar-socio', 'verificar-certificado' ),
			array_keys( $paginas ),
			'las cinco áreas'
		);
		$this->assertTrue( $paginas['alta-socios']['req'], 'el alta es obligatoria' );
		$this->assertTrue( $paginas['panel-socio']['req'] );
		$this->assertTrue( $paginas['pago']['req'] );
		$this->assertFalse( $paginas['verificar-socio']['req'], 'las de QR son opcionales' );
		$this->assertSame( '[convoca_mi_area]', $paginas['panel-socio']['sc'] );
	}

	/**
	 * La lista es filtrable: un plugin puede añadir su área sin tocar el núcleo.
	 */
	public function test_la_lista_es_filtrable(): void {
		add_filter(
			'convoca_wizard_system_pages',
			static function ( array $paginas ): array {
				$paginas['mi-area'] = array(
					'title' => 'Mi área',
					'sc'    => '[mi_area]',
					'req'   => false,
				);

				return $paginas;
			}
		);

		$this->assertArrayHasKey( 'mi-area', Admin_Setup_Wizard::system_pages() );
	}

	/**
	 * **Si todo está bien no se toca nada**: ni una creación, ni una actualización.
	 */
	public function test_si_esta_todo_no_hace_nada(): void {
		$tipos = array(
			1 => array( 'alta-socios', '[convoca_alta_socio]' ),
			2 => array( 'panel-socio', '[convoca_mi_area]' ),
			3 => array( 'pago', '[convoca_pago]' ),
			4 => array( 'verificar-socio', '[convoca_verificar_socio]' ),
			5 => array( 'verificar-certificado', '[convoca_verificar_certificado]' ),
		);
		foreach ( $tipos as $id => $dato ) {
			$this->pagina( $id, $dato[0], $dato[1] );
		}

		$resumen = $this->wizard->crear_paginas_del_sistema();

		$this->assertSame( array( 'creadas' => 0, 'reparadas' => 0, 'ok' => 5 ), $resumen );
		$this->assertSame( array(), (array) ( $GLOBALS['_wp_stores']['queries'] ?? array() ), 'no se escribe nada' );
	}

	/**
	 * Las que faltan se crean **publicadas** y con su shortcode.
	 */
	public function test_crea_las_que_faltan(): void {
		$this->pagina( 1, 'alta-socios', '[convoca_alta_socio]' );

		$resumen = $this->wizard->crear_paginas_del_sistema();

		$this->assertSame( 4, $resumen['creadas'], 'faltaban cuatro' );
		$this->assertSame( 1, $resumen['ok'], 'y la que ya estaba, bien' );
		foreach ( array( 'panel-socio', 'pago', 'verificar-socio', 'verificar-certificado' ) as $slug ) {
			$creada = null;
			foreach ( (array) $GLOBALS['_wp_stores']['posts'] as $p ) {
				if ( $slug === ( $p->post_name ?? '' ) ) {
					$creada = $p;
				}
			}
			$this->assertNotNull( $creada, "«{$slug}» creada" );
			$this->assertSame( 'publish', $creada->post_status, 'y publicada' );
			$this->assertNotEmpty( $creada->post_content, 'con su shortcode' );
		}
	}

	/**
	 * **Una página con el shortcode pero otro slug no se duplica** (issue #14): se reconoce por su shortcode.
	 */
	public function test_no_duplica_la_que_ya_tiene_el_shortcode(): void {
		$this->pagina( 9, 'mi-panel', '[convoca_mi_area]', 'publish' );
		$this->pagina( 1, 'alta-socios', '[convoca_alta_socio]' );
		$this->pagina( 3, 'pago', '[convoca_pago]' );
		$this->pagina( 4, 'verificar-socio', '[convoca_verificar_socio]' );
		$this->pagina( 5, 'verificar-certificado', '[convoca_verificar_certificado]' );

		$resumen = $this->wizard->crear_paginas_del_sistema();

		$this->assertSame( 0, $resumen['creadas'], 'ninguna nueva' );
		$this->assertSame( 5, $resumen['ok'] );
	}

	/**
	 * Una página que existe **sin** el shortcode se repara sin borrar el texto del autor.
	 */
	public function test_repara_sin_pisar_el_texto(): void {
		$this->pagina( 2, 'panel-socio', '<h2>Bienvenido</h2><p>Texto de la asociación.</p>' );
		$this->sembrar_las_otras_cuatro();

		$resumen = $this->wizard->crear_paginas_del_sistema();
		$pagina  = $GLOBALS['_wp_stores']['posts'][2];

		$this->assertSame( 1, $resumen['reparadas'] );
		$this->assertStringContainsString( 'Texto de la asociación', (string) $pagina->post_content, 'el texto se conserva' );
		$this->assertStringContainsString( '[convoca_mi_area]', (string) $pagina->post_content, 'y el shortcode se añade' );
	}

	/**
	 * Un shortcode viejo (`[mi_panel]`) se sustituye por el actual, sin duplicar el nuevo.
	 */
	public function test_sustituye_el_shortcode_retirado(): void {
		$this->pagina( 2, 'panel-socio', '<p>Tu área:</p>[mi_panel]' );
		$this->sembrar_las_otras_cuatro();

		$this->wizard->crear_paginas_del_sistema();
		$contenido = (string) $GLOBALS['_wp_stores']['posts'][2]->post_content;

		$this->assertStringNotContainsString( '[mi_panel]', $contenido, 'el viejo se va' );
		$this->assertSame( 1, substr_count( $contenido, '[convoca_mi_area]' ), 'y el nuevo aparece una sola vez' );
		$this->assertStringContainsString( 'Tu área:', $contenido );
	}

	/**
	 * **Lo que no ve el visitante no cuenta**: una página en borrador con el shortcode se considera ausente y se
	 * crea publicada.
	 */
	public function test_un_borrador_no_cuenta(): void {
		$this->pagina( 9, 'mi-panel', '[convoca_mi_area]', 'draft' );
		$this->sembrar_las_otras_cuatro();

		$resumen = $this->wizard->crear_paginas_del_sistema();

		$this->assertSame( 1, $resumen['creadas'], 'se crea una publicada' );
		$publicada = $this->pagina_con_shortcode( 'convoca_mi_area' );
		$this->assertNotNull( $publicada );
		$this->assertSame( 'publish', $publicada->post_status );
	}

	/**
	 * Si el shortcode no lo registra nadie, se anota en el registro: mejor eso que publicar una página que se verá
	 * como texto literal entre corchetes.
	 */
	public function test_avisa_si_el_shortcode_no_esta_registrado(): void {
		$this->wizard->crear_paginas_del_sistema();

		$mensajes = implode(
			' | ',
			array_map(
				'strval',
				array_column( array_column( (array) $GLOBALS['_wp_stores']['db_inserts'], 'data' ), 'message' )
			)
		);
		$this->assertStringContainsString( 'ningún plugin activo registra', $mensajes );
	}

	/**
	 * Y si está registrado, no hay aviso: el registro no se llena de ruido en una instalación sana.
	 */
	public function test_sin_aviso_si_el_shortcode_esta_registrado(): void {
		foreach ( array( 'convoca_alta_socio', 'convoca_mi_area', 'convoca_pago', 'convoca_verificar_socio', 'convoca_verificar_certificado' ) as $tag ) {
			// `shortcode_exists()` del arnés lee del registro propio, no de `_wp_stores`.
			$GLOBALS['_wp_shortcodes'][ $tag ] = '__return_empty';
		}

		$this->wizard->crear_paginas_del_sistema();

		$mensajes = implode(
			' | ',
			array_map(
				'strval',
				array_column( array_column( (array) $GLOBALS['_wp_stores']['db_inserts'], 'data' ), 'message' )
			)
		);
		$this->assertStringNotContainsString( 'ningún plugin activo registra', $mensajes );
	}

	/**
	 * **Sin permiso no se crea nada**: la guarda corta antes de tocar la base.
	 */
	public function test_sin_permiso_no_crea(): void {
		$GLOBALS['_wp_die_lanza'] = true;
		$GLOBALS['_test_caps']    = array( 'manage_options' => false );
		$_REQUEST['_wpnonce']     = 'bueno';

		try {
			$this->wizard->handle_create_pages();
			$this->fail( 'debía morir' );
		} catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( 'Acceso denegado', $e->getMessage() );
		}

		$this->assertSame( array(), (array) $GLOBALS['_wp_stores']['posts'], 'ni una página' );
	}
}
