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
 * Centralized Granular Capabilities Manager.
 *
 * Defines all custom capabilities across the Convoca ecosystem
 * and ensures they are assigned to the appropriate roles.
 *
 * @package Convoca\Core
 */

namespace Convoca\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Capabilities {

	/**
	 * Get all custom capabilities with descriptions.
	 *
	 * @return array<string, array{description: string, roles: string[]}>
	 */
	public static function get_all(): array {
		return array(
			// Convoca Shifts.
			'gestionar_mis_turnos'              => array(
				'description' => __( 'Gestionar mis turnos (voluntarios)', 'convoca-core' ),
				'roles'       => array( 'administrator', 'monitor_actividad', 'voluntario_aprobado' ),
			),
			'convoca_shifts_manage_turnos'      => array(
				'description' => __( 'Gestionar turnos del calendario (crear, editar)', 'convoca-core' ),
				'roles'       => array( 'administrator', 'monitor_actividad' ),
			),
			'convoca_shifts_view_stats'         => array(
				'description' => __( 'Ver estadísticas de turnos', 'convoca-core' ),
				'roles'       => array( 'administrator', 'monitor_actividad' ),
			),
			'convoca_shifts_audit_hours'        => array(
				'description' => __( 'Auditoría de horas de voluntarios', 'convoca-core' ),
				'roles'       => array( 'administrator', 'monitor_actividad' ),
			),

			// Convoca Enroll.
			// `manage_inscripciones` la ponía enroll al crear el rol `monitor_actividad`. Con el
			// vocabulario en core, tiene que estar en este mapa o el rol se queda a medias: `add_role()`
			// no pisa un rol que ya existe, así que quien llegue segundo no añade nada.
			'manage_inscripciones'              => array(
				'description' => __( 'Gestionar inscripciones', 'convoca-core' ),
				'roles'       => array( 'administrator', 'monitor_actividad', 'shop_manager' ),
			),
			// Gestión de personas: vocabulario del producto. Hoy estas tres sólo existían en el código de
			// Convoca Members. La capacidad puede existir sin que haya gestión de personas instalada: quien
			// la comprueba es la pantalla, y las personas que las tienen son las fichas.
			'gestionar_miembros'                => array(
				'description' => __( 'Gestionar miembros', 'convoca-core' ),
				'roles'       => array( 'administrator', 'monitor_actividad', 'shop_manager' ),
			),
			'gestionar_documentos_voluntariado' => array(
				'description' => __( 'Gestionar documentos de voluntariado', 'convoca-core' ),
				'roles'       => array( 'administrator', 'monitor_actividad', 'shop_manager' ),
			),
			'view_reports'                      => array(
				'description' => __( 'Ver informes', 'convoca-core' ),
				'roles'       => array( 'administrator', 'monitor_actividad', 'shop_manager' ),
			),
			// Edición de contenido: estas ocho son nativas de WordPress y las ponía Convoca Members para
			// que el monitor edite los turnos. Si el vocabulario es del producto, tienen que estar aquí, o
			// el monitor pierde la edición en cuanto members deje de ponerlas.
			'edit_posts'                        => array(
				'description' => __( 'Editar entradas', 'convoca-core' ),
				'roles'       => array( 'administrator', 'monitor_actividad' ),
			),
			'edit_others_posts'                 => array(
				'description' => __( 'Editar entradas de otros', 'convoca-core' ),
				'roles'       => array( 'administrator', 'monitor_actividad' ),
			),
			'edit_published_posts'              => array(
				'description' => __( 'Editar entradas publicadas', 'convoca-core' ),
				'roles'       => array( 'administrator', 'monitor_actividad' ),
			),
			'publish_posts'                     => array(
				'description' => __( 'Publicar entradas', 'convoca-core' ),
				'roles'       => array( 'administrator', 'monitor_actividad' ),
			),
			'delete_posts'                      => array(
				'description' => __( 'Borrar entradas', 'convoca-core' ),
				'roles'       => array( 'administrator', 'monitor_actividad' ),
			),
			'delete_others_posts'               => array(
				'description' => __( 'Borrar entradas de otros', 'convoca-core' ),
				'roles'       => array( 'administrator', 'monitor_actividad' ),
			),
			'delete_published_posts'            => array(
				'description' => __( 'Borrar entradas publicadas', 'convoca-core' ),
				'roles'       => array( 'administrator', 'monitor_actividad' ),
			),
			'read_private_posts'                => array(
				'description' => __( 'Leer entradas privadas', 'convoca-core' ),
				'roles'       => array( 'administrator', 'monitor_actividad' ),
			),
			'convoca_manage_checkin'            => array(
				'description' => __( 'Hacer check-in de asistentes', 'convoca-core' ),
				'roles'       => array( 'administrator', 'monitor_actividad' ),
			),
			'convoca_manage_evaluations'        => array(
				'description' => __( 'Gestionar evaluaciones', 'convoca-core' ),
				'roles'       => array( 'administrator', 'monitor_actividad' ),
			),
			'convoca_view_reports'              => array(
				'description' => __( 'Ver informes de actividades', 'convoca-core' ),
				'roles'       => array( 'administrator', 'monitor_actividad' ),
			),

			// Convoca Members.
			'convoca_manage_hours'              => array(
				'description' => __( 'Gestionar horas de voluntarios (aprobar)', 'convoca-core' ),
				'roles'       => array( 'administrator', 'monitor_actividad' ),
			),
			'convoca_export_members'            => array(
				'description' => __( 'Exportar listado de socios', 'convoca-core' ),
				'roles'       => array( 'administrator' ),
			),
			'convoca_manage_webhooks'           => array(
				'description' => __( 'Gestionar webhooks', 'convoca-core' ),
				'roles'       => array( 'administrator' ),
			),

			// Convoca Gateway.
			'convoca_view_payments'             => array(
				'description' => __( 'Ver pagos y dashboard', 'convoca-core' ),
				'roles'       => array( 'administrator' ),
			),
			'convoca_manage_payments'           => array(
				'description' => __( 'Gestionar pagos manualmente', 'convoca-core' ),
				'roles'       => array( 'administrator' ),
			),

			// Common.
			'common_view_logs'                  => array(
				'description' => __( 'Ver logs del sistema', 'convoca-core' ),
				'roles'       => array( 'administrator' ),
			),
			'common_manage_backup'              => array(
				'description' => __( 'Gestionar copias de seguridad', 'convoca-core' ),
				'roles'       => array( 'administrator' ),
			),
		);
	}

	/**
	 * Crea los roles de personas que falten. Idempotente: no toca los que ya existen.
	 *
	 * El vocabulario es del PRODUCTO: aquí y sólo aquí se crean. Antes los creaban tres plugins por su
	 * cuenta —members, shifts y enroll—, cada uno con sus capacidades y su criterio, y eso hacía que en
	 * un sitio sin la gestión de personas aparecieran roles de socio y de voluntario y se pudiera
	 * asignar gente a los turnos: justo lo contrario de la regla de producto (JC, 21/10/2026: sin la
	 * gestión de personas no hay personas asignables ni horas).
	 *
	 * Quién SÍ es voluntario o monitor no se decide aquí: eso son las fichas y las aprobaciones.
	 *
	 * Se llama desde `register()` porque una instalación que se actualiza no pasa por el gancho de
	 * activación, y no se recrean los roles existentes para no pisar capacidades que el sitio haya
	 * añadido por su cuenta.
	 */
	public static function crear_roles(): void {
		if ( ! function_exists( 'get_role' ) || ! function_exists( 'add_role' ) ) {
			return;
		}

		$nombres = array(
			'socio'               => __( 'Socio', 'convoca-core' ),
			'voluntario_aprobado' => __( 'Voluntario Aprobado', 'convoca-core' ),
			'monitor_actividad'   => __( 'Monitor de Actividad', 'convoca-core' ),
		);

		foreach ( $nombres as $slug => $etiqueta ) {
			if ( ! get_role( $slug ) ) {
				add_role( $slug, $etiqueta, array( 'read' => true ) );
			}
		}
	}

	/**
	 * Register (add) capabilities to their assigned roles.
	 *
	 * Safe to call multiple times; will not duplicate existing caps.
	 */
	public static function register(): void {

		// Primero el vocabulario: sin los roles, el mapa de capacidades no tiene a quién aplicarse.
		self::crear_roles();

		$caps = self::get_all();

		foreach ( $caps as $cap => $config ) {
			foreach ( $config['roles'] as $role_name ) {
				$role = get_role( $role_name );
				if ( $role && ! $role->has_cap( $cap ) ) {
					$role->add_cap( $cap );
				}
			}
		}
	}

	/**
	 * Ensure all granular capabilities exist on all roles.
	 *
	 * Call on plugins_loaded to handle upgrades without re-activation.
	 */
	public static function ensure(): void {
		// `is_admin()` no existe si WordPress no está cargado (arnés de pruebas, CLI suelto): sin la
		// guarda, esto es un fatal en vez de un no-op. Lo cazó la suite de integración del CI.
		if ( ! function_exists( 'is_admin' ) || ! is_admin() ) {
			return;
		}

		// v2 (21/10/2026): ahora también crea los roles de personas. Subir la marca es lo que hace
		// que las instalaciones existentes —donde los roles los creaban shifts o enroll— se alineen.
		$version_hash = md5( 'convoca_caps_v2' );
		if ( get_option( 'convoca_capabilities_hash' ) === $version_hash ) {
			return;
		}

		self::register();
		update_option( 'convoca_capabilities_hash', $version_hash, false );
	}
}
