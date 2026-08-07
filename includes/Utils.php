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
 * Shared utility functions across all plugins.
 *
 * @package Convoca\Core
 */

namespace Convoca\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Utils {

	/* ── Centralized Capability Definitions ── */
	const CAP_MANAGE_INSCRIPCIONES        = 'manage_inscripciones';
	const CAP_GESTIONAR_MIEMBROS          = 'gestionar_miembros';
	const CAP_GESTIONAR_DOCS_VOLUNTARIADO = 'gestionar_documentos_voluntariado';
	const CAP_VIEW_REPORTS                = 'view_reports';
	const CAP_MANAGE_TEMPLATES            = 'manage_convoca_templates';
	const CAP_MANAGE_LOGS                 = 'manage_convoca_logs';
	const CAP_MANAGE_GATEWAY              = 'manage_convoca_gateway';
	const CAP_GESTIONAR_MIS_TURNOS        = 'gestionar_mis_turnos';

	/**
	 * Validate Spanish DNI/NIE checksum.
	 * Consolidates validation from members and enroll logic.
	 *
	 * @param string $dni The document ID to validate.
	 * @return bool True if valid, false otherwise.
	 */
	public static function validate_dni( string $dni ): bool {
		// Normalize to uppercase and remove separators. This ensures the regex.
		// below always receives uppercase letters, even if input was lowercase.
		$dni = strtoupper( trim( $dni ) );
		$dni = str_replace( array( ' ', '-' ), '', $dni );

		if ( empty( $dni ) ) {
			return false;
		}

		// Standard regex for DNI (8 numbers + 1 letter) or NIE (X/Y/Z + 7 numbers + 1 letter).
		if ( ! preg_match( '/^([0-9]{8}|[XYZ][0-9]{7})[A-Z]$/', $dni ) ) {
			return false;
		}

		$letra   = substr( $dni, -1 );
		$numeros = substr( $dni, 0, -1 );

		// NIE handling: map prefixes to numbers (X=0, Y=1, Z=2).
		$numeros = str_replace( array( 'X', 'Y', 'Z' ), array( '0', '1', '2' ), $numeros );

		$letras_validas = 'TRWAGMYFPDXBNJZSQVHLCKE';
		$indice         = ( (int) $numeros ) % 23;

		return $letra === $letras_validas[ $indice ];
	}

	/**
	 * Compatibility alias for validate_dni.
	 */
	public static function validar_dni( string $dni ): bool {
		return self::validate_dni( $dni );
	}

	/**
	 * Standard date formatting.
	 *
	 * @param string $date  Raw date string.
	 * @param string $format Target format.
	 * @return string Formatted date.
	 */
	public static function format_date( string $date, string $format = 'd/m/Y' ): string {
		if ( empty( $date ) ) {
			return '';
		}

		try {
			$tz = new \DateTimeZone( wp_timezone_string() );
			$dt = date_create( $date, $tz );

			return $dt ? wp_date( $format, $dt->getTimestamp() ) : $date;
		} catch ( \Throwable $e ) {
			return $date;
		}
	}

	/**
	 * Proxy to create a payment link through Gateway.
	 *
	 * @param array $args Payment details.
	 * @return array|\WP_Error Result with URL.
	 */
	public static function get_payment_link( array $args ): array|\WP_Error {
		if ( function_exists( '\Convoca\Gateway\convoca_gateway_create_payment' ) ) {
			return \Convoca\Gateway\convoca_gateway_create_payment( $args );
		}
		return new \WP_Error( 'gateway_no_activo', __( 'La pasarela de pago no está activa.', 'convoca-core' ) );
	}

	/**
	 * Format name string: trim and capitalize properly.
	 *
	 * @param string $name The original name.
	 * @return string Formatted name.
	 */
	public static function format_nombre( string $name ): string {
		return mb_convert_case( trim( $name ), MB_CASE_TITLE, 'UTF-8' );
	}

	/**
	 * Escape fields starting with dangerous characters to prevent CSV injection.
	 *
	 * @param string|mixed $field Field value.
	 * @return string Escaped field value.
	 */
	public static function escape_csv_field( $field ): string {
		$field = (string) $field;
		if ( empty( $field ) ) {
			return '';
		}

		$dangerous_chars = array( '=', '+', '-', '@', "\t", "\r", ';' );
		if ( preg_match( '/^[=+\-@\t\r;]/', $field ) ) {
			return '"' . str_replace( '"', '""', $field ) . '"';
		}

		return $field;
	}

	/**
	 * Output CSV headers and BOM for Excel.
	 *
	 * @param string $filename The filename to suggest.
	 */
	public static function csv_headers( string $filename ): void {
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$out = fopen( 'php://output', 'w' );
		// BOM for Excel.
		fprintf( $out, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose — closing php://output stream after BOM write, no WP_Filesystem equivalent for CSV streams
		fclose( $out );
	}
	/**
	 * Generate a unique alphanumeric access code.
	 *
	 * @param int $length Code length.
	 * @return string generated code.
	 */
	public static function generate_access_code( int $length = 8 ): string {
		global $wpdb;
		$chars    = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
		$attempts = 0;

		do {
			$code = '';
			for ( $i = 0; $i < $length; $i++ ) {
				try {
					$code .= $chars[ random_int( 0, strlen( $chars ) - 1 ) ];
				} catch ( \Exception $e ) {
					$code .= $chars[ wp_rand( 0, strlen( $chars ) - 1 ) ];
				}
			}

			// Check uniqueness in database.
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT pm.post_id FROM {$wpdb->postmeta} pm 
                 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id 
                 WHERE pm.meta_key = '_convoca_access_code' AND pm.meta_value = %s 
                 AND p.post_type = 'miembro' LIMIT 1",
					$code
				)
			);

			++$attempts;
		} while ( $exists && $attempts < 10 );

		return $code;
	}

	/**
	 * Helper to fire a new hook and maintain its deprecated version.
	 *
	 * @param string $new_hook    The new hook name.
	 * @param string $old_hook    The deprecated hook name.
	 * @param mixed  ...$args     The arguments to pass both hooks.
	 */
	public static function do_action( string $new_hook, string $old_hook = '', ...$args ): void {
		self::record_fired( $new_hook );
		do_action( $new_hook, ...$args );

		if ( ! empty( $old_hook ) && has_action( $old_hook ) ) {
			if ( function_exists( 'do_action_deprecated' ) ) {
				do_action_deprecated( $old_hook, $args, '1.1.0', $new_hook );
			} else {
				do_action( $old_hook, ...$args );
			}
		}
	}

	/**
	 * Record a hook name as "fired" (used by unit tests to assert emissions).
	 */
	private static function record_fired( string $hook ): void {
		$fired = self::get_fired();
		$fired[ $hook ] = true;
		update_option( 'convoca_utils_fired', $fired );
	}

	/**
	 * Get the set of hooks fired via Utils::do_action.
	 *
	 * @return array<string,bool>
	 */
	public static function get_fired(): array {
		$fired = get_option( 'convoca_utils_fired', array() );
		return is_array( $fired ) ? $fired : array();
	}

	/**
	 * Reset the fired-hooks registry (test utility).
	 */
	public static function clear_fired(): void {
		delete_option( 'convoca_utils_fired' );
	}

	/**
	 * Helper to apply a new filter and maintain its deprecated version.
	 *
	 * @param string $new_hook    The new hook name.
	 * @param string $old_hook    The deprecated hook name.
	 * @param mixed  $value       The value to filter.
	 * @param mixed  ...$args     The additional arguments.
	 * @return mixed The filtered value.
	 */
	public static function apply_filters( string $new_hook, string $old_hook = '', $value = null, ...$args ): mixed {
		$value = apply_filters( $new_hook, $value, ...$args );

		if ( ! empty( $old_hook ) && has_filter( $old_hook ) ) {
			if ( function_exists( 'apply_filters_deprecated' ) ) {
				$value = apply_filters_deprecated( $old_hook, array( $value, ...$args ), '1.1.0', $new_hook );
			} else {
				$value = apply_filters( $old_hook, $value, ...$args );
			}
		}

		return $value;
	}

	/**
	 * Check rate limit for an action per IP.
	 * Uses database-backed storage (works without persistent object cache).
	 *
	 * @param string $action  Action name (e.g. 'login', 'inscribir').
	 * @param int    $max     Max attempts allowed.
	 * @param int    $window  Time window in seconds.
	 * @return bool True if within limits, false if exceeded.
	 */
	public static function check_rate_limit( string $action, int $max = 10, int $window = 300 ): bool {
		$ip        = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
		$cache_key = 'convoca_rl_' . $action . '_' . md5( $ip );

		// Object cache fast-path: only a 0 (blocked) result short-circuits.
		// A 1 (allowed) MUST fall through to the atomic INSERT so the DB
		// counter keeps accumulating within the same request/window.
		$cached = wp_cache_get( $cache_key, 'convoca_rate_limits' );
		if ( $cached === 0 ) {
			return false;
		}

		global $wpdb;
		$option_name = $cache_key;
		$now         = time();
		$expires     = $now + $window;

		try {
			// Atomic INSERT ON DUPLICATE KEY UPDATE — eliminates SELECT+INSERT race.
			// Also handles the case where object cache returns false (non-persistent cache).
			$result = $wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) 
                 VALUES (%s, %d, 'no')
                 ON DUPLICATE KEY UPDATE 
                 option_value = CASE
                     WHEN (option_value >> 20) < %d THEN (%d << 20) | 1
                     ELSE option_value + 1
                 END",
					$option_name,
					( $expires << 20 ) | 1,  // High bits = expiry, low bits = count.
					$now,
					$expires
				)
			);

			// If the query failed entirely, allow the request (fail open).
			if ( $result === false ) {
				return true;
			}

			// Read back the packed value.
			$current = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
					$option_name
				)
			);

			$stored_expires = $current >> 20;
			$attempts       = $current & 0xFFFFF;

			// If within window, check limit.
			if ( $stored_expires > $now && $attempts > $max ) {
				wp_cache_set( $cache_key, 0, 'convoca_rate_limits', $stored_expires - $now );
				Logger::warning( "Rate limit exceeded for $action from IP $ip", 'Common/Security' );
				return false;
			}

			wp_cache_set( $cache_key, 1, 'convoca_rate_limits', max( 60, $stored_expires - $now ) );
			return true;
		} catch ( \Exception $e ) {
			Logger::error( 'Rate limit check failed: ' . $e->getMessage(), 'Common/Security' );
			return true;
		}
	}

	/**
	 * Get the dedicated locks table name.
	 */
	private static function locks_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'convoca_locks';
	}

	/**
	 * Acquire an atomic lock using dedicated locks table or wp_options fallback.
	 *
	 * @param string $key     Unique lock identifier.
	 * @param int    $ttl     Time to live in seconds (default 60).
	 * @return bool True if lock acquired, false if already locked.
	 */
	public static function acquire_lock( string $key, int $ttl = 60 ): bool {
		global $wpdb;
		$lock_key = 'convoca_lock_' . $key;
		$expires  = time() + $ttl;

		// Try dedicated locks table first.
		$locks_table = self::locks_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared — $locks_table is a hardcoded internal name, not user input
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$locks_table'" ) === $locks_table;

		if ( $table_exists ) {
			// Clean expired locks first.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared — $locks_table is a hardcoded internal name
			$wpdb->query( $wpdb->prepare( "DELETE FROM $locks_table WHERE expires < %d", time() ) );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared — $locks_table is a hardcoded internal name
			$result = $wpdb->query(
				$wpdb->prepare(
					"INSERT INTO $locks_table (lock_key, expires, created_at)
                 VALUES (%s, %d, NOW())
                 ON DUPLICATE KEY UPDATE
                 expires = CASE
                     WHEN expires < %d THEN %d
                     ELSE expires END",
					$lock_key,
					$expires,
					$expires,
					$expires
				)
			);

			if ( ! $result ) {
				return false;
			}

			// Verify we got the lock.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared — $locks_table is a hardcoded internal name
			$current = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT expires FROM $locks_table WHERE lock_key = %s",
					$lock_key
				)
			);

			return (int) $current === $expires;
		}

		// Fallback: wp_options.
		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO $wpdb->options (option_name, option_value, autoload)
             VALUES (%s, %d, 'no')
             ON DUPLICATE KEY UPDATE
             option_value = CASE
                 WHEN option_value < %d THEN %d
                 ELSE option_value END",
				$lock_key,
				$expires,
				time(),
				$expires
			)
		);

		if ( ! $result ) {
			return false;
		}

		$current = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM $wpdb->options WHERE option_name = %s",
				$lock_key
			)
		);

		return (int) $current === $expires;
	}

	/**
	 * Release a lock.
	 *
	 * @param string $key Lock identifier.
	 * @return bool True if released.
	 */
	public static function release_lock( string $key ): bool {
		global $wpdb;
		$lock_key = 'convoca_lock_' . $key;

		$locks_table = self::locks_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared — $locks_table is a hardcoded internal name
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$locks_table'" ) === $locks_table;

		if ( $table_exists ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared — $locks_table is a hardcoded internal name
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM $locks_table WHERE lock_key = %s",
					$lock_key
				)
			);
		} else {
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM $wpdb->options WHERE option_name = %s",
					$lock_key
				)
			);
		}

		wp_cache_delete( $lock_key, 'convoca_locks' );
		return true;
	}

	/**
	 * Clean up expired locks from both storage backends.
	 */
	public static function cleanup_expired_locks(): int {
		global $wpdb;
		$now   = time();
		$total = 0;

		$locks_table = self::locks_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared — $locks_table is a hardcoded internal name
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$locks_table'" ) === $locks_table;

		if ( $table_exists ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared — $locks_table is a hardcoded internal name
			$total += (int) $wpdb->query( $wpdb->prepare( "DELETE FROM $locks_table WHERE expires < %d", $now ) );
		}

		$total += (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $wpdb->options WHERE option_name LIKE %s AND CAST(option_value AS SIGNED) < %d",
				'convoca_lock_%',
				$now
			)
		);

		return $total;
	}

	/**
	 * Get the logo URL for branding (emails, PDFs).
	 *
	 * @param string $filter_suffix Suffix for the filter name.
	 * @return string Logo URL or empty string.
	 */
	public static function get_logo_url( string $filter_suffix = 'common' ): string {
		$logo_id  = get_theme_mod( 'custom_logo' );
		$logo_url = '';

		if ( $logo_id ) {
			$logo_src = wp_get_attachment_image_src( $logo_id, 'full' );
			if ( $logo_src ) {
				$logo_url = $logo_src[0];
			}
		}

		// Fallback to internal logo if it exists.
		if ( empty( $logo_url ) ) {
			$logo_path = CONVOCA_COMMON_DIR . 'assets/images/logo.png';
			if ( file_exists( $logo_path ) ) {
				$logo_url = CONVOCA_COMMON_URL . 'assets/images/logo.png';
			}
		}

		return (string) apply_filters( "convoca_{$filter_suffix}_logo_url", $logo_url );
	}

	/**
	 * Get branding HTML (logo or site name).
	 *
	 * @param string $filter_suffix Suffix for the filter name.
	 * @param string $css_class Optional CSS class for the logo.
	 * @param string $style Optional style for the fallback text.
	 * @return string HTML content.
	 */
	public static function get_branding_html( string $filter_suffix = 'common', string $css_class = '', string $style = 'color:#ffffff;margin:0;font-size:24px;' ): string {
		$logo_url  = self::get_logo_url( $filter_suffix );
		$site_name = get_bloginfo( 'name' );

		if ( ! empty( $logo_url ) ) {
			$class_attr = $css_class ? ' class="' . esc_attr( $css_class ) . '"' : '';
			$style_attr = $style ? ' style="' . esc_attr( $style ) . '"' : '';
			return '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( $site_name ) . '"' . $class_attr . $style_attr . '>';
		}

		return '<h1 style="' . esc_attr( $style ) . '">' . esc_html( $site_name ) . '</h1>';
	}

	/**
	 * Get a persistent salt for the Convoca ecosystem.
	 * Unlike wp_salt(), this is stored in the database and remains consistent
	 * even if WordPress salts are regenerated.
	 *
	 * @return string
	 */
	public static function get_persistent_salt(): string {
		$salt = get_option( 'convoca_persistent_salt' );
		if ( ! $salt ) {
			$salt = wp_generate_password( 64, true, true );
			update_option( 'convoca_persistent_salt', $salt, 'no' );
		}
		return $salt;
	}

	/**
	 * Render a diagnostic status panel.
	 *
	 * @param array  $checks Array of check items with keys: title, status (ok|warning|error), message, fix (optional).
	 * @param string $title Optional title override.
	 */
	public static function render_diagnostic_panel( array $checks, string $title = '' ): void {
		$has_errors   = array_filter( $checks, fn( $c ) => $c['status'] === 'error' );
		$has_warnings = array_filter( $checks, fn( $c ) => $c['status'] === 'warning' );

		if ( $has_errors ) {
			$icon          = '✗';
			$level         = 'error';
			$text          = __( 'Se han detectado problemas críticos que impediment el funcionamiento.', 'convoca-core' );
			$default_title = __( 'Estado: Errores detectados', 'convoca-core' );
		} elseif ( $has_warnings ) {
			$icon          = '⚠';
			$level         = 'warning';
			$text          = __( 'Hay advertencias que podrían afectar el funcionamiento.', 'convoca-core' );
			$default_title = __( 'Estado: Advertencias', 'convoca-core' );
		} else {
			$icon          = '✓';
			$level         = 'success';
			$text          = __( 'Todos los componentes están configurados correctamente.', 'convoca-core' );
			$default_title = __( 'Estado: Todo correcto', 'convoca-core' );
		}

		$title = $title ?: $default_title;
		?>
		<div class="convoca-diagnostic">
			<div class="convoca-diagnostic-header convoca-diagnostic-header--<?php echo esc_attr( $level ); ?>">
				<div class="convoca-diagnostic-icon convoca-badge convoca-badge--<?php echo esc_attr( $level ); ?>">
					<?php echo esc_html( $icon ); ?>
				</div>
				<div class="convoca-diagnostic-summary">
					<h3><?php echo esc_html( $title ); ?></h3>
					<p><?php echo esc_html( $text ); ?></p>
				</div>
			</div>

			<div class="convoca-diagnostic-results">
				<?php foreach ( $checks as $check ) : ?>
				<div class="convoca-diagnostic-row">
					<span class="convoca-diagnostic-severity convoca-badge convoca-badge--<?php echo esc_attr( $check['status'] === 'error' ? 'error' : ( $check['status'] === 'warning' ? 'warning' : 'success' ) ); ?>">
						<?php echo $check['status'] === 'error' ? '✗' : ( $check['status'] === 'warning' ? '⚠' : '✓' ); ?>
					</span>
					<div class="convoca-diagnostic-content">
						<strong><?php echo esc_html( $check['title'] ); ?></strong>
						<span class="convoca-diagnostic-message"><?php echo esc_html( $check['message'] ); ?></span>
						<?php if ( ! empty( $check['fix'] ) ) : ?>
						<span class="convoca-diagnostic-fix">💡 <?php echo esc_html( $check['fix'] ); ?></span>
						<?php endif; ?>
					</div>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Get color for log level.
	 */
	public static function get_log_level_color( string $level ): string {
		switch ( $level ) {
			case 'error':
				return '#d63638';
			case 'warning':
				return '#ffb900';
			case 'info':
				return '#72aee6';
			case 'success':
				return '#00a32a';
			default:
				return '#646970';
		}
	}

	/**
	 * Render log level badge HTML using CSS classes.
	 */
	public static function render_log_level_badge( string $level ): string {
		$css_class = 'convoca-badge';
		switch ( $level ) {
			case 'error':
				$css_class .= ' convoca-badge--error';
				break;
			case 'warning':
				$css_class .= ' convoca-badge--warning';
				break;
			case 'success':
				$css_class .= ' convoca-badge--success';
				break;
			case 'info':
			default:
				$css_class .= ' convoca-badge--info';
				break;
		}
		return '<span class="' . esc_attr( $css_class ) . '">' . esc_html( ucfirst( $level ) ) . '</span>';
	}

	/**
	 * Render a convoca-alert notice.
	 *
	 * @param string $message The message to display.
	 * @param string $type    success|danger|warning|info.
	 */
	public static function admin_notice( string $message, string $type = 'success' ): void {
		echo '<div class="convoca-alert convoca-alert--' . esc_attr( $type ) . '" role="alert" style="display:block;margin-bottom:20px;"><p>' . wp_kses_post( $message ) . '</p></div>';
	}

	/**
	 * Store a transient notice for the current user (to be shown on next page load).
	 *
	 * @param string $message The message text.
	 * @param string $type    success|danger|warning|info.
	 */
	public static function set_admin_notice( string $message, string $type = 'success' ): void {
		$notices   = get_transient( 'convoca_notice_' . get_current_user_id() ) ?: array();
		$notices[] = array(
			'message' => $message,
			'type'    => $type,
		);
		set_transient( 'convoca_notice_' . get_current_user_id(), $notices, 60 );
	}

	/**
	 * Render all stored notices for the current user and clear them.
	 */
	public static function render_stored_notices(): void {
		$notices = get_transient( 'convoca_notice_' . get_current_user_id() );
		if ( ! $notices ) {
			return;
		}
		delete_transient( 'convoca_notice_' . get_current_user_id() );
		foreach ( $notices as $notice ) {
			self::admin_notice( $notice['message'], $notice['type'] );
		}
	}

	/**
	 * Safe wrapper for is_plugin_active() that ensures plugin.php is loaded.
	 */
	public static function is_plugin_active_safe( string $plugin ): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active( $plugin );
	}

	/**
	 * Get a PDF template by key from the centralized option.
	 * All plugins should use this instead of get_option('convoca_pdf_templates') directly.
	 *
	 * @param string $key Template key.
	 * @return array|null Template data with 'name' and 'content', or null.
	 */
	public static function get_pdf_template( string $key ): ?array {
		$templates = get_option( 'convoca_pdf_templates', array() );
		return $templates[ $key ] ?? null;
	}

	/**
	 * Get all PDF templates.
	 *
	 * @return array
	 */
	public static function get_pdf_templates(): array {
		return get_option( 'convoca_pdf_templates', array() );
	}

	/**
	 * Feature registry: check if a plugin feature is available without hardcoding class names.
	 *
	 * @param string $feature Feature key: 'members', 'enroll', 'gateway', 'turnos '
	 * @return bool
	 */
	public static function has_feature( string $feature ): bool {
		$map   = array(
			'members' => '\\Convoca\\Members\\CPT_Miembro',
			'enroll'  => '\\Convoca\\Enroll\\CPT_Inscripcion',
			'gateway' => '\\Convoca\\Gateway\\CPT_Pago',
			'turnos'  => '\\Convoca\\Gateway\\Redsys_Client', // Downs: Gateway has Redsys.
		);
		$class = $map[ $feature ] ?? '';
		return $class && class_exists( $class );
	}

	/**
	 * Get plugin version from main plugin file header.
	 */
	public static function get_plugin_version( string $plugin_rel_path ): string {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$full_path = WP_PLUGIN_DIR . '/' . $plugin_rel_path;
		if ( ! file_exists( $full_path ) ) {
			return '';
		}
		$data = get_plugin_data( $full_path );
		return $data['Version'] ?? '';
	}

	/* ── REST Cache ────────────────────────────────── */

	/**
	 * Get cached REST response or compute + cache it.
	 *
	 * @param string   $key    Cache key.
	 * @param int      $ttl    Time-to-live (seconds).
	 * @param callable $callback Function that returns the response data array.
	 * @return array Response data.
	 */
	public static function rest_cache_get( string $key, int $ttl, callable $callback ): array {
		$cache_key = 'convoca_rest_' . md5( $key );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}
		$data = $callback();
		set_transient( $cache_key, $data, $ttl );
		return $data;
	}

	/**
	 * Invalidate a REST cache key.
	 */
	public static function rest_cache_invalidate( string $key ): void {
		delete_transient( 'convoca_rest_' . md5( $key ) );
	}


	/**
	 * Get human-readable label for donation/contribution type.
	 *
	 * @param string $context singular|plural|socio|trasgu|sugerida_socio|sugerida_trasgu
	 * @return string
	 */
	public static function get_aportacion_label( string $context = 'singular' ): string {
		$label = __( 'Aportación', 'convoca-core' );

		switch ( $context ) {
			case 'plural':
				$label = _x( 'Aportaciones', 'plural', 'convoca-core' );
				break;
			case 'socio':
				$label = __( 'Aportación', 'convoca-core' );
				break;
			case 'trasgu':
				$label = _x( 'Aportación Trasgu', 'trasgu', 'convoca-core' );
				break;
			case 'sugerida_socio':
				$label = _x( 'Aportación sugerida para socios', 'socio', 'convoca-core' );
				break;
			case 'sugerida_trasgu':
				$label = _x( 'Aportación sugerida para no socios', 'no-socio', 'convoca-core' );
				break;
		}

		return $label;
	}
}
