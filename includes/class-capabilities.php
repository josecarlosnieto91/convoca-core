<?php
/**
 * Centralized Granular Capabilities Manager.
 *
 * Defines all custom capabilities across the Biodevas ecosystem
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
			// Centro Social Turnos.
			'cst_manage_turnos'      => array(
				'description' => __( 'Gestionar turnos del calendario (crear, editar)', 'convoca-core' ),
				'roles'       => array( 'administrator', 'monitor_actividad' ),
			),
			'cst_view_stats'         => array(
				'description' => __( 'Ver estadísticas de turnos', 'convoca-core' ),
				'roles'       => array( 'administrator', 'monitor_actividad' ),
			),
			'cst_audit_hours'        => array(
				'description' => __( 'Auditoría de horas de voluntarios', 'convoca-core' ),
				'roles'       => array( 'administrator', 'monitor_actividad' ),
			),

			// Biodevas Enroll.
			'bde_manage_checkin'     => array(
				'description' => __( 'Hacer check-in de asistentes', 'convoca-core' ),
				'roles'       => array( 'administrator', 'monitor_actividad' ),
			),
			'bde_manage_evaluations' => array(
				'description' => __( 'Gestionar evaluaciones', 'convoca-core' ),
				'roles'       => array( 'administrator', 'monitor_actividad' ),
			),
			'bde_view_reports'       => array(
				'description' => __( 'Ver informes de actividades', 'convoca-core' ),
				'roles'       => array( 'administrator', 'monitor_actividad' ),
			),

			// Biodevas Members.
			'bdv_manage_hours'       => array(
				'description' => __( 'Gestionar horas de voluntarios (aprobar)', 'convoca-core' ),
				'roles'       => array( 'administrator', 'monitor_actividad' ),
			),
			'bdv_export_members'     => array(
				'description' => __( 'Exportar listado de socios', 'convoca-core' ),
				'roles'       => array( 'administrator' ),
			),
			'bdv_manage_webhooks'    => array(
				'description' => __( 'Gestionar webhooks', 'convoca-core' ),
				'roles'       => array( 'administrator' ),
			),

			// Biodevas Gateway.
			'bdg_view_payments'      => array(
				'description' => __( 'Ver pagos y dashboard', 'convoca-core' ),
				'roles'       => array( 'administrator' ),
			),
			'bdg_manage_payments'    => array(
				'description' => __( 'Gestionar pagos manualmente', 'convoca-core' ),
				'roles'       => array( 'administrator' ),
			),

			// Common.
			'common_view_logs'       => array(
				'description' => __( 'Ver logs del sistema', 'convoca-core' ),
				'roles'       => array( 'administrator' ),
			),
			'common_manage_backup'   => array(
				'description' => __( 'Gestionar copias de seguridad', 'convoca-core' ),
				'roles'       => array( 'administrator' ),
			),
		);
	}

	/**
	 * Register (add) capabilities to their assigned roles.
	 *
	 * Safe to call multiple times; will not duplicate existing caps.
	 */
	public static function register(): void {
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
		if ( ! is_admin() ) {
			return;
		}

		$version_hash = md5( 'biodevas_caps_v1' );
		if ( get_option( 'bdv_capabilities_hash' ) === $version_hash ) {
			return;
		}

		self::register();
		update_option( 'bdv_capabilities_hash', $version_hash, false );
	}
}
