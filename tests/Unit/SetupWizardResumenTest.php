<?php
/**
 * El resumen del asistente: las seis comprobaciones que deciden si el sitio está listo.
 *
 * Este resumen es lo último que ve quien instala, y es el que dice «falta esto». Si miente en positivo, se da por
 * terminada una instalación que no lo está; si miente en negativo, se persigue un fallo que no existe. Se prueban las
 * seis filas con su caso bueno y su caso malo.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use Convoca\Core\Admin_Setup_Wizard;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Utils.php';
require_once dirname( __DIR__, 2 ) . '/includes/Logger.php';
require_once dirname( __DIR__, 2 ) . '/includes/Features.php';
require_once dirname( __DIR__, 2 ) . '/includes/Admin_Setup_Wizard.php';

/**
 * Resumen del asistente.
 */
final class SetupWizardResumenTest extends TestCase {

	/**
	 * Estado global previo.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Asistente.
	 *
	 * @var Admin_Setup_Wizard
	 */
	private Admin_Setup_Wizard $wizard;

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->previo           = $GLOBALS['_wp_stores'] ?? array();
		$GLOBALS['_wp_filters'] = array();
		$GLOBALS['_wp_stores']  = array(
			'options'       => array(),
			'post_meta'     => array(),
			'transients'    => array(),
			'posts'         => array(),
			'usuarios'      => array(),
			'db_inserts'    => array(),
			'queries'       => array(),
			'get_var_mapa'  => array(),
			'locks'         => array(),
			'active_plugins' => array(),
		);
		$GLOBALS['_test_caps'] = array();
		$this->wizard          = new Admin_Setup_Wizard();
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores'] = $this->previo;
	}

	/**
	 * Llama a un método privado.
	 *
	 * @param string $metodo Método.
	 * @param mixed  ...$args Argumentos.
	 * @return mixed
	 */
	private function privado( string $metodo, ...$args ) {
		$reflejo = new \ReflectionMethod( Admin_Setup_Wizard::class, $metodo );

		return $reflejo->invoke( $this->wizard, ...$args );
	}

	/**
	 * Siembra las páginas del sistema con su shortcode.
	 *
	 * @param array<int, string> $sin_shortcode Slugs que se dejan sin el shortcode.
	 * @return void
	 */
	private function sembrar_paginas( array $sin_shortcode = array() ): void {
		$id = 1;
		foreach ( Admin_Setup_Wizard::system_pages() as $slug => $info ) {
			$p               = new \WP_Post();
			$p->ID           = $id;
			$p->post_type    = 'page';
			$p->post_status  = 'publish';
			$p->post_name    = $slug;
			$p->post_content = in_array( $slug, $sin_shortcode, true ) ? 'texto sin el atajo' : $info['sc'];
			$GLOBALS['_wp_stores']['posts'][ $id ] = $p;
			++$id;
		}
	}

	/**
	 * Todo en orden: las seis filas en verde y sin «falta».
	 */
	public function test_el_resumen_completo(): void {
		$this->sembrar_paginas();
		$GLOBALS['_wp_stores']['options'] = array(
			'convoca_gateway_settings'     => array( 'merchant_code' => '123456789' ),
			'convoca_shifts_hora_apertura' => '09:00',
			'convoca_shifts_hora_cierre'   => '19:00',
		);
		add_filter(
			'convoca_member_plans',
			static fn(): array => array(
				'base'   => array(
					'label'     => 'Base',
					'modalidad' => 'Numerario',
					'price'     => 30,
					'active'    => true,
				),
			)
		);
		$GLOBALS['_wp_stores']['active_plugins'] = array(
			'convoca-members/convoca-members.php',
			'convoca-enroll/convoca-enroll.php',
			'convoca-gateway/convoca-gateway.php',
			'convoca-shifts/convoca-shifts.php',
			'convoca-publisher/convoca-publisher.php',
			'convoca-assistant/convoca-assistant.php',
		);

		$filas = $this->privado( 'summary_rows' );

		$this->assertCount( 6, $filas );
		foreach ( $filas as $clave => $fila ) {
			$this->assertTrue( $fila['ok'], "fila {$clave} en verde" );
			$this->assertSame( '', $fila['missing'], "fila {$clave} sin pendientes" );
		}
		$this->assertStringContainsString( 'Base · 30,00 €', implode( ' ', $filas['3']['detail'] ) );
	}

	/**
	 * Faltando tablas, la primera fila lo dice y da la instrucción; y el configurador no se da por completo.
	 */
	public function test_sin_tablas(): void {
		$GLOBALS['_wp_stores']['get_var_mapa'] = array( 'convoca_logs' => '' );

		$filas = $this->privado( 'summary_rows' );

		$this->assertFalse( $filas['1']['ok'] );
		$this->assertStringContainsString( 'Faltan tablas', implode( ' ', $filas['1']['detail'] ) );
		$this->assertStringContainsString( 'paso 1', $filas['1']['missing'] );
		$this->assertFalse( $this->privado( 'is_config_complete' ) );

		// Y con todo lo demás en orden, lo que impide completar es **la tabla**: así el caso de arriba no pasa por
		// otra causa y el mutante que quita la comprobación de tablas no sobrevive.
		$this->sembrar_paginas();
		unset( $GLOBALS['_wp_stores']['get_var_mapa'] );
		$this->assertTrue( $this->privado( 'is_config_complete' ), 'con las páginas y las tablas, sí' );
	}

	/**
	 * Una página sin su shortcode no cuenta como página hecha: salía publicada con el texto a la vista.
	 */
	public function test_una_pagina_sin_shortcode_no_cuenta(): void {
		$this->sembrar_paginas( array( 'alta-socios' ) );

		$filas = $this->privado( 'summary_rows' );

		$this->assertFalse( $filas['2']['ok'] );
		$this->assertStringContainsString( '⚠', implode( ' ', $filas['2']['detail'] ) );
		$this->assertStringContainsString( 'paso 2', $filas['2']['missing'] );
		$this->assertFalse( $this->privado( 'is_config_complete' ), 'el asistente no puede darse por terminado' );
	}

	/**
	 * Los planes: sólo las modalidades de cobro cuentan, y un plan desactivado no se enseña.
	 */
	public function test_los_planes_que_cuentan(): void {
		add_filter(
			'convoca_member_plans',
			static fn(): array => array(
				'base'     => array(
					'label'     => 'Base',
					'modalidad' => 'Numerario',
					'price'     => 30,
					'active'    => true,
				),
				'familiar' => array(
					'label'     => 'Familiar',
					'modalidad' => 'Familiar',
					'price'     => 45,
					'active'    => false,
				),
				'selector' => array(
					'label'     => 'Selector',
					'modalidad' => 'Otra cosa',
					'price'     => 10,
				),
			)
		);

		$filas = $this->privado( 'summary_rows' );

		$detalle = implode( ' | ', $filas['3']['detail'] );
		$this->assertStringContainsString( '1 planes activos', $detalle );
		$this->assertStringContainsString( 'Base · 30,00 €', $detalle );
		$this->assertStringNotContainsString( 'Familiar', $detalle, 'está desactivado' );
		$this->assertStringNotContainsString( 'Selector', $detalle, 'no es una modalidad de cobro' );
	}

	/**
	 * Sin ningún plan activo, la fila lo dice.
	 */
	public function test_sin_planes_activos(): void {
		$filas = $this->privado( 'summary_rows' );

		$this->assertFalse( $filas['3']['ok'] );
		$this->assertStringContainsString( 'Sin planes activos', implode( ' ', $filas['3']['detail'] ) );
		$this->assertStringContainsString( 'paso 3', $filas['3']['missing'] );
	}

	/**
	 * Redsys y turnos: cada uno con su dato, y el horario con guiones cuando falta.
	 */
	public function test_pagos_y_turnos(): void {
		$filas = $this->privado( 'summary_rows' );
		$this->assertFalse( $filas['4']['ok'] );
		$this->assertStringContainsString( 'Merchant Code vacío', implode( ' ', $filas['4']['detail'] ) );
		$this->assertStringContainsString( '--:--', implode( ' ', $filas['5']['detail'] ) );

		$GLOBALS['_wp_stores']['options']['convoca_gateway_settings']  = array( 'merchant_code' => '123' );
		$GLOBALS['_wp_stores']['options']['convoca_shifts_hora_apertura'] = '08:00';
		$GLOBALS['_wp_stores']['options']['convoca_shifts_hora_cierre']   = '20:00';

		$filas = $this->privado( 'summary_rows' );
		$this->assertTrue( $filas['4']['ok'] );
		$this->assertTrue( $filas['5']['ok'] );
		$this->assertStringContainsString( '08:00 – 20:00', implode( ' ', $filas['5']['detail'] ) );

		// Con **una sola** hora el horario no está definido: media jornada no es un horario.
		$GLOBALS['_wp_stores']['options']['convoca_shifts_hora_cierre'] = '';
		$filas = $this->privado( 'summary_rows' );
		$this->assertFalse( $filas['5']['ok'], 'con sólo la apertura no vale' );
	}

	/**
	 * El ecosistema: cuenta los módulos activos y **nombra** los que faltan.
	 */
	public function test_el_ecosistema_nombra_lo_que_falta(): void {
		$GLOBALS['_wp_stores']['active_plugins'] = array(
			'convoca-members/convoca-members.php',
			'convoca-enroll/convoca-enroll.php',
		);

		$filas = $this->privado( 'summary_rows' );

		$this->assertFalse( $filas['6']['ok'] );
		$detalle = implode( ' ', $filas['6']['detail'] );
		$this->assertStringContainsString( '2 de 6 módulos activos', $detalle );
		$this->assertStringContainsString( 'Convoca Gateway', $detalle );
		$this->assertStringContainsString( 'Faltan:', $detalle );
	}
}
