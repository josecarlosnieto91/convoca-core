<?php
/**
 * La entrada al panel y el Escritorio de la junta (core#20).
 *
 * MEDIDO (informe del issue, visto como quien entra a trabajar): al iniciar sesión se caía en el Escritorio de
 * WordPress —el aviso de precio de WPML en inglés y un «Estado de salud del sitio: Necesita mejoras · problema
 * crítico» en rojo, sin decir qué hacer— mientras el Panel de Control de Convoca quedaba un clic más allá.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use PHPUnit\Framework\TestCase;

class EntradaDelPanelTest extends TestCase {

	private function clase(): string {
		$clase = '\\Convoca\\Core\\Admin_Entrada';
		if ( ! class_exists( $clase ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/Admin_Entrada.php';
		}
		if ( ! class_exists( $clase ) ) {
			require_once dirname( __DIR__, 3 ) . '/convoca-core/includes/Admin_Entrada.php';
		}
		return $clase;
	}

	/** Al entrar sin pedir nada, se aterriza en el panel de la asociación. */
	public function test_aterriza_en_el_panel(): void {
		$clase = $this->clase();
		if ( ! class_exists( $clase ) ) {
			$this->fail( 'No se ha podido cargar Admin_Entrada: la prueba no comprobaría nada.' );
		}

		$user       = new \WP_User();
		$user->ID   = 7; // El doble del arnés no tiene constructor: el identificador se pone a mano.
		$GLOBALS['_test_user_caps'][7]['manage_options'] = true;

		$this->assertStringContainsString(
			'page=conv-dashboard',
			(string) $clase::aterrizar( admin_url(), '', $user ),
			'Quien lleva la asociación entra y ve su panel, no el Escritorio de WordPress'
		);

		// Y se respeta a quien ha pedido una pantalla concreta.
		$this->assertSame(
			admin_url( 'admin.php?page=conv-members' ),
			$clase::aterrizar( admin_url( 'admin.php?page=conv-members' ), '', $user ),
			'Si viene de una pantalla concreta, no se le lleva a otra'
		);
		$this->assertSame(
			admin_url( 'edit.php' ),
			$clase::aterrizar( admin_url( 'edit.php' ), admin_url( 'edit.php' ), $user ),
			'Y si ha pedido un destino, se respeta'
		);
	}

	/** Sin permiso para el panel, no se cambia nada. */
	public function test_sin_permiso_no_se_cambia_el_destino(): void {
		$clase = $this->clase();
		if ( ! class_exists( $clase ) ) {
			$this->fail( 'No se ha podido cargar Admin_Entrada.' );
		}

		$user       = new \WP_User();
		$user->ID   = 8;
		$GLOBALS['_test_user_caps'][8]['manage_options'] = false;

		$this->assertSame( admin_url(), $clase::aterrizar( admin_url(), '', $user ) );
	}

	/** La salud del servidor se cuenta en lenguaje de asociación, y sin datos no se inventa nada. */
	public function test_la_salud_se_traduce_y_no_se_inventa(): void {
		$clase = $this->clase();
		if ( ! class_exists( $clase ) ) {
			$this->fail( 'No se ha podido cargar Admin_Entrada.' );
		}

		$salud = $clase::salud();
		$this->assertArrayHasKey( 'estado', $salud );
		$this->assertArrayHasKey( 'texto', $salud );
		// En el arnés no existe `WP_Site_Health`: el camino honesto es decirlo, no dar un diagnóstico falso.
		$this->assertSame( 'sin_datos', $salud['estado'] );
		$this->assertStringContainsString( 'No hemos podido consultar', $salud['texto'] );
	}

	/** La tarjeta lleva el enlace al panel y los primeros pasos. */
	public function test_la_tarjeta_lleva_al_panel(): void {
		$clase = $this->clase();
		if ( ! class_exists( $clase ) ) {
			$this->fail( 'No se ha podido cargar Admin_Entrada.' );
		}

		ob_start();
		$clase::tarjeta();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'page=conv-dashboard', $html, 'El enlace al panel, con botón' );
		$this->assertStringContainsString( 'Abrir el Panel de Control', $html );
		$this->assertStringContainsString( '¿Por dónde empiezo?', $html );
		$this->assertStringContainsString( 'avisa a quien te lo instaló', $html, 'Y qué hacer si el servidor pide atención' );
	}

	/** Los ajustes vienen activados y se pueden apagar. */
	public function test_los_ajustes_vienen_activados(): void {
		$clase = $this->clase();
		if ( ! class_exists( $clase ) ) {
			$this->fail( 'No se ha podido cargar Admin_Entrada.' );
		}

		delete_option( 'convoca_core_entrada' );
		$defecto = $clase::ajustes();
		$this->assertTrue( $defecto['aterrizar'], 'Por defecto, sí: es lo que pide el issue' );
		$this->assertTrue( $defecto['ocultar_avisos'] );

		update_option( 'convoca_core_entrada', array( 'aterrizar' => false, 'ocultar_avisos' => false ) );
		$apagado = $clase::ajustes();
		$this->assertFalse( $apagado['aterrizar'] );
		$this->assertFalse( $apagado['ocultar_avisos'] );
		delete_option( 'convoca_core_entrada' );
	}

	/** Solo se calla a los avisos que no son del producto, y solo en el Escritorio. */
	public function test_solo_callamos_a_los_de_fuera_y_solo_aqui(): void {
		$fuente = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Admin_Entrada.php' );

		$this->assertStringContainsString( "'dashboard' !== \$pantalla->id", $fuente, 'En las pantallas de cada plugin los avisos siguen a la vista' );
		$this->assertStringContainsString( "apply_filters( 'convoca_core_avisos_permitidos'", $fuente, 'Con lista blanca ajustable' );
		$this->assertStringContainsString( "'convoca-'", $fuente, 'Los avisos de Convoca siempre se respetan' );
	}
}
