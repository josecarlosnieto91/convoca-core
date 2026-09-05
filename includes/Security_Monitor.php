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
 * Security Monitor — observabilidad de eventos críticos.
 *
 * Registra accesos no autorizados a rutas REST de Convoca y agrega un
 * digest diario (WP-Cron) con alertas por email cuando se superan umbrales
 * de: fallos repetidos de firma Redsys (Ds_Signature), bloqueos recurrentes
 * en convoca_locks, rate limits y accesos no autorizados.
 *
 * El digest se envía como MÁXIMO una vez al día (el cron ya es diario),
 * por lo que no puede convertirse en spam.
 *
 * @package Convoca\Core
 */

namespace Convoca\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Security_Monitor {

	const CRON_DIGEST = 'convoca_security_digest';

	/** Umbrales por ventana de 24h (configurables vía filtro). */
	const THRESHOLDS = array(
		'redsys_signature_failures' => 3,
		'lock_contentions'          => 10,
		'rest_unauthorized'         => 20,
		'rate_limit_exceeded'       => 50,
	);

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'watch_rest_response' ), 10, 3 );
		add_action( self::CRON_DIGEST, array( __CLASS__, 'run_digest' ) );
		self::ensure_cron();
	}

	/**
	 * Schedule the daily digest if not already scheduled.
	 */
	private static function ensure_cron(): void {
		if ( ! wp_next_scheduled( self::CRON_DIGEST ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_DIGEST );
		}
	}

	/**
	 * Watch REST responses: log 401/403 on Convoca routes as warnings.
	 * Se usa rest_post_dispatch (no rest_request_after_callbacks) porque este
	 * último NO se invoca cuando la autenticación falla o la ruta no matchea,
	 * y la permission_callback se evalúa DESPUÉS de él — es decir, los 403 de
	 * permisos jamás llegarían al monitor con el hook equivocado.
	 * El logger interno (50/min) evita inundaciones.
	 *
	 * @param mixed                     $response Result to send to the client.
	 * @param \WP_REST_Server            $server   Server instance.
	 * @param \WP_REST_Request           $request  Request used to generate the response.
	 * @return mixed
	 */
	public static function watch_rest_response( $response, $server, $request ) {
		// rest_post_dispatch siempre recibe un WP_REST_Request real.
		$route = (string) $request->get_route();
		// Solo rutas del ecosistema Convoca.
		if ( false === strpos( $route, '/convoca' ) && false === strpos( $route, 'convoca-' ) ) {
			return $response;
		}

		// La respuesta puede ser WP_Error (aún sin convertir) o \WP_REST_Response.
		$status = 0;
		if ( is_wp_error( $response ) ) {
			$data   = $response->get_error_data();
			$status = (int) ( is_array( $data ) ? ( $data['status'] ?? 0 ) : 0 );
		} elseif ( $response instanceof \WP_REST_Response ) {
			$status = (int) $response->get_status();
		}

		if ( 401 !== $status && 403 !== $status ) {
			return $response;
		}

		$code = is_wp_error( $response ) ? $response->get_error_code() : 'http_' . $status;
		$ip   = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) );

		Logger::warning(
			sprintf( 'Acceso REST no autorizado (%s) a %s desde %s', $code, $route, $ip ),
			'Common/Security'
		);

		return $response;
	}

	/**
	 * Register a lock contention observation.
	 * Called by Utils::acquire_lock() when a lock cannot be acquired.
	 */
	public static function lock_contended( string $key ): void {
		Logger::warning( 'Lock no adquirido (contención): ' . $key, 'Core/Lock' );
	}

	/**
	 * Daily digest: count critical events in the last 24h and alert by email.
	 */
	public static function run_digest(): void {
		$thresholds = apply_filters( 'convoca_security_thresholds', self::THRESHOLDS );
		$counts     = self::count_last_24h();
		$alerts     = array();

		if ( ( $counts['redsys_signature_failures'] ?? 0 ) >= (int) $thresholds['redsys_signature_failures'] ) {
			$alerts[] = sprintf(
				'🔴 Fallos de firma Redsys (Ds_Signature): %d en 24h (umbral %d). Posible ataque a /notify o error de configuración.',
				$counts['redsys_signature_failures'],
				(int) $thresholds['redsys_signature_failures']
			);
		}

		if ( ( $counts['lock_contentions'] ?? 0 ) >= (int) $thresholds['lock_contentions'] ) {
			$alerts[] = sprintf(
				'🟠 Bloqueos recurrentes en convoca_locks: %d en 24h (umbral %d). Posible condición de carrera o proceso colgado.',
				$counts['lock_contentions'],
				(int) $thresholds['lock_contentions']
			);
		}

		if ( ( $counts['rest_unauthorized'] ?? 0 ) >= (int) $thresholds['rest_unauthorized'] ) {
			$alerts[] = sprintf(
				'🟠 Accesos no autorizados a rutas REST Convoca: %d en 24h (umbral %d).',
				$counts['rest_unauthorized'],
				(int) $thresholds['rest_unauthorized']
			);
		}

		if ( ( $counts['rate_limit_exceeded'] ?? 0 ) >= (int) $thresholds['rate_limit_exceeded'] ) {
			$alerts[] = sprintf(
				'🟡 Rate limits excedidos: %d en 24h (umbral %d). Posible scraping o bucle de reintentos.',
				$counts['rate_limit_exceeded'],
				(int) $thresholds['rate_limit_exceeded']
			);
		}

		if ( empty( $alerts ) ) {
			return; // Nada que notificar.
		}

		// Cooldown adicional anti-spam: no más de un email por tipo cada 24h.
		$sent_key = 'convoca_security_digest_sent';
		$last     = (int) get_option( $sent_key, 0 );
		if ( time() - $last < DAY_IN_SECONDS ) {
			return;
		}
		update_option( $sent_key, time(), false );

		$site  = get_bloginfo( 'name' );
		$email = get_option( 'admin_email' );

		wp_mail(
			$email,
			sprintf( '[%s] Alerta de seguridad Convoca — %d evento(s) crítico(s)', $site, count( $alerts ) ),
			implode( "\n\n", $alerts ) . "\n\n— Convoca Security Monitor (revisa Convoca → Registro)"
		);

		Logger::warning( 'Digest de seguridad enviado: ' . count( $alerts ) . ' alerta(s).', 'Common/Security' );
	}

	/**
	 * Count security-relevant log entries from the last 24 hours.
	 *
	 * @return array
	 */
	private static function count_last_24h(): array {
		global $wpdb;
		$table = $wpdb->prefix . 'convoca_logs';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return array();
		}

		$since = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );

		$counts = array(
			// Fallos de firma Redsys: errores de contexto Gateway con firma/versión.
			'redsys_signature_failures' => (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table}
					WHERE created_at > %s AND level = 'error'
					AND context IN ('Gateway/Redsys','Gateway/Notification')
					AND (message LIKE %s OR message LIKE %s OR message LIKE %s OR message LIKE %s OR message LIKE %s)",
					$since,
					'%firma%',
					'%Firma%',
					'%versión%',
					'%version%',
					'%IP de origen%'
				)
			),
			// Contención de locks.
			'lock_contentions' => (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE created_at > %s AND context = 'Core/Lock' AND level = 'warning'",
					$since
				)
			),
			// REST no autorizados + rate limits (contexto Common/Security).
			'rest_unauthorized' => (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE created_at > %s AND context = 'Common/Security'
					AND message LIKE %s",
					$since,
					'%Acceso REST no autorizado%'
				)
			),
			'rate_limit_exceeded' => (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE created_at > %s AND context = 'Common/Security'
					AND message LIKE %s",
					$since,
					'%Rate limit exceeded%'
				)
			),
		);

		return $counts;
	}
}
