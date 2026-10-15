<?php
/**
 * Las copias de ajustes no crecen sin límite (issue #9).
 *
 * El defecto medido: cada importación o restauración dejaba una opción nueva con la fecha en el nombre
 * (`convoca_enroll_settings_backup_2026-09-09-214906`) y **ninguna se borraba**. En la demo había cuatro; en un
 * sitio que importe ajustes con frecuencia eso crece sin techo, y cada copia lleva dentro los ajustes completos
 * con sus plantillas de correo.
 *
 * Aquí se comprueba lo que hace falta: que se conserve un número fijo, que se conserve **lo más reciente** y
 * que no se cree una opción por copia. El negativo es quitar la poda: entonces la lista no deja de crecer.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use Convoca\Core\Backups;
use PHPUnit\Framework\TestCase;

class CopiasConRetencionTest extends TestCase {

	public function test_no_se_conservan_mas_de_las_acordadas(): void {
		$clave = 'convoca_test_backups_' . wp_rand( 1000, 9999 );
		for ( $i = 1; $i <= 9; $i++ ) {
			Backups::anadir( $clave, array( 'ajuste' => $i ) );
		}

		$copias = Backups::de( $clave );
		$this->assertCount( Backups::RETENCION, $copias, 'Se conservan cinco, no nueve' );
		$this->assertSame( 5, Backups::RETENCION, 'Y cinco es lo acordado: las últimas cinco' );
	}

	public function test_se_conserva_lo_mas_reciente_y_se_va_lo_viejo(): void {
		$clave = 'convoca_test_orden_' . wp_rand( 1000, 9999 );
		for ( $i = 1; $i <= 8; $i++ ) {
			Backups::anadir( $clave, array( 'ajuste' => $i ) );
		}

		$copias  = Backups::de( $clave );
		$ajustes = array_column( $copias, 'datos' );
		$valores = array_map( static fn( $d ) => $d['ajuste'], $ajustes );

		$this->assertSame( 8, $valores[0], 'La primera de la lista es la última que se guardó' );
		$this->assertSame( 4, $valores[4], 'Y la última que se conserva es la quinta más reciente' );
		$this->assertNotContains( 3, $valores, 'Lo que se cae es lo más antiguo' );
	}

	public function test_una_sola_opcion_por_ajuste(): void {
		$clave = 'convoca_test_opcion_' . wp_rand( 1000, 9999 );

		$this->assertStringEndsWith( '_backups', $clave . Backups::SUFIJO, 'No se crea una opción por copia con la fecha en el nombre' );
		$this->assertSame( 1, Backups::anadir( $clave, array( 'a' => 1 ) ), 'La primera copia deja una' );

		// Y la opción que las guarda es una sola, con nombre fijo: el patrón viejo creaba una por copia.
		$fuente = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Backups.php' );
		$this->assertStringContainsString( "'_backups'", $fuente );
		$this->assertStringNotContainsString( "_backup_' . wp_date", $fuente, 'Nada de una opción nueva por copia con la fecha en el nombre' );
	}
}
