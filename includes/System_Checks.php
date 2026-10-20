<?php
/**
 * Diagnóstico agregado del ecosistema: el núcleo pregunta, cada módulo aporta.
 *
 * @package Convoca\Core
 */

namespace Convoca\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Recolector de las comprobaciones de salud de todos los módulos.
 *
 * Antes el núcleo tenía cuatro bloques con `class_exists`/`ReflectionMethod` que llamaban a clases de
 * otros plugins (`Members\Admin_Settings`, `Enroll\Admin_Settings`, `Gateway\Diagnostic` y la función
 * `Shifts\convoca_shifts_get_system_checks`). La dirección era la mala: el núcleo dependía de los
 * addons. Ahora el núcleo **pregunta** por el filtro `convoca_system_checks` y cada módulo contesta con
 * su bloque.
 *
 * Lo que el núcleo sigue sabiendo es la lista de módulos **esperados** (nombres de producto, no
 * clases): es lo que permite seguir avisando de «Plugin no activo» cuando un addon no aporta nada. Sin
 * esa lista, un módulo desactivado desaparecería de la página en silencio, que es peor que el aviso.
 */
class System_Checks {

	/**
	 * Módulos que la página de salud espera, en el orden en que se pintan.
	 *
	 * @var array<string, string>
	 */
	private const ESPERADOS = array(
		'members' => 'Convoca Members',
		'enroll'  => 'Convoca Enroll',
		'gateway' => 'Convoca Gateway',
		'turnos'  => 'Convoca Shifts',
	);

	/**
	 * Todas las comprobaciones: las que aportan los módulos y los avisos de los que faltan.
	 *
	 * @param bool $force Si se ignoran las cachés de cada módulo.
	 * @return array<int, array<string, string>> Lista de comprobaciones normalizadas.
	 */
	public static function recoger( bool $force = false ): array {
		$fallo     = '';
		$aportados = array();

		try {
			/**
			 * Comprobaciones de salud de un módulo.
			 *
			 * @param array<string, array<int, array<string, string>>> $aportados Bloques ya aportados.
			 * @param bool                                             $force     Si se ignoran las cachés.
			 */
			$recibido  = apply_filters( 'convoca_system_checks', array(), $force );
			$aportados = is_array( $recibido ) ? $recibido : array();
		} catch ( \Throwable $e ) {
			// Un módulo que revienta no puede tumbar la página de salud: se pinta el fallo y se sigue.
			$fallo = $e->getMessage();
		}

		$checks = array();

		foreach ( self::ESPERADOS as $slug => $nombre ) {
			$bloque = ( isset( $aportados[ $slug ] ) && is_array( $aportados[ $slug ] ) ) ? $aportados[ $slug ] : array();

			if ( array() === $bloque ) {
				$checks[] = self::aviso_sin_plugin( $slug );
				continue;
			}

			foreach ( $bloque as $check ) {
				if ( is_array( $check ) ) {
					$checks[] = self::normalizar( $check, $nombre );
				}
			}
		}

		// Un módulo que no está en la lista también se pinta: si aparece, es que existe.
		foreach ( $aportados as $slug => $bloque ) {
			if ( isset( self::ESPERADOS[ $slug ] ) || ! is_array( $bloque ) ) {
				continue;
			}
			foreach ( $bloque as $check ) {
				if ( is_array( $check ) ) {
					$checks[] = self::normalizar( $check, (string) $slug );
				}
			}
		}

		if ( '' !== $fallo ) {
			$checks[] = array(
				'title'   => __( 'Diagnóstico del ecosistema', 'convoca-core' ),
				'status'  => 'error',
				'message' => $fallo,
				'fix'     => '',
				'fix_url' => '',
			);
		}

		return $checks;
	}

	/**
	 * Aviso de módulo que no aporta nada (desactivado, o sin diagnóstico propio).
	 *
	 * @param string $slug Módulo esperado.
	 * @return array<string, string>
	 */
	private static function aviso_sin_plugin( string $slug ): array {
		$mensaje = ( 'turnos' === $slug )
			? __( 'Plugin no activo o sin función de diagnóstico.', 'convoca-core' )
			: __( 'Plugin no activo.', 'convoca-core' );

		return array(
			'title'   => self::ESPERADOS[ $slug ],
			'status'  => 'warning',
			'message' => $mensaje,
			'fix'     => '',
			'fix_url' => '',
		);
	}

	/**
	 * Deja una comprobación con las claves que espera la plantilla.
	 *
	 * @param array<string, mixed> $check       Comprobación aportada por el módulo.
	 * @param string               $por_defecto Título si el módulo no pone ninguno.
	 * @return array<string, string>
	 */
	private static function normalizar( array $check, string $por_defecto ): array {
		return array(
			'title'   => isset( $check['title'] ) ? (string) $check['title'] : $por_defecto,
			'status'  => isset( $check['status'] ) ? (string) $check['status'] : 'warning',
			'message' => isset( $check['message'] ) ? (string) $check['message'] : '',
			'fix'     => isset( $check['fix'] ) ? (string) $check['fix'] : '',
			'fix_url' => isset( $check['fix_url'] ) ? (string) $check['fix_url'] : '',
		);
	}
}
