<?php
/**
 * Cifrado transparente de opciones con datos sensibles.
 *
 * El IBAN de la entidad vive en arrays serializados (`convoca_members_settings` y
 * `convoca_gateway_settings`) que leen varios plugins. En vez de tocar cada lector, se filtra la
 * opción: al leer se descifra `iban`; al guardar se cifra. Así el resto del código sigue viendo el
 * IBAN en claro y no hay dos caminos que puedan divergir.
 *
 * @package    Convoca\Core
 * @subpackage Includes
 */

namespace Convoca\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Filtros de lectura y escritura de opciones.
 */
final class Opciones_Cifradas {

	/**
	 * Opciones cuyo campo `iban` se cifra.
	 *
	 * @var string[]
	 */
	private const OPCIONES = array( 'convoca_members_settings', 'convoca_gateway_settings' );

	/**
	 * Engancha los filtros.
	 *
	 * @return void
	 */
	public static function init(): void {
		foreach ( self::OPCIONES as $opcion ) {
			add_filter( 'option_' . $opcion, array( self::class, 'leer' ) );
			add_filter( 'pre_update_option_' . $opcion, array( self::class, 'escribir' ) );
		}
	}

	/**
	 * Descifra el IBAN al leer la opción.
	 *
	 * @param mixed $valor Valor de la opción.
	 * @return mixed
	 */
	public static function leer( $valor ) {
		if ( ! is_array( $valor ) || ! isset( $valor['iban'] ) || ! is_string( $valor['iban'] ) ) {
			return $valor;
		}

		$valor['iban'] = Cifrado::descifrar( $valor['iban'] );

		return $valor;
	}

	/**
	 * Cifra el IBAN al guardar la opción.
	 *
	 * @param mixed $valor Valor nuevo.
	 * @return mixed
	 */
	public static function escribir( $valor ) {
		if ( ! is_array( $valor ) || ! isset( $valor['iban'] ) || ! is_string( $valor['iban'] ) || '' === $valor['iban'] ) {
			return $valor;
		}

		$valor['iban'] = Cifrado::cifrar( $valor['iban'] );

		return $valor;
	}
}
