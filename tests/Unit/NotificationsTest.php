<?php
/**
 * Notificaciones: la campana del panel y las que recibe cada socio.
 *
 * Lo que importa de una bandeja de notificaciones es que **se pueda vaciar**: que marcar una no marque todas, que
 * descartar quite una y no la bandeja entera, y que el contador de no leídas baje cuando toca. Un contador que no
 * baja es una campana que se queda encendida para siempre.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use Convoca\Core\Notifications;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Utils.php';
require_once dirname( __DIR__, 2 ) . '/includes/Logger.php';
require_once dirname( __DIR__, 2 ) . '/includes/Notifications.php';

/**
 * Notificaciones.
 */
final class NotificationsTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	/**
	 * Estado global previo, para devolverlo al terminar.
	 *
	 * @var array
	 */
	private array $previo = array();

	protected function setUp(): void {
		$this->previo = $GLOBALS['_wp_stores'] ?? array();
		$GLOBALS['_wp_filters'] = array();
		$GLOBALS['_wp_logged_in'] = false;
		unset( $_POST['id'], $_POST['nonce'] );
		$GLOBALS['_wp_json']    = array();
		$GLOBALS['_test_caps']  = array();
		unset( $GLOBALS['_wp_die_lanza'], $GLOBALS['_test_now'] );
		$GLOBALS['_wp_stores'] = array(
			'options'     => array(),
			'post_meta'   => array(),
			'user_meta'   => array(),
			'transients'  => array(),
			'posts'       => array(),
			'usuarios'    => array(),
			'db_inserts'  => array(),
			'queries'     => array(),
			'is_admin'    => false,
			'http'        => array(),
			'nonces_validos' => array(),
		);
	}

	/**
	 * Crea un usuario en el almacén.
	 *
	 * @param int    $id        Id.
	 * @param string $rol       Rol.
	 * @param string $email     Correo.
	 * @param string $nombre    Nombre.
	 * @return void
	 */
	private function usuario( int $id, string $rol = 'administrator', string $email = '', string $nombre = '' ): void {
		$u               = new \WP_User();
		$u->ID           = $id;
		$u->user_login   = 'u' . $id;
		$u->user_email   = '' !== $email ? $email : 'u' . $id . '@example.test';
		$u->display_name = '' !== $nombre ? $nombre : 'Usuario ' . $id;
		$u->roles        = array( $rol );
		$GLOBALS['_wp_stores']['usuarios'][ $id ] = $u;
	}

	/**
	 * Una notificación de administración va a los administradores de la asociación y a nadie más.
	 */
	public function test_una_notificacion_va_a_los_administradores(): void {
		$this->usuario( 1, 'administrator' );
		$this->usuario( 2, 'monitor_actividad' );
		$this->usuario( 3, 'subscriber' );

		Notifications::add( 'Nuevo socio pendiente', 'https://example.test/a', 'info' );

		$de_uno = get_user_meta( 1, Notifications::META_KEY, true );
		$de_dos = get_user_meta( 2, Notifications::META_KEY, true );
		$this->assertCount( 1, $de_uno );
		$this->assertSame( 'Nuevo socio pendiente', $de_uno[0]['title'] );
		$this->assertSame( 'https://example.test/a', $de_uno[0]['url'] );
		$this->assertFalse( $de_uno[0]['read'], 'nace sin leer' );
		$this->assertSame( $de_uno[0]['id'], $de_dos[0]['id'], 'la misma notificación para ambos' );
		$this->assertEmpty( get_user_meta( 3, Notifications::META_KEY, true ), 'el suscriptor no la recibe' );
	}

	/**
	 * La bandeja no crece sin fin: se queda con las cincuenta últimas.
	 */
	public function test_la_bandeja_no_crece_sin_fin(): void {
		$this->usuario( 1 );
		$viejas = array();
		for ( $i = 0; $i < 60; $i++ ) {
			$viejas[] = array(
				'id'    => 'n' . $i,
				'title' => 'Vieja ' . $i,
				'url'   => '',
				'type'  => 'info',
				'time'  => '2026-01-01 00:00:00',
				'read'  => false,
			);
		}
		update_user_meta( 1, Notifications::META_KEY, $viejas );

		Notifications::add( 'La nueva', '' );

		$todas = get_user_meta( 1, Notifications::META_KEY, true );
		$this->assertCount( 50, $todas );
		$this->assertSame( 'La nueva', $todas[0]['title'], 'la nueva entra por delante' );
		$this->assertNotContains( 'n59', array_column( $todas, 'id' ), 'la última de las viejas se cae' );
	}

	/**
	 * Una notificación de socio: se lee, se cuenta y se marca **sólo** la que se pide.
	 */
	public function test_marcar_una_no_marca_todas(): void {
		Notifications::add_member( 501, 'Tu cuota vence pronto', '/mi-area' );
		Notifications::add_member( 501, 'Turno confirmado', '/mi-area' );

		$this->assertCount( 2, Notifications::get_member( 501 ) );
		$this->assertSame( 2, Notifications::count_member_unread( 501 ) );

		$todas = Notifications::get_member( 501 );
		$id    = $todas[1]['id'];
		Notifications::mark_member_read( 501, $id );

		$this->assertSame( 1, Notifications::count_member_unread( 501 ), 'sólo baja una' );
		$leidas = array_filter( Notifications::get_member( 501 ), static fn( $n ) => $n['read'] );
		$this->assertCount( 1, $leidas );
	}

	/**
	 * Marcar todas sí las vacía, y el límite de lectura se respeta.
	 */
	public function test_marcar_todas_y_el_limite(): void {
		for ( $i = 0; $i < 5; $i++ ) {
			Notifications::add_member( 502, 'Aviso ' . $i );
		}

		$this->assertCount( 3, Notifications::get_member( 502, 3 ), 'el límite se aplica' );
		Notifications::mark_member_all_read( 502 );
		$this->assertSame( 0, Notifications::count_member_unread( 502 ) );
	}

	/**
	 * Las del usuario actual: el filtro de no leídas no devuelve leídas, y el contador cuadra.
	 */
	public function test_las_del_usuario_actual(): void {
		$this->usuario( 9 );
		$GLOBALS['_test_user_id'] = 9;
		Notifications::add( 'Uno', '' );
		Notifications::add( 'Dos', '' );

		$this->assertCount( 2, Notifications::get() );
		$this->assertSame( 2, Notifications::count_unread() );

		$todas = Notifications::get();
		$GLOBALS['_wp_stores']['user_meta'][9][ Notifications::META_KEY ][0]['read'] = true;

		$this->assertCount( 1, Notifications::get( 10, true ), 'sólo la no leída' );
		$this->assertSame( 'Uno', Notifications::get( 10, true )[0]['title'] );
		$this->assertSame( 1, Notifications::count_unread() );
	}

	/**
	 * El desplegable: contador, estado vacío, icono según el tipo y **el título escapado**.
	 */
	public function test_el_desplegable(): void {
		$reflejo = new \ReflectionMethod( Notifications::class, 'render_dropdown' );

		$vacio = $reflejo->invoke( null, array(), 0 );
		$this->assertStringContainsString( 'Notificaciones (0)', $vacio );
		$this->assertStringContainsString( 'No hay notificaciones.', $vacio );
		$this->assertStringNotContainsString( 'Marcar todas', $vacio, 'sin nada que marcar no se ofrece' );

		$html = $reflejo->invoke(
			null,
			array(
				array(
					'id'    => 'a',
					'title' => 'Pago <script>alert(1)</script>',
					'url'   => 'https://example.test/x?a=1&b=2',
					'type'  => 'error',
					'time'  => '2026-10-23 10:00:00',
					'read'  => false,
				),
				array(
					'id'    => 'b',
					'title' => 'Todo bien',
					'url'   => '',
					'type'  => 'success',
					'time'  => '2026-10-23 10:00:00',
					'read'  => true,
				),
			),
			2
		);

		$this->assertStringContainsString( 'Notificaciones (2)', $html );
		$this->assertStringContainsString( 'Marcar todas', $html );
		$this->assertStringContainsString( '❌', $html, 'el tipo error tiene su icono' );
		$this->assertStringContainsString( '✅', $html, 'y el éxito el suyo' );
		$this->assertStringContainsString( 'conv-notif-unread', $html, 'la no leída se distingue' );
		$this->assertStringNotContainsString( '<script>', $html, 'el título no puede ser la vía de entrada' );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
		$this->assertStringContainsString( 'data-id="a"', $html );
	}

	/**
	 * La campana: fuera del panel no se pinta, dentro sí, y el contador sólo aparece si hay algo sin leer.
	 */
	public function test_la_campana(): void {
		$barra = new \WP_Admin_Bar();

		$GLOBALS['_wp_stores']['is_admin'] = false;
		Notifications::admin_bar_bell( $barra );
		$this->assertSame( array(), $barra->nodes, 'fuera del panel no se pinta' );

		$GLOBALS['_wp_stores']['is_admin'] = true;
		$GLOBALS['_wp_logged_in']          = true;
		$GLOBALS['_test_user_id']          = 5;
		$this->usuario( 5 );

		Notifications::admin_bar_bell( $barra );
		$nodos = (array) $barra->nodes;
		$this->assertArrayHasKey( 'conv-notifications', $nodos );
		$this->assertArrayHasKey( 'conv-notifications-list', $nodos, 'con su desplegable' );
		$titulo = (string) ( is_array( $nodos['conv-notifications'] ) ? ( $nodos['conv-notifications']['title'] ?? '' ) : ( $nodos['conv-notifications']->title ?? '' ) );
		$this->assertStringNotContainsString( 'conv-bell-count', $titulo, 'sin nada sin leer no hay número' );

		$barra->nodes = array();
		Notifications::add( 'Algo nuevo', '' );
		Notifications::admin_bar_bell( $barra );
		$nodos  = (array) $barra->nodes;
		$titulo = (string) ( is_array( $nodos['conv-notifications'] ) ? ( $nodos['conv-notifications']['title'] ?? '' ) : ( $nodos['conv-notifications']->title ?? '' ) );
		$this->assertStringContainsString( 'conv-bell-count', $titulo );
		$this->assertStringContainsString( 'conv-bell-has-unread', $titulo );
	}

	/**
	 * Ajax: marcar una por id, y descartar **sólo** esa.
	 */
	public function test_los_ajax_de_leer_y_descartar(): void {
		$GLOBALS['_test_user_id']          = 11;
		$GLOBALS['_wp_logged_in']          = true;
		$GLOBALS['_wp_stores']['is_admin'] = true;
		$this->usuario( 11 );
		Notifications::add( 'Primera', '' );
		Notifications::add( 'Segunda', '' );
		$todas = Notifications::get( 10 );
		$id    = $todas[0]['id'];

		$_POST['id'] = $id;
		Notifications::ajax_mark_read();
		$this->assertSame( 1, Notifications::count_unread(), 'queda una sin leer' );
		$this->assertTrue( $GLOBALS['_wp_json'][0]['ok'] );

		$_POST['id'] = $id;
		Notifications::ajax_dismiss();
		$quedan = Notifications::get( 10 );
		$this->assertCount( 1, $quedan, 'se descarta una, no la bandeja' );
		$this->assertSame( 'Primera', $quedan[0]['title'], 'la más reciente era «Segunda» y es la que se descartó' );

		$_POST = array();
		$GLOBALS['_wp_json'] = array();
		Notifications::ajax_dismiss();
		$this->assertSame( array(), $GLOBALS['_wp_json'], 'sin id no responde' );
		$this->assertCount( 1, Notifications::get( 10 ) );
	}

	/**
	 * Los eventos: cada uno crea la notificación con su texto y su destino.
	 */
	public function test_los_eventos_crean_su_notificacion(): void {
		$this->usuario( 1 );
		$GLOBALS['_test_user_id'] = 1;

		Notifications::on_member_created( 77, array( 'nombre' => 'Ada Lovelace' ) );
		$this->assertStringContainsString( 'Ada Lovelace', Notifications::get()[0]['title'] );
		$this->assertStringContainsString( 'member_id=77', Notifications::get()[0]['url'] );

		$GLOBALS['_wp_stores']['user_meta'][1][ Notifications::META_KEY ] = array();
		Notifications::on_payment_failed( 5, 'ord-1' );
		$this->assertNotSame( '', Notifications::get()[0]['title'] );
		$this->assertSame( 'error', Notifications::get()[0]['type'] );
	}

	/**
	 * Sin sesión en el ajax se sale antes de tocar nada.
	 */
	public function test_sin_sesion_el_ajax_no_toca_nada(): void {
		$GLOBALS['_test_user_id'] = 0;
		$GLOBALS['_wp_stores']['user_meta'][0][ Notifications::META_KEY ] = array(
			array(
				'id'    => 'x',
				'title' => 'Algo',
				'url'   => '',
				'type'  => 'info',
				'time'  => '2026-10-23 10:00:00',
				'read'  => false,
			),
		);
		$_POST['id'] = 'x';

		Notifications::ajax_mark_read();

		$this->assertFalse( $GLOBALS['_wp_stores']['user_meta'][0][ Notifications::META_KEY ][0]['read'] );
		$this->assertSame( array(), $GLOBALS['_wp_json'] );
	}

	/**
	 * Devuelve el almacén a como estaba: una prueba no puede dejar el terreno preparado a la siguiente.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores'] = $this->previo;
		unset( $GLOBALS['_wp_logged_in'], $GLOBALS['_test_user_id'], $GLOBALS['_wp_json'], $GLOBALS['_wp_die_lanza'] );
	}
}
