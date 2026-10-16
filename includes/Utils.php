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
	 * Crea un pago a través de la pasarela y devuelve sus datos (issue convoca-core#16).
	 *
	 * Antes se llamaba `get_payment_link()`, el mismo nombre que `Gateway\Payment_Handler::get_payment_link()`
	 * (hoy `build_payment_url()`), que hace lo contrario: **construye la URL** de un pago que ya existe. Este
	 * nombre no dice lo que hace y encima invita a confundirlas: aquí se CREA el pago.
	 *
	 * @param array $args Payment details.
	 * @return array|\WP_Error Result with URL.
	 */
	public static function create_payment( array $args ): array|\WP_Error {
		if ( function_exists( '\Convoca\Gateway\convoca_gateway_create_payment' ) ) {
			return \Convoca\Gateway\convoca_gateway_create_payment( $args );
		}
		return new \WP_Error( 'gateway_no_activo', __( 'La pasarela de pago no está activa.', 'convoca-core' ) );
	}

	/**
	 * Alias de compatibilidad de `create_payment()` (issue convoca-core#16).
	 *
	 * Es API publicada en versiones anteriores del núcleo, así que el nombre viejo no desaparece: se queda
	 * delegando. Lo que cambia es el nombre que usa el código nuevo.
	 *
	 * @deprecated 2.3.33 Usa `create_payment()`.
	 *
	 * @param array $args Payment details.
	 * @return array|\WP_Error Result with URL.
	 */
	public static function get_payment_link( array $args ): array|\WP_Error {
		return self::create_payment( $args );
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
	 * `0` y `'0'` NO son celdas vacías: con el `empty()` que había antes, un cero
	 * legítimo (horas, plazas, importe pendiente) salía como celda en blanco al
	 * pasar por este ayudante, y quien lo lee se queda sin el dato. Medido el
	 * 15/10/2026 en la exportación de miembros (`Horas voluntariado`) y en la de
	 * estadísticas de turnos (`Faltas`, `Realizados`).
	 *
	 * @param string|mixed $field Field value.
	 * @return string Escaped field value.
	 */
	public static function escape_csv_field( $field ): string {
		$field = (string) $field;
		if ( '' === $field ) {
			return '';
		}

		$dangerous_chars = array( '=', '+', '-', '@', "\t", "\r", ';' );
		if ( preg_match( '/^[=+\-@\t\r;]/', $field ) ) {
			return '"' . str_replace( '"', '""', $field ) . '"';
		}

		return $field;
	}

	/**
	 * Vuelca un CSV al navegador: cabeceras, BOM y filas, todo en UN solo flujo.
	 *
	 * Por qué no vale `csv_headers()`: abría el flujo, escribía el BOM y lo cerraba, así que quien
	 * siguiera escribiendo se quedaba sin BOM —medido: la exportación de inscripciones salía sin él y
	 * Excel rompía los acentos—. Aquí el flujo se abre una vez y no se cierra hasta escribir todo.
	 *
	 * Cada celda pasa por `escape_csv_field()`, así que ningún exportador puede olvidarse de la
	 * protección contra fórmulas: antes estaba copiada a mano en unos y ausente en otros (medido el
	 * 15/10/2026: la pasarela tenía una versión más corta que no cubría tabulador, retorno ni `;`).
	 *
	 * Separador `;` por defecto, que es el que espera el ecosistema, y una sola cabecera de contenido.
	 * El separador es un parámetro porque hay una descarga cuyo lector manda: la plantilla de
	 * importación de socios se vuelve a subir y su lector (`fgetcsv(…, ',')`) está en el mismo
	 * plugin, así que ahí el flujo tiene que salir con `,` o el fichero descargado deja de
	 * importarse. La ceremonia compartida sigue siendo la misma; lo que cambia es el carácter.
	 *
	 * @param string $filename Nombre del fichero que verá quien lo descarga (sin ruta).
	 * @param array<int, string> $headers Fila de cabeceras, ya traducida por quien llama.
	 * @param iterable<int, array<int, mixed>> $rows Filas. Admite generador para no cargar todo en memoria.
	 * @param string $separator Separador de campos. `;` salvo para lectores que exigen otro.
	 * @return int Filas escritas, sin contar la cabecera.
	 */
	public static function stream_csv( string $filename, array $headers, iterable $rows, string $separator = ';' ): int {
		if ( ob_get_length() ) {
			ob_end_clean();
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- CSV a php://output: no hay equivalente en WP_Filesystem para un flujo de salida.
		$out = fopen( 'php://output', 'w' );

		// BOM: sin él, Excel en Windows se come los acentos del castellano.
		fprintf( $out, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

		$escribir_fila = static function ( array $fila ) use ( $out, $separator ): void {
			fputcsv(
				$out,
				array_map(
					static function ( $celda ): string {
						return self::escape_csv_field( (string) $celda );
					},
					$fila
				),
				$separator,
				'"',
				'\\'
			);
		};

		$escribir_fila( $headers );

		$escritas = 0;
		foreach ( $rows as $fila ) {
			$escribir_fila( $fila );
			++$escritas;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Cerramos el flujo de salida del CSV, ya completo.
		fclose( $out );

		return $escritas;
	}

	/**
	 * Cabeceras de un CSV. Obsoleta: abría y cerraba el flujo, así que el BOM se perdía.
	 *
	 * @deprecated 2.3.34 Usa `stream_csv()`, que hace el trabajo entero sin cortar el flujo.
	 *
	 * @param string $filename Nombre del fichero.
	 */
	public static function csv_headers( string $filename ): void {
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$out = fopen( 'php://output', 'w' );
		// BOM for Excel.
		fprintf( $out, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- closing php://output stream after BOM write, no WP_Filesystem equivalent for CSV streams
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
		$fired          = self::get_fired();
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
	/**
	 * Comprueba el límite de intentos de una acción, **por persona** y por conexión.
	 *
	 * DEFECTO QUE ARREGLA (members#46, medido el 16/10/2026): el contador era SOLO por dirección IP, así que
	 * varios errores de UNA persona dejaban fuera a todas las demás de su misma conexión durante una hora —
	 * una sede con una sola línea, un telecentro, o una familia entrando por el mismo router. Y el siguiente
	 * que lo intentaba leía «Demasiados intentos», sin saber que no era culpa suya.
	 *
	 * Ahora:
	 *
	 *  1. Si se sabe **quién** lo intenta (el correo del alta, el usuario del acceso), el contador es SUYO:
	 *     agotarlo no arrastra a nadie más.
	 *  2. Y se cuenta también **por conexión**, pero con un tope mucho más alto: sirve para frenar a un
	 *     abusón que va cambiando de identidad, sin cerrarle la puerta a una sede entera por los errores de
	 *     una persona. El tope de conexión es el mayor entre `5 × max` y `max + 20`.
	 *
	 * @param string $action   Acción (p. ej. 'alta_socio').
	 * @param int    $max      Intentos permitidos por persona y ventana.
	 * @param int    $window   Ventana en segundos.
	 * @param string $identity Quien lo intenta (correo o usuario). Vacío = solo se mira la conexión.
	 * @return bool true si puede seguir; false si está bloqueado.
	 */
	public static function check_rate_limit( string $action, int $max = 10, int $window = 300, string $identity = '' ): bool {
		$ip = (string) ( $_SERVER['REMOTE_ADDR'] ?? 'unknown' );

		if ( '' !== trim( $identity ) ) {
			$quien = strtolower( trim( $identity ) );
			if ( ! self::contar_intento( 'convoca_rl_' . $action . '_id_' . md5( $quien ), $max, $window, $quien, 'persona' ) ) {
				return false;
			}
		}

		$tope_conexion = max( $max * 5, $max + 20 );

		return self::contar_intento(
			'convoca_rl_' . $action . '_ip_' . md5( $ip ),
			$tope_conexion,
			$window,
			$ip,
			'conexion'
		);
	}

	/**
	 * Cuenta un intento y dice si sigue permitido. Guarda el contador en una opción con la caducidad
	 * empaquetada en los bits altos, como antes; no se toca la mecánica, solo la clave y el tope.
	 *
	 * @param string $option_name Nombre del contador.
	 * @param int    $max         Tope.
	 * @param int    $window      Ventana en segundos.
	 * @param string $quien       Quién es (correo o IP): es lo que ve quien administra.
	 * @param string $tipo        'persona' o 'conexion'.
	 * @return bool true si puede seguir.
	 */
	private static function contar_intento( string $option_name, int $max, int $window, string $quien, string $tipo ): bool {
		// Object cache fast-path: only a 0 (blocked) result short-circuits.
		// A 1 (allowed) MUST fall through to the atomic INSERT so the DB
		// counter keeps accumulating within the same request/window.
		$cached = wp_cache_get( $option_name, 'convoca_rate_limits' );
		if ( 0 === $cached ) {
			return false;
		}

		global $wpdb;
		$now     = time();
		$expires = $now + $window;

		try {
			// Atomic INSERT ON DUPLICATE KEY UPDATE — elimina la carrera SELECT+INSERT.
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

			if ( false === $result ) {
				return true; // Fail open.
			}

			$current = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
					$option_name
				)
			);

			$stored_expires = $current >> 20;
			$attempts       = $current & 0xFFFFF;

			if ( $stored_expires > $now && $attempts > $max ) {
				wp_cache_set( $option_name, 0, 'convoca_rate_limits', $stored_expires - $now );
				self::guardar_etiqueta_bloqueo( $option_name, $quien, $tipo, $stored_expires );
				Logger::warning( 'Límite de intentos superado (' . $tipo . ' ' . self::etiqueta_privada( $quien ) . ') hasta ' . gmdate( 'Y-m-d H:i:s', $stored_expires ), 'Common/Security' );

				return false;
			}

			wp_cache_set( $option_name, 1, 'convoca_rate_limits', max( 60, $stored_expires - $now ) );

			return true;
		} catch ( \Exception $e ) {
			Logger::error( 'Rate limit check failed: ' . $e->getMessage(), 'Common/Security' );

			return true;
		}
	}

	/**
	 * Guarda aparte de quién es el contador, para que quien administra pueda verlo.
	 *
	 * El nombre de la opción lleva un `md5()`, así que de ahí no se puede sacar ni la IP ni el correo: sin
	 * esta etiqueta, el panel podría decir «hay un bloqueo» pero no de quién (members#46, punto 3).
	 *
	 * @param string $option_name Nombre del contador.
	 * @param string $quien       Quién es (correo o IP), en texto llano: es lo que ve quien administra.
	 * @param string $tipo        'persona' o 'conexion'.
	 * @param int    $expires     Caducidad (timestamp).
	 */
	private static function guardar_etiqueta_bloqueo( string $option_name, string $quien, string $tipo, int $expires ): void {
		update_option(
			self::etiqueta_opcion( $option_name ),
			array(
				'etiqueta' => $quien,
				'tipo'     => $tipo,
				'expira'   => $expires,
			),
			false
		);
	}

	/**
	 * Nombre de la opción donde vive la etiqueta de un contador.
	 *
	 * @param string $option_name Nombre del contador.
	 * @return string
	 */
	private static function etiqueta_opcion( string $option_name ): string {
		return str_replace( 'convoca_rl_', 'convoca_rl_quien_', $option_name );
	}

	/**
	 * Pinta una identidad sin enseñarla entera (para los registros): «p***@dominio» o «1.2.3.x».
	 *
	 * @param string $valor IP o correo.
	 * @return string
	 */
	private static function etiqueta_privada( string $valor ): string {
		if ( false !== strpos( $valor, '@' ) ) {
			list( $usuario, $dominio ) = explode( '@', $valor, 2 );

			return substr( $usuario, 0, 1 ) . '***@' . $dominio;
		}

		$partes = explode( '.', $valor );
		if ( count( $partes ) === 4 ) {
			return $partes[0] . '.' . $partes[1] . '.' . $partes[2] . '.x';
		}

		return '(conexión)';
	}

	/**
	 * Los intentos bloqueados ahora mismo, para poder verlos y levantarlos desde el panel (members#46).
	 *
	 * @param string $action Acción concreta ('' = todas).
	 * @return array<int, array{clave:string,accion:string,quien:string,intentos:int,bloqueado_hasta:int,es_persona:bool}>
	 */
	public static function rate_limit_blocks( string $action = '' ): array {
		global $wpdb;
		$now  = time();
		$like = 'convoca_rl_' . ( '' !== $action ? $action . '_' : '' ) . '%';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$filas = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options}
				 WHERE option_name LIKE %s AND option_name NOT LIKE %s",
				$like,
				'convoca_rl_quien_%'
			)
		);
		// phpcs:enable

		$bloqueos = array();
		foreach ( (array) $filas as $fila ) {
			$valor    = (int) $fila->option_value;
			$expira   = $valor >> 20;
			$intentos = $valor & 0xFFFFF;

			if ( $expira <= $now ) {
				// Caducado: se limpia al pasar, para que el listado no arrastre basura.
				self::rate_limit_clear( (string) $fila->option_name );
				continue;
			}

			$etiqueta = get_option( self::etiqueta_opcion( (string) $fila->option_name ), array() );
			if ( empty( $etiqueta['etiqueta'] ) ) {
				// Sin etiqueta NO es un bloqueo: es un contador que va por debajo del tope.
				// Enseñarlo era ruido —y mentira: parecían dieciséis personas cortadas cuando no había ninguna—.
				continue;
			}

			$nombre      = (string) $fila->option_name;
			$es_persona  = ( false !== strpos( $nombre, '_id_' ) );
			$sin_prefijo = preg_replace( '/^convoca_rl_/', '', $nombre );
			$sin_hash    = preg_replace( '/_(id|ip)_[a-f0-9]{32}$/', '', (string) $sin_prefijo );

			$bloqueos[] = array(
				'clave'           => $nombre,
				'accion'          => (string) $sin_hash,
				'quien'           => (string) $etiqueta['etiqueta'],
				'intentos'        => $intentos,
				'bloqueado_hasta' => $expira,
				'es_persona'      => $es_persona,
			);
		}

		usort(
			$bloqueos,
			static function ( $a, $b ) {
				return $b['bloqueado_hasta'] <=> $a['bloqueado_hasta'];
			}
		);

		return $bloqueos;
	}

	/**
	 * Levanta un bloqueo sin tocar la base de datos a mano (members#46).
	 *
	 * @param string $clave Nombre del contador (lo que devuelve `rate_limit_blocks()`).
	 * @return bool true si había algo que levantar.
	 */
	public static function rate_limit_clear( string $clave ): bool {
		if ( '' === $clave || 0 !== strpos( $clave, 'convoca_rl_' ) ) {
			return false;
		}

		$habia = ( false !== get_option( $clave, false ) );

		delete_option( $clave );
		delete_option( self::etiqueta_opcion( $clave ) );
		wp_cache_delete( $clave, 'convoca_rate_limits' );

		return $habia;
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
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $locks_table is a hardcoded internal name, not user input
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$locks_table'" ) === $locks_table;

		if ( $table_exists ) {
			// Clean expired locks first.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $locks_table is a hardcoded internal name
			$wpdb->query( $wpdb->prepare( "DELETE FROM $locks_table WHERE expires < %d", time() ) );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $locks_table is a hardcoded internal name
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
					time(),
					$expires
				)
			);

			if ( false === $result ) {
				// Error real de BD (query falló): no es contención.
				return false;
			}

			if ( 0 === $result ) {
				// 0 filas afectadas: el UPDATE condicional no cambió nada
				// porque otro proceso retiene el lock (expires futuro).
				self::report_lock_contention( $key );
				return false;
			}

			// Verify we got the lock.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $locks_table is a hardcoded internal name
			$current = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT expires FROM $locks_table WHERE lock_key = %s",
					$lock_key
				)
			);

			if ( (int) $current !== $expires ) {
				// Contención real: otro proceso retiene el lock (no caducó).
				self::report_lock_contention( $key );
				return false;
			}

			return true;
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

		if ( false === $result ) {
			// Error real de BD: no es contención.
			return false;
		}

		if ( 0 === $result ) {
			// 0 filas afectadas: lock retenido por otro proceso.
			self::report_lock_contention( $key );
			return false;
		}

		$current = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM $wpdb->options WHERE option_name = %s",
				$lock_key
			)
		);

		if ( (int) $current !== $expires ) {
			self::report_lock_contention( $key );
			return false;
		}

		return true;
	}

	/**
	 * Report a real lock contention to the Security Monitor (observabilidad).
	 * The logger has internal rate limiting (50/min) so bursts cannot flood.
	 *
	 * @param string $key Lock identifier (without the convoca_lock_ prefix).
	 */
	private static function report_lock_contention( string $key ): void {
		if ( class_exists( '\Convoca\Core\Security_Monitor' ) ) {
			Security_Monitor::lock_contended( $key );
		}
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
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $locks_table is a hardcoded internal name
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$locks_table'" ) === $locks_table;

		if ( $table_exists ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $locks_table is a hardcoded internal name
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
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $locks_table is a hardcoded internal name
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$locks_table'" ) === $locks_table;

		if ( $table_exists ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $locks_table is a hardcoded internal name
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

		// NO hay fallback a logo interno del plugin: el branding debe ser siempre
		// el del sitio WordPress (custom_logo) o, si no existe, el nombre del sitio
		// (lo decide get_branding_html). Evita logos de otras organizaciones.

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
	 * Document theme used across PDFs, cards and transactional emails.
	 *
	 * Option: 'convoca_document_theme' — 'light' (default) or 'dark'.
	 * Also filterable per-context: convoca_document_theme_{suffix}.
	 *
	 * @param string $suffix Optional context suffix (card|certificate|email).
	 * @return string 'light'|'dark'
	 */
	public static function get_document_theme( string $suffix = '' ): string {
		$theme = \get_option( 'convoca_document_theme', 'light' );
		if ( ! in_array( $theme, array( 'light', 'dark' ), true ) ) {
			$theme = 'light';
		}
		if ( $suffix ) {
			$theme = (string) apply_filters( 'convoca_document_theme_' . $suffix, $theme );
		}
		$theme = (string) apply_filters( 'convoca_document_theme', $theme );
		return in_array( $theme, array( 'light', 'dark' ), true ) ? $theme : 'light';
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
			update_option( 'convoca_persistent_salt', $salt, false );
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
			$text          = __( 'Se han detectado problemas críticos que impiden el funcionamiento.', 'convoca-core' );
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
							<?php if ( ! empty( $check['fix_url'] ) ) : ?>
								<span class="convoca-diagnostic-fix">💡 <?php echo esc_html( $check['fix'] ); ?>
									<a class="button button-small convoca-diagnostic-fix-btn" href="<?php echo esc_url( $check['fix_url'] ); ?>"><?php esc_html_e( 'Reparar', 'convoca-core' ); ?> →</a>
								</span>
							<?php else : ?>
								<span class="convoca-diagnostic-fix">💡 <?php echo esc_html( $check['fix'] ); ?></span>
							<?php endif; ?>
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
			'turnos'  => '\\Convoca\\Shifts\\Convoca_Shifts_Upgrade_Manager',
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
		return (string) $data['Version'];
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
