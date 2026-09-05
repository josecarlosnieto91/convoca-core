<?php
/**
 * Convoca Core — Performance Probe
 *
 * Profiler ligero de peticiones. Mide:
 *   - Número de consultas SQL y tiempo de las más lentas
 *   - Hooks lentos (do_action / apply_filters con duración > umbral)
 *   - Memoria y tiempo total de la petición
 *
 * Activar por petición: ?convoca_probe=1 (solo admins) o manualmente
 * con Performance_Probe::start().
 *
 * @package Convoca\Core
 */

namespace Convoca\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Performance profiler for Convoca requests.
 */
class Performance_Probe {

	/**
	 * Whether the probe is active.
	 *
	 * @var bool
	 */
	private static $active = false;

	/**
	 * Request start time.
	 *
	 * @var float
	 */
	private static $start = 0;

	/**
	 * Slow hooks collected.
	 *
	 * @var array
	 */
	private static $slow_hooks = array();

	/**
	 * Query timings collected.
	 *
	 * @var array
	 */
	private static $slow_queries = array();

	/**
	 * Threshold (seconds) for a hook to be considered slow.
	 *
	 * @var float
	 */
	private static $hook_threshold = 0.05;

	/**
	 * Init: auto-start on ?convoca_probe=1 for admins.
	 */
	public static function init(): void {
		if ( ! self::$active && isset( $_GET['convoca_probe'] ) && '1' === $_GET['convoca_probe'] && current_user_can( 'manage_options' ) ) {
			self::start();
			add_action( 'shutdown', array( __CLASS__, 'render' ), 999 );
		}
	}

	/**
	 * Start profiling.
	 */
	public static function start(): void {
		self::$active = true;
		self::$start  = microtime( true );

		// Contador de queries con tiempos (SAVEQUERIES habilitado).
		if ( ! defined( 'SAVEQUERIES' ) ) {
			define( 'SAVEQUERIES', true );
		}

		// Hook de captura de queries lentas en shutdown.
		add_action( 'shutdown', array( __CLASS__, 'capture_slow_queries' ), 998 );

		// Envolver do_action y apply_filters para medir hooks lentos.
		// Se registra con prioridad 0 y 999 para medir el tiempo total.
		add_action( 'all', array( __CLASS__, 'hook_enter' ), 0 );
		add_action( 'all', array( __CLASS__, 'hook_exit' ), 999 );
	}

	/**
	 * Enter a hook — record start time.
	 *
	 * @param string $hook Hook name.
	 */
	public static function hook_enter( $hook ): void {
		if ( ! self::$active || in_array( $hook, array( 'all' ), true ) ) {
			return;
		}
		// Usamos array de timers apilados por hook.
		if ( ! isset( self::$hook_stack[ $hook ] ) ) {
			self::$hook_stack[ $hook ] = array();
		}
		self::$hook_stack[ $hook ][] = microtime( true );
	}

	/**
	 * Exit a hook — accumulate duration.
	 *
	 * @param string $hook Hook name.
	 */
	public static function hook_exit( $hook ): void {
		if ( ! self::$active || in_array( $hook, array( 'all' ), true ) ) {
			return;
		}
		if ( ! empty( self::$hook_stack[ $hook ] ) ) {
			$start = array_pop( self::$hook_stack[ $hook ] );
			$dur   = microtime( true ) - $start;
			if ( $dur >= self::$hook_threshold ) {
				self::$slow_hooks[ $hook ] = ( self::$slow_hooks[ $hook ] ?? 0 ) + $dur;
			}
		}
	}

	/**
	 * Capture slow queries from the global $wpdb->queries.
	 */
	public static function capture_slow_queries(): void {
		global $wpdb;
		if ( ! self::$active || empty( $wpdb->queries ) ) {
			return;
		}
		foreach ( $wpdb->queries as $q ) {
			// Formato WP: [query, elapsed, caller] o [query, elapsed, caller, ...]
			$elapsed = isset( $q[1] ) ? (float) $q[1] : 0;
			if ( $elapsed >= 0.02 ) { // 20ms threshold
				$sql                  = isset( $q[0] ) ? preg_replace( '/\s+/', ' ', (string) $q[0] ) : '?';
				$caller               = isset( $q[2] ) ? (string) $q[2] : '';
				self::$slow_queries[] = array(
					'sql'    => substr( $sql, 0, 120 ),
					'ms'     => round( $elapsed * 1000, 1 ),
					'caller' => $caller,
				);
			}
		}
	}

	/**
	 * Render the probe report (shutdown).
	 */
	public static function render(): void {
		if ( ! self::$active ) {
			return;
		}
		global $wpdb;
		$elapsed = microtime( true ) - self::$start;
		$mem     = memory_get_peak_usage( true ) / 1048576;

		// Ordenar hooks por duración.
		arsort( self::$slow_hooks );
		$top_hooks = array_slice( self::$slow_hooks, 0, 8 );

		echo "\n<!-- Convoca Performance Probe -->\n";
		echo '<pre style="background:#1e1e1e;color:#00ff88;padding:12px;font-size:11px;overflow:auto;max-height:400px;direction:ltr;text-align:left">';
		echo "CONVOCA PERFORMANCE PROBE\n";
		printf( "Tiempo total: %.2fs | Memoria pico: %.1f MB | Queries: %d\n", $elapsed, $mem, (int) ( $wpdb->num_queries ?? 0 ) );
		echo "\n-- HOOKS LENTOS (>" . ( self::$hook_threshold * 1000 ) . "ms) --\n";
		if ( empty( $top_hooks ) ) {
			echo "  (ninguno)\n";
		}
		foreach ( $top_hooks as $hook => $dur ) {
			printf( "  %.3fs  %s\n", $dur, $hook );
		}
		echo "\n-- QUERIES LENTAS (>20ms) --\n";
		if ( empty( self::$slow_queries ) ) {
			echo "  (ninguna)\n";
		}
		foreach ( array_slice( self::$slow_queries, 0, 10 ) as $q ) {
			printf( "  %.1fms  %s  [%s]\n", $q['ms'], $q['sql'], $q['caller'] );
		}
		echo "</pre>\n";
		echo "<!-- /Convoca Performance Probe -->\n";
	}

	/**
	 * Hook stack (per-hook timers).
	 *
	 * @var array
	 */
	private static $hook_stack = array();
}
