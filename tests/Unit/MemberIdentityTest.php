<?php
/**
 * El contrato de identidad del socio: el núcleo pregunta, quien gestiona los socios contesta.
 *
 * @package Convoca\Core
 */

namespace Convoca\Core\Tests;

use Convoca\Core\Member_Identity;
use PHPUnit\Framework\TestCase;

/**
 * Antes de esto, cada plugin que necesitaba saber si había un socio con la sesión abierta llamaba
 * directamente a las clases de Convoca Members (`Member_Auth`, `Process_Member`) con un `class_exists`
 * delante y **otro código de reserva detrás**. Dos caminos que había que mantener sincronizados y que se
 * comportaban distinto según qué estuviera instalado.
 *
 * Lo que se fija aquí son los valores por defecto —los de hoy cuando no hay gestión de socios— y que la
 * respuesta se normaliza: lo que conteste un plugin, si no encaja, se convierte en «no hay socio», que es
 * el camino seguro.
 */
class MemberIdentityTest extends TestCase {

	protected function setUp(): void {
		require_once dirname( __DIR__, 2 ) . '/includes/Member_Identity.php';
		Member_Identity::olvidar();
		// Se vacía el registro de filtros del harness: cada caso monta los suyos y no hereda los del
		// anterior (el proceso de PHPUnit es el mismo para toda la clase).
		$GLOBALS['_wp_filters'] = array();
	}

	protected function tearDown(): void {
		$GLOBALS['_wp_filters'] = array();
		Member_Identity::olvidar();
	}

	/**
	 * Aporta una respuesta al filtro.
	 *
	 * @param string   $hook     Filtro.
	 * @param callable $devolver Qué devuelve.
	 * @param int      $args     Argumentos que acepta.
	 */
	private function aportar( string $hook, callable $devolver, int $args = 1 ): void {
		add_filter( $hook, $devolver, 10, $args );
	}

	public function test_sin_quien_conteste_no_hay_socio(): void {
		$this->assertFalse( Member_Identity::is_authenticated() );
		$this->assertSame( 0, Member_Identity::current_member_id() );
		$this->assertSame( array(), Member_Identity::current_member_data() );
	}

	public function test_quien_gestiona_los_socios_contesta(): void {
		$this->aportar(
			'convoca_member_identity',
			static function () {
				return array(
					'autenticado' => true,
					'member_id'   => 42,
					'datos'       => array( 'nombre' => 'Ana' ),
				);
			}
		);

		$this->assertTrue( Member_Identity::is_authenticated() );
		$this->assertSame( 42, Member_Identity::current_member_id() );
		$this->assertSame( array( 'nombre' => 'Ana' ), Member_Identity::current_member_data() );
	}

	public function test_una_respuesta_que_no_encaja_es_no_hay_socio(): void {
		$this->aportar(
			'convoca_member_identity',
			static function () {
				return 'esto no es una identidad';
			}
		);

		$this->assertFalse( Member_Identity::is_authenticated() );
		$this->assertSame( 0, Member_Identity::current_member_id() );
		$this->assertSame( array(), Member_Identity::current_member_data() );
	}

	public function test_los_datos_que_no_son_array_no_rompen_la_pantalla(): void {
		$this->aportar(
			'convoca_member_identity',
			static function () {
				return array(
					'autenticado' => '1',
					'member_id'   => '42',
					'datos'       => 'Ana',
				);
			}
		);

		$this->assertTrue( Member_Identity::is_authenticated() );
		$this->assertSame( 42, Member_Identity::current_member_id() );
		$this->assertSame( array(), Member_Identity::current_member_data(), 'datos siempre es un array' );
	}

	public function test_se_pregunta_una_sola_vez_por_peticion(): void {
		$llamadas = 0;
		$this->aportar(
			'convoca_member_identity',
			static function () use ( &$llamadas ) {
				++$llamadas;
				return array( 'autenticado' => true, 'member_id' => 7, 'datos' => array() );
			}
		);

		Member_Identity::is_authenticated();
		Member_Identity::current_member_id();
		Member_Identity::current_member_data();

		$this->assertSame( 1, $llamadas, 'la sesión se pide una vez, no tres' );
	}

	public function test_olvidar_vuelve_a_preguntar(): void {
		// Después de un login o un logout en la misma carga, la respuesta guardada ya no vale.
		$llamadas = 0;
		$this->aportar(
			'convoca_member_identity',
			static function () use ( &$llamadas ) {
				++$llamadas;
				return array( 'autenticado' => false, 'member_id' => 0, 'datos' => array() );
			}
		);

		Member_Identity::is_authenticated();
		Member_Identity::olvidar();
		Member_Identity::is_authenticated();

		$this->assertSame( 2, $llamadas );
	}

	public function test_la_prioridad_de_socio_por_defecto_se_concede(): void {
		// Es el comportamiento de hoy sin gestión de socios: no hay cuota que verificar, así que marcar
		// «soy socio» bastaba para tener prioridad.
		$this->assertTrue( Member_Identity::prioridad_de_socio( 123 ) );
	}

	public function test_quien_gestiona_los_socios_puede_quitar_la_prioridad(): void {
		$this->aportar(
			'convoca_member_priority',
			static function ( $prioridad, $inscripcion_id ) {
				return 123 !== (int) $inscripcion_id;
			},
			2
		);

		$this->assertFalse( Member_Identity::prioridad_de_socio( 123 ) );
		$this->assertTrue( Member_Identity::prioridad_de_socio( 124 ) );
	}
}
