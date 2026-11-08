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
 * Barrera anti-bot de los formularios públicos.
 *
 * @package Convoca\Core
 */

namespace Convoca\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Honeypot y trampa de tiempo, sin servicios externos.
 *
 * Un bot rellena todos los campos que encuentra y envía al instante; una persona no ve el campo trampa y
 * tarda más de tres segundos en rellenar un alta. No sustituye al limitador de intentos: lo complementa,
 * porque el limitador solo frena después de muchos envíos y este corta el primero.
 *
 * @package Convoca\Core
 */
final class Anti_Bot {

	/**
	 * Nombre del campo trampa. Nadie lo ve: va oculto y con `tabindex="-1"`.
	 */
	public const TRAMPA = 'convoca_web';

	/**
	 * Campo con la marca de tiempo firmada.
	 */
	public const SELLO = 'convoca_t';

	/**
	 * Segundos mínimos que tarda una persona en rellenar el formulario.
	 */
	public const MINIMO_SEGUNDOS = 3;

	/**
	 * Horas que vale el sello.
	 */
	public const VIGENCIA_HORAS = 24;

	/**
	 * Campos ocultos que hay que pintar en el formulario.
	 *
	 * @return string
	 */
	public static function campos(): string {
		$trampa = self::TRAMPA;
		$sello  = self::SELLO;

		return '<div style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden" aria-hidden="true">'
			. '<label for="' . esc_attr( $trampa ) . '">No rellenar</label>'
			. '<input type="text" id="' . esc_attr( $trampa ) . '" name="' . esc_attr( $trampa ) . '" value="" tabindex="-1" autocomplete="off" />'
			. '</div>'
			. '<input type="hidden" name="' . esc_attr( $sello ) . '" value="' . esc_attr( self::firmar( time() ) ) . '" />';
	}

	/**
	 * Firma una marca de tiempo.
	 *
	 * @param int $ts Marca de tiempo.
	 * @return string
	 */
	public static function firmar( int $ts ): string {
		return $ts . '.' . substr( hash_hmac( 'sha256', (string) $ts, wp_salt( 'nonce' ) ), 0, 20 );
	}

	/**
	 * Comprueba la firma y devuelve el momento sellado, o `null` si no vale.
	 *
	 * @param string $token Sello.
	 * @return int|null
	 */
	public static function leer_sello( string $token ): ?int {
		$partes = explode( '.', trim( $token ) );
		if ( 2 !== count( $partes ) || ! ctype_digit( $partes[0] ) ) {
			return null;
		}

		$esperado = substr( hash_hmac( 'sha256', $partes[0], wp_salt( 'nonce' ) ), 0, 20 );
		if ( ! hash_equals( $esperado, $partes[1] ) ) {
			return null;
		}

		return (int) $partes[0];
	}

	/**
	 * ¿Llegó sin sello? No es motivo de bloqueo, pero conviene saberlo.
	 *
	 * @param array<string, mixed> $datos Datos del envío.
	 * @return bool
	 */
	public static function sin_sello( array $datos ): bool {
		$sello = (string) ( $datos[ self::SELLO ] ?? '' );

		return '' === $sello || null === self::leer_sello( $sello );
	}

	/**
	 * ¿Es un bot? Devuelve el motivo, o `null` si parece una persona.
	 *
	 * @param array<string, mixed> $datos Datos del envío ($_POST saneado).
	 * @param int|null             $ahora Momento actual (para las pruebas).
	 * @return string|null honeypot | rapido | caducado
	 */
	public static function motivo( array $datos, ?int $ahora = null ): ?string {
		if ( '' !== trim( (string) ( $datos[ self::TRAMPA ] ?? '' ) ) ) {
			return 'honeypot';
		}

		$sello = (string) ( $datos[ self::SELLO ] ?? '' );

		// Sin sello no se bloquea: una página cacheada puede servirse sin él y no se puede castigar a una
		// persona por eso. Queda registrado (`sin_sello`) para poder medirlo, y el limitador sigue ahí.
		if ( '' === $sello ) {
			return null;
		}

		$momento = self::leer_sello( $sello );
		if ( null === $momento ) {
			return null;
		}

		$ahora        = $ahora ?? time();
		$transcurrido = $ahora - $momento;

		if ( $transcurrido < self::MINIMO_SEGUNDOS ) {
			return 'rapido';
		}

		if ( $transcurrido > self::VIGENCIA_HORAS * HOUR_IN_SECONDS ) {
			return 'caducado';
		}

		return null;
	}
}
