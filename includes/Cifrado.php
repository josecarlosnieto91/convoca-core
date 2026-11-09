<?php

/**
 * Convoca Core — cifrado de datos personales en reposo.
 *
 * Primitivas únicas para no repartir criptografía por los plugins. El formato lleva versión y
 * método para poder rotar la llave sin dejar datos ilegibles. La llave vive fuera de la base de
 * datos y del repositorio: `CONVOCA_ENCRYPTION_KEY` en `wp-config.php` (32 bytes en base64).
 *
 * Lo que este fichero NO hace: decidir qué campos se cifran ni quién puede descifrarlos. Eso vive
 * en Members (`Datos_Socio`) y pasa por capability y auditoría.
 *
 * @package    Convoca\Core
 * @subpackage Includes
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 */

namespace Convoca\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cifrado autenticado y índice ciego.
 */
final class Cifrado {

	/**
	 * Prefijo del formato. Cambiar la versión obliga a migrar, no a adivinar.
	 */
	public const PREFIX = 'enc:v1:';

	/**
	 * Método: sodium secretbox.
	 */
	private const METODO_SODIUM = 's';

	/**
	 * Método: AES-256-GCM de OpenSSL.
	 */
	private const METODO_OPENSSL = 'g';

	/**
	 * ¿Hay llave válida y algún motor de cifrado?
	 *
	 * @return bool
	 */
	public static function disponible(): bool {
		return '' !== self::clave()
			&& ( function_exists( 'sodium_crypto_secretbox' ) || function_exists( 'openssl_encrypt' ) );
	}

	/**
	 * Cifra un valor. Vacío y ya cifrado se devuelven tal cual.
	 *
	 * Si no hay llave, devuelve el valor original y avisa: perder el dato es peor que no cifrarlo.
	 *
	 * @param string $claro Valor en claro.
	 * @return string
	 */
	public static function cifrar( string $claro ): string {
		if ( '' === $claro || self::es_cifrado( $claro ) ) {
			return $claro;
		}

		$clave = self::clave();

		if ( '' === $clave ) {
			self::avisar( 'No hay CONVOCA_ENCRYPTION_KEY válida; el valor se guarda sin cifrar.' );
			return $claro;
		}

		if ( function_exists( 'sodium_crypto_secretbox' ) ) {
			$nonce   = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$cifrado = sodium_crypto_secretbox( $claro, $nonce, $clave );

			return self::PREFIX . base64_encode( self::METODO_SODIUM . $nonce . $cifrado ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- formato binario propio, no ofuscación.
		}

		$nonce = random_bytes( 12 );
		$tag   = '';
		$texto = openssl_encrypt( $claro, 'aes-256-gcm', $clave, OPENSSL_RAW_DATA, $nonce, $tag, '', 16 );

		if ( false === $texto ) {
			self::avisar( 'OpenSSL no pudo cifrar el valor; se guarda sin cifrar.' );
			return $claro;
		}

		return self::PREFIX . base64_encode( self::METODO_OPENSSL . $nonce . $tag . $texto ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- formato binario propio, no ofuscación.
	}

	/**
	 * Descifra un valor. Un valor sin prefijo se considera texto antiguo y se devuelve tal cual.
	 *
	 * Si el valor está cifrado y no se puede autenticar, devuelve cadena vacía: nunca se devuelve
	 * el ciphertext como si fuera el dato.
	 *
	 * @param string $valor Valor almacenado.
	 * @return string
	 */
	public static function descifrar( string $valor ): string {
		if ( '' === $valor || ! self::es_cifrado( $valor ) ) {
			return $valor;
		}

		$clave = self::clave();

		if ( '' === $clave ) {
			self::avisar( 'No hay CONVOCA_ENCRYPTION_KEY válida; no se puede descifrar.' );
			return '';
		}

		$raw = base64_decode( substr( $valor, strlen( self::PREFIX ) ), true );

		if ( false === $raw || strlen( $raw ) < 2 ) {
			return '';
		}

		$metodo  = $raw[0];
		$payload = substr( $raw, 1 );

		if ( self::METODO_SODIUM === $metodo && function_exists( 'sodium_crypto_secretbox_open' ) ) {
			if ( strlen( $payload ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
				return '';
			}

			$nonce   = substr( $payload, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$cifrado = substr( $payload, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$claro   = sodium_crypto_secretbox_open( $cifrado, $nonce, $clave );

			return false === $claro ? '' : $claro;
		}

		if ( self::METODO_OPENSSL === $metodo && function_exists( 'openssl_decrypt' ) ) {
			if ( strlen( $payload ) <= 12 + 16 ) {
				return '';
			}

			$nonce   = substr( $payload, 0, 12 );
			$tag     = substr( $payload, 12, 16 );
			$cifrado = substr( $payload, 28 );
			$claro   = openssl_decrypt( $cifrado, 'aes-256-gcm', $clave, OPENSSL_RAW_DATA, $nonce, $tag );

			return false === $claro ? '' : $claro;
		}

		return '';
	}

	/**
	 * ¿El valor ya está cifrado?
	 *
	 * @param string $valor Valor.
	 * @return bool
	 */
	public static function es_cifrado( string $valor ): bool {
		return str_starts_with( $valor, self::PREFIX );
	}

	/**
	 * Índice ciego para búsquedas por igualdad.
	 *
	 * No es un hash de contraseña: es un HMAC con la llave del sitio. El mismo valor normalizado
	 * produce el mismo índice; sin la llave no se puede reconstruir el valor.
	 *
	 * @param string $valor   Valor original.
	 * @param string $dominio Dominio del índice (dni, iban...).
	 * @return string
	 */
	public static function indice_ciego( string $valor, string $dominio = 'dni' ): string {
		$normalizado = strtoupper( (string) preg_replace( '/[^0-9A-Za-z]/', '', $valor ) );
		$clave       = self::clave();

		if ( '' === $normalizado || '' === $clave ) {
			return '';
		}

		return hash_hmac( 'sha256', $dominio . '|' . $normalizado, $clave );
	}

	/**
	 * Llave binaria de 32 bytes.
	 *
	 * Acepta base64 (recomendado) o 32 bytes en crudo. Cualquier otra cosa se rechaza.
	 *
	 * @return string
	 */
	public static function clave(): string {
		$clave = '';

		if ( defined( 'CONVOCA_ENCRYPTION_KEY' ) && is_string( CONVOCA_ENCRYPTION_KEY ) ) {
			$clave = CONVOCA_ENCRYPTION_KEY;
		}

		/**
		 * Permite inyectar la llave en pruebas o en instalaciones que la cargan por otro medio.
		 *
		 * @param string $clave Llave actual.
		 */
		$clave = (string) apply_filters( 'convoca_cifrado_clave', $clave );
		$clave = trim( $clave );

		if ( '' === $clave ) {
			return '';
		}

		$bin = base64_decode( $clave, true );

		if ( false !== $bin && 32 === strlen( $bin ) ) {
			return $bin;
		}

		return 32 === strlen( $clave ) ? $clave : '';
	}

	/**
	 * Avisa una sola vez por petición para no inundar el log.
	 *
	 * @param string $mensaje Mensaje.
	 * @return void
	 */
	private static function avisar( string $mensaje ): void {
		static $avisado = false;

		if ( $avisado || ! class_exists( __NAMESPACE__ . '\\Logger' ) ) {
			return;
		}

		$avisado = true;
		Logger::warning( $mensaje, 'Core/Cifrado' );
	}
}
