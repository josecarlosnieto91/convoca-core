<?php
/**
 * La revalidación de la licencia solo existe si hay licencia.
 *
 * Regla: un sitio sin clave no habla con ningún servicio externo. Y con clave, la
 * revalidación tiene que estar programada de verdad: el hook existía, pero nadie
 * programaba el evento, así que la licencia no se revalidaba nunca.
 */

namespace Convoca\Core\Tests\Unit;

use Convoca\Core\License_Manager;
use PHPUnit\Framework\TestCase;

final class LicenseCronTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['_wp_stores']['options'] = array();
		$GLOBALS['_wp_cron'] = array( 'programados' => array(), 'agendados' => array(), 'limpiados' => array(), 'consultados' => array() );
	}

	/** @param array<string, mixed> $extra */
	private function conLicencia( array $extra = array() ): void {
		$licencia = array_merge( array(
			'key'      => 'AAAA-BBBB-CCCC-DDDD',
			'status'   => 'active',
			'type'     => 'unlimited',
			'features' => array(),
			'expires'  => '',
		), $extra );
		update_option( License_Manager::OPTION_KEY, $licencia );
	}

	public function test_sin_licencia_no_se_programa_nada_y_se_limpia(): void {
		License_Manager::ensure_cron( 1000 );

		$this->assertSame( array(), $GLOBALS['_wp_cron']['agendados'], 'Sin licencia no se programa ninguna llamada.' );
		$this->assertContains( 'convoca_license_validate', $GLOBALS['_wp_cron']['limpiados'], 'Y lo que hubiera programado, se quita.' );
	}

	public function test_con_licencia_se_programa_la_revalidacion_semanal(): void {
		$this->conLicencia();
		License_Manager::ensure_cron( 1000 );

		$this->assertCount( 1, $GLOBALS['_wp_cron']['agendados'] );
		$evento = $GLOBALS['_wp_cron']['agendados'][0];
		$this->assertSame( 'convoca_license_validate', $evento['hook'] );
		$this->assertSame( 'weekly', $evento['recurrencia'], 'Cada semana, no en cada carga.' );
		$this->assertSame( 1000 + HOUR_IN_SECONDS, $evento['ts'], 'La primera comprobación no es inmediata.' );
	}

	public function test_no_se_programa_dos_veces(): void {
		$this->conLicencia();
		$GLOBALS['_wp_cron']['programados']['convoca_license_validate'] = 500;
		License_Manager::ensure_cron( 1000 );

		$this->assertSame( array(), $GLOBALS['_wp_cron']['agendados'], 'Ya estaba programado: no se duplica.' );
	}

	public function test_una_clave_en_blanco_cuenta_como_sin_licencia(): void {
		$this->conLicencia( array( 'key' => '   ' ) );
		License_Manager::ensure_cron( 1000 );

		$this->assertSame( array(), $GLOBALS['_wp_cron']['agendados'] );
		$this->assertContains( 'convoca_license_validate', $GLOBALS['_wp_cron']['limpiados'] );
	}

	public function test_quedarse_sin_licencia_quita_el_evento(): void {
		$this->conLicencia();
		License_Manager::ensure_cron( 1000 );
		$this->assertCount( 1, $GLOBALS['_wp_cron']['agendados'] );

		update_option( License_Manager::OPTION_KEY, array( 'key' => '', 'status' => 'inactive', 'type' => 'free', 'features' => array(), 'expires' => '' ) );
		License_Manager::ensure_cron( 2000 );
		$this->assertContains( 'convoca_license_validate', $GLOBALS['_wp_cron']['limpiados'] );
	}
}
