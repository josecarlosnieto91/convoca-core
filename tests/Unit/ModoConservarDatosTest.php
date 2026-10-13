<?php
/**
 * Modo «conservar datos» al desinstalar: el núcleo tiene que respetarlo por sus dos vías.
 *
 * Por qué existe: includes/Uninstall_Settings.php ofrece la casilla y dice de sí mismo que «todos
 * los plugins leen esta misma opción» (convoca_uninstall_keep_data). Medido en un WordPress limpio
 * (13/10/2026): con la casilla marcada, este plugin borraba sus tres tablas igualmente porque su
 * uninstall.php solo miraba la constante de wp-config.php.
 *
 * @package Convoca\Core
 */

namespace Convoca\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;

class ModoConservarDatosTest extends TestCase {

	private function uninstall(): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/uninstall.php' );
	}

	public function test_la_constante_de_wp_config_detiene_el_borrado(): void {
		$this->assertStringContainsString( 'CONVOCA_KEEP_DATA_ON_UNINSTALL', $this->uninstall() );
	}

	public function test_la_opcion_de_la_interfaz_tambien_detiene_el_borrado(): void {
		$this->assertStringContainsString(
			'convoca_uninstall_keep_data',
			$this->uninstall(),
			'La interfaz ofrece la casilla de conservar datos, así que la desinstalación tiene que leerla.'
		);
	}

	public function test_la_guarda_esta_antes_de_cualquier_borrado(): void {
		$txt    = $this->uninstall();
		$guarda = strpos( $txt, 'convoca_uninstall_keep_data' );
		$borrar = strpos( $txt, 'delete_option' );
		$tabla  = strpos( $txt, 'DROP TABLE' );
		$this->assertNotFalse( $guarda, 'No hay guarda de conservar datos.' );
		$this->assertNotFalse( $borrar, 'La prueba espera que el fichero borre opciones.' );
		$this->assertLessThan( $borrar, $guarda, 'La guarda tiene que ir ANTES de borrar nada.' );
		if ( false !== $tabla ) {
			$this->assertLessThan( $tabla, $guarda, 'La guarda tiene que ir ANTES de tocar las tablas.' );
		}
	}
}
