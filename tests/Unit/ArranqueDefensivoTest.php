<?php
/**
 * El arranque del núcleo no debe tumbar el sitio (core#32).
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Un despliegue a medias deja ficheros sin llegar; el sitio tiene que seguir sirviendo.
 */
final class ArranqueDefensivoTest extends TestCase {

	/**
	 * Carga el arranque del plugin una sola vez.
	 */
	protected function setUp(): void {
		if ( ! function_exists( '\Convoca\Core\convoca_core_require' ) ) {
			require_once dirname( __DIR__, 2 ) . '/convoca-core.php';
		}
	}

	/**
	 * Un fichero que no está se declara y no mata.
	 */
	public function test_un_fichero_ausente_no_mata(): void {
		$this->assertTrue( function_exists( '\Convoca\Core\convoca_core_require' ), 'el arranque debe ofrecer la puerta' );
		$this->assertFalse( \Convoca\Core\convoca_core_require( 'No_Existe_Este_Fichero.php' ) );
	}

	/**
	 * Un fichero que sí está se carga.
	 */
	public function test_un_fichero_presente_se_carga(): void {
		$this->assertTrue( \Convoca\Core\convoca_core_require( 'Logger.php' ) );
	}

	/**
	 * Un módulo sin clase no arranca y no mata.
	 */
	public function test_un_modulo_ausente_no_mata(): void {
		$iniciado = \Convoca\Core\convoca_core_init( 'Convoca\Core\Modulo_Que_No_Existe' );

		$this->assertFalse( $iniciado );
	}

	/**
	 * Un módulo con clase arranca de verdad.
	 */
	public function test_un_modulo_presente_arranca(): void {
		$clase = __NAMESPACE__ . '\ModuloDePrueba';

		$this->assertTrue( \Convoca\Core\convoca_core_init( $clase ) );
		$this->assertTrue( ModuloDePrueba::$iniciado, 'init() se ha llamado' );
	}
}

/**
 * Módulo de mentira para la prueba anterior.
 */
final class ModuloDePrueba {

	/**
	 * Si le han llamado.
	 *
	 * @var bool
	 */
	public static bool $iniciado = false;

	/**
	 * Arranque.
	 *
	 * @return void
	 */
	public static function init(): void {
		self::$iniciado = true;
	}
}
