<?php
/**
 * La sonda de rendimiento.
 *
 * Sirve para mirar una petición lenta en producción, así que lo que se prueba es que **mida lo que dice**: que no
 * arranque sin permiso, que sólo apunte lo que pasa del umbral (si apunta todo, el informe es ruido) y que el informe
 * diga algo incluso cuando no hay nada lento.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use Convoca\Core\Performance_Probe;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Utils.php';
require_once dirname( __DIR__, 2 ) . '/includes/Logger.php';
require_once dirname( __DIR__, 2 ) . '/includes/Performance_Probe.php';

/**
 * Sonda de rendimiento.
 */
final class PerformanceProbeTest extends TestCase {

	/**
	 * Estado global previo.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Prepara el caso y deja la sonda apagada (las estáticas sobreviven entre pruebas).
	 */
	protected function setUp(): void {
		$this->previo              = $GLOBALS['_wp_stores'] ?? array();
		$GLOBALS['_wp_filters']    = array();
		$GLOBALS['_wp_hooks']      = array();
		$GLOBALS['_wp_stores']     = array();
		$GLOBALS['_test_caps']     = array();
		$GLOBALS['_wp_stub_next_id'] = null;
		unset( $_GET['convoca_probe'] );

		$licencia = new \ReflectionClass( Performance_Probe::class );
		foreach ( array( 'active', 'slow_hooks', 'slow_queries', 'hook_stack', 'start' ) as $prop ) {
			$p = $licencia->getProperty( $prop );
			$p->setValue( null, 'start' === $prop ? 0.0 : ( 'active' === $prop ? false : array() ) );
		}
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores'] = $this->previo;
		unset( $GLOBALS['_test_caps'], $_GET['convoca_probe'] );
	}

	/**
	 * Lee una estática privada.
	 *
	 * @param string $prop Propiedad.
	 * @return mixed
	 */
	private function estatica( string $prop ) {
		$p = new \ReflectionProperty( Performance_Probe::class, $prop );

		return $p->getValue();
	}

	/**
	 * No arranca sola: hace falta el parámetro **y** permiso.
	 */
	public function test_no_arranca_sola(): void {
		Performance_Probe::init();
		$this->assertFalse( $this->estatica( 'active' ) );

		$_GET['convoca_probe'] = '1';
		$GLOBALS['_test_caps'] = array( 'manage_options' => false );
		Performance_Probe::init();
		$this->assertFalse( $this->estatica( 'active' ), 'sin permiso no' );

		$GLOBALS['_test_caps'] = array( 'manage_options' => true );
		Performance_Probe::init();
		$this->assertTrue( $this->estatica( 'active' ) );
		$en_shutdown = (array) ( $GLOBALS['_wp_hooks']['shutdown'] ?? array() );
		$this->assertContains( array( Performance_Probe::class, 'render' ), $en_shutdown );
		// `start()` también engancha la captura de consultas lentas: aquí se cuenta **render**, que es lo que
		// duplicaría el informe si `init()` se llamara dos veces.
		$renders = static fn( array $hooks ): int => count(
			array_filter( $hooks, static fn( $c ): bool => array( Performance_Probe::class, 'render' ) === $c )
		);

		Performance_Probe::init();
		$this->assertSame( 1, $renders( (array) ( $GLOBALS['_wp_hooks']['shutdown'] ?? array() ) ), 'no se duplica' );
	}

	/**
	 * Sólo se apunta lo que pasa del umbral: si se apuntara todo, el informe no diría nada.
	 */
	public function test_solo_lo_lento(): void {
		Performance_Probe::start();
		$umbral = new \ReflectionProperty( Performance_Probe::class, 'hook_threshold' );
		$umbral->setValue( null, 0.005 );

		Performance_Probe::hook_enter( 'convoca_rapido' );
		Performance_Probe::hook_exit( 'convoca_rapido' );
		$this->assertSame( array(), $this->estatica( 'slow_hooks' ), 'un hook instantáneo no se apunta' );

		Performance_Probe::hook_enter( 'convoca_lento' );
		usleep( 10000 );
		Performance_Probe::hook_exit( 'convoca_lento' );
		$this->assertArrayHasKey( 'convoca_lento', $this->estatica( 'slow_hooks' ) );

		// El pseudo-hook `all` se ignora: es el que dispara el propio medidor.
		Performance_Probe::hook_enter( 'all' );
		Performance_Probe::hook_exit( 'all' );
		$this->assertArrayNotHasKey( 'all', $this->estatica( 'slow_hooks' ) );
	}

	/**
	 * Con la sonda apagada, los ganchos no hacen nada.
	 */
	public function test_apagada_no_mide(): void {
		Performance_Probe::hook_enter( 'convoca_x' );
		Performance_Probe::hook_exit( 'convoca_x' );
		$this->assertSame( array(), $this->estatica( 'slow_hooks' ) );
	}

	/**
	 * Las consultas lentas: umbral de 20 ms, SQL normalizado y recortado.
	 */
	public function test_las_consultas_lentas(): void {
		global $wpdb;
		Performance_Probe::start();
		$wpdb->queries = array(
			array( "SELECT  *\n  FROM wp_posts   WHERE ID = 1", 0.030, 'llamador.php:1' ),
			array( 'SELECT 1', 0.001, 'otro.php:2' ),
			array( 'SELECT 2' ),
		);

		Performance_Probe::capture_slow_queries();

		$lentas = $this->estatica( 'slow_queries' );
		$this->assertCount( 1, $lentas, 'sólo la que pasa de 20 ms' );
		$this->assertSame( 'SELECT * FROM wp_posts WHERE ID = 1', $lentas[0]['sql'], 'sin saltos ni espacios de más' );
		$this->assertSame( 30.0, $lentas[0]['ms'] );
		$this->assertSame( 'llamador.php:1', $lentas[0]['caller'] );
	}

	/**
	 * El informe: sin nada lento lo dice, y con datos enseña el total y el top.
	 */
	public function test_el_informe(): void {
		$apagado = $this->pinta();
		$this->assertSame( '', $apagado, 'apagada no imprime nada' );

		Performance_Probe::start();
		global $wpdb;
		$wpdb->num_queries = 42;

		$vacio = $this->pinta();
		$this->assertStringContainsString( 'CONVOCA PERFORMANCE PROBE', $vacio );
		$this->assertStringContainsString( 'Queries: 42', $vacio );
		$this->assertStringContainsString( '(ninguno)', $vacio );
		$this->assertStringContainsString( '(ninguna)', $vacio );

		$umbral = new \ReflectionProperty( Performance_Probe::class, 'hook_threshold' );
		$umbral->setValue( null, 0.0 );
		Performance_Probe::hook_enter( 'convoca_pesado' );
		Performance_Probe::hook_exit( 'convoca_pesado' );

		$con = $this->pinta();
		$this->assertStringContainsString( 'convoca_pesado', $con );
	}

	/**
	 * Pinta el informe y devuelve el HTML.
	 *
	 * @return string
	 */
	private function pinta(): string {
		ob_start();
		try {
			Performance_Probe::render();
		} finally {
			$html = (string) ob_get_clean();
		}

		return $html;
	}
}
