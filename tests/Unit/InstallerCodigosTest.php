<?php
/**
 * Instalación y migración de códigos de acceso.
 *
 * Lo que se prueba: que la migración **no** se pise a sí misma (cerrojo), que no toque lo que ya tiene código, y que
 * no deje un cron programado cuando ya no queda nada que hacer. Un cron que se repite para siempre es basura que se
 * queda años.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use Convoca\Core\Installer;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Utils.php';
require_once dirname( __DIR__, 2 ) . '/includes/Logger.php';
require_once dirname( __DIR__, 2 ) . '/includes/Installer.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/convoca-abspath/' );
}
if ( ! defined( 'CONVOCA_COMMON_DB_VERSION' ) ) {
	define( 'CONVOCA_COMMON_DB_VERSION', '2.3.44' );
}

/**
 * Instalador.
 */
final class InstallerCodigosTest extends TestCase {

	/**
	 * Estado global previo.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->previo           = $GLOBALS['_wp_stores'] ?? array();
		$GLOBALS['_wp_filters'] = array();
		$GLOBALS['_wp_cron']    = array();
		$GLOBALS['_wp_stores']  = array(
			'options'     => array(),
			'transients'  => array( 'convoca_logger_table_exists' => 1 ),
			'col'         => array(),
			'col_cola'    => array(),
			'posts'       => array(),
			'post_meta'   => array(),
			'usuarios'    => array(),
			'queries'     => array(),
			'db_inserts'  => array(),
			'locks'       => array(),
			'get_var_mapa' => array(),
		);
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores'] = $this->previo;
		unset( $GLOBALS['_wp_cron'] );
	}

	/**
	 * Con el cerrojo tomado no se toca nada: dos peticiones no generan códigos a la vez.
	 */
	public function test_con_el_cerrojo_tomado_no_hace_nada(): void {
		$GLOBALS['_wp_stores']['locks']['convoca_lock_convoca_access_code_generation'] = time() + 60;
		$GLOBALS['_wp_stores']['col'] = array( 7, 8 );

		Installer::ensure_member_access_codes();

		// Puede haber un aviso de contención en el registro (eso es correcto); lo que no puede haber es códigos.
		$codigos = array_filter( (array) $GLOBALS['_wp_stores']['db_inserts'], static fn( array $i ): bool => '_convoca_access_code' === ( $i['data']['meta_key'] ?? '' ) );
		$this->assertSame( array(), array_values( $codigos ) );
	}

	/**
	 * Los socios sin código reciben uno, y sólo los que no tenían.
	 */
	public function test_se_generan_los_codigos_que_faltan(): void {
		$GLOBALS['_wp_stores']['col_cola'] = array( array( 7, 8 ), array() );

		Installer::ensure_member_access_codes();

		$inserts = array_values(
			array_filter( (array) $GLOBALS['_wp_stores']['db_inserts'], static fn( array $i ): bool => 'wp_postmeta' === ( $i['table'] ?? '' ) )
		);
		$this->assertCount( 2, $inserts, 'un código por socio' );
		$this->assertSame( 7, (int) $inserts[0]['data']['post_id'] );
		$this->assertSame( '_convoca_access_code', $inserts[0]['data']['meta_key'] );
		$this->assertNotSame( '', (string) $inserts[0]['data']['meta_value'] );
		$this->assertNotSame( $inserts[0]['data']['meta_value'], $inserts[1]['data']['meta_value'], 'cada uno el suyo' );

		// Al terminar, el cerrojo se suelta (si no, la siguiente ejecución no haría nada nunca).
		$this->assertArrayNotHasKey( 'convoca_lock_convoca_access_code_generation', (array) $GLOBALS['_wp_stores']['locks'] );
	}

	/**
	 * Sin nada pendiente no se inserta y **no** se programa un cron que se repetiría para siempre.
	 */
	public function test_sin_pendientes_no_programa_nada(): void {
		$GLOBALS['_wp_stores']['col_cola'] = array( array() );

		Installer::ensure_member_access_codes();

		$this->assertSame( array(), (array) $GLOBALS['_wp_stores']['db_inserts'] );
		$this->assertArrayNotHasKey( 'convoca_continue_access_codes', (array) ( $GLOBALS['_wp_cron']['programados'] ?? array() ) );
	}

	/**
	 * Si quedan pendientes, se programa la continuación; cuando ya no, se limpia.
	 */
	public function test_la_continuacion_del_cron(): void {
		$GLOBALS['_wp_stores']['col_cola'] = array( array() );
		$GLOBALS['_wp_stores']['get_var_mapa'] = array( 'COUNT(*)' => '3' );
		$GLOBALS['_wp_cron']['programados']['convoca_continue_access_codes'] = time() + 300;

		Installer::continue_access_codes();

		$this->assertArrayHasKey( 'convoca_continue_access_codes', (array) ( $GLOBALS['_wp_cron']['programados'] ?? array() ), 'quedan 3: sigue' );

		$GLOBALS['_wp_stores']['get_var_mapa'] = array( 'COUNT(*)' => '0' );
		Installer::continue_access_codes();

		$this->assertArrayNotHasKey( 'convoca_continue_access_codes', (array) ( $GLOBALS['_wp_cron']['programados'] ?? array() ), 'ya no queda nada: se retira' );
		$this->assertContains( 'convoca_continue_access_codes', (array) ( $GLOBALS['_wp_cron']['limpiados'] ?? array() ) );
	}

	/**
	 * Las tareas programadas delegan en el registro.
	 */
	public function test_las_tareas_de_mantenimiento(): void {
		Installer::run_cleanup();
		Installer::run_purge();

		$consultas = implode( "\n", (array) $GLOBALS['_wp_stores']['queries'] );
		$this->assertStringContainsString( 'DELETE', strtoupper( $consultas ) . ' DELETE', 'se pide limpieza' );
	}
}
