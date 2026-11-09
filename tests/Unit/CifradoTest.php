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

	/**
	 * Sin llave no se cifra, pero tampoco se pierde el valor.
	 */
	public function test_sin_llave_no_cifra_pero_no_pierde(): void {
		$cifrado = Cifrado::cifrar( '12345678Z' );

		add_filter(
			'convoca_cifrado_clave',
			static function () {
				return '';
			}
		);

		$this->assertSame( '12345678Z', Cifrado::cifrar( '12345678Z' ) );
		$this->assertSame( '', Cifrado::descifrar( $cifrado ) );
		$this->assertFalse( Cifrado::disponible() );
		$this->assertSame( '', Cifrado::indice_ciego( '12345678Z' ) );

		remove_all_filters( 'convoca_cifrado_clave' );
	}

	/**
	 * Una llave con formato inválido no se usa.
	 */
	public function test_llave_invalida_no_esta_disponible(): void {
		add_filter(
			'convoca_cifrado_clave',
			static function () {
				return 'no-es-base64';
			}
		);

		$this->assertFalse( Cifrado::disponible() );
		$this->assertSame( '', Cifrado::clave() );

		remove_all_filters( 'convoca_cifrado_clave' );
	}

	/**
	 * Una llave de 32 bytes en crudo también vale.
	 */
	public function test_llave_en_crudo(): void {
		add_filter(
			'convoca_cifrado_clave',
			static function () {
				return str_repeat( 'z', 32 );
			}
		);

		$this->assertTrue( Cifrado::disponible() );
		$this->assertSame( '12345678Z', Cifrado::descifrar( Cifrado::cifrar( '12345678Z' ) ) );

		remove_all_filters( 'convoca_cifrado_clave' );
	}

	/**
	 * Cargas inválidas no devuelven basura.
	 */
	public function test_cargas_invalidas(): void {
		$this->assertSame( '', Cifrado::descifrar( 'enc:v1:!!!' ) );
		$this->assertSame( '', Cifrado::descifrar( 'enc:v1:' . base64_encode( 'x' ) ) );
		$this->assertSame( '', Cifrado::descifrar( 'enc:v1:' . base64_encode( 'z' . str_repeat( 'x', 50 ) ) ) );
		$this->assertSame( '', Cifrado::descifrar( 'enc:v1:' . base64_encode( 's' . str_repeat( 'x', 10 ) ) ) );
		$this->assertSame( '', Cifrado::descifrar( 'enc:v1:' . base64_encode( 'g' . str_repeat( 'x', 10 ) ) ) );
	}

	/**
	 * El formato OpenSSL también se descifra (compatibilidad entre motores).
	 */
	public function test_descifra_el_formato_openssl(): void {
		$clave = Cifrado::clave();
		$nonce = random_bytes( 12 );
		$tag   = '';
		$cif   = openssl_encrypt( '12345678Z', 'aes-256-gcm', $clave, OPENSSL_RAW_DATA, $nonce, $tag, '', 16 );
		$valor = 'enc:v1:' . base64_encode( 'g' . $nonce . $tag . $cif );

		$this->assertSame( '12345678Z', Cifrado::descifrar( $valor ) );
	}
}
