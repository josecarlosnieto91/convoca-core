<?php
/**
 * Migraciones versionadas: ejecutar una vez, en orden, y no perder lo ya hecho.
 *
 * La regla que importa: si la migración 2.0 falla, la versión guardada se queda en la **última que salió bien**, para
 * que el siguiente intento no se salte nada. Y una migración de datos o se aplica entera o no se aplica (transacción).
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use Convoca\Core\Upgrade_Manager;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Utils.php';
require_once dirname( __DIR__, 2 ) . '/includes/Logger.php';
require_once dirname( __DIR__, 2 ) . '/includes/Upgrade_Manager.php';

/**
 * Gestor de migraciones de prueba: versión y callbacks controlados desde el test.
 */
final class Upgrade_Manager_De_Prueba extends Upgrade_Manager {

	/**
	 * Versión «actual» del plugin.
	 *
	 * @var string
	 */
	public string $actual = '2.0.0';

	/**
	 * Callbacks por versión.
	 *
	 * @var array<string, callable>
	 */
	public array $callbacks = array();

	/**
	 * Versión actual.
	 *
	 * @return string
	 */
	protected function get_db_version(): string {
		return $this->actual;
	}

	/**
	 * Opción donde se guarda.
	 *
	 * @return string
	 */
	protected function get_option_name(): string {
		return 'convoca_prueba_db_version';
	}

	/**
	 * Prefijo de transients.
	 *
	 * @return string
	 */
	protected function get_transient_prefix(): string {
		return 'convoca_prueba';
	}

	/**
	 * Callbacks.
	 *
	 * @return array<string, callable>
	 */
	protected function get_upgrade_callbacks(): array {
		return $this->callbacks;
	}
}

/**
 * Migraciones.
 */
final class UpgradeManagerTest extends TestCase {

	/**
	 * Estado global previo.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Gestor.
	 *
	 * @var Upgrade_Manager_De_Prueba
	 */
	private Upgrade_Manager_De_Prueba $gestor;

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->previo           = $GLOBALS['_wp_stores'] ?? array();
		$GLOBALS['_wp_filters'] = array();
		$GLOBALS['_wp_hooks']   = array();
		$GLOBALS['_wp_stores']  = array(
			'options'     => array(),
			'transients'  => array(),
			'queries'     => array(),
			'locks'       => array(),
			'db_inserts'  => array(),
			'post_meta'   => array(),
			'posts'       => array(),
			'usuarios'    => array(),
			'get_var_mapa' => array(),
		);
		$this->gestor           = new Upgrade_Manager_De_Prueba();
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores'] = $this->previo;
	}

	/**
	 * Al día: no se ejecuta nada y se cachea la comprobación.
	 */
	public function test_al_dia_no_hace_nada(): void {
		$this->gestor->actual    = '1.0.0';
		$this->gestor->callbacks = array( '1.0.0' => static fn() => print( 'NO' ) );
		// «Al día» hay que sembrarlo: sin opción guardada la versión es `0.0.0` y la migración **sí** tocaría.
		$GLOBALS['_wp_stores']['options']['convoca_prueba_db_version'] = '1.0.0';

		ob_start();
		$this->gestor->maybe_upgrade();
		$salida = (string) ob_get_clean();

		$this->assertSame( '', $salida, 'no se ejecutan migraciones' );
		$this->assertNotEmpty( get_transient( 'convoca_prueba_last_version_check' ), 'queda cacheado' );
	}

	/**
	 * Con la versión atrasada: se ejecutan **en orden** las que tocan, y se guarda la nueva.
	 */
	public function test_las_migraciones_pendientes_en_orden(): void {
		$orden        = array();
		$this->gestor->callbacks = array(
			'1.5.0' => function () use ( &$orden ) {
				$orden[] = '1.5.0';
			},
			'1.0.1' => function () use ( &$orden ) {
				$orden[] = '1.0.1';
			},
			'2.5.0' => function () use ( &$orden ) {
				$orden[] = '2.5.0';
			},
		);
		$GLOBALS['_wp_stores']['options']['convoca_prueba_db_version'] = '1.0.0';

		$this->gestor->maybe_upgrade();

		$this->assertSame( array( '1.0.1', '1.5.0' ), $orden, 'en orden y sólo las pendientes' );
		$this->assertSame( '2.0.0', $GLOBALS['_wp_stores']['options']['convoca_prueba_db_version'] );
	}

	/**
	 * Si una falla, se guarda la **última que salió bien** y se relanza: el siguiente intento no se salta nada.
	 */
	public function test_si_una_falla_no_se_pierde_lo_hecho(): void {
		$hechas                  = array();
		$this->gestor->callbacks = array(
			'1.0.1' => function () use ( &$hechas ) {
				$hechas[] = '1.0.1';
			},
			'1.5.0' => static function (): void {
				throw new \RuntimeException( 'esta migración se rompe' );
			},
			'1.9.0' => function () use ( &$hechas ) {
				$hechas[] = '1.9.0';
			},
		);
		$GLOBALS['_wp_stores']['options']['convoca_prueba_db_version'] = '0.9.0';

		try {
			$this->gestor->maybe_upgrade();
			$this->fail( 'debía relanzar el error' );
		} catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( 'se rompe', $e->getMessage() );
		}

		$this->assertSame( array( '1.0.1' ), $hechas, 'la de después no se intenta' );
		$this->assertSame(
			'1.0.1',
			$GLOBALS['_wp_stores']['options']['convoca_prueba_db_version'],
			'la versión se queda en la última buena'
		);
		$this->assertArrayNotHasKey( 'convoca_lock_upgrade_convoca_prueba', $GLOBALS['_wp_stores']['locks'], 'el cerrojo se suelta' );
	}

	/**
	 * Un `callable` que no lo es corta antes de ejecutar nada.
	 */
	public function test_un_callback_que_no_es_llamable(): void {
		$this->gestor->callbacks = array( '1.0.1' => 'funcion_que_no_existe' );
		$GLOBALS['_wp_stores']['options']['convoca_prueba_db_version'] = '1.0.0';

		$this->expectException( \RuntimeException::class );
		$this->gestor->maybe_upgrade();
	}

	/**
	 * La versión no baja nunca.
	 */
	public function test_la_version_no_baja(): void {
		$GLOBALS['_wp_stores']['options']['convoca_prueba_db_version'] = '3.0.0';

		$reflejo = new \ReflectionMethod( Upgrade_Manager_De_Prueba::class, 'update_db_version' );
		$reflejo->invoke( $this->gestor, '1.0.0' );

		$this->assertSame( '3.0.0', $GLOBALS['_wp_stores']['options']['convoca_prueba_db_version'] );
	}

	/**
	 * Migración de datos: o entera o nada. Un fallo hace ROLLBACK y no COMMIT.
	 */
	public function test_la_migracion_de_datos_es_atomica(): void {
		$reflejo = new \ReflectionMethod( Upgrade_Manager_De_Prueba::class, 'run_data_migration' );

		$reflejo->invoke( $this->gestor, static fn() => true, 'que va bien' );
		$consultas = (array) $GLOBALS['_wp_stores']['queries'];
		$this->assertContains( 'START TRANSACTION', $consultas );
		$this->assertContains( 'COMMIT', $consultas );
		$this->assertNotContains( 'ROLLBACK', $consultas );

		$GLOBALS['_wp_stores']['queries'] = array();
		try {
			$reflejo->invoke(
				$this->gestor,
				static function (): void {
					throw new \RuntimeException( 'a medias' );
				},
				'que se rompe'
			);
			$this->fail( 'debía lanzar' );
		} catch ( \ReflectionException $e ) {
			$this->fail( 'no debería ser un error de reflexión' );
		} catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( 'que se rompe', $e->getMessage() );
		}

		$consultas = (array) $GLOBALS['_wp_stores']['queries'];
		$this->assertContains( 'ROLLBACK', $consultas, 'se deshace' );
		$this->assertNotContains( 'COMMIT', $consultas, 'y no se confirma' );
	}

	/**
	 * Los enganches: comprobación al entrar al panel y después de actualizar el plugin.
	 */
	public function test_los_enganches(): void {
		$this->gestor->init();

		$this->assertContains( array( $this->gestor, 'maybe_upgrade' ), (array) ( $GLOBALS['_wp_hooks']['admin_init'] ?? array() ) );
		$this->assertContains( array( $this->gestor, 'force_version_check' ), (array) ( $GLOBALS['_wp_hooks']['upgrader_process_complete'] ?? array() ) );
	}
}
