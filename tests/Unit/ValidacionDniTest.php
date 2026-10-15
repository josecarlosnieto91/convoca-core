<?php
/**
 * La validación del DNI/NIE, con la letra como manda el algoritmo (issue convoca-core#16).
 *
 * Este contrato existe porque **tres plugins tenían su propia copia** de esta función (core, members y
 * enroll) y el triaje del issue midió que no eran la misma. Hoy las tres delegan aquí y solo hay una
 * implementación, así que lo que hay que impedir es que vuelva a separarse: el test fija los casos y,
 * sobre todo, los **negativos** —que es lo que de verdad se rompe cuando alguien «simplifica» la
 * comprobación de la letra.
 *
 * La letra no es opinable: sale del resto módulo 23 sobre la parte numérica, con la tabla
 * `TRWAGMYFPDXBNJZSQVHLCKE`, y el NIE mapea su prefijo (X=0, Y=1, Z=2). Los dos casos canónicos de la
 * tabla (`12345678Z` y `X1234567L`) coinciden con los que publican las referencias del algoritmo.
 *
 * Probado en negativo: si se quitara la comprobación de la letra (dejando solo el formato), los casos
 * «letra cambiada» dejan de fallar y este fichero cae.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;

class ValidacionDniTest extends TestCase {

	private const TABLA = 'TRWAGMYFPDXBNJZSQVHLCKE';

	/**
	 * La letra que le toca a un número, según el algoritmo (resto 23).
	 *
	 * @param int $numero Parte numérica del documento.
	 * @return string
	 */
	private static function letra( int $numero ): string {
		return self::TABLA[ $numero % 23 ];
	}

	/**
	 * Casos: documento, resultado esperado y por qué.
	 *
	 * @return array<string, array{0: string, 1: bool, 2: string}>
	 */
	public static function casos(): array {
		$valido   = '12345678' . self::letra( 12345678 );
		$otra     = self::TABLA[ ( 12345678 % 23 + 1 ) % 23 ];
		$nie      = '1234567';
		$letra_x  = self::letra( 1234567 );
		$letra_y  = self::letra( 11234567 );
		$letra_z  = self::letra( 21234567 );

		return array(
			'dni correcto (caso canónico 12345678Z)' => array( '12345678Z', true, 'El ejemplo de manual: 12345678Z' ),
			'dni con la letra cambiada'             => array( '12345678' . $otra, false, 'Misma cifra, letra que no toca: inválido' ),
			'ceros con su letra'                    => array( '00000000' . self::letra( 0 ), true, '00000000T' ),
			'ceros con letra cambiada'              => array( '00000000' . self::TABLA[1], false, 'Letra que no toca' ),
			'minusculas se normalizan'              => array( strtolower( $valido ), true, 'Se pasa a mayúsculas' ),
			'con guion se limpia'                   => array( '12345678-' . self::letra( 12345678 ), true, 'El guion es separador' ),
			'con espacios se recorta'               => array( ' ' . $valido . ' ', true, 'Se recorta el espacio' ),
			'siete cifras'                          => array( $nie . self::letra( 1234567 ), false, 'No es un DNI: le falta una cifra' ),
			'nueve cifras'                          => array( '123456789' . self::letra( 123456789 ), false, 'No es un DNI: le sobra una cifra' ),
			'sin letra'                             => array( '12345678', false, 'Falta la letra' ),
			'letras en la parte numerica'           => array( 'ABCDEFGH' . self::letra( 1 ), false, 'La parte numérica tiene letras' ),
			'nie con X (caso canónico X1234567L)'   => array( 'X' . $nie . $letra_x, true, 'X vale 0' ),
			'nie con Y'                             => array( 'Y' . $nie . $letra_y, true, 'Y vale 1' ),
			'nie con Z'                             => array( 'Z' . $nie . $letra_z, true, 'Z vale 2' ),
			'nie con la letra cambiada'             => array( 'X' . $nie . self::TABLA[ ( 1234567 % 23 + 1 ) % 23 ], false, 'Misma cifra, letra que no toca' ),
			'prefijo que no existe'                 => array( 'W' . $nie . $letra_x, false, 'Solo X, Y y Z' ),
			'vacio'                                 => array( '', false, 'Sin documento no hay nada que validar' ),
		);
	}

	/**
	 * Carga la clase del núcleo si el bootstrap del repo no la ha cargado ya.
	 */
	private function utils(): void {
		if ( ! class_exists( '\Convoca\Core\Utils' ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/Utils.php';
		}
	}

	/**
	 * @dataProvider casos
	 *
	 * @param string $documento Documento a validar.
	 * @param bool   $esperado  Resultado que manda el algoritmo.
	 * @param string $motivo    Por qué.
	 */
	public function test_validate_dni( string $documento, bool $esperado, string $motivo ): void {
		$this->utils();
		$this->assertSame( $esperado, \Convoca\Core\Utils::validate_dni( $documento ), $motivo . ' (' . $documento . ')' );
	}

	/**
	 * El alias dice lo mismo que el nombre bueno: si se separan, esto cae.
	 */
	public function test_el_alias_y_el_nombre_nuevo_dicen_lo_mismo(): void {
		$this->utils();
		foreach ( array( '12345678Z', '12345678A', 'X1234567L', 'W1234567L', '1234567L' ) as $documento ) {
			$this->assertSame(
				\Convoca\Core\Utils::validate_dni( $documento ),
				\Convoca\Core\Utils::validar_dni( $documento ),
				'validar_dni() y validate_dni() tienen que coincidir para ' . $documento
			);
		}
	}

	/**
	 * La tabla de letras es la oficial, no una parecida.
	 */
	public function test_la_tabla_de_letras_es_la_oficial(): void {
		$this->assertSame( 'TRWAGMYFPDXBNJZSQVHLCKE', self::TABLA );
		$this->assertSame( 'Z', self::letra( 12345678 ) );
		$this->assertSame( 'L', self::letra( 1234567 ) );
	}
}
