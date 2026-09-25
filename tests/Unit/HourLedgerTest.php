<?php
/**
 * Horas de voluntariado reversibles: un hecho, una acreditación.
 *
 * Contrato (convoca-enroll#2): marcar, desmarcar y volver a marcar N veces un mismo hecho
 * —una asistencia a una actividad o un turno— debe dejar **un único** registro de horas y
 * acreditar exactamente las horas del estado final, nunca N veces. Retirar el hecho invalida
 * solo su propia acreditación y no toca las horas de otras actividades ni las de otros
 * voluntarios. Los consumidores (Members, certificados, renovación) exigen el estado
 * `aprobada`, así que invalidar basta para que dejen de contar.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use PHPUnit\Framework\TestCase;
use Convoca\Core\Hour_Ledger;

class HourLedgerTest extends TestCase {

	private const USER_A   = 501;
	private const USER_B   = 502;
	private const MIEMBRO_A = 601;
	private const MIEMBRO_B = 602;
	private const ACT_1    = 701;
	private const ACT_2    = 702;
	private const INSC_1   = 801;
	private const INSC_2   = 802;
	private const INSC_3   = 803; // segunda inscripción del mismo voluntario a la misma actividad

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['_wp_stores']          = array( 'posts' => array(), 'post_meta' => array(), 'post_types' => array(), 'options' => array(), 'transients' => array() );
		$GLOBALS['_wp_stub_next_id']    = 900;
		$GLOBALS['_test_users']         = array();

		$this->usuario( self::USER_A, 'voluntario-a@ejemplo.org' );
		$this->usuario( self::USER_B, 'voluntario-b@ejemplo.org' );

		$this->socio( self::MIEMBRO_A, self::USER_A, 'voluntario-a@ejemplo.org' );
		$this->socio( self::MIEMBRO_B, self::USER_B, 'voluntario-b@ejemplo.org' );

		$this->inscripcion( self::INSC_1, self::USER_A, self::ACT_1 );
		$this->inscripcion( self::INSC_2, self::USER_A, self::ACT_2 );
		$this->inscripcion( self::INSC_3, self::USER_A, self::ACT_1 );
	}

	/** Crea el usuario en el mock de get_userdata(). */
	private function usuario( int $id, string $email ): void {
		$u               = new \WP_User();
		$u->ID           = $id;
		$u->display_name = 'Voluntario ' . $id;
		$u->user_email   = $email;
		$GLOBALS['_test_users'][ $id ] = $u;
	}

	/** Ficha de socio (la usa `credit()` para el vínculo `_convoca_member_id`). */
	private function socio( int $id, int $user_id, string $email ): void {
		$p              = new \WP_Post();
		$p->ID          = $id;
		$p->post_type   = 'miembro';
		$p->post_status = 'publish';
		$GLOBALS['_wp_stores']['posts'][ $id ]      = $p;
		$GLOBALS['_wp_stores']['post_types'][ $id ] = 'miembro';
		$GLOBALS['_wp_stores']['post_meta'][ $id ]  = array(
			'_convoca_email'   => $email,
			'_convoca_user_id' => $user_id,
		);
	}

	/** Inscripción a una actividad, como la crea Enroll. */
	private function inscripcion( int $id, int $user_id, int $actividad_id ): void {
		$p              = new \WP_Post();
		$p->ID          = $id;
		$p->post_type   = 'inscripcion';
		$p->post_status = 'publish';
		$GLOBALS['_wp_stores']['posts'][ $id ]      = $p;
		$GLOBALS['_wp_stores']['post_types'][ $id ] = 'inscripcion';
		$GLOBALS['_wp_stores']['post_meta'][ $id ]  = array(
			'_convoca_usuario_id'  => $user_id,
			'_convoca_actividad_id' => $actividad_id,
		);
	}

	/** Acredita la asistencia de una inscripción (lo que hace el producto al marcar 'si'). */
	private function marcar( int $inscripcion_id, int $user_id, int $actividad_id, float $horas = 4.0 ): int {
		return Hour_Ledger::credit(
			Hour_Ledger::ORIGEN_INSCRIPCION,
			$inscripcion_id,
			$user_id,
			$horas,
			array( 'actividad_id' => $actividad_id, 'tareas' => 'Asistencia a actividad programada' )
		);
	}

	/** Retira la asistencia (lo que hace el producto al desmarcar). */
	private function retirar( int $inscripcion_id ): bool {
		return Hour_Ledger::revoke( Hour_Ledger::ORIGEN_INSCRIPCION, $inscripcion_id, 'Asistencia retirada' );
	}

	/** Espejo de lo que ve Members: suma las horas acreditadas del socio. */
	private function horas_de_members( int $miembro_id ): float {
		$total = 0.0;
		foreach ( $GLOBALS['_wp_stores']['post_meta'] as $post_id => $meta ) {
			$post = $GLOBALS['_wp_stores']['posts'][ $post_id ] ?? null;
			if ( ! $post || 'registro_hora' !== $post->post_type ) {
				continue;
			}
			if ( (int) ( $meta['_convoca_member_id'] ?? 0 ) !== $miembro_id ) {
				continue;
			}
			if ( 'aprobada' !== ( $meta['_convoca_estado'] ?? '' ) ) {
				continue;
			}
			$total += (float) ( $meta['_convoca_horas'] ?? 0 );
		}
		return $total;
	}

	/** Registros de horas de un origen concreto. */
	private function registros_de( string $origen, int $origen_id ): array {
		$ids = array();
		foreach ( $GLOBALS['_wp_stores']['post_meta'] as $post_id => $meta ) {
			if ( ( $meta['_convoca_origen'] ?? '' ) === $origen && (int) ( $meta['_convoca_origen_id'] ?? 0 ) === $origen_id ) {
				$ids[] = (int) $post_id;
			}
		}
		return $ids;
	}

	public function test_primera_marcacion_crea_un_unico_registro_aprobado(): void {
		$log = $this->marcar( self::INSC_1, self::USER_A, self::ACT_1 );

		$this->assertGreaterThan( 0, $log );
		$this->assertSame( 'aprobada', get_post_meta( $log, '_convoca_estado', true ) );
		$this->assertEquals( 4.0, (float) get_post_meta( $log, '_convoca_horas', true ) );
		$this->assertSame( self::MIEMBRO_A, (int) get_post_meta( $log, '_convoca_member_id', true ) );
		$this->assertSame( self::INSC_1, (int) get_post_meta( $log, '_convoca_origen_id', true ) );
		$this->assertCount( 1, $this->registros_de( Hour_Ledger::ORIGEN_INSCRIPCION, self::INSC_1 ) );
		$this->assertEquals( 4.0, $this->horas_de_members( self::MIEMBRO_A ) );
	}

	public function test_retirar_la_asistencia_deja_de_contar_sin_borrar_el_registro(): void {
		$log = $this->marcar( self::INSC_1, self::USER_A, self::ACT_1 );
		$this->retirar( self::INSC_1 );

		$this->assertNotNull( get_post( $log ), 'El histórico no se borra.' );
		$this->assertSame( 'anulada', get_post_meta( $log, '_convoca_estado', true ) );
		$this->assertNotSame( '', get_post_meta( $log, '_convoca_anulada_en', true ) );
		$this->assertSame( 'Asistencia retirada', get_post_meta( $log, '_convoca_anulada_motivo', true ) );
		$this->assertEquals( 0.0, $this->horas_de_members( self::MIEMBRO_A ) );
	}

	public function test_volver_a_marcar_reactiva_el_mismo_registro(): void {
		$log = $this->marcar( self::INSC_1, self::USER_A, self::ACT_1 );
		$this->retirar( self::INSC_1 );
		$de_nuevo = $this->marcar( self::INSC_1, self::USER_A, self::ACT_1 );

		$this->assertSame( $log, $de_nuevo, 'Debe reutilizar el registro, no crear otro.' );
		$this->assertCount( 1, $this->registros_de( Hour_Ledger::ORIGEN_INSCRIPCION, self::INSC_1 ) );
		$this->assertSame( 'aprobada', get_post_meta( $log, '_convoca_estado', true ) );
		$this->assertSame( '', get_post_meta( $log, '_convoca_anulada_en', true ), 'La anulación se limpia al reactivar.' );
		$this->assertEquals( 4.0, $this->horas_de_members( self::MIEMBRO_A ) );
	}

	public function test_ciclos_repetidos_no_acumulan_horas(): void {
		$this->marcar( self::INSC_1, self::USER_A, self::ACT_1 );

		for ( $i = 0; $i < 5; $i++ ) {
			$this->retirar( self::INSC_1 );
			$this->marcar( self::INSC_1, self::USER_A, self::ACT_1 );
		}
		$this->retirar( self::INSC_1 );
		$this->marcar( self::INSC_1, self::USER_A, self::ACT_1 );

		$this->assertCount( 1, $this->registros_de( Hour_Ledger::ORIGEN_INSCRIPCION, self::INSC_1 ) );
		$this->assertEquals( 4.0, $this->horas_de_members( self::MIEMBRO_A ), 'Tras 7 ciclos siguen siendo 4 h, no 28.' );
	}

	public function test_marcar_dos_veces_seguidas_no_duplica(): void {
		$log = $this->marcar( self::INSC_1, self::USER_A, self::ACT_1 );
		$otra = $this->marcar( self::INSC_1, self::USER_A, self::ACT_1 );

		$this->assertSame( $log, $otra );
		$this->assertCount( 1, $this->registros_de( Hour_Ledger::ORIGEN_INSCRIPCION, self::INSC_1 ) );
		$this->assertEquals( 4.0, $this->horas_de_members( self::MIEMBRO_A ) );
	}

	public function test_retirar_dos_veces_seguidas_es_idempotente(): void {
		$this->marcar( self::INSC_1, self::USER_A, self::ACT_1 );

		$this->assertTrue( $this->retirar( self::INSC_1 ) );
		$this->assertTrue( $this->retirar( self::INSC_1 ), 'Repetir la retirada no falla ni rompe nada.' );
		$this->assertEquals( 0.0, $this->horas_de_members( self::MIEMBRO_A ) );
	}

	public function test_retirar_una_asistencia_no_toca_las_horas_de_otra_actividad(): void {
		$this->marcar( self::INSC_1, self::USER_A, self::ACT_1, 4.0 );
		$this->marcar( self::INSC_2, self::USER_A, self::ACT_2, 2.5 );
		$this->assertEquals( 6.5, $this->horas_de_members( self::MIEMBRO_A ) );

		$this->retirar( self::INSC_1 );

		$this->assertEquals( 2.5, $this->horas_de_members( self::MIEMBRO_A ), 'Solo se retira la acreditación de esa asistencia.' );
		$this->assertSame( 'aprobada', get_post_meta( $this->registros_de( Hour_Ledger::ORIGEN_INSCRIPCION, self::INSC_2 )[0], '_convoca_estado', true ) );
	}

	public function test_dos_asistencias_independientes_del_mismo_voluntario_suman(): void {
		$this->marcar( self::INSC_1, self::USER_A, self::ACT_1, 4.0 );
		$this->marcar( self::INSC_2, self::USER_A, self::ACT_2, 3.0 );

		$this->assertCount( 1, $this->registros_de( Hour_Ledger::ORIGEN_INSCRIPCION, self::INSC_1 ) );
		$this->assertCount( 1, $this->registros_de( Hour_Ledger::ORIGEN_INSCRIPCION, self::INSC_2 ) );
		$this->assertEquals( 7.0, $this->horas_de_members( self::MIEMBRO_A ) );
	}

	public function test_las_horas_de_voluntarios_distintos_no_se_mezclan(): void {
		$this->marcar( self::INSC_1, self::USER_A, self::ACT_1, 4.0 );
		Hour_Ledger::credit( Hour_Ledger::ORIGEN_TURNO, 950, self::USER_B, 2.0 );

		$this->assertEquals( 4.0, $this->horas_de_members( self::MIEMBRO_A ) );
		$this->assertEquals( 2.0, $this->horas_de_members( self::MIEMBRO_B ) );
	}

	public function test_un_registro_historico_sin_vinculo_se_reutiliza_si_es_inequivoco(): void {
		// Registro antiguo (creado antes del vínculo) del mismo voluntario y actividad.
		$viejo = Hour_Ledger::credit( Hour_Ledger::ORIGEN_INSCRIPCION, self::INSC_2, self::USER_A, 3.0, array( 'actividad_id' => self::ACT_2 ) );
		delete_post_meta( $viejo, '_convoca_origen' );
		delete_post_meta( $viejo, '_convoca_origen_id' );

		// Al volver a acreditar el mismo hecho se enlaza y reutiliza ese registro.
		$reusado = $this->marcar( self::INSC_2, self::USER_A, self::ACT_2, 3.0 );

		$this->assertSame( $viejo, $reusado );
		$this->assertSame( self::INSC_2, (int) get_post_meta( $reusado, '_convoca_origen_id', true ) );
		$this->assertEquals( 3.0, $this->horas_de_members( self::MIEMBRO_A ) );
	}

	public function test_con_varios_candidatos_historicos_no_se_reutiliza_ninguno(): void {
		// Dos registros idénticos sin vínculo (el escenario que dejaba el bug antiguo).
		foreach ( array( 1, 2 ) as $ignorado ) {
			$log = wp_insert_post( array( 'post_type' => 'registro_hora', 'post_status' => 'publish' ) );
			update_post_meta( $log, '_convoca_usuario_id', self::USER_A );
			update_post_meta( $log, '_convoca_actividad_id', self::ACT_2 );
			update_post_meta( $log, '_convoca_horas', 3.0 );
			update_post_meta( $log, '_convoca_estado', 'aprobada' );
		}

		$nuevo = $this->marcar( self::INSC_2, self::USER_A, self::ACT_2, 3.0 );

		$this->assertGreaterThan( 0, $nuevo );
		$this->assertSame( self::INSC_2, (int) get_post_meta( $nuevo, '_convoca_origen_id', true ), 'Con ambigüedad se crea uno nuevo y vinculado.' );
		$this->assertCount( 1, $this->registros_de( Hour_Ledger::ORIGEN_INSCRIPCION, self::INSC_2 ) );
	}

	public function test_revoke_sin_acreditacion_previa_no_falla(): void {
		$this->assertFalse( Hour_Ledger::revoke( Hour_Ledger::ORIGEN_INSCRIPCION, 9999, 'Sin registro' ) );
		$this->assertEquals( 0.0, $this->horas_de_members( self::MIEMBRO_A ) );
	}

	public function test_credits_for_user_separa_aprobadas_de_anuladas(): void {
		$log = $this->marcar( self::INSC_1, self::USER_A, self::ACT_1 );

		$this->assertCount( 1, Hour_Ledger::credits_for_user( self::USER_A, true ) );

		$this->retirar( self::INSC_1 );

		$this->assertCount( 0, Hour_Ledger::credits_for_user( self::USER_A, true ) );
		$this->assertCount( 1, Hour_Ledger::credits_for_user( self::USER_A, false ), 'El registro sigue existiendo para auditoría.' );
	}
}
