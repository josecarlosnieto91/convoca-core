<?php

/**
 * Convoca Core
 *
 * @package    Convoca\Core
 * @subpackage Includes
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 */

/**
 * Registro de intentos de los formularios públicos.
 *
 * @package Convoca\Core
 */

namespace Convoca\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deja rastro de cada intento, también de los rechazados.
 *
 * Antes solo quedaba lo que llegaba a ser socio o inscripción: de quien lo intentaba y no podía no se sabía
 * nada, y los bloqueos por fuerza bruta no dejaban más huella que una línea de log que se rota. Aquí va una
 * fila por intento —aceptado, rechazado, bloqueado o detectado como bot— sin datos personales en claro: del
 * correo y de la IP solo se guarda un HMAC.
 *
 * @package Convoca\Core
 */
final class Intentos {

	/**
	 * Versión del esquema. Al cambiar, la tabla se vuelve a crear sola en el siguiente intento.
	 */
	public const DB_VERSION = '1';

	/**
	 * Días que se guardan los intentos.
	 */
	public const RETENCION_DIAS = 90;

	/**
	 * Nombre de la tabla.
	 *
	 * @return string
	 */
	public static function tabla(): string {
		global $wpdb;

		return $wpdb->prefix . 'convoca_intentos';
	}

	/**
	 * Huella del correo: no se guarda el correo, solo un HMAC con la sal del sitio.
	 *
	 * @param string $email Correo.
	 * @return string
	 */
	public static function identidad( string $email ): string {
		$email = strtolower( trim( $email ) );
		if ( '' === $email ) {
			return '';
		}

		return substr( hash_hmac( 'sha256', $email, wp_salt( 'auth' ) ), 0, 32 );
	}

	/**
	 * Huella de la IP.
	 *
	 * @param string $ip Dirección.
	 * @return string
	 */
	public static function huella_ip( string $ip ): string {
		$ip = trim( $ip );
		if ( '' === $ip ) {
			return '';
		}

		return substr( hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) ), 0, 32 );
	}

	/**
	 * Anota un intento.
	 *
	 * @param string               $tipo      alta | inscripcion | login.
	 * @param string               $resultado ok | validacion | duplicado | bloqueo | bot.
	 * @param array<string, mixed> $args      email, ip, referencia, motivo.
	 * @return int Id de la fila (0 si no se pudo escribir).
	 */
	public static function registrar( string $tipo, string $resultado, array $args = array() ): int {
		global $wpdb;

		self::asegurar_tabla();

		try {
			$insertado = $wpdb->insert(
				self::tabla(),
				array(
					'creado'     => current_time( 'mysql' ),
					'tipo'       => substr( sanitize_key( $tipo ), 0, 30 ),
					'resultado'  => substr( sanitize_key( $resultado ), 0, 30 ),
					'identidad'  => self::identidad( (string) ( $args['email'] ?? '' ) ),
					'ip_hash'    => self::huella_ip( (string) ( $args['ip'] ?? ( $_SERVER['REMOTE_ADDR'] ?? '' ) ) ),
					'referencia' => (int) ( $args['referencia'] ?? 0 ),
					'motivo'     => substr( sanitize_text_field( (string) ( $args['motivo'] ?? '' ) ), 0, 191 ),
				),
				array( '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
			);
		} catch ( \Throwable $e ) {
			// Un fallo al anotar no puede tumbar el formulario: se pierde la traza, no el alta.
			return 0;
		}

		return $insertado ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Engancha la purga diaria.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'convoca_intentos_purgar', array( __CLASS__, 'purgar' ) );

		if ( ! wp_next_scheduled( 'convoca_intentos_purgar' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'convoca_intentos_purgar' );
		}
	}

	/**
	 * Crea la tabla si hace falta. Idempotente y sin pasar por la activación: un sitio ya activo no vuelve
	 * a activarse nunca.
	 *
	 * @return void
	 */
	public static function asegurar_tabla(): void {
		if ( self::DB_VERSION === get_option( 'convoca_intentos_db_version' ) ) {
			return;
		}

		global $wpdb;
		$tabla           = self::tabla();
		$charset_collate = $wpdb->get_charset_collate();

		// En el arnés de pruebas no existe wp-admin: sin `dbDelta` no se puede crear la tabla y tampoco
		// hace falta (las pruebas no escriben en base de datos).
		if ( ! function_exists( 'dbDelta' ) ) {
			$ruta = ABSPATH . 'wp-admin/includes/upgrade.php';
			if ( ! is_file( $ruta ) ) {
				return;
			}
			// @phpstan-ignore-next-line
			require_once $ruta;
		}

		$sql = "CREATE TABLE $tabla (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			creado datetime NOT NULL,
			tipo varchar(30) NOT NULL DEFAULT '',
			resultado varchar(30) NOT NULL DEFAULT '',
			identidad char(32) NOT NULL DEFAULT '',
			ip_hash char(32) NOT NULL DEFAULT '',
			referencia bigint(20) unsigned NOT NULL DEFAULT 0,
			motivo varchar(191) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY tipo_creado (tipo, creado),
			KEY resultado_creado (resultado, creado),
			KEY identidad (identidad)
		) $charset_collate;";

		// @phpstan-ignore-next-line
		dbDelta( $sql );

		update_option( 'convoca_intentos_db_version', self::DB_VERSION, false );
	}

	/**
	 * Borra los intentos más viejos que la retención.
	 *
	 * @param int $dias Días a conservar.
	 * @return int Filas borradas.
	 */
	public static function purgar( int $dias = self::RETENCION_DIAS ): int {
		global $wpdb;

		$dias   = max( 1, $dias );
		$limite = gmdate( 'Y-m-d H:i:s', time() - ( $dias * DAY_IN_SECONDS ) );

		// @phpstan-ignore-next-line
		return (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::tabla() . ' WHERE creado < %s', $limite ) );
	}

	/**
	 * Cuenta intentos de un tipo en las últimas horas.
	 *
	 * @param string $tipo      Tipo.
	 * @param string $resultado Resultado (vacío = cualquiera).
	 * @param int    $horas     Ventana.
	 * @return int
	 */
	public static function contar( string $tipo, string $resultado = '', int $horas = 24 ): int {
		global $wpdb;

		$desde = gmdate( 'Y-m-d H:i:s', time() - ( max( 1, $horas ) * HOUR_IN_SECONDS ) );
		$tabla = self::tabla();

		if ( '' === $resultado ) {
			// @phpstan-ignore-next-line
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $tabla WHERE tipo = %s AND creado >= %s", $tipo, $desde ) );
		}

		// @phpstan-ignore-next-line
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $tabla WHERE tipo = %s AND resultado = %s AND creado >= %s", $tipo, $resultado, $desde ) );
	}
}
