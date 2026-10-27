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
 * Upgrade Manager for Convoca Core.
 *
 * Handles database structure upgrades for the common plugin.
 *
 * To add a new upgrade:
 * 1. Increment CONVOCA_COMMON_DB_VERSION in convoca-common.php
 * 2. Add a callback: '1.0.1' => [$this, 'upgrade_to_1_0_1']
 * 3. Implement the private method with idempotent logic.
 *
 * @package Convoca\Core
 */

namespace Convoca\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Common_Upgrade_Manager extends Upgrade_Manager {

	public function __construct() {
		$this->init();
	}

	protected function get_db_version(): string {
		return defined( 'CONVOCA_COMMON_DB_VERSION' ) ? CONVOCA_COMMON_DB_VERSION : '0.0.0';
	}

	protected function get_option_name(): string {
		return 'convoca_common_db_version';
	}

	protected function get_transient_prefix(): string {
		return 'convoca_common';
	}

	/**
	 * Callbacks de migración indexados por versión.
	 *
	 * @return array<string, mixed>
	 */
	protected function get_upgrade_callbacks(): array {
		return array(
			'1.0.1'  => array( $this, 'upgrade_to_1_0_1' ),
			'2.3.28' => array( $this, 'upgrade_to_2_3_28' ),
		);
	}

	/**
	 * Paso 2.3.28: retirar las tablas del prefijo viejo que dejó la migración del 09/10/2026.
	 *
	 * En la demo quedaron 7 tablas `wp_conv_*` junto a sus `wp_convoca_*` equivalentes, una de ellas con
	 * datos (`wp_conv_media_templates`, 8 filas). El código ya no las usa —todo se construye con
	 * `$wpdb->prefix . 'convoca_*'`—, pero una tabla duplicada con datos invita a que alguien lea la vieja
	 * (issue #12), y en un sitio con otro prefijo el problema se multiplica.
	 *
	 * La limpieza es CONSERVADORA a propósito: solo borra la tabla vieja cuando puede demostrar que la
	 * nueva tiene al menos tantas filas. Si la vieja tiene más, no se toca y se avisa en el log para que lo
	 * mire una persona: no se destruye nada que no se pueda cuadrar.
	 *
	 * @return array<string, mixed> Resumen: [ tabla_vieja => estado ].
	 */
	public static function limpiar_tablas_del_prefijo_viejo(): array {
		global $wpdb;

		$resumen = array();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$tablas = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . 'conv_' ) . '%' ) );

		foreach ( (array) $tablas as $vieja ) {
			$sufijo = substr( $vieja, strlen( $wpdb->prefix . 'conv_' ) );
			$nueva  = $wpdb->prefix . 'convoca_' . $sufijo;

			// Si no existe la nueva, esto no es el resto de una migración de prefijo: no se toca.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $nueva ) ) !== $nueva ) {
				$resumen[ $vieja ] = 'se deja: no hay tabla nueva equivalente';
				continue;
			}

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$filas_viejas = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$vieja`" );
			$filas_nuevas = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$nueva`" );
			// phpcs:enable

			if ( $filas_viejas > $filas_nuevas ) {
				$resumen[ $vieja ] = sprintf( 'se deja: tiene %d filas y la nueva %d', $filas_viejas, $filas_nuevas );
				\Convoca\Core\Logger::warning(
					sprintf( 'Tabla del prefijo viejo con más filas que su equivalente: %s (%d) frente a %s (%d). No se borra.', $vieja, $filas_viejas, $nueva, $filas_nuevas ),
					'Common/Upgrade'
				);
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DROP TABLE IF EXISTS `$vieja`" );

			$resumen[ $vieja ] = sprintf( 'borrada: %d filas frente a %d de %s', $filas_viejas, $filas_nuevas, $nueva );
			\Convoca\Core\Logger::info(
				sprintf( 'Tabla del prefijo viejo retirada: %s (%d filas); su equivalente %s tiene %d.', $vieja, $filas_viejas, $nueva, $filas_nuevas ),
				'Common/Upgrade'
			);
		}

		return $resumen;
	}

	/**
	 * Upgrade 2.3.28: retirar los restos de la migración de prefijo (issue #12).
	 */
	protected function upgrade_to_2_3_28(): void {
		self::limpiar_tablas_del_prefijo_viejo();
	}

	/**
	 * Upgrade to 1.0.1: Add whatsapp_reminder_sent column to convoca_logs table.
	 *
	 * This column tracks whether a WhatsApp reminder has been sent for a log entry.
	 * Idempotent: checks if column exists before adding.
	 */
	protected function upgrade_to_1_0_1(): void {
		global $wpdb;
		$table  = $wpdb->prefix . 'convoca_logs';
		$column = 'whatsapp_reminder_sent';

		// Check if table exists first.
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = %s AND table_name = %s',
				$wpdb->dbname,
				$table
			)
		);

		if ( ! $table_exists ) {
			return;
		}

		// Check if column already exists.
		$column_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = %s AND table_name = %s AND column_name = %s',
				$wpdb->dbname,
				$table,
				$column
			)
		);

		if ( ! $column_exists ) {
			$wpdb->query( "ALTER TABLE $table ADD COLUMN $column TINYINT(1) DEFAULT 0" );
		}
	}
}
