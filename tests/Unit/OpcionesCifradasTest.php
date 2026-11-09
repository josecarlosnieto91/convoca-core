<?php
/**
 * Pruebas del cifrado transparente de opciones.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use Convoca\Core\Cifrado;
use Convoca\Core\Opciones_Cifradas;
use PHPUnit\Framework\TestCase;

/**
 * Cubre lectura, escritura y valores que no se tocan.
 */
final class OpcionesCifradasTest extends TestCase {

	/**
	 * Filtro de llave de la prueba.
	 *
	 * @var callable|null
	 */
	private $filtro = null;

	/**
	 * Prepara la llave sin dejar la constante global.
	 */
	protected function setUp(): void {
		$this->filtro = static fn(): string => base64_encode( str_repeat( 'k', 32 ) );
		add_filter( 'convoca_cifrado_clave', $this->filtro );
	}

	/**
	 * Quita la llave.
	 */
	protected function tearDown(): void {
		if ( $this->filtro ) {
			remove_filter( 'convoca_cifrado_clave', $this->filtro );
		}
	}

	/**
	 * Al guardar, el IBAN se cifra.
	 */
	public function test_escribir_cifra_el_iban(): void {
		$salida = Opciones_Cifradas::escribir(
			array(
				'iban' => 'ES91 2100 0418 4502 0005 1332',
				'otro' => 'se queda',
			)
		);

		$this->assertStringStartsWith( 'enc:v1:', (string) $salida['iban'] );
		$this->assertSame( 'se queda', $salida['otro'] );
	}

	/**
	 * Al leer, el IBAN vuelve en claro.
	 */
	public function test_leer_descifra_el_iban(): void {
		$cifrado = Cifrado::cifrar( 'ES91 2100 0418 4502 0005 1332' );
		$salida  = Opciones_Cifradas::leer( array( 'iban' => $cifrado ) );

		$this->assertSame( 'ES91 2100 0418 4502 0005 1332', $salida['iban'] );
	}

	/**
	 * Un texto antiguo se devuelve tal cual.
	 */
	public function test_leer_tolera_texto_antiguo(): void {
		$salida = Opciones_Cifradas::leer( array( 'iban' => 'ES00 0000' ) );

		$this->assertSame( 'ES00 0000', $salida['iban'] );
	}

	/**
	 * Lo que no es un array o no tiene IBAN no se toca.
	 */
	public function test_valores_ajenos_no_se_tocan(): void {
		$this->assertSame( 'texto', Opciones_Cifradas::leer( 'texto' ) );
		$this->assertSame( 'texto', Opciones_Cifradas::escribir( 'texto' ) );
		$this->assertSame( array( 'otro' => 'x' ), Opciones_Cifradas::leer( array( 'otro' => 'x' ) ) );
		$this->assertSame( array( 'iban' => '' ), Opciones_Cifradas::escribir( array( 'iban' => '' ) ) );
	}
}
