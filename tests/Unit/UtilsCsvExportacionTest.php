<?php
/**
 * La exportación a CSV del núcleo: un solo flujo, con BOM y con cada celda protegida.
 *
 * Tres cosas que aquí se rompen sin hacer ruido y por eso se prueban leyendo la salida de verdad:
 *
 * - **el BOM**: sin él, Excel en Windows se come los acentos del castellano;
 * - **la protección por celda**: si el escapado se queda fuera en un exportador, una celda que empieza por `=`
 *   se convierte en fórmula al abrir el fichero;
 * - **el separador**: la plantilla de importación de socios se vuelve a subir y su lector espera `,`; con `;`
 *   el fichero descargado deja de importarse.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use Convoca\Core\Utils;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Utils.php';

/**
 * Exportación CSV.
 */
final class UtilsCsvExportacionTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		unset( $GLOBALS['_wp_stores'], $GLOBALS['_wp_cache'] );
		$GLOBALS['_wp_stores'] = array(
			'options'    => array(),
			'post_meta'  => array(),
			'transients' => array(),
		);
	}

	/**
	 * Ejecuta la exportación y devuelve lo que salió.
	 *
	 * @param callable $fn Exportación.
	 * @return string
	 */
	private function salida( callable $fn ): string {
		ob_start();
		$fn();

		return (string) ob_get_clean();
	}

	/**
	 * Lee la salida CSV como la leería quien la abre: con su separador.
	 *
	 * @param string $salida    Salida cruda.
	 * @param string $separador Separador.
	 * @return array<int, array<int, string>>
	 */
	private function leer( string $salida, string $separador ): array {
		$cuerpo = substr( $salida, 3 ); // sin BOM
		$filas  = array();
		foreach ( explode( "\n", $cuerpo ) as $linea ) {
			if ( '' === trim( $linea ) ) {
				continue;
			}
			$filas[] = str_getcsv( $linea, $separador, '"', '\\' );
		}

		return $filas;
	}

	/**
	 * El BOM va delante y las cabeceras primero. Y devuelve cuántas filas escribió.
	 */
	public function test_el_flujo_completo(): void {
		$filas = array(
			array( 'Ada Lovelace', 'ada@example.test' ),
			array( 'Grace Hopper', 'grace@example.test' ),
		);

		$escritas = 0;
		$salida    = $this->salida(
			static function () use ( $filas, &$escritas ) {
				$escritas = Utils::stream_csv( 'socios.csv', array( 'Nombre', 'Correo' ), $filas );
			}
		);

		$this->assertSame( 2, $escritas, 'devuelve las filas escritas' );
		$this->assertStringStartsWith(
			chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ),
			$salida,
			'empieza por el BOM: sin él Excel se come los acentos'
		);

		// Se comparan los **valores leídos**, no el texto crudo: `fputcsv()` decide por su cuenta cuándo
		// entrecomillar (un espacio ya lo justifica) y eso no es un fallo, siempre que se lea bien.
		$filas_leidas = $this->leer( $salida, ';' );
		$this->assertSame( array( 'Nombre', 'Correo' ), $filas_leidas[0], 'las cabeceras primero' );
		$this->assertSame( array( 'Ada Lovelace', 'ada@example.test' ), $filas_leidas[1] );
		$this->assertSame( array( 'Grace Hopper', 'grace@example.test' ), $filas_leidas[2] );
	}

	/**
	 * Con coma —para lectores que la exigen, como la plantilla de importación— el fichero sale con comas.
	 */
	public function test_el_separador_que_pide_el_lector(): void {
		$salida = $this->salida(
			static function () {
				Utils::stream_csv( 'plantilla.csv', array( 'Nombre', 'DNI' ), array( array( 'Ada', '00000000T' ) ), ',' );
			}
		);

		$filas_leidas = $this->leer( $salida, ',' );
		$this->assertSame( array( 'Nombre', 'DNI' ), $filas_leidas[0], 'con coma, o el fichero no se puede volver a importar' );
		$this->assertSame( array( 'Ada', '00000000T' ), $filas_leidas[1] );
	}

	/**
	 * La protección va **en el flujo**, no en cada exportador: una celda con fórmula sale neutralizada.
	 */
	public function test_la_proteccion_viaja_en_el_flujo(): void {
		$salida = $this->salida(
			static function () {
				Utils::stream_csv( 'x.csv', array( 'A' ), array( array( '=SUM(A1:A9)' ), array( '0' ), array( '@usuario' ) ) );
			}
		);

		$filas = $this->leer( $salida, ';' );
		$this->assertSame( '0', $filas[2][0], 'un cero sigue siendo un dato, no una celda vacía' );
		$this->assertStringNotContainsString( '=SUM(A1:A9)\n', $salida, 'la fórmula queda neutralizada en el fichero' );

		// Arreglado core#27: se neutraliza con **apóstrofo** y el dato llega entero. Antes salía `"=SUM(A1:A9)"`
		// —entrecomillado dos veces, porque `stream_csv()` ya entrecomillaba y `fputcsv()` lo volvía a hacer—.
		$this->assertSame(
			"'=SUM(A1:A9)",
			$filas[1][0],
			'el apóstrofo quita la fórmula sin corromper el dato'
		);
		$this->assertStringNotContainsString( '"', (string) $filas[1][0], 'sin comillas espurias en el valor' );
	}

	/**
	 * Admite un generador: se escriben todas las filas sin cargarlas en memoria.
	 */
	public function test_admite_generador(): void {
		$generador = static function () {
			yield array( 'uno' );
			yield array( 'dos' );
			yield array( 'tres' );
		};

		$escritas = 0;
		$salida    = $this->salida(
			static function () use ( $generador, &$escritas ) {
				$escritas = Utils::stream_csv( 'g.csv', array( 'Columna' ), $generador() );
			}
		);

		$this->assertSame( 3, $escritas );
		foreach ( array( 'uno', 'dos', 'tres' ) as $valor ) {
			$this->assertStringContainsString( $valor, $salida );
		}
	}

	/**
	 * La función obsoleta sigue emitiendo cabeceras y BOM (hay código que aún la llama).
	 */
	public function test_la_obsoleta_sigue_sirviendo(): void {
		$salida = $this->salida(
			static function () {
				Utils::csv_headers( 'viejo.csv' );
			}
		);

		$this->assertStringContainsString(
			chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ),
			$salida,
			'el BOM sigue saliendo'
		);
	}

	/**
	 * El limitador de intentos: si la caché dice «bloqueado», no se pasa —ni se toca la base de datos—.
	 */
	public function test_el_limitador_usa_el_atajo_de_cache(): void {
		$identidad = 'ada@example.test';
		$clave     = 'convoca_rl_login_id_' . md5( strtolower( $identidad ) );
		wp_cache_set( $clave, 0, 'convoca_rate_limits' );

		$this->assertFalse(
			Utils::check_rate_limit( 'login', 10, 300, $identidad ),
			'con la marca de bloqueo puesta, no se permite el intento'
		);

		// Y con otra persona el contador es otro: el bloqueo de una no cierra la puerta a todos.
		$this->assertTrue(
			Utils::check_rate_limit( 'login', 10, 300, 'grace@example.test' ),
			'el bloqueo es de quien lo provoca'
		);
	}
}
