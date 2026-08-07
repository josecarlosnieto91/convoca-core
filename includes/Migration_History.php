<?php
/**
 * Convoca Core — Migration History
 *
 * Historial persistente de migraciones del ecosistema Convoca.
 * Complementa Upgrade_Manager con:
 *   - Registro de cada migración ejecutada (versión, fecha, duración, estado)
 *   - Validación post-migración (el callback puede declarar un validador)
 *   - Rollback (deshacer la última migración si el validador falla)
 *
 * @package Convoca\Core
 */

namespace Convoca\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persistent migration history for Convoca plugins.
 */
class Migration_History {

	/**
	 * Option name for the history log.
	 */
	private const OPTION = 'convoca_migration_history';

	/**
	 * Record a successful migration.
	 *
	 * @param string $plugin   Plugin prefix (e.g. 'convoca-core').
	 * @param string $from     Previous DB version.
	 * @param string $to       New DB version.
	 * @param float  $duration Seconds taken.
	 * @param array  $meta     Extra data (rows affected, etc.).
	 */
	public static function record( string $plugin, string $from, string $to, float $duration, array $meta = array() ): void {
		$history = self::get_all();
		$history[] = array(
			'plugin'    => $plugin,
			'from'      => $from,
			'to'        => $to,
			'duration'  => round( $duration, 3 ),
			'status'    => 'success',
			'date'      => current_time( 'mysql' ),
			'meta'      => $meta,
		);
		// Mantener las últimas 100 entradas (evitar crecimiento infinito).
		$history = array_slice( $history, -100 );
		update_option( self::OPTION, $history, false );
	}

	/**
	 * Record a failed migration.
	 *
	 * @param string $plugin Plugin prefix.
	 * @param string $from   Previous version.
	 * @param string $to     Attempted version.
	 * @param string $error  Error message.
	 */
	public static function record_failure( string $plugin, string $from, string $to, string $error ): void {
		$history = self::get_all();
		$history[] = array(
			'plugin'    => $plugin,
			'from'      => $from,
			'to'        => $to,
			'duration'  => 0,
			'status'    => 'failed',
			'error'     => $error,
			'date'      => current_time( 'mysql' ),
			'meta'      => array(),
		);
		$history = array_slice( $history, -100 );
		update_option( self::OPTION, $history, false );
	}

	/**
	 * Get the full migration history (newest last).
	 *
	 * @return array
	 */
	public static function get_all(): array {
		$history = get_option( self::OPTION, array() );
		return is_array( $history ) ? $history : array();
	}

	/**
	 * Get recent history filtered by plugin.
	 *
	 * @param string $plugin Plugin prefix.
	 * @param int    $limit  Max entries.
	 * @return array
	 */
	public static function get_for_plugin( string $plugin, int $limit = 20 ): array {
		$rows = array();
		foreach ( self::get_all() as $entry ) {
			if ( ( $entry['plugin'] ?? '' ) === $plugin ) {
				$rows[] = $entry;
			}
		}
		return array_slice( $rows, -$limit );
	}

	/**
	 * Run a migration with history + validation + optional rollback.
	 *
	 * Wrapper pensado para migraciones nuevas:
	 *
	 *   Migration_History::run(
	 *       'convoca-core',
	 *       '2.1.4',
	 *       '2.1.5',
	 *       function () { /* migración * / },
	 *       function () { return true; }, // validador post-migración
	 *       function () { /* rollback * / }  // opcional
	 *   );
	 *
	 * @param string   $plugin     Plugin prefix.
	 * @param string   $from       Version de origen.
	 * @param string   $to         Version destino.
	 * @param callable $migration  Migración (debe ser idempotente).
	 * @param callable $validator  Validador post-migración (devuelve bool).
	 * @param callable $rollback   Rollback opcional si el validador falla.
	 * @return bool True si la migración se completó y validó.
	 */
	public static function run(
		string $plugin,
		string $from,
		string $to,
		callable $migration,
		callable $validator,
		?callable $rollback = null
	): bool {
		$start   = microtime( true );
		$started = false;

		try {
			$started = true;
			call_user_func( $migration );

			$ok = (bool) call_user_func( $validator );
			if ( ! $ok ) {
				// Validación fallió → rollback si está definido.
				if ( $rollback ) {
					call_user_func( $rollback );
					Logger::warning( "Migration {$plugin} {$from}→{$to}: validation failed, rolled back", 'System' );
				} else {
					Logger::warning( "Migration {$plugin} {$from}→{$to}: validation failed, no rollback defined", 'System' );
				}
				self::record_failure( $plugin, $from, $to, 'validation_failed' );
				return false;
			}

			self::record( $plugin, $from, $to, microtime( true ) - $start );
			Logger::info( "Migration {$plugin} {$from}→{$to} OK", 'System' );
			return true;

		} catch ( \Throwable $e ) {
			if ( $started && $rollback ) {
				try {
					call_user_func( $rollback );
				} catch ( \Throwable $re ) {
					Logger::error( "Rollback failed for {$plugin}: " . $re->getMessage(), 'System' );
				}
			}
			self::record_failure( $plugin, $from, $to, $e->getMessage() );
			Logger::error( "Migration {$plugin} {$from}→{$to} failed: " . $e->getMessage(), 'System' );
			return false;
		}
	}

	/**
	 * Clear history (admin utility).
	 */
	public static function clear(): void {
		delete_option( self::OPTION );
	}
}
