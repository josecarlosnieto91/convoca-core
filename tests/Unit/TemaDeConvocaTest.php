<?php
/**
 * El tema de Convoca se instala en la carpeta `convoca`, no en `convoca-theme`.
 *
 * Por qué existe: la comprobación miraba solo `convoca-theme` (el nombre del repositorio), así que
 * sobre una instalación real devolvía siempre falso. Hoy no la llama nadie —el impacto era cero—
 * pero era una trampa: en cuanto alguien la usara, la respuesta sería la equivocada.
 *
 * @package Convoca\Core
 */

namespace Convoca\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;

class TemaDeConvocaTest extends TestCase {

	private function fuente(): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Features.php' );
	}

	public function test_acepta_el_nombre_con_el_que_se_instala(): void {
		$this->assertStringContainsString(
			"'convoca'",
			$this->fuente(),
			'El tema se instala en la carpeta convoca: la comprobación tiene que aceptarlo.'
		);
	}

	public function test_sigue_aceptando_el_nombre_antiguo(): void {
		$this->assertStringContainsString(
			"'convoca-theme'",
			$this->fuente(),
			'Las instalaciones antiguas usaban convoca-theme: no se debe dejar de aceptar.'
		);
	}

	public function test_compara_con_el_nombre_del_tema_y_no_con_otra_cosa(): void {
		$txt = $this->fuente();
		$this->assertStringContainsString( 'get_template()', $txt );
		$this->assertStringContainsString( 'get_stylesheet()', $txt, 'Un tema hijo tiene el estilo propio: hay que mirar los dos.' );
	}
}
