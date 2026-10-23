<?php
/**
 * Migraciones del núcleo que tocan la base de datos.
 *
 * La limpieza de las tablas del prefijo viejo (issue #12) **borra tablas**, así que lo que se prueba no es que
 * funcione: es que **no borre cuando no puede demostrar que el contenido está en la nueva**. Si la vieja tiene más
 * filas, se deja y se avisa.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use Convoca\Core\Common_Upgrade_Manager;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Utils.php';
require_once dirname( __DIR__, 2 ) . '/includes/Logger.php';
require_once dirname( __DIR__, 2 ) . '/includes/Common_Upgrade_Manager.php';

/**
 * Migraciones del núcleo.
 */
final class CommonUpgradeTest extends TestCase {

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
		$GLOBALS['_wp_stores']  = array(
			'options'      => array(),
			'transients'   => array( 'convoca_logger_table_exists' => 1 ),
			'col'          => array(),
			'get_var_mapa' => array(),
			'queries'      => array(),
			'db_inserts'   => array(),
			'locks'        => array(),
			'post_meta'    => array(),
			'posts'        => array(),
			'usuarios'     => array(),
		);
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores'] = $this->previo;
	}

	/**
	 * ¿Se pidió un DROP de esa tabla?
	 *
	 * @param string $tabla Tabla.
	 * @return bool
	 */
	private function se_borro( string $tabla ): bool {
		foreach ( (array) $GLOBALS['_wp_stores']['queries'] as $q ) {
			if ( false !== strpos( (string) $q, 'DROP TABLE' ) && false !== strpos( (string) $q, $tabla ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * La limpieza: sólo borra cuando la nueva tiene al menos tantas filas.
	 */
	public function test_la_limpieza_del_prefijo_viejo(): void {
		$GLOBALS['_wp_stores']['col'] = array( 'wp_conv_media_templates', 'wp_conv_logs', 'wp_conv_huerfana' );
		// El orden importa: las agujas con `FROM` van **antes** que las de `SHOW TABLES`, porque si no el conteo
		// recibiría el nombre de la tabla (la primera aguja que casa gana).
		$GLOBALS['_wp_stores']['get_var_mapa'] = array(
			'FROM `wp_conv_media_templates`'    => '8',
			'FROM `wp_convoca_media_templates`' => '8',
			// La vieja de logs tiene **más** filas que la nueva: no se puede cuadrar.
			'FROM `wp_conv_logs`'               => '40',
			'FROM `wp_convoca_logs`'            => '12',
			'wp_convoca_media_templates'        => 'wp_convoca_media_templates',
			'wp_convoca_logs'                   => 'wp_convoca_logs',
			'wp_convoca_huerfana'               => 'NO-EXISTE',
		);

		$resumen = Common_Upgrade_Manager::limpiar_tablas_del_prefijo_viejo();

		$this->assertStringContainsString( 'borrada', $resumen['wp_conv_media_templates'], 'cuadra: 8 frente a 8' );
		$this->assertTrue( $this->se_borro( 'wp_conv_media_templates' ) );

		$this->assertStringContainsString( 'se deja', $resumen['wp_conv_logs'], '40 frente a 12' );
		$this->assertStringContainsString( '40 filas', $resumen['wp_conv_logs'] );
		$this->assertFalse( $this->se_borro( 'wp_conv_logs' ), 'no se destruye lo que no cuadra' );

		$this->assertStringContainsString( 'no hay tabla nueva', $resumen['wp_conv_huerfana'] );
		$this->assertFalse( $this->se_borro( 'wp_conv_huerfana' ) );

		$this->assertCount( 3, $resumen );
	}

	/**
	 * Sin tablas del prefijo viejo no hay nada que hacer (y no se pide ningún DROP).
	 */
	public function test_sin_tablas_viejas(): void {
		$this->assertSame( array(), Common_Upgrade_Manager::limpiar_tablas_del_prefijo_viejo() );
		$this->assertSame( array(), array_filter( (array) $GLOBALS['_wp_stores']['queries'], static fn( $q ) => false !== strpos( (string) $q, 'DROP' ) ) );
	}
}
