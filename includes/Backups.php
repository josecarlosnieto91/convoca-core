<?php
/**
 * Copias de ajustes y plantillas, con retención (issue #9).
 *
 * Antes de sobrescribir unos ajustes, el plugin guarda una copia. Estaba bien guardarla; el defecto era que
 * **no se borraba nunca**: cada importación o restauración dejaba una opción nueva con la fecha en el nombre
 * (`convoca_enroll_settings_backup_2026-09-09-214906`) y ninguna se limpiaba. En un sitio que importe ajustes
 * con frecuencia eso crece sin techo, y cada copia lleva dentro los ajustes completos.
 *
 * Aquí se guardan como una **lista con las últimas cinco** dentro de una sola opción, que es el patrón que ya
 * usaba `Admin_Templates::create_versioned_backup()` para lo mismo: no se poda lo que crece, se deja de crear
 * opciones sin fin.
 *
 * @package Convoca\Core
 */

namespace Convoca\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Copias de seguridad con retención.
 */
final class Backups {

	/**
	 * Cuántas copias se conservan por ajuste.
	 */
	public const RETENCION = 5;

	/**
	 * Sufijo de la opción que las guarda.
	 */
	public const SUFIJO = '_backups';

	/**
	 * Guarda una copia de $valor bajo la clave dada y deja solo las últimas `RETENCION`.
	 *
	 * @param string $clave Ajuste del que se copia (p. ej. `convoca_members_settings`).
	 * @param mixed  $valor Valor actual, antes de sobrescribirlo.
	 * @return int Cuántas copias quedan guardadas.
	 */
	public static function anadir( string $clave, $valor ): int {
		$copias = get_option( $clave . self::SUFIJO, array() );
		if ( ! is_array( $copias ) ) {
			$copias = array();
		}

		array_unshift(
			$copias,
			array(
				'fecha'   => wp_date( 'Y-m-d H:i:s' ),
				'usuario' => function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0,
				'datos'   => $valor,
			)
		);

		$copias = array_slice( $copias, 0, self::RETENCION );
		update_option( $clave . self::SUFIJO, $copias, false );

		return count( $copias );
	}

	/**
	 * Las copias guardadas de un ajuste, de la más reciente a la más antigua.
	 *
	 * @param string $clave Ajuste del que se copió.
	 * @return array<int, array{fecha: string, usuario: int, datos: mixed}>
	 */
	public static function de( string $clave ): array {
		$copias = get_option( $clave . self::SUFIJO, array() );

		return is_array( $copias ) ? $copias : array();
	}
}
