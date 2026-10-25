<?php
/**
 * La ceremonia del CSV: cabeceras, BOM, filas y cierre en UN solo flujo (issue convoca-core#16).
 *
 * Por qué existe: `Utils::csv_headers()` abría el flujo, escribía el BOM y lo cerraba, y quien
 * siguiera escribiendo se quedaba sin BOM —Excel en Windows se comía los acentos—. Ese intento
 * quedó marcado obsoleto y **nadie lo usaba**, así que los nueve exportadores del ecosistema
 * volvieron a copiar la ceremonia a mano: cabeceras de contenido, BOM, `fputcsv` con separador y,
 * en unos sí y en otros no, el escapado de cada celda. Medido el 15/10/2026: tres de los nueve
 * no escapaban nada y dos usaban un separador que Excel en español no reconoce.
 *
 * Este fichero fija dos cosas, y las dos se pueden romper a mano para ver el rojo:
 *
 * 1. `stream_csv()` escribe **lo mismo** que la ceremonia de antes, byte a byte, cuando la
 *    ceremonia de antes ya escapaba cada celda y usaba `;`.
 * 2. La protección contra inyección de fórmulas y el separador salen del fichero en el que están
 *    (`escape_csv_field` decide, el ayudante escribe), no de la memoria de cada exportador.
 *
 * Prueba en negativo (ejecutada el 15/10/2026): quitando el `array_map` del escapado de
 * `stream_csv`, el caso de la celda `=1+1` deja de salir entrecomillado y
 * `test_la_celda_peligrosa_sale_protegida` cae. Y sustituyendo `'' === $field` por
 * `empty( $field )` en `escape_csv_field`, cae `test_un_cero_no_se_pierde`.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use PHPUnit\Framework\TestCase;

class StreamCsvTest extends TestCase {

	/** BOM de UTF-8: sin él, Excel en Windows se come los acentos del castellano. */
	private const BOM = "\xEF\xBB\xBF";

	/**
	 * Datos de la prueba: una celda peligrosa, una con acentos, una con el separador dentro,
	 * una con comillas, un cero y una vacía. Es lo que hay que poder leer de vuelta.
	 *
	 * @return array<int, array<int, string>>
	 */
	private function filas(): array {
		return array(
			array( 'Aurora Pérez', 'aurora@example.org', '=1+1', '0' ),
			array( 'Ñoño Ángel', 'nono@example.org', 'nota;con separador', '' ),
			array( 'Comillas "raras"', 'raro@example.org', 'normal', '2,5' ),
		);
	}

	/**
	 * Cabeceras de la prueba.
	 *
	 * @return array<int, string>
	 */
	private function cabeceras(): array {
		return array( 'Nombre', 'Email', 'Notas', 'Horas' );
	}

	/**
	 * Replica EXACTA de la ceremonia anterior: cabeceras de contenido, BOM, cada celda por
	 * `escape_csv_field()`, `fputcsv` con los mismos argumentos y cierre. Escribe en un flujo
	 * temporal porque aquí se comparan los BYTES, no las cabeceras HTTP.
	 *
	 * @param array<int, string>              $headers   Fila de cabeceras.
	 * @param iterable<int, array<int, mixed>> $rows     Filas.
	 * @param string                          $separator Separador de campos.
	 * @return string Bytes que producía la ceremonia copiada a mano.
	 */
	private function ceremoniaEsperada( array $headers, iterable $rows, string $separator = ';' ): string {
		$h = fopen( 'php://temp', 'r+' );
		fprintf( $h, self::BOM );
		$escribir = static function ( array $fila ) use ( $h, $separator ): void {
			fputcsv(
				$h,
				array_map(
					static function ( $celda ): string {
						// La misma mitigación que el producto: **apóstrofo**, no comillas. Con
						// `escape_csv_field()` aquí, la referencia reproducía el doble escape (core#27).
						return \Convoca\Core\Utils::neutralizar_formula( (string) $celda );
					},
					$fila
				),
				$separator,
				'"',
				'\\'
			);
		};
		$escribir( $headers );
		foreach ( $rows as $fila ) {
			$escribir( $fila );
		}
		rewind( $h );
		$bytes = (string) stream_get_contents( $h );
		fclose( $h );

		return $bytes;
	}

	/**
	 * Ejecuta el ayudante capturando su salida (escribe en `php://output`, que sí pasa por el
	 * búfer de salida) y devuelve las filas que dice haber escrito.
	 *
	 * @param array<int, string>              $headers   Cabeceras.
	 * @param iterable<int, array<int, mixed>> $rows     Filas.
	 * @param string                          $separator Separador.
	 * @return array{0: string, 1: int} Bytes y filas escritas.
	 */
	private function capturar( array $headers, iterable $rows, string $separator = ';' ): array {
		$filas = 0;
		ob_start();
		$filas = \Convoca\Core\Utils::stream_csv( 'prueba.csv', $headers, $rows, $separator );
		$bytes = (string) ob_get_clean();

		return array( $bytes, $filas );
	}

	/**
	 * La prueba de que el refactor no cambia ni un byte: la salida del ayudante es la de la
	 * ceremonia copiada a mano, con la misma entrada.
	 */
	public function test_la_salida_es_byte_a_byte_la_de_la_ceremonia_anterior(): void {
		list( $bytes ) = $this->capturar( $this->cabeceras(), $this->filas() );
		$esperado      = $this->ceremoniaEsperada( $this->cabeceras(), $this->filas() );

		$this->assertSame(
			md5( $esperado ),
			md5( $bytes ),
			'La ceremonia compartida tiene que escribir los mismos bytes que la copiada a mano'
		);
		$this->assertSame( $esperado, $bytes );
	}

	/**
	 * El BOM tiene que ser lo primero del flujo, y estar entero: es el fallo que dejó escrito el
	 * intento anterior.
	 */
	public function test_el_bom_son_los_tres_primeros_bytes(): void {
		list( $bytes ) = $this->capturar( $this->cabeceras(), $this->filas() );

		$this->assertSame( self::BOM, substr( $bytes, 0, 3 ) );
	}

	/**
	 * La celda peligrosa sale protegida y se lee de vuelta con un lector de verdad: el valor no
	 * puede empezar por `=` (la comilla que añade el ayudante y las que añade `fputcsv` se
	 * duplican, así que la aserción correcta es sobre el primer carácter, no sobre la forma).
	 */
	public function test_la_celda_peligrosa_sale_protegida(): void {
		list( $bytes ) = $this->capturar( $this->cabeceras(), $this->filas() );

		// Se lee de vuelta con un lector real, no mirando la cadena.
		$filas = $this->leerDeVuelta( $bytes );

		$this->assertCount( 4, $filas, 'Cabecera + tres filas' );

		$celda = (string) $filas[1][2];

		$this->assertSame(
			"'=1+1",
			$celda,
			'se neutraliza con apóstrofo y el dato llega **entero**: sin comillas dentro y sin perder el ='
		);
		$this->assertStringNotContainsString( '"', $celda, 'nada de comillas espurias (core#27)' );
		$this->assertNotSame(
			'=',
			substr( $celda, 0, 1 ),
			'Una celda que empieza por = es una fórmula para Excel: no puede salir tal cual'
		);
	}

	/**
	 * Los acentos y el separador dentro de una celda sobreviven a la ida y vuelta.
	 */
	public function test_los_acentos_y_el_separador_interno_sobreviven(): void {
		list( $bytes ) = $this->capturar( $this->cabeceras(), $this->filas() );
		$filas         = $this->leerDeVuelta( $bytes );

		$this->assertSame( 'Ñoño Ángel', $filas[2][0] );
		$this->assertSame( 'nota;con separador', $filas[2][2] );
	}

	/**
	 * Un cero legítimo (horas, plazas, importe pendiente) no puede salir como celda vacía.
	 */
	public function test_un_cero_no_se_pierde(): void {
		list( $bytes ) = $this->capturar( $this->cabeceras(), $this->filas() );
		$filas         = $this->leerDeVuelta( $bytes );

		$this->assertSame( '0', $filas[1][3] );
		$this->assertSame( '2,5', $filas[3][3] );
	}

	/**
	 * El ayudante admite un generador y devuelve cuántas filas escribió, sin contar la cabecera.
	 */
	public function test_cuenta_las_filas_y_admite_generador(): void {
		$generador = static function () {
			for ( $i = 1; $i <= 5000; $i++ ) {
				yield array( 'Socio ' . $i, 'socio' . $i . '@example.org', '=SUM(A1)', (string) $i );
			}
		};

		list( $bytes, $escritas ) = $this->capturar( $this->cabeceras(), $generador() );

		$this->assertSame( 5000, $escritas );
		$this->assertSame( 5001, count( $this->leerDeVuelta( $bytes ) ), 'Cabecera + 5.000 filas' );
	}

	/**
	 * El separador es un parámetro porque hay un lector que manda (la plantilla de importación de
	 * socios se lee con `,`): con `,` el fichero tiene que salir separado por comas.
	 */
	public function test_el_separador_del_lector_se_respeta(): void {
		list( $bytes ) = $this->capturar( array( 'nombre', 'email' ), array( array( 'Juan', 'j@example.org' ) ), ',' );

		$this->assertStringContainsString( "nombre,email\n", $bytes );
		$this->assertSame( $this->ceremoniaEsperada( array( 'nombre', 'email' ), array( array( 'Juan', 'j@example.org' ) ), ',' ), $bytes );
	}

	/**
	 * Lector de verdad para leer lo que se escribió.
	 *
	 * @param string $bytes Contenido del CSV, con BOM.
	 * @return array<int, array<int, string|null>>
	 */
	private function leerDeVuelta( string $bytes ): array {
		$h = fopen( 'php://temp', 'r+' );
		fwrite( $h, $bytes );
		rewind( $h );

		$filas = array();
		while ( ( $fila = fgetcsv( $h, 0, ';', '"', '\\' ) ) !== false ) {
			$filas[] = $fila;
		}
		fclose( $h );

		return $filas;
	}
}
