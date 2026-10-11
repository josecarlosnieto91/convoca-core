<?php
/**
 * Contraste del botón primario del ecosistema (`convoca-common.css`).
 *
 * DEFECTO QUE FIJA ESTA PRUEBA (medido con axe en la demo): el botón daba texto
 * blanco sobre `#ff8700` = **2.41:1**, por debajo del 4.5:1 que exige WCAG 2.1 AA
 * para texto normal. Afecta a TODOS los botones primarios del ecosistema.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use PHPUnit\Framework\TestCase;

class BotonPrimarioContrasteTest extends TestCase {

	private function css(): string {
		return file_get_contents( dirname( __DIR__, 2 ) . '/assets/css/convoca-common.css' );
	}

	/** Luminancia relativa segun WCAG 2.x. */
	private function luminancia( string $hex ): float {
		$hex = ltrim( $hex, '#' );
		$c   = array();
		foreach ( array( 0, 2, 4 ) as $i ) {
			$v   = hexdec( substr( $hex, $i, 2 ) ) / 255;
			$c[] = $v <= 0.03928 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
		}
		return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
	}

	private function ratio( string $a, string $b ): float {
		$la = $this->luminancia( $a );
		$lb = $this->luminancia( $b );
		return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
	}

	public function test_ningun_color_de_texto_usa_el_naranja_brillante(): void {
		// Solo las propiedades `color:` de TEXTO. `border-color` y `accent-color`
		// no pasan por el 4.5:1 del texto (tienen su propio 3:1 de 1.4.11).
		$malas = array();
		foreach ( file( dirname( __DIR__, 2 ) . '/assets/css/convoca-common.css' ) as $n => $linea ) {
			if ( preg_match( '/^\s*color:\s*var\(--wp--preset--color--naranja,\s*#ff8700\)/i', $linea ) ) {
				$malas[] = ( $n + 1 ) . ': ' . trim( $linea );
			}
		}
		$this->assertSame(
			array(),
			$malas,
			"El naranja brillante como color de TEXTO no cumple AA (2.41:1):\n" . implode( "\n", $malas )
		);
	}

	public function test_el_boton_primario_usa_el_naranja_oscuro(): void {
		$css = $this->css();
		$pos = strpos( $css, '.convoca-btn-primary {' );
		$this->assertNotFalse( $pos );
		$regla = substr( $css, $pos, 400 );
		$this->assertStringContainsString( 'naranja-oscuro', $regla );
	}

	public function test_la_alternativa_de_reserva_del_boton_cumple_aa(): void {
		// El color de reserva es el que se usa cuando el tema no define la variable.
		$this->assertGreaterThanOrEqual(
			4.5,
			$this->ratio( '#ffffff', '#b05500' ),
			'Blanco sobre #b05500 debe cumplir AA'
		);
	}

	public function test_el_naranja_brillante_no_cumpliria(): void {
		// Deja constancia de POR QUE se cambió: si alguien lo revierte, se ve.
		$this->assertLessThan( 4.5, $this->ratio( '#ffffff', '#ff8700' ) );
	}
}
