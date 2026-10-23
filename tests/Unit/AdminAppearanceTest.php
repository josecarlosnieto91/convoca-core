<?php
/**
 * La apariencia del panel: el pie, la barra de administración y las cifras del escritorio.
 *
 * Nada de esto es crítico, y por eso se rompe sin que nadie mire: el pie anuncia la versión equivocada, el menú
 * «Nuevo» lleva a una pantalla que ya no existe, o el escritorio enseña cifras de módulos que no están instalados.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Utils.php';
require_once dirname( __DIR__, 2 ) . '/includes/Features.php';
require_once dirname( __DIR__, 2 ) . '/includes/admin-appearance.php';

if ( ! defined( 'CONVOCA_COMMON_VERSION' ) ) {
	define( 'CONVOCA_COMMON_VERSION', '2.0.0' );
}

/**
 * Apariencia del panel.
 */
final class AdminAppearanceTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		unset( $GLOBALS['_wp_filters'], $GLOBALS['_test_caps'], $GLOBALS['_test_screen'], $GLOBALS['_wp_user'] );
		$GLOBALS['_wp_stores'] = array(
			'options'     => array(),
			'post_meta'   => array(),
			'transients'  => array(),
			'posts'       => array(),
			'usuarios'    => array(),
			'db_inserts'  => array(),
			'queries'     => array(),
			'db_reads'    => array(),
			'get_var_mapa' => array(),
			'bar_add'     => array(),
			'bar_remove'  => array(),
		);
		$GLOBALS['_test_caps'] = array();
	}

	/**
	 * Pantalla del panel.
	 *
	 * @param string $id   Id de pantalla.
	 * @param string $base Base.
	 * @return \WP_Screen
	 */
	private function pantalla( string $id, string $base = '' ): \WP_Screen {
		$p       = new \WP_Screen();
		$p->id   = $id;
		$p->base = $base;

		return $p;
	}

	/**
	 * En una pantalla de Convoca, las opciones de pantalla se ocultan; en las demás, se respeta lo que diga WordPress.
	 */
	public function test_oculta_opciones_de_pantalla_solo_en_convoca(): void {
		$this->assertFalse( \Convoca\Core\convoca_hide_screen_options( true, $this->pantalla( 'toplevel_page_conv-members' ) ) );
		$this->assertFalse( \Convoca\Core\convoca_hide_screen_options( true, $this->pantalla( 'x', 'convoca-core_page_algo' ) ) );
		$this->assertTrue( \Convoca\Core\convoca_hide_screen_options( true, $this->pantalla( 'edit-post' ) ), 'en una pantalla ajena se respeta' );
		$this->assertFalse( \Convoca\Core\convoca_hide_screen_options( false, $this->pantalla( 'edit-post' ) ), 'y si venía oculto, sigue oculto' );
	}

	/**
	 * El pie lleva el año, el nombre del sitio y el enlace del proyecto, y ese enlace es filtrable.
	 */
	public function test_el_pie_del_panel(): void {
		$pie = \Convoca\Core\convoca_admin_footer( 'texto de WordPress' );

		$this->assertStringContainsString( '© ' . wp_date( 'Y' ), $pie );
		$this->assertStringContainsString( get_bloginfo( 'name' ), $pie );
		$this->assertStringContainsString( 'https://getconvoca.app', $pie );
		$this->assertStringNotContainsString( 'texto de WordPress', $pie, 'el pie de WordPress se sustituye' );

		add_filter( 'convoca_admin_appearance_url', static fn(): string => 'https://example.test/proyecto' );
		$this->assertStringContainsString( 'https://example.test/proyecto', \Convoca\Core\convoca_admin_footer( '' ) );
	}

	/**
	 * La versión que se anuncia es la de Convoca, no la de WordPress.
	 */
	public function test_la_version_del_pie(): void {
		$this->assertSame( 'Convoca v2.0.0', \Convoca\Core\convoca_admin_footer_version( '6.9' ), 'no se cuela la de WordPress' );
	}

	/**
	 * En las pantallas de Convoca se quitan las pestañas de ayuda y el bloque «Publicar»; en las demás, no.
	 */
	public function test_quita_ayuda_y_publicar_solo_en_convoca(): void {
		$convoca = $this->pantalla( 'conv-members' );
		$GLOBALS['_test_screen'] = $convoca;

		\Convoca\Core\convoca_remove_help_tab();
		\Convoca\Core\convoca_remove_submitdiv();

		$this->assertSame( 1, $convoca->ayudas_quitadas, 'la ayuda se quita' );

		$ajena = $this->pantalla( 'edit-post' );
		$GLOBALS['_test_screen'] = $ajena;
		\Convoca\Core\convoca_remove_help_tab();

		$this->assertSame( 0, $ajena->ayudas_quitadas, 'en una pantalla ajena no se toca' );
	}

	/**
	 * Sin pantalla (por ejemplo en una petición AJAX) no revienta.
	 */
	public function test_sin_pantalla_no_revienta(): void {
		$GLOBALS['_test_screen'] = null;

		\Convoca\Core\convoca_remove_help_tab();
		\Convoca\Core\convoca_remove_submitdiv();

		$this->assertTrue( true, 'no lanza' );
	}

	/**
	 * El menú «Nuevo» apunta a las pantallas de Convoca y **quita** los tipos que ya no existen.
	 */
	public function test_el_menu_nuevo(): void {
		$barra = new \WP_Admin_Bar();
		foreach ( array( 'new-miembro', 'new-actividad', 'new-proyecto', 'new-registro_hora', 'new-centro_turno', 'new-inscripcion', 'new-pago' ) as $id ) {
			$barra->nodes[ $id ] = (object) array(
				'id'   => $id,
				'href' => 'https://example.test/viejo',
			);
		}

		\Convoca\Core\convoca_customize_new_menu( $barra );

		$this->assertStringContainsString( 'conv-member-editor', $barra->nodes['new-miembro']->href );
		$this->assertStringContainsString( 'convoca-actividad-editor', $barra->nodes['new-actividad']->href );
		$this->assertStringNotContainsString( '/viejo', $barra->nodes['new-centro_turno']->href );
		$this->assertArrayNotHasKey( 'new-inscripcion', $barra->nodes, 'los retirados se quitan' );
		$this->assertArrayNotHasKey( 'new-pago', $barra->nodes );
	}

	/**
	 * Un nodo que no existe no se inventa: solo se reescriben los que ya están.
	 */
	public function test_un_nodo_ausente_no_se_crea(): void {
		$barra = new \WP_Admin_Bar();

		\Convoca\Core\convoca_customize_new_menu( $barra );

		$this->assertSame( array(), $barra->nodes, 'ni un nodo nuevo' );
	}

	/**
	 * El enlace al asistente solo lo ve quien puede: es una pantalla de administración.
	 */
	public function test_el_enlace_al_asistente(): void {
		$barra = new \WP_Admin_Bar();
		\Convoca\Core\convoca_add_wizard_link( $barra );
		$this->assertArrayHasKey( 'conv-wizard', $barra->nodes );
		$this->assertSame( 'top-secondary', $barra->nodes['conv-wizard']->parent );
		$this->assertStringContainsString( 'conv-setup-wizard', $barra->nodes['conv-wizard']->href );

		$barra                  = new \WP_Admin_Bar();
		$GLOBALS['_test_caps']  = array( 'manage_options' => false );
		\Convoca\Core\convoca_add_wizard_link( $barra );

		$this->assertArrayNotHasKey( 'conv-wizard', $barra->nodes, 'sin permiso, no hay enlace' );
	}

	/**
	 * Las cifras del escritorio: **sin el módulo de socios no se inventan** las de socios.
	 */
	public function test_metricas_sin_socios(): void {
		$metricas = \Convoca\Core\convoca_build_metrics();

		$this->assertArrayNotHasKey( 'members_total', $metricas, 'sin estados declarados no hay total' );
		$this->assertArrayHasKey( 'members_new_month', $metricas, 'pero las altas del mes se preguntan igual' );
		$this->assertArrayHasKey( 'turnos_sin_cubrir', $metricas );
		$this->assertArrayHasKey( 'turnos_url', $metricas );
	}

	/**
	 * Con los estados declarados por el módulo, se cuenta **cada** estado y se marca el de activos.
	 */
	public function test_metricas_con_socios(): void {
		add_filter(
			'convoca_member_states',
			static function (): array {
				return array(
					'activo'    => 'Activo',
					'pendiente' => 'Pendiente',
				);
			}
		);
		foreach ( array( 1 => 'activo', 2 => 'activo', 3 => 'pendiente' ) as $id => $estado ) {
			$GLOBALS['_wp_stores']['posts'][ $id ] = (object) array(
				'ID'          => $id,
				'post_type'   => 'miembro',
				'post_status' => 'publish',
				'post_title'  => 'Socio ' . $id,
				'post_date'   => '2026-10-01 10:00:00',
			);
			$GLOBALS['_wp_stores']['post_meta'][ $id ] = array( '_convoca_estado_miembro' => $estado );
		}

		$metricas = \Convoca\Core\convoca_build_metrics();

		$this->assertSame( 3, (int) $metricas['members_total'] );
		$this->assertSame( 2, (int) $metricas['members_activos'] );
		$this->assertStringContainsString( 'conv-members', $metricas['members_url'] );
	}

	/**
	 * Los ingresos del mes se leen en euros (céntimos / 100), no en céntimos.
	 */
	public function test_los_ingresos_en_euros(): void {
		$GLOBALS['_wp_stores']['get_var_mapa']['_convoca_amount_cents'] = 123456;

		$metricas = \Convoca\Core\convoca_build_metrics();

		$this->assertSame( 1234.56, (float) $metricas['payments_month'] );
		$this->assertStringContainsString( 'conv-gateway-payments', $metricas['payments_url'] );
	}

	/**
	 * Sin el módulo de inscripciones no hay cifra de «pendientes de pago»: no se enseña un cero que parece un dato.
	 */
	public function test_sin_inscripciones_no_hay_su_cifra(): void {
		$metricas = \Convoca\Core\convoca_build_metrics();

		$this->assertArrayNotHasKey( 'inscripciones_pendientes_pago', $metricas );
		$this->assertArrayNotHasKey( 'inscripciones_url', $metricas );
	}
}
