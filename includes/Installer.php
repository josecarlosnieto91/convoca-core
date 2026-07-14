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
 * Database installation and maintenance.
 *
 * @package Convoca\Core
 */

namespace Convoca\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Installer {


	/**
	 * Create/Update custom DB tables.
	 */
	public static function db_init(): void {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		// 1. Centralized logs (wp_convoca_logs).
		$table_logs = $wpdb->prefix . 'convoca_logs';

		$sql_logs = "CREATE TABLE $table_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            level varchar(20) DEFAULT 'info' NOT NULL,
            context varchar(50) DEFAULT 'General' NOT NULL,
            message longtext NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            object_id bigint(20) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY level (level),
            KEY context (context),
            KEY object_id (object_id),
            KEY created_at (created_at),
            KEY level_created (level, created_at)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_logs );

		// Ensure composite index for filtered queries (context + level + created_at).
		$index_check = $wpdb->get_results( "SHOW INDEX FROM $table_logs WHERE Key_name = 'context_level_created'" );
		if ( empty( $index_check ) ) {
			$wpdb->query( "ALTER TABLE $table_logs ADD INDEX context_level_created (context, level, created_at)" );
		}

		// Verify table was created.
		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table_logs )
		);

		if ( ! $table_exists ) {
			if ( ! empty( $wpdb->last_error ) ) {
				Logger::error( 'Failed to create convoca_logs table: ' . $wpdb->last_error, 'System' );
			} else {
				Logger::error( 'Failed to create convoca_logs table: unknown error', 'System' );
			}
		} else {
			// Invalidate logger cache now that the table exists.
			delete_transient( 'convoca_logger_table_exists' );
		}

		update_option( 'convoca_common_db_version', CONVOCA_COMMON_DB_VERSION );

		// 2. Webhook retries (wp_convoca_webhook_retries).
		$table_retries = $wpdb->prefix . 'convoca_webhook_retries';
		$sql_retries   = "CREATE TABLE $table_retries (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            webhook_id varchar(50) NOT NULL,
            webhook_url text NOT NULL,
            webhook_secret text DEFAULT NULL,
            payload longtext NOT NULL,
            status varchar(20) DEFAULT 'pending' NOT NULL,
            attempt int(11) DEFAULT 1 NOT NULL,
            scheduled_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            KEY webhook_id (webhook_id),
            KEY scheduled_at (scheduled_at),
            KEY status (status)
        ) $charset_collate;";
		dbDelta( $sql_retries );
		// Ensure index exists for existing installations.
		$index_status = $wpdb->get_results( "SHOW INDEX FROM $table_retries WHERE Key_name = 'status'" );
		if ( empty( $index_status ) ) {
		$wpdb->query( "ALTER TABLE $table_retries ADD INDEX status (status)" );
		}

		// 3. Locks table (wp_convoca_locks).
		$table_locks = $wpdb->prefix . 'convoca_locks';
		$sql_locks   = "CREATE TABLE $table_locks (
            lock_key varchar(100) NOT NULL,
            expires int(11) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (lock_key),
            KEY expires (expires)
        ) $charset_collate;";
		dbDelta( $sql_locks );

		// 4. Member sequence table (for atomic member number assignment)
		$table_seq = $wpdb->prefix . 'convoca_member_sequence';
		$sql_seq   = "CREATE TABLE $table_seq (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            member_id BIGINT(20) UNSIGNED NOT NULL,
            PRIMARY KEY  (id),
            KEY member_id (member_id)
        ) $charset_collate;";
		dbDelta( $sql_seq );

		// Schedule daily log cleanup.
		if ( ! wp_next_scheduled( 'convoca_log_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'convoca_log_cleanup' );
		}
		// Schedule daily log purge (60-day retention).
		if ( ! wp_next_scheduled( 'convoca_log_purge' ) ) {
			wp_schedule_event( time(), 'daily', 'convoca_log_purge' );
		}

		// Run migrations.
		self::ensure_member_access_codes();
	}

	/**
	 * Cleanup scheduled action callback.
	 */
	public static function run_cleanup(): void {
		Logger::cleanup();
	}

	/**
	 * Purge old logs (60-day retention).
	 */
	public static function run_purge(): void {
		Logger::purge_old_logs();
	}

	/**
	 * Migration: Ensure all members have an access code.
	 * Uses direct SQL for atomicity and to avoid offset-based pagination issues.
	 */
	public static function ensure_member_access_codes(): void {
		global $wpdb;
		$meta_key   = '_convoca_access_code';
		$batch_size = 100;
		$start_time = time();
		$max_time   = 25;

		// Acquire lock to prevent concurrent runs.
		if ( ! Utils::acquire_lock( 'convoca_access_code_generation', 300 ) ) {
			return;
		}

		try {
			do {
				$ids = $wpdb->get_col(
					"SELECT p.ID FROM {$wpdb->posts} p
                     LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '{$meta_key}'
                     WHERE p.post_type = 'miembro' AND pm.meta_id IS NULL
                     LIMIT {$batch_size}"
				);

				if ( empty( $ids ) ) {
					return;
				}

				$values = array();
				foreach ( $ids as $member_id ) {
					$code     = Utils::generate_access_code();
					$values[] = $wpdb->prepare( '(%d, %s, %s)', $member_id, $meta_key, $code );
				}

				if ( ! empty( $values ) ) {
					// Use INSERT IGNORE to handle race conditions gracefully.
					// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared — $values are individually built with $wpdb->prepare(), implode only joins already-prepared placeholders
					$wpdb->query(
						"INSERT IGNORE INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES " . implode( ',', $values )
					);
				}

				if ( time() - $start_time >= $max_time ) {
					break;
				}
			} while ( true );

			if ( ! wp_next_scheduled( 'convoca_continue_access_codes' ) ) {
				wp_schedule_event( time() + 300, 'hourly', 'convoca_continue_access_codes' );
			}
		} finally {
			Utils::release_lock( 'convoca_access_code_generation' );
		}
	}

	/**
	 * Continue processing access codes from cron.
	 */
	public static function continue_access_codes(): void {
		self::ensure_member_access_codes();

		// If all done, clear the cron.
		global $wpdb;
		$remaining = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_convoca_access_code'
             WHERE p.post_type = 'miembro' AND pm.meta_id IS NULL"
		);
		if ( $remaining === 0 ) {
			wp_clear_scheduled_hook( 'convoca_continue_access_codes' );
		}
	}
}
