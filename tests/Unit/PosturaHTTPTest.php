<?php
/**
 * La versión de PHP no se anuncia en cada respuesta (issue #11).
 *
 * Esta prueba vigila el mecanismo —que la limpieza esté enganchada donde tiene que estar—; que la cabecera
 * desaparezca de verdad se mide contra el sitio, con `curl -I`, y está en la evidencia del issue. Una prueba que
 * dijera «la cabecera ya no está» sin salir del proceso estaría comprobando otra cosa.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;

class PosturaHTTPTest extends TestCase {

	/** El código SIN comentarios: un comentario que menciona la CSP no es tocar la CSP. */
	private function codigo(): string {
		return php_strip_whitespace( dirname( __DIR__, 2 ) . '/includes/Postura_HTTP.php' );
	}

	private function clase(): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Postura_HTTP.php' );
	}

	public function test_retira_la_cabecera_de_la_version_de_php(): void {
		$this->assertStringContainsString( "header_remove( 'X-Powered-By' )", $this->clase() );
	}

	public function test_va_en_send_headers_y_no_despues(): void {
		$this->assertStringContainsString( "add_action( 'send_headers'", $this->clase(), 'Quitarla cuando ya se ha enviado no hace nada y no avisa' );
	}

	public function test_no_toca_la_csp(): void {
		$this->assertStringNotContainsString( 'Content-Security-Policy', $this->codigo(), 'La CSP va en modo informe por decisión tomada: se cambia por fases, no en un arreglo de cabeceras' );
	}

	public function test_esta_enganchada(): void {
		$arranque = (string) file_get_contents( dirname( __DIR__, 2 ) . '/convoca-core.php' );
		$this->assertStringContainsString( 'convoca_core_init( Postura_HTTP::class )', $arranque, 'Una clase que nadie llama no quita nada' );
	}
}
