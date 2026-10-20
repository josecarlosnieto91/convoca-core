<?php
/**
 * Pruebas del diagnóstico agregado: el núcleo pregunta, los módulos aportan.
 *
 * @package Convoca\Core
 */

namespace Convoca\Core\Tests;

use Convoca\Core\System_Checks;
use PHPUnit\Framework\TestCase;

/**
 * Lo que se fija aquí es el contrato del punto 7 del informe §3.5: el núcleo NO nombra clases de otros
 * plugins; pregunta por el filtro `convoca_system_checks`. Y lo que no puede perderse por el camino: el
 * aviso de «Plugin no activo» cuando un módulo no aporta nada.
 */
class SystemChecksTest extends TestCase {

	protected function setUp(): void {
		require_once dirname( __DIR__, 2 ) . '/includes/System_Checks.php';
		$GLOBALS['_wp_filters'] = array();
	}

	protected function tearDown(): void {
		$GLOBALS['_wp_filters'] = array();
	}

	/**
	 * Sin ningún módulo, la página avisa de los cuatro que faltan, en orden.
	 *
	 * @return void
	 */
	public function test_sin_modulos_avisa_de_los_cuatro(): void {
		$checks = System_Checks::recoger();

		$this->assertCount( 4, $checks );
		$this->assertSame(
			array( 'Convoca Members', 'Convoca Enroll', 'Convoca Gateway', 'Convoca Shifts' ),
			array_column( $checks, 'title' )
		);
		foreach ( $checks as $check ) {
			$this->assertSame( 'warning', $check['status'] );
		}
		$this->assertSame( 'Plugin no activo.', $checks[0]['message'] );
		$this->assertSame( 'Plugin no activo o sin función de diagnóstico.', $checks[3]['message'] );
	}

	/**
	 * Un módulo que aporta sustituye a su aviso, y su bloque se pinta en su sitio.
	 *
	 * @return void
	 */
	public function test_lo_que_aporta_un_modulo_sustituye_al_aviso(): void {
		add_filter(
			'convoca_system_checks',
			static function ( array $bloques ) {
				$bloques['gateway'] = array(
					array(
						'title'   => 'Convoca Gateway: entorno',
						'status'  => 'ok',
						'message' => 'Todo en orden.',
					),
				);
				return $bloques;
			}
		);

		$checks = System_Checks::recoger();

		$this->assertCount( 4, $checks );
		$this->assertSame( 'Convoca Gateway: entorno', $checks[2]['title'] );
		$this->assertSame( 'ok', $checks[2]['status'] );
		// Los demás siguen avisando.
		$this->assertSame( 'Convoca Members', $checks[0]['title'] );
	}

	/**
	 * El filtro recibe $force y se le puede decir que ignore cachés.
	 *
	 * @return void
	 */
	public function test_el_filtro_recibe_force(): void {
		$recibido = null;
		add_filter(
			'convoca_system_checks',
			static function ( array $bloques, $force = false ) use ( &$recibido ) {
				$recibido = $force;
				return $bloques;
			},
			10,
			2
		);

		System_Checks::recoger( true );

		$this->assertTrue( $recibido );
	}

	/**
	 * Un módulo que lanza una excepción no tumba la página: se pinta el fallo.
	 *
	 * @return void
	 */
	public function test_un_modulo_que_revienta_no_tumba_la_pagina(): void {
		add_filter(
			'convoca_system_checks',
			static function ( array $bloques ) {
				throw new \RuntimeException( 'el módulo ha reventado' );
			}
		);

		$checks = System_Checks::recoger();

		$this->assertCount( 5, $checks );
		$ultimo = end( $checks );
		$this->assertSame( 'error', $ultimo['status'] );
		$this->assertSame( 'el módulo ha reventado', $ultimo['message'] );
	}

	/**
	 * Un módulo nuevo (slug no esperado) también se pinta.
	 *
	 * @return void
	 */
	public function test_un_modulo_no_esperado_tambien_se_pinta(): void {
		add_filter(
			'convoca_system_checks',
			static function ( array $bloques ) {
				$bloques['cursos'] = array(
					array(
						'title'   => 'Cursos: vídeo',
						'status'  => 'ok',
						'message' => 'Bucket configurado.',
					),
				);
				return $bloques;
			}
		);

		$checks = System_Checks::recoger();

		$this->assertCount( 5, $checks );
		$this->assertSame( 'Cursos: vídeo', $checks[4]['title'] );
	}

	/**
	 * Las comprobaciones siempre salen con las cinco claves, aunque el módulo aporte dos.
	 *
	 * @return void
	 */
	public function test_normaliza_las_claves(): void {
		add_filter(
			'convoca_system_checks',
			static function ( array $bloques ) {
				$bloques['members'] = array( array( 'title' => 'Solo título' ) );
				return $bloques;
			}
		);

		$checks = System_Checks::recoger();

		$this->assertSame(
			array( 'title', 'status', 'message', 'fix', 'fix_url' ),
			array_keys( $checks[0] )
		);
		$this->assertSame( 'warning', $checks[0]['status'] );
	}
}
