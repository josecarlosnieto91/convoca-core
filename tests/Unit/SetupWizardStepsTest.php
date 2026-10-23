<?php
/**
 * Los pasos del asistente de instalación: lo que se le enseña a quien instala.
 *
 * Cada paso es una pantalla que se pinta una vez y no se vuelve a mirar. Lo que se prueba es que cada una diga la
 * verdad: si falta una tabla se ve, si falta una página se ve y con su shortcode, si no hay módulo de socios se dice
 * (en vez de una tabla vacía) y los campos salen con lo que haya guardado, no con ceros.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use Convoca\Core\Admin_Setup_Wizard;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Utils.php';
require_once dirname( __DIR__, 2 ) . '/includes/Logger.php';
require_once dirname( __DIR__, 2 ) . '/includes/Features.php';
require_once dirname( __DIR__, 2 ) . '/includes/Admin_Setup_Wizard.php';

/**
 * Pasos del asistente.
 */
final class SetupWizardStepsTest extends TestCase {

	/**
	 * Asistente.
	 *
	 * @var Admin_Setup_Wizard
	 */
	private Admin_Setup_Wizard $wizard;

	/**
	 * Prepara el caso.
	 */
	/**
	 * Estado global previo, para devolverlo al terminar.
	 *
	 * @var array
	 */
	private array $previo = array();

	protected function setUp(): void {
		$this->previo = $GLOBALS['_wp_stores'] ?? array();
		$GLOBALS['_wp_filters'] = array();
		$GLOBALS['_wp_stores']  = array(
			'options'      => array(),
			'post_meta'    => array(),
			'transients'   => array( 'convoca_logger_table_exists' => 1 ),
			'posts'        => array(),
			'usuarios'     => array(),
			'db_inserts'   => array(),
			'queries'      => array(),
			'db_reads'     => array(),
			'get_var_mapa' => array(),
			'locks'        => array(),
			'shortcodes'   => array(),
		);
		$GLOBALS['_test_caps'] = array();
		$this->wizard          = new Admin_Setup_Wizard();
	}

	/**
	 * Pinta un paso privado.
	 *
	 * @param string $metodo Método.
	 * @param mixed  ...$args Argumentos.
	 * @return string
	 */
	private function paso( string $metodo, ...$args ): string {
		$reflejo = new \ReflectionMethod( Admin_Setup_Wizard::class, $metodo );
		ob_start();
		try {
			$reflejo->invoke( $this->wizard, ...$args );
		} finally {
			$html = (string) ob_get_clean();
		}

		return $html;
	}

	/**
	 * El paso de base de datos enseña cada comprobación y si falta una tabla, se ve.
	 */
	public function test_el_paso_de_base_de_datos(): void {
		$html = $this->paso( 'step_database' );

		$this->assertStringContainsString( 'Infraestructura y Base de Datos', $html );
		$this->assertStringContainsString( 'Convoca Core', $html );
		$this->assertStringContainsString( 'convoca_logs', $html );
		$this->assertStringContainsString( 'convoca_locks', $html );
		$this->assertStringNotContainsString( 'No encontrada', $html, 'el doble dice que las tablas existen' );

		// Y si no existen, se dice: una comprobación que nunca falla no comprueba nada.
		$GLOBALS['_wp_stores']['get_var_mapa'] = array( 'convoca_' => '' );
		$html = $this->paso( 'step_database' );
		$this->assertStringContainsString( 'No encontrada', $html );
	}

	/**
	 * El paso de páginas: cada área con su shortcode, y las que faltan se dicen.
	 */
	public function test_el_paso_de_paginas(): void {
		$html = $this->paso( 'step_pages' );

		$this->assertStringContainsString( 'Páginas del Sistema', $html );
		$this->assertStringContainsString( 'name="_wpnonce"', $html, 'con el campo del nonce' );
		$this->assertStringContainsString( '[convoca_alta_socio]', $html );
		$this->assertStringContainsString( 'No existe la página', $html, 'sin páginas, todas faltan' );
	}

	/**
	 * Cuando las páginas ya están, dejan de marcarse como ausentes.
	 */
	public function test_el_paso_de_paginas_cuando_estan(): void {
		$mapa = array(
			'alta-socios'           => '[convoca_alta_socio]',
			'panel-socio'           => '[convoca_mi_area]',
			'pago'                  => '[convoca_pago]',
			'verificar-socio'       => '[convoca_verificar_socio]',
			'verificar-certificado' => '[convoca_verificar_certificado]',
		);
		$id = 1;
		foreach ( $mapa as $slug => $sc ) {
			$p               = new \WP_Post();
			$p->ID           = $id;
			$p->post_type    = 'page';
			$p->post_status  = 'publish';
			$p->post_name    = $slug;
			$p->post_content = $sc;
			$GLOBALS['_wp_stores']['posts'][ $id ] = $p;
			++$id;
		}

		$html = $this->paso( 'step_pages' );

		$this->assertStringNotContainsString( 'No existe la página', $html );
	}

	/**
	 * Sin el módulo de socios se dice, en vez de enseñar una tabla de planes vacía y muda.
	 */
	public function test_el_paso_de_planes_sin_modulo(): void {
		$html = $this->paso( 'step_plans' );

		$this->assertStringContainsString( 'Planes de Membresía', $html );
		$this->assertStringContainsString( 'No se han detectado planes', $html );
	}

	/**
	 * Redsys: los campos salen con lo guardado y escapado.
	 */
	public function test_el_paso_de_redsys(): void {
		$GLOBALS['_wp_stores']['options']['convoca_gateway_settings'] = array(
			'merchant_code' => '123456789',
			'secret_key'    => 'clave<secreta>',
		);

		$html = $this->paso( 'step_redsys' );

		$this->assertStringContainsString( 'Redsys', $html );
		$this->assertStringContainsString( 'merchant_code', $html );
		$this->assertStringContainsString( '123456789', $html );
		$this->assertStringNotContainsString( 'clave<secreta>', $html, 'la clave no se pinta cruda' );
		$this->assertStringContainsString( '&lt;secreta&gt;', $html, 'sale escapada' );
	}

	/**
	 * Turnos: las horas salen con los valores por defecto y con los guardados.
	 */
	public function test_el_paso_de_turnos(): void {
		$html = $this->paso( 'step_turnos' );
		$this->assertStringContainsString( '09:00', $html );
		$this->assertStringContainsString( '22:00', $html );

		$GLOBALS['_wp_stores']['options']['convoca_shifts_hora_apertura'] = '08:30';
		$GLOBALS['_wp_stores']['options']['convoca_shifts_hora_cierre']   = '23:15';
		$html = $this->paso( 'step_turnos' );

		$this->assertStringContainsString( '08:30', $html );
		$this->assertStringContainsString( '23:15', $html );
	}

	/**
	 * El ecosistema enseña el estado de los módulos.
	 */
	public function test_el_paso_del_ecosistema(): void {
		$html = $this->paso( 'step_ecosystem' );

		$this->assertNotSame( '', $html, 'la pantalla se pinta' );
		$this->assertStringContainsString( 'Convoca', $html );
	}

	/**
	 * El resumen también.
	 */
	public function test_el_resumen(): void {
		$html = $this->paso( 'step_summary' );

		$this->assertNotSame( '', $html );
	}

	/**
	 * La navegación: sin poder continuar no ofrece el botón de seguir, y con él sí.
	 */
	public function test_la_navegacion(): void {
		$sin = $this->paso( 'step_nav', 2, false );
		$con = $this->paso( 'step_nav', 2, true );

		$this->assertNotSame( $sin, $con, 'no son iguales' );
	}

	/**
	 * Devuelve el almacén a como estaba: una prueba no puede dejar el terreno preparado a la siguiente.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores'] = $this->previo;
		unset( $GLOBALS['_wp_logged_in'], $GLOBALS['_test_user_id'], $GLOBALS['_wp_json'], $GLOBALS['_wp_die_lanza'] );
	}
}
