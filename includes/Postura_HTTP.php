<?php
/**
 * Postura HTTP del sitio (issue #11).
 *
 * La auditoría de la pasada 8 observó que la demo anunciaba la versión exacta de PHP en cada respuesta
 * (`x-powered-by: PHP/8.5.10`). No es un fallo explotable por sí mismo, pero es información que se regala: quien
 * busque qué atacar agradece saber la versión. Aquí se retira esa cabecera y nada más.
 *
 * Lo que **no** hace este fichero, a propósito: tocar la `Content-Security-Policy`. Va en modo informe por
 * decisión tomada —pasarla a aplicada exige revisar antes que nada legítimo se rompa: la política lleva
 * `unsafe-inline` y `unsafe-eval`, y el sitio usa bloques y scripts propios—. Eso se hará por fases y midiendo.
 *
 * @package Convoca\Core
 */

namespace Convoca\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Cabeceras que no hacen falta.
 */
final class Postura_HTTP {

	/**
	 * Engancha la limpieza de cabeceras.
	 */
	public static function init(): void {
		add_action( 'send_headers', array( __CLASS__, 'quitar_cabeceras' ) );
	}

	/**
	 * Retira `X-Powered-By`.
	 *
	 * Va en `send_headers` (antes de que WordPress envíe nada) y no en `init`: quitarla cuando ya se ha enviado la
	 * cabecera no hace nada y no avisa.
	 */
	public static function quitar_cabeceras(): void {
		if ( ! headers_sent() ) {
			header_remove( 'X-Powered-By' );
		}
	}
}
