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
 * Webhook Manager for external integrations.
 *
 * Allows registering outbound webhook URLs that receive JSON payloads
 * when specific events occur across the Convoca ecosystem.
 *
 * Webhooks are stored in wp_options and dispatched asynchronously via wp_remote_post.
 *
 * @package Convoca\Core
 */

namespace Convoca\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Webhook_Manager {

	/** Option key for stored webhooks. */
	private const OPTION = 'convoca_webhooks';

	/** Supported event types. */
	public static function events(): array {
		return array(
			'member.created'           => __( 'Nuevo socio registrado', 'convoca-core' ),
			'member.activated'         => __( 'Socio activado', 'convoca-core' ),
			'member.suspended'         => __( 'Socio suspendido', 'convoca-core' ),
			'member.expired'           => __( 'Membresía expirada', 'convoca-core' ),
			'member.renewed'           => __( 'Membresía renovada', 'convoca-core' ),
			'payment.completed'        => __( 'Pago completado', 'convoca-core' ),
			'payment.failed'           => __( 'Pago fallido', 'convoca-core' ),
			'payment.reminder_sent'    => __( 'Recordatorio de pago enviado', 'convoca-core' ),
			'enrollment.created'       => __( 'Nueva inscripción', 'convoca-core' ),
			'enrollment.cancelled'     => __( 'Inscripción cancelada', 'convoca-core' ),
			'enrollment.checkin'       => __( 'Check-in realizado', 'convoca-core' ),
			'volunteer.hours_logged'   => __( 'Horas de voluntariado registradas', 'convoca-core' ),
			'volunteer.hours_approved' => __( 'Horas de voluntariado aprobadas', 'convoca-core' ),
		);
	}

	/**
	 * Maximum webhook delivery attempts (initial delivery + retries).
	 */
	private const MAX_RETRIES = 3;

	/**
	 * Default retry backoff schedule in seconds.
	 */
	private const DEFAULT_BACKOFF = array( 60, 120, 240 );

	/**
	 * Get the database table name for webhook retries.
	 */
	private static function get_retry_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'convoca_webhook_retries';
	}

	/**
	 * Get the retry backoff schedule (seconds) for failed webhook deliveries.
	 *
	 * Filterable via 'convoca_webhook_retry_backoff'. Values are sanitized to
	 * positive integers; an empty or invalid schedule falls back to the default.
	 *
	 * @return int[] Non-empty list of positive delays in seconds.
	 */
	public static function get_backoff(): array {
		$default = self::DEFAULT_BACKOFF;
		$backoff = apply_filters( 'convoca_webhook_retry_backoff', $default );

		if ( ! is_array( $backoff ) ) {
			$backoff = $default;
		}

		$backoff = array_values( array_filter( array_map( 'absint', $backoff ) ) );

		return empty( $backoff ) ? $default : $backoff;
	}

	/**
	 * Compute the delay (seconds) before the next retry after a failed attempt.
	 *
	 * @param int        $failed_attempt 1-based attempt number that just failed.
	 * @param int[]|null $backoff        Optional backoff schedule (defaults to get_backoff()).
	 * @return int|null Seconds to wait, or null when retries are exhausted.
	 */
	public static function get_retry_delay( int $failed_attempt, ?array $backoff = null ): ?int {
		if ( $failed_attempt >= self::MAX_RETRIES ) {
			return null;
		}

		$backoff = $backoff ?? self::get_backoff();
		$index   = $failed_attempt - 1;

		if ( isset( $backoff[ $index ] ) ) {
			return (int) $backoff[ $index ];
		}

		// Schedule shorter than expected: repeat the last configured delay.
		$last = end( $backoff );

		return false !== $last ? (int) $last : null;
	}

	public function __construct() {
		$this->register_hooks();

		// Register cron hook for webhook retries.
		add_action( 'convoca_webhook_retry', array( __CLASS__, 'process_retries' ) );
	}

	/**
	 * Register WordPress hooks that trigger webhook dispatches.
	 */
	private function register_hooks(): void {
		// Member events.
		add_action( 'convoca_members_estado_changed', array( $this, 'on_member_state_change' ), 20, 3 );
		add_action( 'convoca_members_created', array( $this, 'on_member_created' ), 20, 1 );
		add_action( 'convoca_members_membership_expired', array( $this, 'on_member_expired' ), 20, 1 );

		// Payment events.
		add_action( 'convoca_gateway_payment_completed', array( $this, 'on_payment_completed' ), 20, 4 );
		add_action( 'convoca_gateway_payment_failed', array( $this, 'on_payment_failed' ), 20, 2 );
		add_action( 'convoca_members_payment_reminder_sent', array( $this, 'on_payment_reminder' ), 20, 3 );

		// Enrollment events.
		add_action( 'convoca_enroll_inscripcion_nueva', array( $this, 'on_enrollment_created' ), 20, 2 );
		add_action( 'convoca_enroll_inscripcion_cancelada', array( $this, 'on_enrollment_cancelled' ), 20, 2 );
		add_action( 'convoca_enroll_asistencia_cambiada', array( $this, 'on_enrollment_checkin' ), 20, 2 );

		// Volunteer events.
		add_action( 'convoca_members_hours_submitted', array( $this, 'on_volunteer_hours_logged' ), 20, 2 );
		add_action( 'convoca_members_hora_aprobada', array( $this, 'on_volunteer_hours_approved' ), 20, 2 );
		add_action( 'convoca_members_hora_rechazada', array( $this, 'on_volunteer_hours_rejected' ), 20, 2 );
	}

	/* ── Event Handlers ───────────────────────────────────── */

	public function on_member_created( int $member_id ): void {
		$this->dispatch(
			'member.created',
			array(
				'member_id' => $member_id,
				'nombre'    => get_the_title( $member_id ),
				'email'     => get_post_meta( $member_id, '_convoca_email', true ),
				'plan'      => get_post_meta( $member_id, '_convoca_plan', true ),
			)
		);
	}

	public function on_member_state_change( int $member_id, string $new, string $old ): void {
		$event_map = array(
			'activo'     => 'member.activated',
			'suspendido' => 'member.suspended',
		);

		$event = $event_map[ $new ] ?? null;
		if ( ! $event ) {
			return;
		}

		// Special case: if previously suspended and now active => renewal.
		if ( $old === 'suspendido' && $new === 'activo' ) {
			$event = 'member.renewed';
		}

		$this->dispatch(
			$event,
			array(
				'member_id'  => $member_id,
				'nombre'     => get_the_title( $member_id ),
				'old_status' => $old,
				'new_status' => $new,
			)
		);
	}

	public function on_member_expired( int $member_id ): void {
		$this->dispatch(
			'member.expired',
			array(
				'member_id'        => $member_id,
				'nombre'           => get_the_title( $member_id ),
				'fecha_renovacion' => get_post_meta( $member_id, '_convoca_fecha_renovacion', true ),
			)
		);
	}

	public function on_payment_completed( int $pago_id, string $origin, int $origin_id, array $meta ): void {
		$this->dispatch(
			'payment.completed',
			array(
				'payment_id' => $pago_id,
				'origin'     => $origin,
				'origin_id'  => $origin_id,
				'amount'     => $meta['amount_cents'] ?? 0,
				'method'     => $meta['method'] ?? '',
			)
		);
	}

	public function on_payment_failed( int $pago_id, string $response_code ): void {
		$this->dispatch(
			'payment.failed',
			array(
				'payment_id'    => $pago_id,
				'response_code' => $response_code,
			)
		);
	}

	public function on_payment_reminder( int $member_id, string $reminder_key, int $days ): void {
		$this->dispatch(
			'payment.reminder_sent',
			array(
				'member_id'    => $member_id,
				'reminder_key' => $reminder_key,
				'days_pending' => $days,
			)
		);
	}

	public function on_enrollment_created( int $inscripcion_id, int $actividad_id ): void {
		$this->dispatch(
			'enrollment.created',
			array(
				'inscripcion_id' => $inscripcion_id,
				'actividad_id'   => $actividad_id,
				'nombre'         => get_post_meta( $inscripcion_id, '_convoca_nombre', true ),
			)
		);
	}

	public function on_enrollment_cancelled( int $inscripcion_id, int $actividad_id ): void {
		$this->dispatch(
			'enrollment.cancelled',
			array(
				'inscripcion_id' => $inscripcion_id,
				'actividad_id'   => $actividad_id,
			)
		);
	}

	public function on_enrollment_checkin( int $inscripcion_id, string $asistencia ): void {
		$this->dispatch(
			'enrollment.checkin',
			array(
				'inscripcion_id' => $inscripcion_id,
				'asistencia'     => $asistencia,
			)
		);
	}

	public function on_volunteer_hours_logged( int $registro_id, int $member_id ): void {
		$this->dispatch(
			'volunteer.hours_logged',
			array(
				'registro_id' => $registro_id,
				'member_id'   => $member_id,
				'horas'       => get_post_meta( $registro_id, '_convoca_horas', true ),
				'descripcion' => get_post_meta( $registro_id, '_convoca_descripcion', true ),
			)
		);
	}

	public function on_volunteer_hours_approved( int $registro_id, int $member_id ): void {
		$this->dispatch(
			'volunteer.hours_approved',
			array(
				'registro_id' => $registro_id,
				'member_id'   => $member_id,
				'horas'       => get_post_meta( $registro_id, '_convoca_horas', true ),
			)
		);
	}

	public function on_volunteer_hours_rejected( int $registro_id, int $member_id ): void {
		$this->dispatch(
			'volunteer.hours_rejected',
			array(
				'registro_id' => $registro_id,
				'member_id'   => $member_id,
				'horas'       => get_post_meta( $registro_id, '_convoca_horas', true ),
			)
		);
	}

	/* ── Core Dispatch ────────────────────────────────────── */

	/**
	 * Dispatch a webhook event to all registered URLs.
	 *
	 * @param string $event   Event type key.
	 * @param array  $payload Data to send.
	 */
	public function dispatch( string $event, array $payload = array() ): void {
		$webhooks = self::get_webhooks();

		if ( empty( $webhooks ) ) {
			return;
		}

		// Dedup: prevent duplicate notifications for the same event+payload within 10 seconds.
		$dedup_key = 'convoca_webhook_dedup_' . $event . '_' . md5( wp_json_encode( $payload ) );
		if ( get_transient( $dedup_key ) ) {
			Logger::debug( "Webhook $event omitido (dedup dentro de 10s)", 'Common/Webhook' );
			return;
		}
		set_transient( $dedup_key, 1, 10 );

		$full_payload = array(
			'event'     => $event,
			'timestamp' => current_time( 'c' ),
			'site_url'  => home_url(),
			'data'      => $payload,
		);

		foreach ( $webhooks as $webhook ) {
			// Check if this webhook is subscribed to this event.
			if ( ! empty( $webhook['events'] ) && ! in_array( $event, $webhook['events'], true ) ) {
				continue;
			}

			// Check if webhook is active.
			if ( isset( $webhook['active'] ) && ! $webhook['active'] ) {
				continue;
			}

			$delivered = $this->deliver( $webhook, $full_payload );

			// On failure, queue the first retry using the configured backoff.
			if ( ! $delivered ) {
				$delay = self::get_retry_delay( 1 );
				if ( null !== $delay ) {
					$this->schedule_retry( $webhook, $full_payload, 2, $delay );
				}
			}
		}
	}

	/**
	 * Deliver a webhook payload to a single URL.
	 *
	 * Performs the HTTP POST, logs the outcome and stores a delivery log entry.
	 * Retry scheduling is handled by the caller (dispatch() / process_retries()).
	 *
	 * @param array $webhook Webhook definition (url, secret, id).
	 * @param array $payload Full payload (event, timestamp, site_url, data).
	 * @return bool True if delivery succeeded, false otherwise.
	 */
	private function deliver( array $webhook, array $payload ): bool {
		$url = $webhook['url'] ?? '';
		if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		// Add HMAC signature if secret is configured.
		$body        = wp_json_encode( $payload );
		$delivery_id = wp_generate_uuid4();
		$headers     = array(
			'Content-Type'       => 'application/json',
			'X-Convoca-Event'    => $payload['event'],
			'X-Convoca-Delivery' => $delivery_id,
		);

		if ( ! empty( $webhook['secret'] ) ) {
			$headers['X-Convoca-Signature'] = hash_hmac( 'sha256', $body, $webhook['secret'] );
		}

		// Use blocking mode with short timeout to detect actual delivery status.
		$response = wp_remote_post(
			$url,
			array(
				'body'        => $body,
				'headers'     => $headers,
				'timeout'     => 5,
				'redirection' => 2,
				'blocking'    => true,
				'sslverify'   => apply_filters( 'convoca_common_webhook_sslverify', true ),
			)
		);

		// Check for HTTP errors.
		$success     = false;
		$status_code = 0;
		if ( ! is_wp_error( $response ) ) {
			$status_code = wp_remote_retrieve_response_code( $response );
			$success     = $status_code >= 200 && $status_code < 300;
		}

		$log_msg = $success
			? sprintf( 'Webhook "%s" entregado a %s (HTTP %d)', $payload['event'], $url, $status_code )
			: sprintf( 'Error webhook "%s" a %s: HTTP %d', $payload['event'], $url, $status_code );

		if ( is_wp_error( $response ) ) {
			$log_msg = sprintf( 'Error webhook "%s" a %s: %s', $payload['event'], $url, $response->get_error_message() );
		}

		$log_level = $success ? 'info' : 'warning';
		Logger::log( $log_msg, $log_level, 'Common/Webhook' );

		// Store delivery log.
		self::log_delivery( $webhook['id'] ?? '', $payload['event'], $success, $log_msg );

		return $success;
	}

	/**
	 * Schedule a webhook retry with exponential backoff.
	 */
	private function schedule_retry( array $webhook, array $payload, int $attempt, int $delay ): void {
		global $wpdb;
		$retry_key = $webhook['id'] ?? '';
		if ( empty( $retry_key ) ) {
			return;
		}

		$table_name = self::get_retry_table_name();

		// Verify table exists before inserting; attempt to create if missing.
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) !== $table_name ) {
			$charset_collate = $wpdb->get_charset_collate();
			$sql             = "CREATE TABLE IF NOT EXISTS $table_name (
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
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );
			if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) !== $table_name ) {
				Logger::error( "No se pudo crear la tabla de reintentos de webhook ($table_name).", 'Common/Webhook' );
				return;
			}
		}

		$wpdb->insert(
			$table_name,
			array(
				'webhook_id'     => $retry_key,
				'webhook_url'    => $webhook['url'],
				'webhook_secret' => $webhook['secret'] ?? '',
				'payload'        => wp_json_encode( $payload ),
				'attempt'        => $attempt,
				'scheduled_at'   => gmdate( 'Y-m-d H:i:s', time() + $delay ),
				'created_at'     => current_time( 'mysql' ),
			)
		);

		// Schedule cron job if not already scheduled.
		if ( ! wp_next_scheduled( 'convoca_webhook_retry' ) ) {
			wp_schedule_event( time(), 'hourly', 'convoca_webhook_retry' );
		}

		Logger::info( sprintf( 'Webhook retry scheduled: intento %d en %ds para %s', $attempt, $delay, $webhook['url'] ), 'Common/Webhook' );
	}

	/**
	 * Process pending webhook retries.
	 * Hooked to hourly cron.
	 */
	public static function process_retries(): void {
		global $wpdb;
		$table_name = self::get_retry_table_name();

		// Acquire lock to prevent concurrent runs.
		if ( get_transient( 'convoca_webhook_retry_lock' ) ) {
			return;
		}
		set_transient( 'convoca_webhook_retry_lock', true, 10 * MINUTE_IN_SECONDS );

		try {
			// Get retries that are due.
			$retries = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM $table_name WHERE scheduled_at <= %s ORDER BY created_at ASC LIMIT 50",
					current_time( 'mysql' )
				)
			);

			if ( empty( $retries ) ) {
				return;
			}

			foreach ( $retries as $retry ) {
				// Mark as processing to prevent data loss on crash.
				$wpdb->update(
					$table_name,
					array( 'status' => 'processing' ),
					array( 'id' => $retry->id )
				);

				// Attempt delivery.
				$manager = new self();
				$webhook = array(
					'id'     => $retry->webhook_id,
					'url'    => $retry->webhook_url,
					'secret' => $retry->webhook_secret,
				);

				$payload = json_decode( $retry->payload, true );
				$success = false;
				if ( $payload ) {
					$success = $manager->deliver( $webhook, $payload );
				}

				// Delete on success, update retry count on failure.
				if ( $success ) {
					$wpdb->delete( $table_name, array( 'id' => $retry->id ) );
				} else {
					$delay = self::get_retry_delay( (int) $retry->attempt );

					// Sin más reintentos disponibles: agotado, eliminar y loguear.
					if ( null === $delay ) {
						$wpdb->delete( $table_name, array( 'id' => $retry->id ) );
						Logger::warning(
							sprintf(
								'Webhook %s agotó los %d intentos a %s. Eliminado de la cola.',
								$retry->webhook_id,
								self::MAX_RETRIES,
								$retry->webhook_url
							),
							'Common/Webhook'
						);
						continue;
					}

					$next_attempt   = (int) $retry->attempt + 1;
					$next_scheduled = gmdate( 'Y-m-d H:i:s', time() + $delay );
					$wpdb->update(
						$table_name,
						array(
							'status'       => 'pending',
							'attempt'      => $next_attempt,
							'scheduled_at' => $next_scheduled,
						),
						array( 'id' => $retry->id )
					);
				}
			}
		} finally {
			delete_transient( 'convoca_webhook_retry_lock' );
		}
	}

	/* ── CRUD for Webhooks ────────────────────────────────── */

	/**
	 * Get all registered webhooks.
	 */
	public static function get_webhooks(): array {
		return get_option( self::OPTION, array() );
	}

	/**
	 * Get a single webhook by ID.
	 */
	public static function get_webhook( string $id ): ?array {
		$webhooks = self::get_webhooks();
		foreach ( $webhooks as $wh ) {
			if ( ( $wh['id'] ?? '' ) === $id ) {
				return $wh;
			}
		}
		return null;
	}

	/**
	 * Register a new webhook.
	 *
	 * @param array $data {
	 *     @type string   $url     Target URL.
	 *     @type string   $secret  Signing secret (optional).
	 *     @type string[] $events  Event types to subscribe to (empty = all).
	 *     @type string   $label   Human-readable label.
	 * }
	 * @return string Webhook ID.
	 */
	public static function add_webhook( array $data ): string {
		if ( ! Utils::acquire_lock( 'webhook_crud', 30 ) ) {
			return '';
		}

		try {
			$webhooks = self::get_webhooks();

			$id         = wp_generate_uuid4();
			$webhooks[] = array(
				'id'         => $id,
				'url'        => sanitize_url( $data['url'] ?? '' ),
				'secret'     => sanitize_text_field( $data['secret'] ?? '' ),
				'events'     => array_map( 'sanitize_text_field', $data['events'] ?? array() ),
				'label'      => sanitize_text_field( $data['label'] ?? '' ),
				'active'     => true,
				'created_at' => current_time( 'mysql' ),
			);

			update_option( self::OPTION, $webhooks );

			Logger::info(
				sprintf( 'Webhook registrado: %s → %s', $data['label'] ?? 'Sin nombre', $data['url'] ?? '' ),
				'Common/Webhook'
			);
		} finally {
			Utils::release_lock( 'webhook_crud' );
		}

		return $id;
	}

	/**
	 * Update an existing webhook.
	 */
	public static function update_webhook( string $id, array $data ): bool {
		if ( ! Utils::acquire_lock( 'webhook_crud', 30 ) ) {
			return false;
		}

		try {
			$webhooks = self::get_webhooks();
			$found    = false;

			foreach ( $webhooks as &$wh ) {
				if ( ( $wh['id'] ?? '' ) === $id ) {
					if ( isset( $data['url'] ) ) {
						$wh['url'] = sanitize_url( $data['url'] );
					}
					if ( isset( $data['secret'] ) ) {
						$wh['secret'] = sanitize_text_field( $data['secret'] );
					}
					if ( isset( $data['events'] ) ) {
						$wh['events'] = array_map( 'sanitize_text_field', $data['events'] );
					}
					if ( isset( $data['label'] ) ) {
						$wh['label'] = sanitize_text_field( $data['label'] );
					}
					if ( isset( $data['active'] ) ) {
						$wh['active'] = (bool) $data['active'];
					}
					$found = true;
					break;
				}
			}

			if ( $found ) {
				update_option( self::OPTION, $webhooks );
			}

			return $found;
		} finally {
			Utils::release_lock( 'webhook_crud' );
		}
	}

	/**
	 * Delete a webhook by ID.
	 */
	public static function delete_webhook( string $id ): bool {
		if ( ! Utils::acquire_lock( 'webhook_crud', 30 ) ) {
			return false;
		}

		try {
			$webhooks = self::get_webhooks();
			$filtered = array_filter( $webhooks, fn( $wh ) => ( $wh['id'] ?? '' ) !== $id );

			if ( count( $filtered ) === count( $webhooks ) ) {
				return false;
			}

			update_option( self::OPTION, array_values( $filtered ) );
			self::clear_delivery_logs( $id );
			Logger::info( "Webhook eliminado: $id", 'Common/Webhook' );
			return true;
		} finally {
			Utils::release_lock( 'webhook_crud' );
		}
	}

	/**
	 * Test a webhook by sending a ping event.
	 */
	public static function test_webhook( string $id ): bool {
		$webhook = self::get_webhook( $id );
		if ( ! $webhook ) {
			return false;
		}

		$manager = new self();
		$manager->deliver(
			$webhook,
			array(
				'event'     => 'test.ping',
				'timestamp' => current_time( 'c' ),
				'site_url'  => home_url(),
				'data'      => array(
					'message'    => __( 'Test ping from ', 'convoca-core' ) . get_bloginfo( 'name' ),
					'webhook_id' => $id,
				),
			)
		);

		return true;
	}

	/* ── Delivery Logs ────────────────────────────────────── */

	/**
	 * Store webhook delivery log (recent entries only).
	 * Uses autoload=no to avoid loading in every page load.
	 */
	private static function log_delivery( string $webhook_id, string $event, bool $success, string $message ): void {
		$log_key = 'convoca_webhook_log_' . $webhook_id;
		$logs    = get_option( $log_key, array() );

		$logs[] = array(
			'event'   => $event,
			'success' => $success,
			'message' => $message,
			'time'    => current_time( 'mysql' ),
		);

		// Keep only last 50 entries.
		if ( count( $logs ) > 50 ) {
			$logs = array_slice( $logs, -50 );
		}

		// Use autoload=no to avoid loading these on every page.
		update_option( $log_key, $logs, false );
	}

	/**
	 * Get delivery logs for a webhook.
	 */
	public static function get_delivery_logs( string $webhook_id, int $limit = 20 ): array {
		$logs = get_option( 'convoca_webhook_log_' . $webhook_id, array() );
		return array_slice( array_reverse( $logs ), 0, $limit );
	}

	/**
	 * Clear delivery logs for a webhook.
	 */
	public static function clear_delivery_logs( string $webhook_id ): void {
		delete_option( 'convoca_webhook_log_' . $webhook_id );
	}
}
