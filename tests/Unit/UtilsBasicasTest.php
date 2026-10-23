<?php
/**
 * Las utilidades del núcleo que todo el ecosistema usa: DNI, nombres, CSV y códigos.
 *
 * Cuatro de estas funciones tienen historia detrás y por eso están aquí, con su caso concreto:
 *
 * - el DNI se valida con la letra, no con la forma: un `12345678A` tiene la forma correcta y la letra no;
 * - `escape_csv_field( '0' )` devolvía celda vacía por un `empty()`, y quien lee la exportación se queda sin el
 *   dato (horas, plazas, importe pendiente);
 * - el CSV se protege de fórmulas (`=`, `+`, `-`, `@`, tabulador, retorno y `;`): una celda que empieza por `=`
 *   es una fórmula que ejecuta Excel al abrirla;
 * - el código de acceso se genera de un alfabeto sin caracteres que se confunden al dictarlo (0/O, 1/I/L).
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use Convoca\Core\Utils;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Utils.php';

/**
 * Utilidades básicas.
 */
final class UtilsBasicasTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		unset( $GLOBALS['_wp_stores'], $GLOBALS['_fired_hooks'] );
		$GLOBALS['_wp_stores'] = array(
			'options'    => array(),
			'post_meta'  => array(),
			'transients' => array(),
			'posts'      => array(),
			'locks'      => array(),
		);
		Utils::clear_fired();
	}

	/**
	 * Un DNI con la letra correcta pasa; con la letra cambiada, no.
	 */
	public function test_el_dni_se_valida_por_su_letra(): void {
		// 12345678 % 23 = 14 → 'Z'.
		$this->assertTrue( Utils::validate_dni( '12345678Z' ), 'la letra que toca' );
		$this->assertFalse( Utils::validate_dni( '12345678A' ), 'la forma es correcta y la letra no' );
		// 0 % 23 = 0 → 'T'.
		$this->assertTrue( Utils::validate_dni( '00000000T' ) );
	}

	/**
	 * Se normaliza antes de comprobar: mayúsculas, espacios y guiones dan igual.
	 */
	public function test_el_dni_se_normaliza(): void {
		$this->assertTrue( Utils::validate_dni( ' 12345678-z ' ), 'espacios, guion y minúscula' );
	}

	/**
	 * Un NIE se valida con su prefijo convertido a número (X=0, Y=1, Z=2).
	 */
	public function test_el_nie(): void {
		// La letra sale del número que resulta de cambiar el prefijo: X=0, Y=1, Z=2.
		$this->assertTrue( Utils::validate_dni( 'X0000000T' ), 'X vale 0 (0 % 23 = 0 → T)' );
		$this->assertTrue( Utils::validate_dni( 'Y0000001S' ), 'Y vale 1 (10000001 % 23 = 15 → S)' );
		$this->assertTrue( Utils::validate_dni( 'Z0000002F' ), 'Z vale 2 (20000002 % 23 = 17 → F)' );
		$this->assertFalse( Utils::validate_dni( 'X0000000A' ), 'la forma bien, la letra no' );
	}

	/**
	 * Lo que no tiene forma de DNI no pasa: ni corto, ni largo, ni vacío.
	 */
	public function test_lo_que_no_es_un_dni(): void {
		// `1234567L` es el caso fino: la letra **sí** cuadra (1234567 % 23 = 11 → L) pero faltan dígitos. Si solo
		// se mirara la letra, pasaría un DNI que no existe.
		$this->assertFalse( Utils::validate_dni( '1234567L' ), 'siete dígitos con la letra correcta no es un DNI' );
		foreach ( array( '', '1234567Z', '123456789Z', 'ABCDEFGHZ', '12345678', 'ZZZZZZZZZ' ) as $basura ) {
			$this->assertFalse( Utils::validate_dni( $basura ), "«{$basura}» no es un DNI" );
		}
	}

	/**
	 * El alias hace lo mismo: hay código antiguo que llama `validar_dni()`.
	 */
	public function test_el_alias(): void {
		$this->assertTrue( Utils::validar_dni( '12345678Z' ) );
		$this->assertFalse( Utils::validar_dni( '12345678A' ) );
	}

	/**
	 * Los nombres se normalizan respetando acentos y eñes.
	 */
	public function test_el_nombre(): void {
		$this->assertSame( 'Ada Lovelace', Utils::format_nombre( '  ada lovelace ' ) );
		$this->assertSame( 'María Núñez', Utils::format_nombre( 'MARÍA NÚÑEZ' ), 'con acentos y eñe' );
	}

	/**
	 * El CSV: el `0` es un dato, no una celda vacía; y lo que empieza por carácter peligroso se entrecomilla.
	 */
	public function test_el_csv(): void {
		$this->assertSame( '', Utils::escape_csv_field( '' ), 'vacío de verdad' );
		$this->assertSame( '0', Utils::escape_csv_field( '0' ), 'un cero es un dato (horas, plazas, importe)' );
		$this->assertSame( '0', Utils::escape_csv_field( 0 ), 'también si llega como número' );

		$this->assertSame( '"=SUM(A1:A9)"', Utils::escape_csv_field( '=SUM(A1:A9)' ), 'una fórmula no se ejecuta sola' );
		$this->assertSame( '"+34 600"', Utils::escape_csv_field( '+34 600' ) );
		$this->assertSame( '"-5"', Utils::escape_csv_field( '-5' ) );
		$this->assertSame( '"@usuario"', Utils::escape_csv_field( '@usuario' ) );
		$this->assertSame( "\"\tcelda\"", Utils::escape_csv_field( "\tcelda" ) );
		$this->assertSame( "\"\rtexto\"", Utils::escape_csv_field( "\rtexto" ) );
		$this->assertSame( '";no, separador"', Utils::escape_csv_field( ';no, separador' ) );

		$this->assertSame( 'Ada Lovelace', Utils::escape_csv_field( 'Ada Lovelace' ), 'un valor normal no se toca' );
		$this->assertSame( '"=a""b"', Utils::escape_csv_field( '=a"b' ), 'y las comillas se duplican' );
	}

	/**
	 * El código de acceso: la longitud que se pide, del alfabeto seguro y sin repetirse entre socios.
	 */
	public function test_el_codigo_de_acceso(): void {
		$codigo = Utils::generate_access_code();
		$this->assertSame( 8, strlen( $codigo ), 'ocho por defecto' );
		$this->assertSame( 12, strlen( Utils::generate_access_code( 12 ) ), 'o lo que se pida' );
		$this->assertMatchesRegularExpression(
			'/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]+$/',
			$codigo,
			'sin caracteres que se confunden al dictarlos (0, O, 1, I)'
		);

		$varios = array();
		for ( $i = 0; $i < 200; $i++ ) {
			$varios[] = Utils::generate_access_code();
		}
		$this->assertCount( 200, array_unique( $varios ), 'no se repiten' );
		// Muestra amplia a propósito: con veinte códigos, un carácter ambiguo puede no salir por azar y la prueba
		// pasaría sin comprobar el alfabeto.
		foreach ( $varios as $uno ) {
			// El alfabeto excluye 0, O, 1 e I (la L sí está: no se confunde con un uno en este tipo de letra).
			$this->assertSame( 0, preg_match( '/[0O1I]/', $uno ), "«{$uno}» no debe llevar caracteres que se confunden" );
		}
	}

	/**
	 * Si el código ya está en la base, se genera otro: un código repetido es una puerta abierta.
	 */
	public function test_el_codigo_no_se_repite_con_uno_existente(): void {
		// La consulta de unicidad devuelve un id la primera vez y nada después: el primer intento choca.
		$GLOBALS['_wp_stores']['get_var'] = '99';
		$codigo                           = Utils::generate_access_code( 8 );
		$this->assertSame( 8, strlen( $codigo ) );
		$GLOBALS['_wp_stores']['get_var'] = '0';
	}

	/**
	 * Los ganchos: se dispara el nuevo **y** el viejo, y queda constancia de los dos.
	 */
	public function test_los_ganchos_nuevo_y_viejo(): void {
		$GLOBALS['_wp_actions'] = array();
		// El gancho viejo se dispara **si alguien lo escucha**: se registra para poder afirmarlo.
		add_action( 'convoca_viejo', static function () {} );

		Utils::do_action( 'convoca_nuevo', 'convoca_viejo', 'dato' );

		$lanzados = array_column( (array) $GLOBALS['_wp_actions'], 'hook' );
		$this->assertContains( 'convoca_nuevo', $lanzados, 'el gancho nuevo se dispara' );
		$this->assertContains( 'convoca_viejo', $lanzados, 'y el viejo también, por compatibilidad' );
		$con_dato = array_values( array_filter( (array) $GLOBALS['_wp_actions'], static fn( $a ) => array( 'dato' ) === $a['args'] ) );
		$this->assertCount( 2, $con_dato, 'los dos reciben el mismo argumento' );

		$this->assertArrayHasKey( 'convoca_nuevo', Utils::get_fired(), 'queda constancia de lo disparado' );
		Utils::clear_fired();
		$this->assertSame( array(), Utils::get_fired(), 'y se puede vaciar' );
	}

	/**
	 * Y sin gancho viejo no se inventa uno.
	 */
	public function test_los_ganchos_sin_viejo(): void {
		$GLOBALS['_wp_actions'] = array();

		Utils::do_action( 'convoca_solo_nuevo' );

		$this->assertSame(
			array( 'convoca_solo_nuevo' ),
			array_column( (array) $GLOBALS['_wp_actions'], 'hook' ),
			'sólo el que existe: sin gancho viejo no se inventa uno'
		);
	}

	/**
	 * Y si nadie escucha el gancho viejo, no se dispara: no se inventan ganchos.
	 */
	public function test_el_gancho_viejo_sin_oyentes_no_se_dispara(): void {
		$GLOBALS['_wp_actions'] = array();

		Utils::do_action( 'convoca_nuevo', 'convoca_nadie_lo_escucha', 'dato' );

		$this->assertNotContains(
			'convoca_nadie_lo_escucha',
			array_column( (array) $GLOBALS['_wp_actions'], 'hook' ),
			'sin oyentes no hay nada que disparar'
		);
	}

	/**
	 * Las fechas: vacío no es una fecha, y una cadena que no se entiende se devuelve tal cual.
	 */
	public function test_las_fechas(): void {
		$this->assertSame( '', Utils::format_date( '' ), 'sin fecha no hay nada que formatear' );
		$this->assertSame( '05/11/2026', Utils::format_date( '2026-11-05 18:00:00' ) );
		$this->assertSame( '05/11/2026 18:00', Utils::format_date( '2026-11-05 18:00:00', 'd/m/Y H:i' ) );
		$this->assertSame( 'no es una fecha', Utils::format_date( 'no es una fecha' ), 'si no se entiende, no se rompe' );
	}
}
