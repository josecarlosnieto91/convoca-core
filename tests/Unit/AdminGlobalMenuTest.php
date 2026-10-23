<?php
/**
 * El menú global y la página de salud.
 *
 * La página de salud es lo primero que se abre cuando algo va mal: tiene que decir la verdad sobre las tablas (si
 * falta una, se ve) y no puede pintarse sin permiso. Y el menú no puede registrar una página con la capacidad
 * equivocada: la de registros se lee con `common_view_logs`, no con `manage_options`.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Utils.php';
require_once dirname( __DIR__, 2 ) . '/includes/Logger.php';
require_once dirname( __DIR__, 2 ) . '/includes/Features.php';
require_once dirname( __DIR__, 2 ) . '/includes/System_Checks.php';
require_once dirname( __DIR__, 2 ) . '/includes/admin-global-menu.php';

if ( ! defined( 'CONVOCA_COMMON_URL' ) ) {
	define( 'CONVOCA_COMMON_URL', 'https://example.test/convoca-core/' );
}
if ( ! defined( 'CONVOCA_COMMON_VERSION' ) ) {
	define( 'CONVOCA_COMMON_VERSION', '2.3.44' );
}

/**
 * Menú global.
 */
final class AdminGlobalMenuTest extends TestCase {

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
		$this->previo             = $GLOBALS['_wp_stores'] ?? array();
		$GLOBALS['_wp_filters']   = array();
		$GLOBALS['_wp_hooks']     = array();
		$GLOBALS['_wp_enqueue']   = array( 'styles' => array(), 'scripts' => array() );
		$GLOBALS['_test_caps']    = array();
		$GLOBALS['_wp_stores']    = array(
			'options'      => array(),
			'transients'   => array( 'convoca_logger_table_exists' => 1 ),
			'posts'        => array(),
			'post_meta'    => array(),
			'usuarios'     => array(),
			'queries'      => array(),
			'db_inserts'   => array(),
			'styles'       => array(),
			'scripts'      => array(),
			'localizados'  => array(),
			'menu_pages'   => array(),
			'submenu_pages' => array(),
			'get_var_mapa' => array(),
		);
		unset( $_GET['force'] );
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores'] = $this->previo;
		unset( $GLOBALS['_wp_enqueue'], $GLOBALS['_test_caps'], $GLOBALS['post'], $_GET['force'] );
	}

	/**
	 * Pinta una función y devuelve el HTML.
	 *
	 * @param callable $fn Función.
	 * @return string
	 */
	private function pinta( callable $fn ): string {
		ob_start();
		try {
			$fn();
		} finally {
			$html = (string) ob_get_clean();
		}

		return $html;
	}

	/**
	 * El menú: cuatro entradas y cada una con **su** capacidad.
	 */
	public function test_el_menu(): void {
		\Convoca\Core\convoca_register_global_menu();

		$menu = (array) $GLOBALS['_wp_stores']['menu_pages'];
		$this->assertArrayHasKey( 'convoca-core', $menu );
		$this->assertSame( 'manage_options', $menu['convoca-core']['capability'] );

		$submenus = (array) $GLOBALS['_wp_stores']['submenu_pages'];
		$por_slug = array();
		foreach ( $submenus as $p ) {
			$por_slug[ $p['menu_slug'] ] = $p;
		}

		$this->assertArrayHasKey( 'convoca-core', $por_slug );
		$this->assertArrayHasKey( 'conv-dashboard', $por_slug );
		$this->assertArrayHasKey( 'conv-logs-central', $por_slug );
		$this->assertSame( 'common_view_logs', $por_slug['conv-logs-central']['capability'], 'los registros se leen con su capacidad' );
		$this->assertSame( 'manage_options', $por_slug['conv-dashboard']['capability'] );
	}

	/**
	 * Los textos del JS dicen qué hacer y están todos.
	 */
	public function test_los_textos_del_js(): void {
		$textos = \Convoca\Core\convoca_common_js_strings();

		$this->assertSame(
			array( 'sinConexion', 'sesionCaducada', 'accionNoDisponible', 'servidorNoDisponible', 'respuestaInesperada', 'reintentar' ),
			array_keys( $textos )
		);

		foreach ( $textos as $clave => $texto ) {
			$this->assertNotSame( '', trim( $texto ), $clave );
			$this->assertGreaterThan( 20, strlen( $texto ), "«{$clave}» explica algo, no es un «Error»" );
			$this->assertStringNotContainsString( 'Error desconocido', $texto );
			$this->assertStringNotContainsString( 'Service temporarily', $texto );
			$this->assertStringNotContainsString( 'Authentication required', $texto );
		}
	}

	/**
	 * Los assets públicos: sólo si la página lleva algo de Convoca, o si un filtro lo pide.
	 */
	public function test_los_assets_publicos(): void {
		$post               = new \WP_Post();
		$post->ID           = 1;
		$post->post_type    = 'page';
		$post->post_status  = 'publish';
		$post->post_content = '<p>Texto normal</p>';
		$GLOBALS['_wp_stores']['posts'][1] = $post;
		// `get_post()` sin id lee el post actual, como WordPress.
		$GLOBALS['post'] = $post;

		$GLOBALS['_wp_stores']['enqueues'] = array();
		\Convoca\Core\convoca_common_enqueue_assets();

		$this->assertSame( array(), $GLOBALS['_wp_enqueue']['styles'], 'sin Convoca en la página no se sirve nada' );
		$this->assertSame( array(), (array) $GLOBALS['_wp_stores']['enqueues'], 'ni el script' );
		$this->assertArrayHasKey( 'convoca-common-js', (array) $GLOBALS['_wp_stores']['scripts'], 'pero sí se registra' );
		$this->assertArrayHasKey( 'convocaI18n', (array) $GLOBALS['_wp_stores']['localizados'], 'con sus textos' );

		$post->post_content = '[convoca_alta_socio]';
		\Convoca\Core\convoca_common_enqueue_assets();
		$this->assertContains( 'convoca-core', $GLOBALS['_wp_enqueue']['styles'] );
		$this->assertContains( 'convoca-common-js', (array) $GLOBALS['_wp_stores']['enqueues'] );

		// Un filtro puede pedirlo aunque el contenido no lo lleve.
		$GLOBALS['_wp_enqueue'] = array( 'styles' => array(), 'scripts' => array() );
		$GLOBALS['_wp_stores']['enqueues'] = array();
		$post->post_content     = '';
		add_filter( 'convoca_need_common_assets', static fn(): bool => true );
		\Convoca\Core\convoca_common_enqueue_assets();
		$this->assertContains( 'convoca-core', $GLOBALS['_wp_enqueue']['styles'] );
	}

	/**
	 * En el panel el CSS va **siempre**: las pantallas propias usan sus clases y depender del contenido dejaba la de
	 * salud sin estilos.
	 */
	public function test_los_assets_del_panel(): void {
		\Convoca\Core\convoca_common_enqueue_admin_assets();

		$this->assertContains( 'convoca-core', $GLOBALS['_wp_enqueue']['styles'] );
		$this->assertContains( 'convoca-common-admin-js', (array) $GLOBALS['_wp_stores']['enqueues'] );
		$this->assertArrayHasKey( 'convocaAdminI18n', (array) $GLOBALS['_wp_stores']['localizados'] );
	}

	/**
	 * La página de salud: sin permiso muere; con permiso dice la versión y el estado de sus tablas.
	 */
	public function test_la_pagina_de_salud(): void {
		$GLOBALS['_test_caps']    = array( 'manage_options' => false );
		$GLOBALS['_wp_die_lanza'] = true;
		try {
			$this->pinta( 'Convoca\\Core\\convoca_health_page' );
			$this->fail( 'sin permiso no puede pintarse' );
		} catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( 'wp_die', $e->getMessage() );
		}
		unset( $GLOBALS['_wp_die_lanza'] );

		$GLOBALS['_test_caps'] = array( 'manage_options' => true );
		// Las tres tablas: dos existen y la de reintentos no.
		$GLOBALS['_wp_stores']['get_var_mapa'] = array( 'convoca_webhook_retries' => '' );

		$html = $this->pinta( 'Convoca\\Core\\convoca_health_page' );

		$this->assertStringContainsString( 'Salud del Sistema Convoca', $html );
		$this->assertStringContainsString( 'v' . CONVOCA_COMMON_VERSION, $html );
		$this->assertStringContainsString( 'Forzar comprobación', $html );
		$this->assertStringContainsString( 'convoca_logs', $html );
		$this->assertStringContainsString( 'No encontrada', $html, 'la tabla que falta se ve' );
		$this->assertStringContainsString( 'convoca-diagnostic', $html );
	}
}
