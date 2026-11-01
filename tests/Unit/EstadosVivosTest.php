<?php
/**
 * Los contadores del panel y los listados tienen que mirar los mismos estados.
 *
 * El defecto que vigila: los listados de los plugins usan `post_status => 'any'` (publish, future,
 * draft, pending, private) y varios contadores de core filtraban solo `publish`, así que una ficha
 * fuera de `publish` se veía en la tabla y no sumaba en el número de arriba.
 *
 * @package ConvocaCore
 */

namespace Convoca\Core\Tests\Unit;

use Convoca\Core\Admin_Analytics;
use PHPUnit\Framework\TestCase;

/**
 * Guardián de coherencia entre listados y contadores.
 */
final class EstadosVivosTest extends TestCase {

	/**
	 * La lista es la que corresponde a `any` en WP_Query.
	 */
	public function test_la_lista_cubre_los_estados_de_any(): void {
		$esperados = array( 'publish', 'pending', 'draft', 'private', 'future' );

		$this->assertSame( $esperados, Admin_Analytics::ESTADOS_VIVOS );
	}

	/**
	 * Y se convierte en un IN (...) literal, sin comillas raras.
	 */
	public function test_la_lista_sale_como_in_sql(): void {
		$this->assertSame(
			"'publish','pending','draft','private','future'",
			Admin_Analytics::in_vivos()
		);
	}

	/**
	 * Ningún contador de miembro vuelve a filtrar `publish` a mano.
	 */
	public function test_los_contadores_de_miembro_no_filtran_publish_a_mano(): void {
		$fuente = (string) file_get_contents( __DIR__ . '/../../includes/Admin_Analytics.php' );

		$this->assertStringContainsString( 'ESTADOS_VIVOS', $fuente );
		$this->assertStringNotContainsString(
			"post_type = 'miembro' AND p.post_status = 'publish'",
			$fuente,
			'Queda un contador de socios con publish: no cuadraría con el listado.'
		);
	}
}
