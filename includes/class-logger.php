<?php
/**
 * Persistent specialized DB Logger.
 * Replaces the fragmented loggers in separate plugins.
 *
 * @package Convoca\Core
 */

namespace Convoca\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Logger {

	/**
	 * Maximum logs to retrieve by default.
	 */
	const DEFAULT_LIMIT = 100;
	const MAX_LIMIT     = 1000;

	/**
	 * Track logging depth to prevent recursion.
	 */
	private static $log_depth = 0;
	const MAX_LOG_DEPTH       = 5;

	/**
	 * Rate limiting: track calls per time window.
	 */
	private static $log_timestamps = array();
	const RATE_LIMIT_WINDOW        = 60;
	const RATE_LIMIT_MAX           = 50;

	/**
	 * Log a message to the custom DB table.
	 * Includes recursion protection and rate limiting.
	 *
	 * @param string   $message   The message to log.
	 * @param string   $level     info|warning|error
	 * @param string   $context   The component or action taking place (e.g. Members, Enroll, System).
	 * @param int|null $object_id Optional object ID related to this log entry.
	 */
	public static function log( string $message, string $level = 'info', string $context = 'General', ?int $object_id = null ): void {
		// Prevent recursion.
		if ( self::$log_depth >= self::MAX_LOG_DEPTH ) {
			return;
		}

		// Rate limiting.
		$now                  = time();
		self::$log_timestamps = array_filter(
			self::$log_timestamps,
			function ( $t ) use ( $now ) {
				return ( $now - $t ) < self::RATE_LIMIT_WINDOW;
			}
		);
		if ( count( self::$log_timestamps ) >= self::RATE_LIMIT_MAX ) {
			return;
		}
		self::$log_timestamps[] = $now;

		global $wpdb;
		$table_name = $wpdb->prefix . 'biodevas_logs';

		if ( ! self::table_exists() ) {
			return;
		}

		++self::$log_depth;

		try {
			$result = $wpdb->insert(
				$table_name,
				array(
					'created_at' => current_time( 'mysql' ),
					'level'      => $level,
					'context'    => $context,
					'message'    => mb_substr( $message, 0, 5000 ),
					'user_id'    => get_current_user_id() ?: null,
					'object_id'  => $object_id,
				),
				array( '%s', '%s', '%s', '%s', '%d', '%d' )
			);

			$logged_to_file = false;

			if ( $result === false && ! empty( $wpdb->last_error ) ) {
				error_log( '[BIODEVAS DB ERROR] ' . $wpdb->last_error . ' - Message: ' . $message );
				$logged_to_file = true;
				// Table may have been dropped — invalidate cache so next call rechecks.
				self::clear_table_cache();
			}

			if ( ! $logged_to_file && ( 'error' === $level || ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) ) {
				error_log( sprintf( '[BIODEVAS] [%s] [%s] %s', strtoupper( $level ), $context, $message ) );
			}
		} catch ( \Throwable $e ) {
			error_log( '[BIODEVAS LOGGER EXCEPTION] ' . $e->getMessage() );
		} finally {
			--self::$log_depth;
		}
	}

	public static function info( string $message, string $context = 'General', ?int $object_id = null ): void {
		self::log( $message, 'info', $context, $object_id );
	}

	public static function warning( string $message, string $context = 'General', ?int $object_id = null ): void {
		self::log( $message, 'warning', $context, $object_id );
	}

	public static function error( string $message, string $context = 'General', ?int $object_id = null ): void {
		self::log( $message, 'error', $context, $object_id );
	}

	/**
	 * Retrieve logs from the database.
	 * Includes max limit protection.
	 *
	 * @param array $args Filter arguments (context, object_id, level, limit).
	 * @return array
	 */
	public static function get_logs( array $args = array() ): array {
		global $wpdb;
		$table_name = $wpdb->prefix . 'biodevas_logs';

		if ( ! self::table_exists() ) {
			return array();
		}

		$query  = "SELECT * FROM $table_name WHERE 1=1";
		$params = array();

		if ( ! empty( $args['context'] ) ) {
			$query   .= ' AND context = %s';
			$params[] = $args['context'];
		}

		if ( isset( $args['object_id'] ) ) {
			$query   .= ' AND object_id = %d';
			$params[] = (int) $args['object_id'];
		}

		if ( ! empty( $args['level'] ) ) {
			$query   .= ' AND level = %s';
			$params[] = $args['level'];
		}

		$query .= ' ORDER BY created_at DESC';

		if ( ! empty( $args['limit'] ) ) {
			$limit = (int) $args['limit'];
			if ( $limit < 1 ) {
				$limit = self::DEFAULT_LIMIT;
			} elseif ( $limit > self::MAX_LIMIT ) {
				$limit = self::MAX_LIMIT;
			}
			$query   .= ' LIMIT %d';
			$params[] = $limit;
		} else {
			$query .= ' LIMIT ' . self::DEFAULT_LIMIT;
		}

		if ( ! empty( $params ) ) {
			return $wpdb->get_results( $wpdb->prepare( $query, ...$params ), ARRAY_A );
		}

		return $wpdb->get_results( $query, ARRAY_A );
	}

	/**
	 * Clean up old logs (retention policy).
	 * Default: keep logs for 90 days, errors for 1 year.
	 * Called via daily cron.
	 */
	public static function cleanup(): void {
		if ( ! self::table_exists() ) {
			return;
		}

		@set_time_limit( 30 );

		global $wpdb;
		$table_name    = $wpdb->prefix . 'biodevas_logs';
		$today         = current_time( 'mysql' );
		$batch_size    = 1000;
		$total_deleted = 0;

		// Delete old info/warning logs (90 days) in batches.
		do {
			$batch_size = max( 1, $batch_size );
			$affected   = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM $table_name 
                 WHERE created_at < DATE_SUB(%s, INTERVAL 90 DAY) 
                 AND level IN ('info', 'warning')
                 LIMIT %d",
					$today,
					$batch_size
				)
			);
			if ( $affected ) {
				$total_deleted += $affected;
			}
		} while ( $affected !== false && $affected > 0 && $affected >= $batch_size );

		// Delete old error logs (1 year) in batches.
		do {
			$affected = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM $table_name 
                 WHERE created_at < DATE_SUB(%s, INTERVAL 1 YEAR) 
                 AND level = 'error'
                 LIMIT %d",
					$today,
					$batch_size
				)
			);
			if ( $affected ) {
				$total_deleted += $affected;
			}
		} while ( $affected !== false && $affected > 0 && $affected >= $batch_size );

		if ( $total_deleted > 0 ) {
			self::info( "Log cleanup: $total_deleted registros eliminados", 'System' );
		}
	}

	/**
	 * Purge old logs with a 60-day retention (daily cron).
	 * Logs the number of rows deleted after each run.
	 */
	public static function purge_old_logs(): void {
		if ( ! self::table_exists() ) {
			return;
		}

		@set_time_limit( 30 );

		global $wpdb;
		$table_name    = $wpdb->prefix . 'biodevas_logs';
		$cutoff        = current_time( 'mysql' );
		$batch_size    = 1000;
		$total_deleted = 0;

		do {
			$affected = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM $table_name WHERE created_at < DATE_SUB(%s, INTERVAL 60 DAY) LIMIT %d",
					$cutoff,
					$batch_size
				)
			);
			if ( $affected ) {
				$total_deleted += $affected;
			}
		} while ( $affected !== false && $affected > 0 && $affected >= $batch_size );

		self::info( "Purga automática: $total_deleted registros antiguos eliminados (60 días).", 'System' );
	}

	/**
	 * Get log statistics for dashboard.
	 */
	public static function get_stats(): array {
		global $wpdb;
		$table_name = $wpdb->prefix . 'biodevas_logs';

		if ( ! self::table_exists() ) {
			return array(
				'total'      => 0,
				'by_level'   => array(),
				'by_context' => array(),
				'size_kb'    => 0,
			);
		}

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );

		$by_level = $wpdb->get_results(
			"SELECT level, COUNT(*) as count FROM $table_name GROUP BY level",
			ARRAY_A
		);

		$by_context = $wpdb->get_results(
			"SELECT context, COUNT(*) as count FROM $table_name GROUP BY context ORDER BY count DESC LIMIT 10",
			ARRAY_A
		);

		// Estimate table size.
		$size = $wpdb->get_var(
			"
            SELECT ROUND((data_length + index_length) / 1024, 2) 
            FROM information_schema.TABLES 
            WHERE table_schema = DATABASE() 
            AND table_name = '$table_name'
        "
		) ?? 0;

		return array(
			'total'      => $total,
			'by_level'   => array_column( $by_level, 'count', 'level' ),
			'by_context' => $by_context,
			'size_kb'    => $size,
		);
	}

	/**
	 * Check if the logs table exists.
	 * Uses transient cache for cross-request caching, but verifies on each call to handle edge cases.
	 */
	private static function table_exists(): bool {
		global $wpdb;
		$transient_key = 'bdv_logger_table_exists';

		// Only trust a POSITIVE transient cache.
		$cached = get_transient( $transient_key );
		if ( $cached === 1 ) {
			return true;
		}

		// Verify table existence against the database.
		$table_name = $wpdb->prefix . 'biodevas_logs';
		$exists     = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name );

		if ( $exists ) {
			set_transient( $transient_key, 1, 10 * MINUTE_IN_SECONDS );
		}

		return $exists;
	}

	/**
	 * Invalidate the table existence cache (e.g., after a write failure).
	 */
	private static function clear_table_cache(): void {
		delete_transient( 'bdv_logger_table_exists' );
	}
}
