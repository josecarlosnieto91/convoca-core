<?php
/**
 * Limpieza de eventos de cron con nombres anteriores al prefijo `convoca_`.
 *
 * Vive en su propia clase a propósito: metido como un método más de `Installer`, este
 * fichero cruzaba sus umbrales de complejidad de clase y el gate de calidad subía de 119 a
 * 122. Una responsabilidad pequeña y separada cuesta menos que engordar el instalador.
 *
 * @package Convoca\Core
 */

namespace Convoca\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Retira ganchos de cron huérfanos de nomenclaturas anteriores.
 */
class Cron_Limpieza {

	/**
	 * Ganchos de la nomenclatura anterior (`bdv_`/`bde_`) que ya no tiene nadie.
	 *
	 * @var array<int, string>
	 */
	private const LEGACY = array(
		'bdv_weekly_event',
		'bdv_log_purge',
		'bdv_daily_event',
		'bde_daily_maintenance',
	);

	/**
	 * Retira los eventos huérfanos.
	 *
	 * Sin oyente siguen ocupando sitio en el array de cron —que WordPress carga en cada
	 * petición— y despertando WP-Cron para nada. Medido en la demo el 1/11/2026: cuatro
	 * eventos huérfanos de 50 programados.
	 */
	public static function limpiar(): void {
		foreach ( self::LEGACY as $gancho ) {
			wp_clear_scheduled_hook( $gancho );
		}
	}
}
