<?php
/**
 * Las bibliotecas que viajan con Core se resuelven de verdad.
 *
 * Por qué existe: Core es quien trae `dompdf` y `chillerlan/php-qrcode`, y tres plugins
 * (Members, Courses y Enroll) dependen de que esas clases se resuelvan desde su autoloader.
 * Ya pasó una vez que un paquete salió sin `vendor/` y el arranque siguió en silencio: el
 * plugin se activaba y las clases «no existían» al usarlas.
 *
 * Esta prueba comprueba las dos cosas que fallaron entonces: que la clase **carga** y que
 * carga **desde el `vendor/` del plugin**, no desde otro sitio por casualidad.
 *
 * Prueba en negativo: si se empaqueta sin `vendor/`, `class_exists()` devuelve falso y el
 * segundo caso señala la ruta.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Bibliotecas compartidas.
 */
final class BibliotecasCompartidasTest extends TestCase {

	/**
	 * Las clases comunes cargan.
	 *
	 * @dataProvider bibliotecas
	 *
	 * @param string $clase Clase a comprobar.
	 */
	public function test_la_clase_carga( string $clase ): void {
		$this->assertTrue(
			class_exists( $clase ),
			$clase . ' no se resuelve: ¿falta vendor/ en el paquete?'
		);
	}

	/**
	 * Y cargan desde el vendor de este plugin.
	 *
	 * @dataProvider bibliotecas
	 *
	 * @param string $clase Clase a comprobar.
	 */
	public function test_la_clase_sale_del_vendor_del_plugin( string $clase ): void {
		if ( ! class_exists( $clase ) ) {
			$this->markTestSkipped( $clase . ' no está instalada en este entorno.' );
		}

		$ruta = ( new \ReflectionClass( $clase ) )->getFileName();

		$this->assertIsString( $ruta );
		$this->assertStringContainsString(
			dirname( __DIR__, 2 ) . '/vendor/',
			$ruta,
			$clase . ' se resolvió desde fuera del vendor del plugin: ' . $ruta
		);
	}

	/**
	 * Bibliotecas que Core pone a disposición de los demás plugins.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function bibliotecas(): array {
		return array(
			'dompdf'     => array( \Dompdf\Dompdf::class ),
			'qr (php-qrcode)' => array( \chillerlan\QRCode\QRCode::class ),
		);
	}
}
