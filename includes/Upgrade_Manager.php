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
 * Base Upgrade Manager for Convoca plugins.
 *
 * Manages database versioning and upgrade routines across plugin updates.
 * Each plugin extends this class and defines its own version constant,
 * option name, transient prefix, and upgrade callbacks.
 *
 * Usage:
 * 1. Extend this class in your plugin.
 * 2. Define: get_db_version(), get_option_name(), get_transient_prefix(), get_upgrade_callbacks()
 * 3. Instantiate during plugins_loaded.
 *
 * Adding a new upgrade routine:
 * 1. Increment the DB_VERSION constant in your plugin's main file.
 * 2. Add a callback to get_upgrade_callbacks(): '1.0.1' => [$this, 'upgrade_to_1_0_1']
 * 3. Implement the private method upgrade_to_1_0_1() with idempotent logic.
 * 4. The system will automatically run all pending upgrades in order.
 *
 * @package Convoca\Core
 */

namespace Convoca\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Upgrade_Manager {

	/**
	 * Initialize hooks for version checking.
	 */
	public function init(): void {
		add_action( 'admin_init', array( $this, 'maybe_upgrade' ) );
		add_action( 'upgrader_process_complete', array( $this, 'force_version_check' ), 10, 2 );
	}

	/**
	 * Check if an upgrade is needed and run it.
	 *
	 * Uses a 24-hour cache to avoid unnecessary checks on every admin_init.
	 * If the saved DB version is behind the constant, runs all pending
	 * upgrade callbacks in order.
	 */
	public function maybe_upgrade(): void {
		$saved   = $this->get_saved_version();
		$current = $this->get_db_version();

		if ( version_compare( $saved, $current, '>=' ) ) {
			$this->set_cache();
			return;
		}

		// If versions differ, skip cache and upgrade immediately.
		if ( $saved !== $current ) {
			$this->clear_cache();
		} elseif ( $this->is_cached() ) {
			return;
		}

		// Atomic lock using dedicated table, with try/finally to prevent stuck locks.
		$lock_key = 'upgrade_' . $this->get_transient_prefix();

		// Register shutdown handler to release lock if a Fatal Error kills the process.
		$shutdown_release = function () use ( $lock_key ) {
			$error = error_get_last();
			if ( $error && in_array( $error['type'], array( E_ERROR, E_USER_ERROR, E_COMPILE_ERROR, E_PARSE ) ) ) {
				\Convoca\Core\Utils::release_lock( $lock_key );
			}
		};
		register_shutdown_function( $shutdown_release );

		if ( ! \Convoca\Core\Utils::acquire_lock( $lock_key, HOUR_IN_SECONDS ) ) {
			return;
		}

		try {
			$this->run_upgrades( $saved, $current );
			$this->update_db_version( $current );
			$this->set_cache();
		} finally {
			\Convoca\Core\Utils::release_lock( $lock_key );
		}
		// Remove the shutdown handler since the finally block already released the lock.
		// (it will fire harmlessly later, but release_lock is idempotent)
	}

	/**
	 * Force a version check immediately (e.g., after plugin update).
	 */
	public function force_version_check(): void {
		$this->clear_cache();
		$this->maybe_upgrade();
	}

	/**
	 * Run all upgrade callbacks between the saved and current version.
	 */
	protected function run_upgrades( string $from, string $to ): void {
		$callbacks = $this->get_upgrade_callbacks();
		uksort( $callbacks, 'version_compare' );
		$last_successful = $from;

		foreach ( $callbacks as $version => $callback ) {
			if ( version_compare( $from, $version, '<' ) && version_compare( $version, $to, '<=' ) ) {
				if ( ! is_callable( $callback ) ) {
					Logger::error( "Upgrade $version callback not callable in {$this->get_transient_prefix()}", 'System' );
					throw new \RuntimeException( esc_html( "Upgrade callback not callable for version $version" ) );
				}
				try {
					call_user_func( $callback );
					$last_successful = $version;
				} catch ( \Throwable $e ) {
					Logger::error(
						"Upgrade $version failed in {$this->get_transient_prefix()}: " . $e->getMessage(),
						'System'
					);
					// Only update DB version to the last successful migration.
					// to allow retrying the failed one.
					if ( version_compare( $last_successful, $this->get_saved_version(), '>' ) ) {
						update_option( $this->get_option_name(), $last_successful );
					}
					throw $e; // Re-throw to be caught by maybe_upgrade's finally.
				}
			}
		}
	}

	/**
	 * Execute a data migration inside a database transaction.
	 *
	 * DDL statements (CREATE TABLE, ALTER TABLE, etc.) auto-commit in MySQL/MariaDB
	 * and CANNOT be rolled back. This helper should be used for DATA migrations
	 * only (UPDATE, INSERT, DELETE).
	 *
	 * If the callback throws, the transaction is rolled back.
	 * If successful, the transaction is committed.
	 *
	 * @param callable $callback Data migration logic (must not contain DDL).
	 * @param string   $description Human-readable label for logging.
	 * @throws \RuntimeException On failure.
	 */
	protected function run_data_migration( callable $callback, string $description ): void {
		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );
		try {
			$callback();
			$wpdb->query( 'COMMIT' );
			Logger::info( "Data migration '$description' completada.", 'System' );
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			Logger::error(
				"Data migration '$description' fallo: " . $e->getMessage(),
				'System'
			);
			throw new \RuntimeException( esc_html( "Data migration '$description' failed: " . $e->getMessage() ) );
		}
	}

	/**
	 * Update the database version in wp_options.
	 * Only called if all upgrades succeed.
	 */
	protected function update_db_version( string $version ): void {
		$saved = $this->get_saved_version();
		if ( version_compare( $version, $saved, '>' ) ) {
			update_option( $this->get_option_name(), $version, false );
			Logger::info( "DB version updated to $version in {$this->get_transient_prefix()}", 'System' );
			$this->set_cache();
		}
	}

	/**
	 * Get the saved database version from wp_options.
	 */
	protected function get_saved_version(): string {
		return get_option( $this->get_option_name(), '0.0.0' );
	}

	/**
	 * Check if the version check cache is still valid (24 hours).
	 */
	protected function is_cached(): bool {
		return (bool) get_transient( $this->get_transient_prefix() . '_last_version_check' );
	}

	/**
	 * Set the version check cache for 24 hours.
	 */
	protected function set_cache(): void {
		set_transient( $this->get_transient_prefix() . '_last_version_check', true, DAY_IN_SECONDS );
	}

	/**
	 * Clear the version check cache.
	 */
	protected function clear_cache(): void {
		delete_transient( $this->get_transient_prefix() . '_last_version_check' );
	}

	/**
	 * Get the current database version constant for this plugin.
	 */
	abstract protected function get_db_version(): string;

	/**
	 * Get the wp_options key where the DB version is stored.
	 */
	abstract protected function get_option_name(): string;

	/**
	 * Get the prefix for transients used by this plugin.
	 */
	abstract protected function get_transient_prefix(): string;

	/**
	 * Get an array of upgrade callbacks keyed by version.
	 *
	 * Example:
	 *   return [
	 *       '1.0.1' => [$this, 'upgrade_to_1_0_1'],
	 *       '1.1.0' => [$this, 'upgrade_to_1_1_0'],
	 *   ];
	 *
	 * Callbacks are sorted by version and executed in order.
	 * Each callback must be idempotent (safe to run multiple times).
	 */
	abstract protected function get_upgrade_callbacks(): array;
}
