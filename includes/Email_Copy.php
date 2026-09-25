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
 * Copia informativa de los correos de Convoca al administrador o al monitor.
 *
 * Regla de negocio: todo correo que Convoca envía a una persona se notifica también
 * a la asociación — a los monitores si el correo es de una actividad, y al correo de
 * administración en cualquier otro caso.
 *
 * El canal de envío lo decide quien invoca (`$context['send']`), para que la copia
 * salga por el mismo camino que el correo original. Si no se indica, se usa wp_mail().
 *
 * @package Convoca\Core
 */
namespace Convoca\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Email_Copy {

	/**
	 * Ajustes del mecanismo (enabled, recipients).
	 */
	const OPTION = 'convoca_email_copy';

	/**
	 * ¿Está activada la copia?
	 *
	 * Precedencia: el ajuste del mecanismo, el ajuste de la interfaz de socios
	 * («Enviar copia de todos los correos») y, si no hay ninguno, activada.
	 */
	public static function enabled(): bool {
		$enabled = true;

		$option = get_option( self::OPTION, array() );
		if ( is_array( $option ) && array_key_exists( 'enabled', $option ) ) {
			$enabled = (bool) $option['enabled'];
		}

		$members = get_option( 'convoca_members_settings', array() );
		if ( is_array( $members ) && array_key_exists( 'copy_all_emails', $members ) ) {
			$enabled = (bool) $members['copy_all_emails'];
		}

		return (bool) apply_filters( 'convoca_email_copy_enabled', $enabled );
	}

	/**
	 * Monitores (responsables) de una actividad, por correo.
	 *
	 * Los responsables se guardan como IDs de usuario separados por comas.
	 *
	 * @param int $actividad_id ID de la actividad.
	 * @return array<int, string> Correos válidos.
	 */
	public static function activity_monitors( int $actividad_id ): array {
		if ( $actividad_id <= 0 || 'actividad' !== get_post_type( $actividad_id ) ) {
			return array();
		}

		$ids = get_post_meta( $actividad_id, '_convoca_responsables', true );
		if ( empty( $ids ) ) {
			return array();
		}

		$emails = array();
		foreach ( array_map( 'trim', explode( ',', (string) $ids ) ) as $uid ) {
			$user = get_userdata( (int) $uid );
			if ( $user && is_email( $user->user_email ) ) {
				$emails[] = $user->user_email;
			}
		}

		return $emails;
	}

	/**
	 * Destinatarios de la copia para un contexto dado.
	 *
	 * @param array $context Contexto del correo (actividad_id, …).
	 * @return array<int, string> Correos válidos.
	 */
	public static function recipients( array $context = array() ): array {
		$emails = array();

		if ( ! empty( $context['actividad_id'] ) ) {
			$emails = self::activity_monitors( (int) $context['actividad_id'] );
		}

		// Sin monitores (o sin actividad): a la administración.
		if ( empty( $emails ) ) {
			$option     = get_option( self::OPTION, array() );
			$configured = is_array( $option ) ? (array) ( $option['recipients'] ?? array() ) : array();

			if ( empty( $configured ) ) {
				$members = get_option( 'convoca_members_settings', array() );
				$admin   = is_array( $members ) ? (string) ( $members['admin_email'] ?? '' ) : '';

				$configured = '' !== $admin ? array( $admin ) : array( (string) get_option( 'admin_email' ) );
			}

			$emails = $configured;
		}

		$clean = array();
		foreach ( (array) $emails as $email ) {
			$email = sanitize_email( (string) $email );
			if ( is_email( $email ) ) {
				$clean[] = $email;
			}
		}

		return array_values(
			array_unique(
				(array) apply_filters( 'convoca_email_copy_recipients', $clean, $context )
			)
		);
	}

	/**
	 * Envía la copia si procede.
	 *
	 * Nunca altera el envío original: si no hay destinatario o el envío falla, deja
	 * constancia en el log y devuelve false.
	 *
	 * @param array $context Contexto: to, subject, body, plugin, template, actividad_id,
	 *                       inscripcion_id, entity_id, has_attachments, send (callable).
	 * @return bool Verdadero si la copia se envió.
	 */
	public static function maybe_copy( array $context ): bool {
		if ( ! self::enabled() ) {
			return false;
		}

		// Un envío que ya va a la administración no se copia a sí mismo.
		$original = array();
		foreach ( (array) ( $context['to'] ?? array() ) as $to ) {
			$to = sanitize_email( (string) $to );
			if ( is_email( $to ) ) {
				$original[] = $to;
			}
		}

		$recipients = array_values( array_diff( self::recipients( $context ), $original ) );
		if ( empty( $recipients ) ) {
			Logger::info( 'Copia de correo omitida: no hay destinatario distinto al original.', 'Core/EmailCopy' );
			return false;
		}

		$subject      = (string) ( $context['subject'] ?? '' );
		$body         = (string) ( $context['body'] ?? '' );
		$send         = $context['send'] ?? null;
		$copy_subject = sprintf(
			/* translators: %s: asunto del correo original. */
			__( '[Copia] %s', 'convoca-core' ),
			$subject
		);

		$notice = '<div style="border-left:4px solid #ff8700;background:#fff7ed;padding:12px 14px;margin:0 0 16px;font-family:sans-serif;font-size:13px;color:#320028">'
			. '<strong>' . esc_html__( 'Copia informativa', 'convoca-core' ) . '</strong><br>'
			. sprintf(
				/* translators: 1: destinatario del correo original, 2: origen del correo. */
				esc_html__( 'Este correo se ha enviado a %1$s. Origen: %2$s.', 'convoca-core' ),
				esc_html( implode( ', ', $original ) ?: '—' ),
				esc_html( self::describe_origin( $context ) )
			)
			. ( ! empty( $context['has_attachments'] ) ? '<br>' . esc_html__( 'El correo original incluye archivos adjuntos.', 'convoca-core' ) : '' )
			. '</div>';

		$copy_body = $notice . $body;
		$headers   = array( 'Content-Type: text/html; charset=UTF-8' );

		/**
		 * Permite ajustar la copia antes de enviarla (destinatarios, asunto, cuerpo, cabeceras).
		 */
		$copy = apply_filters(
			'convoca_email_copy_payload',
			array(
				'recipients' => $recipients,
				'subject'    => $copy_subject,
				'body'       => $copy_body,
				'headers'    => $headers,
			),
			$context
		);

		if ( empty( $copy['recipients'] ) || ! is_email( (string) ( $copy['recipients'][0] ?? '' ) ) ) {
			Logger::warning( 'Copia de correo sin destinatario válido; no se envía. Origen: ' . self::describe_origin( $context ), 'Core/EmailCopy' );
			return false;
		}

		if ( is_callable( $send ) ) {
			$sent = (bool) call_user_func( $send, (array) $copy['recipients'], (string) $copy['subject'], (string) $copy['body'], (array) $copy['headers'] );
		} else {
			$sent = (bool) wp_mail( (array) $copy['recipients'], (string) $copy['subject'], (string) $copy['body'], (array) $copy['headers'] );
		}

		$message = sprintf(
			'Copia %s para %s (%s).',
			$sent ? 'enviada' : 'NO enviada',
			implode( ', ', (array) $copy['recipients'] ),
			self::describe_origin( $context )
		);
		$sent ? Logger::info( $message, 'Core/EmailCopy' ) : Logger::warning( $message, 'Core/EmailCopy' );

		return $sent;
	}

	/**
	 * Texto legible del origen del correo (plugin, plantilla, entidad).
	 *
	 * @param array $context Contexto del correo.
	 */
	private static function describe_origin( array $context ): string {
		$parts = array();

		if ( ! empty( $context['plugin'] ) ) {
			$parts[] = (string) $context['plugin'];
		}
		if ( ! empty( $context['template'] ) ) {
			$parts[] = 'plantilla ' . (string) $context['template'];
		}
		if ( ! empty( $context['actividad_id'] ) ) {
			$parts[] = sprintf( 'actividad #%d «%s»', (int) $context['actividad_id'], get_the_title( (int) $context['actividad_id'] ) );
		}
		if ( ! empty( $context['inscripcion_id'] ) ) {
			$parts[] = 'inscripción #' . (int) $context['inscripcion_id'];
		}
		if ( ! empty( $context['entity_id'] ) ) {
			$parts[] = sprintf( '#%d «%s»', (int) $context['entity_id'], get_the_title( (int) $context['entity_id'] ) );
		}

		return $parts ? implode( ' · ', $parts ) : 'Convoca';
	}
}
