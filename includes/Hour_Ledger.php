<?php

/**
 * Convoca Core
 *
 * @package    Convoca\Core
 * @subpackage Includes
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 */

/**
 * Ciclo de vida de las horas de voluntariado acreditadas (`registro_hora`).
 *
 * Un hecho de voluntariado —una asistencia a una actividad o un turno realizado— tiene
 * **un único** registro de horas, vinculado a su origen. Retirar el hecho lo **invalida**
 * (no lo borra) y volver a marcarlo lo **reactiva**, así que marcar/desmarcar nunca acumula.
 *
 * Los consumidores (Members, certificados, renovación) exigen `_convoca_estado = 'aprobada'`,
 * de modo que invalidar basta para que las horas dejen de contar sin tocar convoca-members.
 *
 * @package Convoca\Core
 */
namespace Convoca\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hour_Ledger {

	/**
	 * Tipos de hecho de origen.
	 */
	const ORIGEN_INSCRIPCION = 'inscripcion';
	const ORIGEN_TURNO       = 'turno';

	/**
	 * Estados de una acreditación.
	 */
	const ESTADO_APROBADA = 'aprobada';
	const ESTADO_ANULADA  = 'anulada';

	/**
	 * Acredita las horas de un hecho: reutiliza su registro si ya existe (reactivándolo)
	 * y solo crea uno nuevo cuando el hecho no tiene acreditación previa.
	 *
	 * @param string $origen    Tipo de origen (`inscripcion` o `turno`).
	 * @param int    $origen_id ID del hecho (inscripción o turno).
	 * @param int    $user_id   Usuario que realizó el hecho.
	 * @param float  $hours     Horas acreditadas.
	 * @param array  $args      title, tareas, actividad_id, fecha.
	 * @return int ID del registro, o 0 si no se pudo acreditar.
	 */
	public static function credit( string $origen, int $origen_id, int $user_id, float $hours, array $args = array() ): int {
		if ( $origen_id <= 0 || $user_id <= 0 || $hours <= 0 ) {
			return 0;
		}

		if ( ! post_type_exists( 'registro_hora' ) ) {
			Logger::warning(
				"Horas de voluntariado no registradas: el CPT 'registro_hora' no está disponible. Activa convoca-members.",
				'Core/HourLedger',
				$origen_id
			);
			return 0;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return 0;
		}

		$fecha = (string) ( $args['fecha'] ?? wp_date( 'Y-m-d' ) );

		$log_id = self::find( $origen, $origen_id );

		if ( $log_id ) {
			// El hecho ya tenía acreditación: se reactiva el MISMO registro (no se duplica).
			update_post_meta( $log_id, '_convoca_horas', $hours );
			update_post_meta( $log_id, '_convoca_fecha', $fecha );
			update_post_meta( $log_id, '_convoca_estado', self::ESTADO_APROBADA );
			delete_post_meta( $log_id, '_convoca_anulada_en' );
			delete_post_meta( $log_id, '_convoca_anulada_motivo' );

			// Un registro anulado pudo quedar con otro autor o estado: se normaliza.
			update_post_meta( $log_id, '_convoca_usuario_id', $user_id );
			if ( 'publish' !== get_post_status( $log_id ) ) {
				wp_update_post(
					array(
						'ID'          => $log_id,
						'post_status' => 'publish',
					)
				);
			}

			self::link_origin( $log_id, $origen, $origen_id );

			Logger::info(
				sprintf( 'Acreditación reactivada: registro #%d (%s #%d, %.2f h)', $log_id, $origen, $origen_id, $hours ),
				'Core/HourLedger',
				$log_id
			);

			return $log_id;
		}

		$log_id = wp_insert_post(
			array(
				'post_type'   => 'registro_hora',
				'post_status' => 'publish',
				'post_author' => $user_id,
				'post_title'  => (string) ( $args['title'] ?? sprintf( 'Horas %s #%d - %s', $origen, $origen_id, $user->display_name ) ),
			)
		);

		if ( ! $log_id ) {
			return 0;
		}

		update_post_meta( $log_id, '_convoca_usuario_id', $user_id );
		update_post_meta( $log_id, '_convoca_fecha', $fecha );
		update_post_meta( $log_id, '_convoca_horas', $hours );
		update_post_meta( $log_id, '_convoca_estado', self::ESTADO_APROBADA );
		update_post_meta( $log_id, '_convoca_actividad_id', (int) ( $args['actividad_id'] ?? 0 ) );
		update_post_meta( $log_id, '_convoca_tareas', (string) ( $args['tareas'] ?? '' ) );
		self::link_origin( $log_id, $origen, $origen_id );

		// Vínculo con el socio: Members lee `_convoca_member_id` (clave compartida).
		$members = get_posts(
			array(
				'post_type'      => 'miembro',
				'meta_key'       => '_convoca_email',
				'meta_value'     => $user->user_email,
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		if ( ! empty( $members ) ) {
			update_post_meta( $log_id, '_convoca_member_id', (int) $members[0] );
		} else {
			Logger::info(
				sprintf( 'Horas acreditadas sin socio vinculado (%s #%d): el usuario %d no tiene ficha de socio.', $origen, $origen_id, $user_id ),
				'Core/HourLedger',
				$log_id
			);
		}

		return (int) $log_id;
	}

	/**
	 * Invalida la acreditación de un hecho (sin borrar el registro, que queda auditable).
	 *
	 * @param string $origen    Tipo de origen.
	 * @param int    $origen_id ID del hecho.
	 * @param string $motivo    Motivo de la anulación, para el histórico.
	 * @return bool Verdadero si había una acreditación que invalidar.
	 */
	public static function revoke( string $origen, int $origen_id, string $motivo = '' ): bool {
		$log_id = self::find( $origen, $origen_id );
		if ( ! $log_id ) {
			Logger::info(
				sprintf( 'Sin acreditación que retirar para %s #%d.', $origen, $origen_id ),
				'Core/HourLedger',
				$origen_id
			);
			return false;
		}

		update_post_meta( $log_id, '_convoca_estado', self::ESTADO_ANULADA );
		update_post_meta( $log_id, '_convoca_anulada_en', current_time( 'mysql' ) );
		update_post_meta( $log_id, '_convoca_anulada_motivo', $motivo );

		Logger::info(
			sprintf( 'Acreditación retirada: registro #%d (%s #%d). %s', $log_id, $origen, $origen_id, $motivo ),
			'Core/HourLedger',
			$log_id
		);

		return true;
	}

	/**
	 * Registro de horas vigente de un hecho, si lo tiene.
	 *
	 * @param string $origen    Tipo de origen.
	 * @param int    $origen_id ID del hecho.
	 * @return int ID del registro, o 0.
	 */
	public static function find( string $origen, int $origen_id ): int {
		if ( $origen_id <= 0 || ! post_type_exists( 'registro_hora' ) ) {
			return 0;
		}

		// 1) Vínculo explícito con el hecho.
		$vinculados = get_posts(
			array(
				'post_type'      => 'registro_hora',
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'meta_query'     => array(
					array(
						'key'   => '_convoca_origen',
						'value' => $origen,
					),
					array(
						'key'   => '_convoca_origen_id',
						'value' => (string) $origen_id,
					),
				),
			)
		);

		if ( ! empty( $vinculados ) ) {
			return (int) $vinculados[0];
		}

		return self::find_unlinked( $origen, $origen_id );
	}

	/**
	 * Acreditaciones históricas sin vínculo: se reutilizan solo si la correspondencia es
	 * inequívoca (un único candidato con el mismo hecho). Nunca se adivina entre varios.
	 *
	 * @param string $origen    Tipo de origen.
	 * @param int    $origen_id ID del hecho.
	 * @return int ID del registro, o 0.
	 */
	private static function find_unlinked( string $origen, int $origen_id ): int {
		// El vínculo fiable de un turno histórico lo pone la migración del plugin: aquí solo
		// se reconstruye el de una inscripción, donde usuario + actividad identifican el hecho.
		if ( self::ORIGEN_INSCRIPCION !== $origen ) {
			return 0;
		}

		$user_id      = (int) get_post_meta( $origen_id, '_convoca_usuario_id', true );
		$actividad_id = (int) get_post_meta( $origen_id, '_convoca_actividad_id', true );

		if ( $user_id <= 0 ) {
			$email = (string) get_post_meta( $origen_id, '_convoca_email', true );
			$user  = $email ? get_user_by( 'email', $email ) : false;
			$user_id = $user ? (int) $user->ID : 0;
		}

		if ( $user_id <= 0 || $actividad_id <= 0 ) {
			return 0;
		}

		$candidatos = get_posts(
			array(
				'post_type'      => 'registro_hora',
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 10,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'meta_query'     => array(
					array(
						'key'   => '_convoca_usuario_id',
						'value' => (string) $user_id,
					),
					array(
						'key'   => '_convoca_actividad_id',
						'value' => (string) $actividad_id,
					),
					array(
						'key'     => '_convoca_origen_id',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		if ( count( $candidatos ) !== 1 ) {
			if ( count( $candidatos ) > 1 ) {
				Logger::warning(
					sprintf(
						'Acreditaciones históricas ambiguas para %s #%d: %d candidatos sin vínculo; no se reutiliza ninguna.',
						$origen,
						$origen_id,
						count( $candidatos )
					),
					'Core/HourLedger',
					$origen_id
				);
			}
			return 0;
		}

		$log_id = (int) $candidatos[0];
		self::link_origin( $log_id, $origen, $origen_id );

		Logger::info(
			sprintf( 'Registro histórico #%d enlazado a %s #%d (correspondencia inequívoca).', $log_id, $origen, $origen_id ),
			'Core/HourLedger',
			$log_id
		);

		return $log_id;
	}

	/**
	 * Escribe (o corrige) el vínculo del registro con su hecho de origen.
	 */
	private static function link_origin( int $log_id, string $origen, int $origen_id ): void {
		update_post_meta( $log_id, '_convoca_origen', $origen );
		update_post_meta( $log_id, '_convoca_origen_id', $origen_id );
	}

	/**
	 * Registros de horas de un usuario, opcionalmente solo los acreditados.
	 *
	 * @param int  $user_id  Usuario de WordPress.
	 * @param bool $solo_aprobadas Limitar a los acreditados.
	 * @return array<int, int> IDs de registro_hora.
	 */
	public static function credits_for_user( int $user_id, bool $solo_aprobadas = false ): array {
		if ( $user_id <= 0 || ! post_type_exists( 'registro_hora' ) ) {
			return array();
		}

		$meta_query = array(
			array(
				'key'   => '_convoca_usuario_id',
				'value' => (string) $user_id,
			),
		);

		if ( $solo_aprobadas ) {
			$meta_query[] = array(
				'key'   => '_convoca_estado',
				'value' => self::ESTADO_APROBADA,
			);
		}

		$ids = get_posts(
			array(
				'post_type'      => 'registro_hora',
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'meta_query'     => $meta_query,
			)
		);

		return array_map( 'intval', (array) $ids );
	}
}
