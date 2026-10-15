<?php
/**
 * El escapado de los CSV: una sola regla para todo el ecosistema (issue convoca-core#16).
 *
 * Los exportadores de CSV están en tres plugins y **los tres tienen que decidir lo mismo** sobre qué valor es
 * peligroso: una celda que empieza por `=`, `+`, `-` o `@` la interpreta Excel como fórmula, y ahí entra la
 * inyección de fórmulas (un campo de texto libre como «Notas» puede acabar ejecutando `=cmd|...` en la hoja
 * de quien lo abre).
 *
 * Medido el 15/10/2026: `enroll` y `members` usaban este ayudante, y la pasarela tenía la regla **copiada a
 * mano y más corta** (solo `= + - @`, sin el tabulador, el retorno de carro ni el punto y coma). Tres sitios
 * decidiendo lo mismo es como se acaba con dos coberturas distintas y una de ellas sin que nadie lo note.
 *
 * Este contrato fija la cobertura del ayudante. Probado en negativo: quitando `\t`, `\r` y `;` de la lista de
 * caracteres peligrosos, los casos correspondientes dejan de escapar y este fichero cae.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;

class EscapeCsvFieldTest extends TestCase {

	/**
	 * Carga la clase del núcleo si el bootstrap no la ha cargado ya.
	 */
	private function utils(): void {
		if ( ! class_exists( '\Convoca\Core\Utils' ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/Utils.php';
		}
	}

	/**
	 * Casos: valor de entrada, salida esperada y por qué.
	 *
	 * @return array<string, array{0: string, 1: string, 2: string}>
	 */
	public static function casos(): array {
		return array(
			'empieza por igual'       => array( '=1+1', '"=1+1"', 'Excel lo leeria como formula' ),
			'empieza por mas'         => array( '+34 600 000 000', '"+34 600 000 000"', 'Formula' ),
			'empieza por menos'       => array( '-10', '"-10"', 'Formula' ),
			'empieza por arroba'      => array( '@SUM(A1)', '"@SUM(A1)"', 'Formula' ),
			'empieza por tabulador'   => array( "\t=1+1", "\"\t=1+1\"", 'El tabulador tambien lo interpreta Excel' ),
			'empieza por retorno'     => array( "\r=1+1", "\"\r=1+1\"", 'Retorno de carro' ),
			'empieza por punto y coma' => array( ';=1+1', '";=1+1"', 'Punto y coma: separador del CSV' ),
			'con comillas dentro'     => array( '=a"b', '"=a""b"', 'Las comillas se duplican al entrecomillar' ),
			'texto normal'            => array( 'Oviedo', 'Oviedo', 'Un valor normal no se toca' ),
			'vacio'                   => array( '', '', 'Vacio se queda vacio' ),
			'cero'                    => array( '0', '0', 'Un cero es un dato, no una celda vacia' ),
			'numero'                  => array( '12345678', '12345678', 'Un numero no es formula' ),
			'peligro en medio no cuenta' => array( 'a=1+1', 'a=1+1', 'Solo importa el PRIMER caracter' ),
		);
	}

	/**
	 * @dataProvider casos
	 *
	 * @param string $entrada Valor tal cual sale de la base de datos.
	 * @param string $esperado Lo que tiene que escribirse en el CSV.
	 * @param string $motivo Por qué.
	 */
	public function test_escape_csv_field( string $entrada, string $esperado, string $motivo ): void {
		$this->utils();
		$this->assertSame( $esperado, \Convoca\Core\Utils::escape_csv_field( $entrada ), $motivo );
	}

	/**
	 * La cobertura es la misma para todos los caracteres peligrosos: si alguien la recorta, esto lo dice.
	 */
	public function test_no_se_olvida_ningun_caracter_peligroso(): void {
		$this->utils();
		foreach ( array( '=', '+', '-', '@', "\t", "\r", ';' ) as $caracter ) {
			$this->assertSame(
				'"' . $caracter . 'dato"',
				\Convoca\Core\Utils::escape_csv_field( $caracter . 'dato' ),
				'El caracter ' . var_export( $caracter, true ) . ' tiene que contar como peligroso al principio'
			);
		}
	}
}
