<?php
/**
 * Los avisos del núcleo: el aviso de la campana y el del socio.
 *
 * Un aviso que no se puede marcar leído es una campana encendida para siempre; uno que se marca de más apaga
 * los que la persona no ha visto; y uno que no se reparte a todos los administradores deja a parte del equipo
 * sin enterarse. Eso es lo que se prueba aquí.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use Convoca\Core\Notifications;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Notifications.php';

/**
 * Avisos.
 */
final class NotificationsAvisosTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->limpiar();
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$this->limpiar();
	}

	/**
	 * Deja el arnés como estaba.
	 */
	private function limpiar(): void {
		unset( $GLOBALS['_wp_stores'], $GLOBALS['_test_posts'] );

		$GLOBALS['_wp_stores'] = array(
			'options'   => array(),
			'post_meta' => array(),
			'user_meta' => array(),
			'posts'     => array(),
			'usuarios'  => array(),
		);
	}

	/**
	 * Un administrador en el almacén.
	 *
	 * @param int    $id    Usuario.
	 * @param string $rol   Rol.
	 */
	private function usuario( int $id, string $rol = 'administrator' ): void {
		$GLOBALS['_wp_stores']['usuarios'][ $id ] = (object) array(
			'ID'           => $id,
			'display_name' => 'Admin ' . $id,
			'user_email'   => 'admin' . $id . '@example.test',
			'roles'        => array( $rol ),
		);
	}

	/**
	 * Los avisos de un socio.
	 *
	 * @param int $member_id Socio.
	 * @return array
	 */
	private function avisos_de( int $member_id ): array {
		return (array) get_post_meta( $member_id, Notifications::META_KEY, true );
	}

	/**
	 * Un aviso nuevo nace **no leído**, el más reciente primero, y con sus datos.
	 */
	public function test_el_aviso_del_socio(): void {
		Notifications::add_member( 55, 'Tu cuota vence pronto', '/mi-area', 'warning' );
		Notifications::add_member( 55, 'Bienvenida', '/mi-area' );

		$avisos = $this->avisos_de( 55 );
		$this->assertCount( 2, $avisos );
		$this->assertSame( 'Bienvenida', $avisos[0]['title'], 'el último, arriba' );
		$this->assertSame( 'Tu cuota vence pronto', $avisos[1]['title'] );
		$this->assertFalse( $avisos[0]['read'], 'nace sin leer' );
		$this->assertSame( 'warning', $avisos[1]['type'] );
		$this->assertSame( '/mi-area', $avisos[1]['url'], 'con su enlace' );
		$this->assertNotSame( '', (string) $avisos[1]['time'], 'y su fecha' );
		$this->assertNotSame( $avisos[0]['id'], $avisos[1]['id'], 'cada uno con su identificador' );
	}

	/**
	 * `get_member` respeta el límite por arriba: los más recientes.
	 */
	public function test_el_limite_del_listado(): void {
		for ( $i = 1; $i <= 5; $i++ ) {
			Notifications::add_member( 55, 'Aviso ' . $i );
		}

		$dos = Notifications::get_member( 55, 2 );
		$this->assertCount( 2, $dos );
		$this->assertSame( 'Aviso 5', $dos[0]['title'], 'los dos últimos' );
		$this->assertSame( 'Aviso 4', $dos[1]['title'] );
	}

	/**
	 * No se guardan más de 50: los viejos se van solos.
	 */
	public function test_el_tope_de_cincuenta(): void {
		for ( $i = 1; $i <= 55; $i++ ) {
			Notifications::add_member( 55, 'Aviso ' . $i );
		}

		$avisos = $this->avisos_de( 55 );
		$this->assertCount( 50, $avisos, 'el tope' );
		$this->assertSame( 'Aviso 55', $avisos[0]['title'] );
		$this->assertSame( 'Aviso 6', $avisos[49]['title'], 'y los cinco más viejos ya no están' );
	}

	/**
	 * Marcar uno marca **sólo** ese.
	 */
	public function test_marcar_uno(): void {
		Notifications::add_member( 55, 'Primero' );
		Notifications::add_member( 55, 'Segundo' );
		$this->assertSame( 2, Notifications::count_member_unread( 55 ), 'los dos sin leer' );

		$avisos = $this->avisos_de( 55 );
		Notifications::mark_member_read( 55, (string) $avisos[0]['id'] );

		$despues = $this->avisos_de( 55 );
		$this->assertTrue( $despues[0]['read'], 'el que se pidió' );
		$this->assertFalse( $despues[1]['read'], 'y el otro, no' );
		$this->assertSame( 1, Notifications::count_member_unread( 55 ) );
	}

	/**
	 * Un identificador que no existe no rompe ni marca nada.
	 */
	public function test_marcar_uno_que_no_existe(): void {
		Notifications::add_member( 55, 'Primero' );

		Notifications::mark_member_read( 55, 'no-existe' );

		$this->assertSame( 1, Notifications::count_member_unread( 55 ), 'sigue sin leer' );
	}

	/**
	 * Marcar todos los deja todos leídos y la campana a cero.
	 */
	public function test_marcar_todos(): void {
		for ( $i = 1; $i <= 3; $i++ ) {
			Notifications::add_member( 55, 'Aviso ' . $i );
		}

		Notifications::mark_member_all_read( 55 );

		$this->assertSame( 0, Notifications::count_member_unread( 55 ) );
		$this->assertCount( 3, $this->avisos_de( 55 ), 'sin borrar ninguno' );
	}

	/**
	 * El aviso de la campana va a **todos** los administradores y a los monitores de actividad.
	 */
	public function test_el_aviso_a_la_junta(): void {
		$this->usuario( 1 );
		$this->usuario( 2, 'monitor_actividad' );
		$this->usuario( 3, 'socio' );

		Notifications::add( 'Pago fallido', '/admin', 'error' );

		foreach ( array( 1, 2 ) as $destinatario ) {
			$suyos = (array) get_user_meta( $destinatario, Notifications::META_KEY, true );
			$this->assertCount( 1, $suyos, "el usuario {$destinatario} recibe el aviso" );
			$this->assertSame( 'Pago fallido', $suyos[0]['title'] );
			$this->assertFalse( $suyos[0]['read'] );
		}
		$this->assertEmpty(
			get_user_meta( 3, Notifications::META_KEY, true ),
			'a un socio no se le manda el aviso de la junta'
		);
	}

	/**
	 * La lista de la campana: respeta el límite y puede pedir sólo los no leídos.
	 */
	public function test_la_campana(): void {
		$this->usuario( 1 );
		Notifications::add( 'Uno', '/a' );
		Notifications::add( 'Dos', '/b' );

		$this->assertCount( 2, Notifications::get(), 'sin filtro salen todos' );
		$this->assertCount( 1, Notifications::get( 1 ), 'con límite, uno' );
		$this->assertSame( 2, Notifications::count_unread(), 'y los dos sin leer' );
	}
}
