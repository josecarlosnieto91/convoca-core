<?php
/**
 * El historial de migraciones: lo que queda escrito cuando se actualiza un plugin.
 *
 * `Migration_History` estaba al 0 % (56 statements). Es pequeño, pero es la única memoria de lo que
 * pasó durante una actualización: si una migración falló, el error vive aquí y en ningún otro sitio.
 * Lo que se prueba: que se guarde con su estado, que el fallo guarde el motivo, que el historial no
 * crezca sin freno y que el filtro por plugin devuelva lo que toca.
 *
 * @package Convoca\Core
 */

namespace Convoca\Core\Tests\Unit;

use Convoca\Core\Migration_History;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Migration_History.php';

/**
 * Historial de migraciones del ecosistema.
 */
final class MigrationHistoryTest extends TestCase {

	/**
	 * Cada prueba parte de un historial vacío.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$GLOBALS['_wp_stores']['options'] = array();
	}

	/**
	 * Y no deja restos.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']['options'] = array();
	}

	/**
	 * Sin nada registrado el historial viene vacío, no como nulo.
	 *
	 * @return void
	 */
	public function test_sin_historial_devuelve_lista_vacia(): void {
		$this->assertSame( array(), Migration_History::get_all() );
	}

	/**
	 * Una migración buena se guarda con su estado y su duración.
	 *
	 * @return void
	 */
	public function test_una_migracion_correcta_se_guarda(): void {
		Migration_History::record( 'convoca-members', '2.9.17', '2.9.18', 0.4567 );

		$historial = Migration_History::get_all();

		$this->assertCount( 1, $historial );
		$this->assertSame( 'convoca-members', $historial[0]['plugin'] );
		$this->assertSame( '2.9.17', $historial[0]['from'] );
		$this->assertSame( '2.9.18', $historial[0]['to'] );
		$this->assertSame( 'success', $historial[0]['status'] );
		$this->assertSame( 0.457, $historial[0]['duration'], 'la duración se redondea a tres decimales' );
		$this->assertNotEmpty( $historial[0]['date'] );
	}

	/**
	 * Un fallo guarda el motivo, no solo que falló.
	 *
	 * @return void
	 */
	public function test_un_fallo_guarda_el_motivo(): void {
		Migration_History::record_failure( 'convoca-enroll', '2.7.67', '2.7.68', 'la tabla no existe' );

		$fila = Migration_History::get_all()[0];

		$this->assertSame( 'failed', $fila['status'] );
		$this->assertSame( 'la tabla no existe', $fila['error'], 'sin el motivo, el historial no sirve para arreglar nada' );
		$this->assertSame( 0, $fila['duration'] );
	}

	/**
	 * La meta adicional se conserva tal cual.
	 *
	 * @return void
	 */
	public function test_la_meta_adicional_se_conserva(): void {
		Migration_History::record( 'convoca-gateway', '2.13.21', '2.13.22', 1.0, array( 'filas' => 12 ) );

		$this->assertSame( array( 'filas' => 12 ), Migration_History::get_all()[0]['meta'] );
	}

	/**
	 * El historial no crece sin freno: se queda con las últimas cien.
	 *
	 * @return void
	 */
	public function test_el_historial_se_queda_en_las_ultimas_cien(): void {
		for ( $i = 0; $i < 105; $i++ ) {
			Migration_History::record( 'convoca-core', '2.3.48', '2.3.49', 0.1, array( 'n' => $i ) );
		}

		$historial = Migration_History::get_all();

		$this->assertCount( 100, $historial, 'una opción no puede crecer sin límite' );
		$this->assertSame( 104, $historial[99]['meta']['n'], 'y se conservan las últimas, no las primeras' );
	}

	/**
	 * El filtro por plugin devuelve solo las suyas.
	 *
	 * @return void
	 */
	public function test_el_filtro_por_plugin_no_mezcla(): void {
		Migration_History::record( 'convoca-members', '2.9.17', '2.9.18', 0.2 );
		Migration_History::record( 'convoca-enroll', '2.7.67', '2.7.68', 0.3 );
		Migration_History::record( 'convoca-members', '2.9.18', '2.9.19', 0.4 );

		$suyas = Migration_History::get_for_plugin( 'convoca-members' );

		$this->assertCount( 2, $suyas );
		foreach ( $suyas as $fila ) {
			$this->assertSame( 'convoca-members', $fila['plugin'] );
		}
	}

	/**
	 * El límite del filtro se respeta.
	 *
	 * @return void
	 */
	public function test_el_limite_del_filtro_se_respeta(): void {
		for ( $i = 0; $i < 10; $i++ ) {
			Migration_History::record( 'convoca-core', '1.0.' . $i, '1.0.' . ( $i + 1 ), 0.1 );
		}

		$this->assertCount( 3, Migration_History::get_for_plugin( 'convoca-core', 3 ) );
	}

	/**
	 * Un plugin sin historial devuelve lista vacía, no error.
	 *
	 * @return void
	 */
	public function test_un_plugin_sin_historial_devuelve_vacio(): void {
		Migration_History::record( 'convoca-members', '2.9.17', '2.9.18', 0.1 );

		$this->assertSame( array(), Migration_History::get_for_plugin( 'no-existe' ) );
	}

	/**
	 * Limpiar deja el historial vacío.
	 *
	 * @return void
	 */
	public function test_clear_vacia_el_historial(): void {
		Migration_History::record( 'convoca-core', '2.3.48', '2.3.49', 0.1 );

		Migration_History::clear();

		$this->assertSame( array(), Migration_History::get_all() );
	}
}
