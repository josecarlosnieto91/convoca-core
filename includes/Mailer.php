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
 * Punto de salida único para los correos de Convoca.
 *
 * Hace las tres cosas que un correo de Convoca debe hacer siempre: envolverlo con la identidad
 * visual (`Email_Layout`), copiar a la asociación si el ajuste está marcado (`Email_Copy`) y
 * mandarlo por el canal que se le indique. Antes cada plugin llamaba a `wp_mail()` por su cuenta y
 * los correos de Shifts y Gateway salían sin envoltorio y sin copia (issue convoca-core#6).
 *
 * El canal es inyectable para que quien ya tiene proveedor propio (Members con `Email_Verifier`)
 * pueda usarlo y su copia no se duplique.
 *
 * @package Convoca\Core
 */
namespace Convoca\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mailer {

	/**
	 * Opciones por defecto de un envío.
	 *
	 * @var array<string, mixed>
	 */
	private const DEFAULTS = array(
		'plugin'      => 'convoca-core',
		'template'    => '',
		'entity_id'   => 0,
		'headers'     => array(),
		'attachments' => array(),
		'layout'      => true,
		'copy'        => true,
		'channel'     => null,
	);

	/**
	 * Manda un correo y, si toca, su copia a la asociación.
	 *
	 * @param string|array<int, string> $to      Destinatario o destinatarios.
	 * @param string                    $subject Asunto.
	 * @param string                    $body    Cuerpo (HTML o texto plano).
	 * @param array<string, mixed>      $args    Ver la especificación (docs/mailer-espec.md).
	 * @return bool Verdadero si el correo original salió.
	 */
	public static function send( $to, string $subject, string $body, array $args = array() ): bool {
		$args = wp_parse_args( $args, self::DEFAULTS );

		$recipients = self::normalize_recipients( $to );
		if ( empty( $recipients ) ) {
			Logger::warning( 'Correo sin destinatario válido; no se envía. Origen: ' . $args['plugin'] . '/' . $args['template'], 'Core/Mailer' );
			return false;
		}

		$html = $body;
		$wrap = (bool) $args['layout'];
		if ( $wrap && ! self::looks_like_html( $html ) ) {
			$html = self::text_to_html( $html );
		}

		if ( $wrap ) {
			$html = Email_Layout::render( $html, $subject );
		}

		$headers = (array) $args['headers'];
		if ( $wrap && ! self::has_content_type( $headers ) ) {
			$headers[] = 'Content-Type: text/html; charset=UTF-8';
		}

		$attachments = array_values( array_filter( array_map( 'strval', (array) $args['attachments'] ) ) );
		$channel     = is_callable( $args['channel'] ) ? $args['channel'] : null;

		if ( $channel ) {
			$sent = (bool) call_user_func( $channel, $recipients, $subject, $html, $headers, $attachments );
		} else {
			$sent = (bool) wp_mail( $recipients, $subject, $html, $headers, $attachments );
		}

		Logger::info(
			sprintf(
				'Correo %s a %s (%s/%s).',
				$sent ? 'enviado' : 'NO enviado',
				implode( ', ', $recipients ),
				(string) $args['plugin'],
				(string) ( $args['template'] ?: 'sin plantilla' )
			),
			'Core/Mailer'
		);

		// La copia va DESPUÉS del original: `Email_Copy` no se copia a sí misma cuando el destino ya
		// es la asociación, y así el aviso puede decir a quién se envió.
		if ( ! empty( $args['copy'] ) ) {
			Email_Copy::maybe_copy(
				array(
					'to'              => $recipients,
					'subject'         => $subject,
					'body'            => $html,
					'plugin'          => (string) $args['plugin'],
					'template'        => (string) $args['template'],
					'entity_id'       => (int) $args['entity_id'],
					'headers'         => $headers,
					'has_attachments' => ! empty( $attachments ),
					'send'            => $channel ? self::as_copy_channel( $channel, $attachments ) : null,
				)
			);
		}

		return $sent;
	}

	/**
	 * Adapta un canal de cinco argumentos al de `Email_Copy`, que usa cuatro.
	 *
	 * @param callable           $channel     Canal del correo original.
	 * @param array<int, string> $attachments Adjuntos del original.
	 * @return callable
	 */
	private static function as_copy_channel( callable $channel, array $attachments ): callable {
		return static function ( array $to, string $subject, string $body, array $headers ) use ( $channel, $attachments ): bool {
			return (bool) call_user_func( $channel, $to, $subject, $body, $headers, $attachments );
		};
	}

	/**
	 * Deja solo destinatarios válidos.
	 *
	 * @param string|array<int, string> $to Destinatario o destinatarios.
	 * @return array<int, string>
	 */
	private static function normalize_recipients( $to ): array {
		$out = array();
		foreach ( (array) $to as $one ) {
			$one = sanitize_email( (string) $one );
			if ( is_email( $one ) ) {
				$out[] = $one;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * ¿El cuerpo ya viene con etiquetas HTML?
	 *
	 * @param string $body Cuerpo.
	 * @return bool
	 */
	private static function looks_like_html( string $body ): bool {
		return (bool) preg_match( '/<[a-z][a-z0-9]*\b[^>]*>/i', $body );
	}

	/**
	 * Convierte texto plano en párrafos, sin cambiar ni una palabra.
	 *
	 * Los avisos de turnos son texto con saltos de línea y el envoltorio espera HTML.
	 *
	 * @param string $text Texto plano.
	 * @return string
	 */
	private static function text_to_html( string $text ): string {
		$parrafos = preg_split( '/\R{2,}/', trim( $text ) );
		$out      = '';
		foreach ( (array) $parrafos as $parrafo ) {
			$parrafo = trim( (string) $parrafo );
			if ( '' === $parrafo ) {
				continue;
			}
			$out .= '<p>' . nl2br( esc_html( $parrafo ) ) . '</p>';
		}
		return $out;
	}

	/**
	 * ¿Las cabeceras ya dicen el tipo de contenido?
	 *
	 * @param array<int, mixed> $headers Cabeceras.
	 * @return bool
	 */
	private static function has_content_type( array $headers ): bool {
		foreach ( $headers as $header ) {
			if ( is_string( $header ) && false !== stripos( $header, 'content-type' ) ) {
				return true;
			}
		}
		return false;
	}
}
