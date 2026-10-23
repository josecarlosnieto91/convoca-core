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
		$this->previo             = $GLOBALS['_wp_stores'] ?? array();
		$GLOBALS['_wp_filters']   = array();
		// Las redirecciones y la página actual son globales y **sobreviven** entre pruebas: sin limpiarlas, esta
		// prueba veía las de otra (semilla 71) y creía que había redirigido de más.
		$GLOBALS['_wp_redirects'] = array();
		unset( $GLOBALS['pagenow'], $_GET['page'] );
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
		unset( $GLOBALS['_wp_redirects'], $GLOBALS['pagenow'], $_GET['page'] );
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

		// Aislamiento: con las **páginas ya sembradas**, lo único que puede impedir completar es la tabla. Si no se
		// aísla, el caso también falla por las páginas y la comprobación de tablas podría no existir sin notarse.
		$this->sembrar_paginas();
		$this->assertFalse( $this->privado( 'is_config_complete' ), 'faltan tablas' );

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

	/**
	 * Los planes del paso 3, sin escribir: lo que se guardaría.
	 */
	public function test_los_planes_del_paso_3(): void {
		add_filter(
			'convoca_member_plans',
			static fn(): array => array(
				'base'     => array(
					'label'     => 'Base',
					'modalidad' => 'Numerario',
					'price'     => 30,
					'active'    => true,
				),
				'selector' => array(
					'label'     => 'Selector',
					'modalidad' => 'Otra',
				),
				'juvenil2' => array(
					'label'     => 'Joven',
					'modalidad' => 'Juvenil',
					'price'     => 15,
					'active'    => true,
				),
			)
		);

		$res = $this->privado(
			'procesar_planes',
			array(
				'base'     => array(
					'label'     => '  Base renombrada ',
					'modalidad' => 'Familiar',
					'price'     => '45.5',
					'active'    => '1',
					'slug'      => 'base-nueva',
				),
				// `juvenil2` no viaja: el checkbox estaba desmarcado.
			)
		);

		$this->assertSame( '', $res['colision'] );
		$this->assertArrayHasKey( 'base-nueva', $res['planes'], 'el slug nuevo es la clave' );
		$this->assertArrayNotHasKey( 'base', $res['planes'] );
		$this->assertSame( 'Base renombrada', $res['planes']['base-nueva']['label'] );
		$this->assertSame( 'Familiar', $res['planes']['base-nueva']['modalidad'] );
		$this->assertSame( 45.5, $res['planes']['base-nueva']['price'] );
		$this->assertTrue( $res['planes']['base-nueva']['active'] );
		$this->assertSame( array( 'base' => 'base-nueva' ), $res['movimientos'], 'hay que migrar los socios' );

		$this->assertTrue( $res['planes']['juvenil2']['active'] === false, 'desmarcado → desactivado, no borrado' );
		$this->assertSame( 'Selector', $res['planes']['selector']['label'], 'el selector no se toca' );

		// Un precio que no es número **no** se guarda: se queda el que había (30, no 0 y no «carísimo»).
		$otro = $this->privado(
			'procesar_planes',
			array(
				'base'     => array(
					'label'  => 'Base',
					'price'  => 'carísimo',
					'active' => '1',
				),
				'juvenil2' => array( 'label' => 'Joven', 'price' => '15', 'active' => '1' ),
			)
		);
		$this->assertSame( 30, $otro['planes']['base']['price'], 'un precio no numérico no sustituye al bueno' );
		$this->assertArrayNotHasKey( 'active', $res['planes']['selector'] );
	}

	/**
	 * Un slug reservado o ya usado **no se guarda**: vuelve al paso con aviso y no pisa el plan existente.
	 */
	public function test_el_slug_en_conflicto(): void {
		add_filter(
			'convoca_member_plans',
			static fn(): array => array(
				'base'  => array(
					'label'     => 'Base',
					'modalidad' => 'Numerario',
					'active'    => true,
				),
				'otro' => array(
					'label'     => 'Otro',
					'modalidad' => 'Familiar',
				),
			)
		);

		$reservado = $this->privado(
			'procesar_planes',
			array(
				'base' => array(
					'label'     => 'Base',
					'modalidad' => 'Numerario',
					'active'    => '1',
					'slug'      => 'familiar',
				),
				'otro' => array(
					'label'     => 'Otro',
					'modalidad' => 'Familiar',
					'slug'      => 'otro',
				),
			)
		);
		$this->assertSame( 'familiar', $reservado['colision'], '«familiar» está reservado' );

		$repetido = $this->privado(
			'procesar_planes',
			array(
				'base' => array(
					'label'     => 'Base',
					'modalidad' => 'Numerario',
					'active'    => '1',
					'slug'      => 'otro',
				),
				'otro' => array(
					'label'     => 'Otro',
					'modalidad' => 'Familiar',
					'slug'      => 'otro',
				),
			)
		);
		$this->assertSame( 'otro', $repetido['colision'], 'ya existe' );
	}

	/**
	 * Sin el módulo de socios no hay nada que procesar.
	 */
	public function test_sin_planes_no_hay_nada(): void {
		$res = $this->privado( 'procesar_planes', array( 'base' => array( 'label' => 'x' ) ) );

		$this->assertNull( $res['planes'] );
		$this->assertSame( array(), $res['movimientos'] );
	}

	/**
	 * La redirección al asistente: sólo cuando toca, y una vez por sesión.
	 *
	 * Cada guarda es una forma de no ser pesado: en ajax, en cron, en la propia pantalla del asistente, en una página
	 * que no es el escritorio o los plugins, o si ya se enseñó hace poco. Si el sitio ya está configurado, se marca
	 * como completado y no se redirige nunca más.
	 */
	public function test_la_redireccion_al_asistente(): void {
		$GLOBALS['_test_caps'] = array( 'manage_options' => true );
		$GLOBALS['pagenow']    = 'index.php';

		// 1) Sin permiso no se hace nada.
		$GLOBALS['_test_caps'] = array( 'manage_options' => false );
		$this->wizard->maybe_redirect();
		$this->assertSame( array(), $GLOBALS['_wp_redirects'] ?? array() );

		// 2) Ya completado o descartado: silencio.
		$GLOBALS['_test_caps'] = array( 'manage_options' => true );
		$GLOBALS['_wp_stores']['options'][ Admin_Setup_Wizard::COMPLETED_OPTION ] = 1;
		$this->wizard->maybe_redirect();
		$this->assertSame( array(), $GLOBALS['_wp_redirects'] ?? array() );
		unset( $GLOBALS['_wp_stores']['options'][ Admin_Setup_Wizard::COMPLETED_OPTION ] );

		$GLOBALS['_wp_stores']['options'][ Admin_Setup_Wizard::DISMISS_OPTION ] = 1;
		$this->wizard->maybe_redirect();
		$this->assertSame( array(), $GLOBALS['_wp_redirects'] ?? array() );
		unset( $GLOBALS['_wp_stores']['options'][ Admin_Setup_Wizard::DISMISS_OPTION ] );

		// 3) Ajax y cron no se interrumpen.
		$GLOBALS['_wp_stores']['doing_ajax'] = true;
		$this->wizard->maybe_redirect();
		$this->assertSame( array(), $GLOBALS['_wp_redirects'] ?? array() );
		$GLOBALS['_wp_stores']['doing_ajax'] = false;
		$GLOBALS['_wp_stores']['doing_cron'] = true;
		$this->wizard->maybe_redirect();
		$this->assertSame( array(), $GLOBALS['_wp_redirects'] ?? array() );
		$GLOBALS['_wp_stores']['doing_cron'] = false;

		// 4) En la propia pantalla del asistente, no se redirige a sí misma.
		$_GET['page'] = 'conv-setup-wizard';
		$this->wizard->maybe_redirect();
		$this->assertSame( array(), $GLOBALS['_wp_redirects'] ?? array() );
		unset( $_GET['page'] );

		// 5) Si ya se enseñó en esta sesión, no se repite.
		$GLOBALS['_wp_stores']['transients'][ Admin_Setup_Wizard::SEEN_OPTION . '_' . get_current_user_id() ] = 1;
		$this->wizard->maybe_redirect();
		$this->assertSame( array(), $GLOBALS['_wp_redirects'] ?? array() );
		unset( $GLOBALS['_wp_stores']['transients'][ Admin_Setup_Wizard::SEEN_OPTION . '_' . get_current_user_id() ] );

		// 6) Desde una página que no toca (una lista cualquiera), tampoco.
		$GLOBALS['pagenow'] = 'edit.php';
		$this->wizard->maybe_redirect();
		$this->assertSame( array(), $GLOBALS['_wp_redirects'] ?? array() );

		// 7) Si ya está configurado, se marca como completado y no se molesta más.
		$this->sembrar_paginas();
		$GLOBALS['pagenow'] = 'index.php';
		$this->wizard->maybe_redirect();
		$this->assertSame( 1, (int) $GLOBALS['_wp_stores']['options'][ Admin_Setup_Wizard::COMPLETED_OPTION ] );
		$this->assertSame( array(), $GLOBALS['_wp_redirects'] ?? array() );
	}

	/**
	 * Y cuando todo está por configurar y estamos en el escritorio: redirige **una vez** y marca la sesión para no
	 * repetirlo en cada carga. El doble de `wp_safe_redirect()` corta con excepción, como el `exit` de WordPress.
	 */
	public function test_redirige_una_vez(): void {
		$GLOBALS['_test_caps'] = array( 'manage_options' => true );
		$GLOBALS['pagenow']    = 'index.php';

		try {
			$this->wizard->maybe_redirect();
			$this->fail( 'debía redirigir' );
		} catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( 'wp_safe_redirect', $e->getMessage() );
		}

		$this->assertCount( 1, $GLOBALS['_wp_redirects'] );
		$this->assertStringContainsString( 'page=conv-setup-wizard', $GLOBALS['_wp_redirects'][0]['url'] );
		$this->assertSame(
			1,
			(int) ( $GLOBALS['_wp_stores']['transients'][ Admin_Setup_Wizard::SEEN_OPTION . '_' . get_current_user_id() ] ?? 0 ),
			'queda marcado para esta sesión'
		);

		// La segunda vez ya no molesta.
		$this->wizard->maybe_redirect();
		$this->assertCount( 1, $GLOBALS['_wp_redirects'] );
	}
}
