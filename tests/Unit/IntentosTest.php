<?php
/**
 * Registro de intentos y huellas sin datos personales (members#105, enroll#72).
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use Convoca\Core\Anti_Bot;
use Convoca\Core\Intentos;
use PHPUnit\Framework\TestCase;

class IntentosTest extends TestCase {

	/**
	 * El correo no se guarda: solo una huella estable.
	 */
	public function test_la_huella_del_correo_no_es_el_correo(): void {
		$uno = Intentos::identidad( 'Ada@Example.test' );
		$dos = Intentos::identidad( '  ada@example.test ' );

		$this->assertSame( $uno, $dos, 'mayúsculas y espacios dan la misma persona' );
		$this->assertNotSame( $uno, Intentos::identidad( 'grace@example.test' ) );
		$this->assertSame( 32, strlen( $uno ) );
		$this->assertStringNotContainsString( 'ada', $uno, 'no se puede leer el correo' );
		$this->assertSame( '', Intentos::identidad( '' ) );
	}

	/**
	 * La IP, igual: huella y nada más.
	 */
	public function test_la_huella_de_la_ip(): void {
		$uno = Intentos::huella_ip( '203.0.113.7' );

		$this->assertSame( $uno, Intentos::huella_ip( ' 203.0.113.7 ' ) );
		$this->assertNotSame( $uno, Intentos::huella_ip( '203.0.113.8' ) );
		$this->assertStringNotContainsString( '203.0.113', $uno );
	}

	/**
	 * Un sello vale para su momento y no para otro.
	 */
	public function test_el_sello_se_firma(): void {
		$sello = Anti_Bot::firmar( 1700000000 );

		$this->assertSame( 1700000000, Anti_Bot::leer_sello( $sello ) );
		$this->assertNull( Anti_Bot::leer_sello( '1700000000.00000000000000000000' ), 'firma falsa' );
		$this->assertNull( Anti_Bot::leer_sello( 'no-es-un-sello' ) );
		$this->assertNull( Anti_Bot::leer_sello( '' ) );
	}

	/**
	 * Las cuatro señales de bot: campo trampa, demasiado rápido, caducado o sin sello.
	 */
	public function test_las_senales_de_bot(): void {
		$ahora = 1700000000;
		$base  = array( Anti_Bot::TRAMPA => '', Anti_Bot::SELLO => Anti_Bot::firmar( $ahora - 30 ) );

		$this->assertNull( Anti_Bot::motivo( $base, $ahora ), 'una persona tarda 30 s y no toca la trampa' );

		$trampa = $base;
		$trampa[ Anti_Bot::TRAMPA ] = 'http://spam.example';
		$this->assertSame( 'honeypot', Anti_Bot::motivo( $trampa, $ahora ) );

		$rapido = $base;
		$rapido[ Anti_Bot::SELLO ] = Anti_Bot::firmar( $ahora - 1 );
		$this->assertSame( 'rapido', Anti_Bot::motivo( $rapido, $ahora ) );

		$viejo = $base;
		$viejo[ Anti_Bot::SELLO ] = Anti_Bot::firmar( $ahora - ( 25 * HOUR_IN_SECONDS ) );
		$this->assertSame( 'caducado', Anti_Bot::motivo( $viejo, $ahora ) );

		// Sin sello no se bloquea (una página cacheada puede servirse sin él), pero se puede medir.
		$sin = $base;
		unset( $sin[ Anti_Bot::SELLO ] );
		$this->assertNull( Anti_Bot::motivo( $sin, $ahora ) );
		$this->assertTrue( Anti_Bot::sin_sello( $sin ) );
		$this->assertFalse( Anti_Bot::sin_sello( $base ) );
	}

	/**
	 * Los campos ocultos llevan la trampa, el sello firmado y no estorban al teclado.
	 */
	public function test_los_campos_ocultos(): void {
		$html = Anti_Bot::campos();

		$this->assertStringContainsString( 'name="' . Anti_Bot::TRAMPA . '"', $html );
		$this->assertStringContainsString( 'name="' . Anti_Bot::SELLO . '"', $html );
		$this->assertStringContainsString( 'tabindex="-1"', $html );
		$this->assertStringContainsString( 'aria-hidden="true"', $html );
	}
}
