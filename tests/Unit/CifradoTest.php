<?php
/**
 * Cifrado de datos personales: ida y vuelta, manipulación y búsqueda por índice ciego.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use Convoca\Core\Cifrado;
use PHPUnit\Framework\TestCase;

/**
 * Primitivas de cifrado.
 */
final class CifradoTest extends TestCase {

	/**
	 * Prepara llave y clase.
	 */
	protected function setUp(): void {
		if ( ! defined( 'CONVOCA_ENCRYPTION_KEY' ) ) {
			define( 'CONVOCA_ENCRYPTION_KEY', base64_encode( str_repeat( 'k', 32 ) ) );
		}

		require_once dirname( __DIR__, 2 ) . '/includes/Cifrado.php';
	}

	/**
	 * Ida y vuelta con un DNI real de ejemplo.
	 */
	public function test_ida_y_vuelta(): void {
		$cifrado = Cifrado::cifrar( '12345678Z' );

		$this->assertStringStartsWith( 'enc:v1:', $cifrado );
		$this->assertSame( '12345678Z', Cifrado::descifrar( $cifrado ) );
	}

	/**
	 * Dos cifrados del mismo valor no son iguales: el nonce es aleatorio.
	 */
	public function test_dos_cifrados_no_coinciden(): void {
		$this->assertNotSame( Cifrado::cifrar( '12345678Z' ), Cifrado::cifrar( '12345678Z' ) );
	}

	/**
	 * Un ciphertext manipulado no devuelve basura: devuelve vacío.
	 */
	public function test_valor_manipulado_no_devuelve_basura(): void {
		$cifrado = Cifrado::cifrar( '12345678Z' );
		$roto    = substr( $cifrado, 0, -2 ) . 'AA';

		$this->assertSame( '', Cifrado::descifrar( $roto ) );
	}

	/**
	 * Los valores antiguos en claro se leen sin migrar.
	 */
	public function test_texto_antiguo_en_claro_se_lee(): void {
		$this->assertSame( '12345678Z', Cifrado::descifrar( '12345678Z' ) );
		$this->assertFalse( Cifrado::es_cifrado( '12345678Z' ) );
	}

	/**
	 * Vacío no se cifra.
	 */
	public function test_vacio_no_se_toca(): void {
		$this->assertSame( '', Cifrado::cifrar( '' ) );
		$this->assertSame( '', Cifrado::descifrar( '' ) );
	}

	/**
	 * Ya cifrado no se vuelve a cifrar.
	 */
	public function test_no_se_cifra_dos_veces(): void {
		$cifrado = Cifrado::cifrar( '12345678Z' );

		$this->assertSame( $cifrado, Cifrado::cifrar( $cifrado ) );
	}

	/**
	 * El índice ciego normaliza guiones, puntos y mayúsculas.
	 */
	public function test_indice_ciego_normaliza(): void {
		$this->assertSame(
			Cifrado::indice_ciego( '12.345.678-Z' ),
			Cifrado::indice_ciego( '12345678z' )
		);
		$this->assertNotSame(
			Cifrado::indice_ciego( '12345678Z' ),
			Cifrado::indice_ciego( 'X1234567L' )
		);
	}

	/**
	 * Hay llave y motor en el arnés.
	 */
	public function test_disponible(): void {
		$this->assertTrue( Cifrado::disponible() );
	}
}
