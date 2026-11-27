<?php
/**
 * El push no puede salir a un canal público por defecto (core#51).
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use Convoca\Core\Push_Notifier;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Push_Notifier.php';

/**
 * Destino del push.
 */
final class PushNotifierTest extends TestCase {

	/**
	 * Prepara opciones y peticiones.
	 */
	protected function setUp(): void {
		$GLOBALS['_wp_stores'] = array( 'options' => array(), 'transients' => array() );
		$GLOBALS['_wp_http_posts'] = array();
	}

	/**
	 * Sin tema no se envía nada.
	 */
	public function test_sin_tema_no_envia(): void {
		update_option( Push_Notifier::CONFIG_OPTION, array( 'enabled' => true, 'topic' => '' ) );

		$this->assertFalse( Push_Notifier::send( '', 'Aviso', 'Mensaje' ) );
		$this->assertSame( array(), $GLOBALS['_wp_http_posts'], 'no hay petición' );
	}

	/**
	 * El tema de fábrica tampoco vale.
	 */
	public function test_el_tema_de_fabrica_se_rechaza(): void {
		update_option( Push_Notifier::CONFIG_OPTION, array( 'enabled' => true, 'topic' => 'convoca_alerts' ) );

		$this->assertFalse( Push_Notifier::send( '', 'Aviso', 'Mensaje' ) );
		$this->assertSame( array(), $GLOBALS['_wp_http_posts'] );
	}

	/**
	 * Con un tema privado, la petición va solo a ese destino.
	 */
	public function test_con_tema_privado_envia_a_su_destino(): void {
		update_option(
			Push_Notifier::CONFIG_OPTION,
			array( 'enabled' => true, 'topic' => 'asociacion-privada-7f3a', 'server' => 'https://ntfy.example.test' )
		);

		$this->assertTrue( Push_Notifier::send( '', 'Aviso', 'Mensaje' ) );
		$this->assertCount( 1, $GLOBALS['_wp_http_posts'] );
		$this->assertSame( 'https://ntfy.example.test/asociacion-privada-7f3a', $GLOBALS['_wp_http_posts'][0]['url'] );
	}
}
