<?php
/**
 * Upgrade Manager for Convoca Common.
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

	protected function get_upgrade_callbacks(): array {
		return array(
			'1.0.1' => array( $this, 'upgrade_to_1_0_1' ),
		);
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
