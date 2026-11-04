<?php
/**
 * Comandos WP-CLI de los valores del sitio.
 *
 * El mapa de claves heredadas de los datos de evento es de una instalación concreta y se carga una vez,
 * no se edita a mano en una pantalla: este comando lo deja en la opción y el filtro del producto lo lee.
 *
 * @package Convoca\Core
 */

namespace Convoca\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

/**
 * Valores del sitio por consola.
 */
class CLI_Site extends \WP_CLI_Command {

	/**
	 * Carga el mapa de claves heredadas de los datos de evento.
	 *
	 * ## OPTIONS
	 *
	 * --claves=<pares>
	 * : Pares «clave_canonica=clave_antigua» separados por comas.
	 *
	 * ## EXAMPLES
	 *
	 *     wp convoca-core sitio claves-legado --claves="_convoca_event_start_date=_viejo_start,_convoca_has_event=_viejo_has"
	 *
	 * @param array<int, string>    $args       Posicionales (ninguno).
	 * @param array<string, string> $assoc_args Opciones.
	 * @return void
	 */
	public function claves_legado( $args, $assoc_args ): void {
		unset( $args );

		$crudo = (string) ( $assoc_args['claves'] ?? '' );
		if ( '' === $crudo ) {
			\WP_CLI::error( 'Falta --claves="canonica=antigua,..."' );
		}

		$mapa = array();
		foreach ( explode( ',', $crudo ) as $par ) {
			$partes = array_map( 'trim', explode( '=', $par, 2 ) );
			if ( count( $partes ) < 2 ) {
				continue;
			}

			$canonica = (string) preg_replace( '/[^a-z0-9_]/', '', strtolower( $partes[0] ) );
			$antigua  = (string) preg_replace( '/[^a-z0-9_]/', '', strtolower( $partes[1] ) );

			if ( '' === $canonica || '' === $antigua ) {
				continue;
			}

			$mapa[ $canonica ] = $antigua;
		}

		if ( empty( $mapa ) ) {
			\WP_CLI::error( 'No hay pares válidos en --claves.' );
		}

		$datos                = Site_Settings::datos();
		$datos['legacy_keys'] = $mapa;
		update_option( Site_Settings::OPTION, $datos );

		\WP_CLI::success( sprintf( '%d clave(s) heredada(s) cargada(s).', count( $mapa ) ) );
	}

	/**
	 * Muestra los valores guardados.
	 *
	 * ## EXAMPLES
	 *
	 *     wp convoca-core sitio mostrar
	 *
	 * @param array<int, string>    $args       Posicionales (ninguno).
	 * @param array<string, string> $assoc_args Opciones (ninguna).
	 * @return void
	 */
	public function mostrar( $args, $assoc_args ): void {
		unset( $args, $assoc_args );

		\WP_CLI::line( (string) wp_json_encode( Site_Settings::datos(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
	}

	/**
	 * Vacía las claves heredadas (los demás valores no se tocan).
	 *
	 * ## EXAMPLES
	 *
	 *     wp convoca-core sitio limpiar-claves-legado
	 *
	 * @param array<int, string>    $args       Posicionales (ninguno).
	 * @param array<string, string> $assoc_args Opciones (ninguna).
	 * @return void
	 */
	public function limpiar_claves_legado( $args, $assoc_args ): void {
		unset( $args, $assoc_args );

		$datos = Site_Settings::datos();
		unset( $datos['legacy_keys'] );
		update_option( Site_Settings::OPTION, $datos );

		\WP_CLI::success( 'Claves heredadas vaciadas.' );
	}
}
