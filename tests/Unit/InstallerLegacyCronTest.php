<?php
/**
 * Limpieza de los eventos de cron con el prefijo viejo.
 *
 * Por qué existe: en la demo quedaban cuatro eventos `bdv_*`/`bde_*` programados, sin oyente
 * desde el renombrado a `convoca_*`. El array de cron se carga en cada petición, así que cada
 * evento huérfano se paga siempre y además despierta WP-Cron para nada.
 *
 * Prueba en negativo: si `limpiar_ganchos_legacy()` dejara de llamar a `wp_clear_scheduled_hook`,
 * las cuatro comprobaciones caen.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use Convoca\Core\Installer;
use PHPUnit\Framework\TestCase;

/**
 * Los ganchos antiguos se retiran.
 */
final class InstallerLegacyCronTest extends TestCase {

	/**
	 * Prepara el registro de limpiezas.
	 */
	protected function setUp(): void {
		$GLOBALS['_wp_cron'] = array( 'programados' => array(), 'limpiados' => array() );
	}

	/**
	 * Los cuatro ganchos antiguos se limpian.
	 */
	public function test_limpia_los_ganchos_anteriores_al_prefijo(): void {
		Installer::limpiar_ganchos_legacy();

		$limpiados = $GLOBALS['_wp_cron']['limpiados'];
		foreach ( array( 'bdv_weekly_event', 'bdv_log_purge', 'bdv_daily_event', 'bde_daily_maintenance' ) as $gancho ) {
			$this->assertContains( $gancho, $limpiados, "No se ha retirado el evento huérfano «{$gancho}»." );
		}
	}

	/**
	 * No toca ningún gancho con el prefijo actual.
	 */
	public function test_no_toca_los_ganchos_actuales(): void {
		$GLOBALS['_wp_cron']['programados'] = array(
			'convoca_daily_event'   => time(),
			'convoca_log_cleanup'   => time(),
		);

		Installer::limpiar_ganchos_legacy();

		$this->assertSame( array(), array_intersect(
			array( 'convoca_daily_event', 'convoca_log_cleanup' ),
			$GLOBALS['_wp_cron']['limpiados']
		) );
		$this->assertArrayHasKey( 'convoca_daily_event', $GLOBALS['_wp_cron']['programados'] );
	}
}
