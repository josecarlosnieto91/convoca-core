<?php
/**
 * El vocabulario de personas es del producto (core) y la puerta de horas, del libro.
 *
 * @package Convoca\Core
 */

namespace Convoca\Core\Tests;

use Convoca\Core\Capabilities;
use Convoca\Core\Hour_Ledger;
use PHPUnit\Framework\TestCase;

/**
 * Dos reglas de producto (21/10/2026), que antes vivían repartidas por tres plugins:
 *
 *  1. Los roles de personas los crea core y sólo core. Antes los creaban también shifts y enroll por
 *     su cuenta, y eso era lo que permitía asignar gente a los turnos en un sitio sin la gestión de
 *     personas.
 *  2. Sin quien gestione las personas no se acreditan horas. El libro dice NO por defecto.
 */
class RolesYHorasTest extends TestCase {

	protected function setUp(): void {
		require_once dirname( __DIR__, 2 ) . '/includes/Capabilities.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Hour_Ledger.php';
		$GLOBALS['_wp_roles']      = array();
		$GLOBALS['_roles_creados'] = 0;
		$GLOBALS['_wp_filters']    = array();
	}

	protected function tearDown(): void {
		unset( $GLOBALS['_wp_filters']['convoca_can_credit_hours'] );
	}

	public function test_crear_roles_crea_los_tres_de_personas(): void {
		Capabilities::crear_roles();

		$this->assertSame( 3, $GLOBALS['_roles_creados'] );
		foreach ( array( 'socio', 'voluntario_aprobado', 'monitor_actividad' ) as $slug ) {
			$this->assertNotNull( get_role( $slug ), "falta el rol {$slug}" );
		}
	}

	public function test_crear_roles_es_idempotente(): void {
		Capabilities::crear_roles();
		$primera = $GLOBALS['_roles_creados'];
		Capabilities::crear_roles();

		$this->assertSame( $primera, $GLOBALS['_roles_creados'], 'la segunda pasada no debe crear nada' );
	}

	public function test_no_pisa_un_rol_que_ya_existe(): void {
		$GLOBALS['_wp_roles']['voluntario_aprobado'] = new \WP_Role( 'Voluntario', array( 'read' => true, 'cap_propia_del_sitio' => true ) );

		Capabilities::crear_roles();

		$rol = get_role( 'voluntario_aprobado' );
		$this->assertTrue( $rol->has_cap( 'cap_propia_del_sitio' ), 'las capacidades del sitio no se tocan' );
	}

	public function test_register_crea_los_roles_y_aplica_el_mapa(): void {
		Capabilities::register();

		$this->assertTrue( get_role( 'voluntario_aprobado' )->has_cap( 'gestionar_mis_turnos' ) );
		$this->assertTrue( get_role( 'monitor_actividad' )->has_cap( 'convoca_shifts_manage_turnos' ) );
		// Esta la ponía enroll al crear el rol; si falta en el mapa, `add_role()` no la repone y el rol
		// se queda a medias (lo cazó la suite de integración del CI, no este test: por eso está aquí).
		$this->assertTrue( get_role( 'monitor_actividad' )->has_cap( 'manage_inscripciones' ) );
		// Y estas las ponía members: sin ellas en el mapa, el monitor pierde la edición de turnos en
		// cuanto members deje de ponerlas (que es el paso siguiente del plan).
		foreach ( array( 'edit_posts', 'edit_others_posts', 'edit_published_posts', 'publish_posts', 'delete_posts', 'delete_others_posts', 'delete_published_posts', 'read_private_posts', 'gestionar_miembros', 'gestionar_documentos_voluntariado', 'view_reports' ) as $cap ) {
			$this->assertTrue( get_role( 'monitor_actividad' )->has_cap( $cap ), "el rol monitor se queda sin {$cap}" );
		}
		$this->assertFalse( get_role( 'socio' )->has_cap( 'convoca_export_members' ), 'socio no administra la exportación de socios' );
	}

	public function test_register_es_idempotente(): void {
		Capabilities::register();
		$antes = $GLOBALS['_roles_creados'];
		Capabilities::register();

		$this->assertSame( $antes, $GLOBALS['_roles_creados'] );
	}

	public function test_toda_capacidad_del_mapa_la_tiene_el_administrador(): void {
		// Regla del repo (la vigila `CapabilitiesIntegrationTest::test_all_caps_have_admin_role` contra
		// WordPress de verdad): si core declara una capacidad, el administrador tiene que poder ejercerla.
		// Se comprueba aquí también para que un añadido al mapa falle en local y no en el CI.
		foreach ( Capabilities::get_all() as $cap => $config ) {
			$this->assertContains( 'administrator', $config['roles'], "la capacidad {$cap} se ha quedado sin administrador" );
		}
	}

	public function test_horas_sin_quien_las_gestione_no_se_acreditan(): void {
		$this->assertFalse( Hour_Ledger::puede_acreditar_horas( 5 ) );
	}

	public function test_horas_con_usuario_invalido_no_se_acreditan(): void {
		$this->assertFalse( Hour_Ledger::puede_acreditar_horas( 0 ) );
		$this->assertFalse( Hour_Ledger::puede_acreditar_horas( -2 ) );
	}

	public function test_horas_quien_las_gestiona_las_abre_por_filtro(): void {
		add_filter(
			'convoca_can_credit_hours',
			static function ( $puede, $user_id ) {
				return 5 === (int) $user_id;
			},
			10,
			2
		);

		$this->assertTrue( Hour_Ledger::puede_acreditar_horas( 5 ) );
		$this->assertFalse( Hour_Ledger::puede_acreditar_horas( 6 ) );
	}
}
