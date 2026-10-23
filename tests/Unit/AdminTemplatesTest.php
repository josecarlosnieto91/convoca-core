<?php
/**
 * Plantillas PDF: lo que se puede guardar y lo que no.
 *
 * Una plantilla se guarda desde el panel y luego la usa Dompdf para fabricar documentos con datos de personas. Sus
 * tres riesgos, y los tres se prueban aquí:
 *
 * - **código ejecutable o recursos externos**: el HTML de la plantilla va a un renderizador; un `script`, un
 *   `onerror` o un `url(http://…)` en un `style` no pueden quedar guardados;
 * - **agotar el servidor**: una plantilla de 5 MB, 20.000 etiquetas, 40 niveles de anidamiento o un base64 enorme
 *   se rechaza **antes** de intentar renderizarla;
 * - **perder lo que había**: si la opción guardada está corrupta, se aparta una copia y se restauran las de por
 *   defecto, en vez de dejar el panel sin plantillas.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use Convoca\Core\Admin_Templates;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'CONVOCA_COMMON_DIR' ) ) {
	define( 'CONVOCA_COMMON_DIR', dirname( __DIR__, 2 ) . '/' );
}

require_once dirname( __DIR__, 2 ) . '/includes/Utils.php';
require_once dirname( __DIR__, 2 ) . '/includes/Logger.php';
require_once dirname( __DIR__, 2 ) . '/includes/Backups.php';
require_once dirname( __DIR__, 2 ) . '/includes/Admin_Templates.php';

/**
 * Plantillas PDF.
 */
final class AdminTemplatesTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		unset( $_GET['page'], $_POST['convoca_restore_templates'], $_POST['convoca_save_templates'], $_POST['template'] );
		$GLOBALS['_wp_stores'] = array(
			'options'        => array(),
			'post_meta'      => array(),
			'transients'     => array( 'convoca_logger_table_exists' => 1 ),
			'posts'          => array(),
			'usuarios'       => array(),
			'db_inserts'     => array(),
			'queries'        => array(),
			'db_reads'       => array(),
			'options_pages'  => array(),
			'admin_notices'  => array(),
		);
		$GLOBALS['_test_caps'] = array();
	}

	/**
	 * Llama a un método privado.
	 *
	 * @param string $metodo   Método.
	 * @param mixed  ...$args  Argumentos.
	 * @return mixed
	 */
	private function privado( string $metodo, ...$args ) {
		$reflejo = new \ReflectionMethod( Admin_Templates::class, $metodo );

		return $reflejo->invoke( null, ...$args );
	}

	/**
	 * `init()` engancha el menú y las acciones.
	 */
	public function test_init_engancha(): void {
		Admin_Templates::init();

		$this->assertContains( array( 'Convoca\Core\Admin_Templates', 'add_menu_page' ), (array) ( $GLOBALS['_wp_hooks']['admin_menu'] ?? array() ) );
		$this->assertContains( array( 'Convoca\Core\Admin_Templates', 'handle_actions' ), (array) ( $GLOBALS['_wp_hooks']['admin_init'] ?? array() ) );
	}

	/**
	 * La página se registra con **su** capacidad, no con `manage_options`.
	 */
	public function test_la_pagina_se_registra_con_su_capacidad(): void {
		Admin_Templates::add_menu_page();

		$pagina = $GLOBALS['_wp_stores']['options_pages'][0] ?? array();
		$this->assertSame( 'manage_convoca_templates', $pagina['capability'], 'con la capacidad propia' );
		$this->assertSame( 'conv-pdf-templates', $pagina['menu_slug'] );
		$this->assertSame( array( 'Convoca\Core\Admin_Templates', 'render_page' ), $pagina['callback'] );
	}

	/**
	 * **Un `style` con recursos externos o posicionamiento se limpia**: `url()` puede pedir un recurso de fuera y
	 * `position:fixed` rompe la paginación del PDF.
	 */
	public function test_los_estilos_peligrosos_se_limpian(): void {
		$html = '<p style="color:red;background:url(http://ejemplo.test/x.png);position:fixed;top:0">Hola</p>';

		$salida = (string) $this->privado( 'sanitize_styles', $html );

		$this->assertStringContainsString( 'color:red', $salida, 'lo inocente se queda' );
		$this->assertStringNotContainsString( 'url(', $salida );
		$this->assertStringNotContainsString( 'position:fixed', $salida );
		$this->assertStringNotContainsString( 'http://ejemplo.test', $salida );
	}

	/**
	 * Un `style` que se queda vacío **desaparece** en vez de dejar `style=""`.
	 */
	public function test_un_estilo_vacio_desaparece(): void {
		$salida = (string) $this->privado( 'sanitize_styles', '<p style="expression(alert(1))">Hola</p>' );

		$this->assertStringNotContainsString( 'style=', $salida );
		$this->assertStringContainsString( '>Hola</p>', $salida, 'el contenido no se toca' );
	}

	/**
	 * Una propiedad **con** valor ejecutable se limpia y deja sólo el nombre: `background:alert(1)` no es CSS
	 * válido y no puede quedarse.
	 */
	public function test_una_propiedad_que_se_queda_sin_valor_desaparece(): void {
		$salida = (string) $this->privado( 'sanitize_styles', '<p style="color:red;background:expression(alert(1))">Hola</p>' );

		$this->assertStringContainsString( 'color:red', $salida, 'la propiedad buena se queda' );
		$this->assertStringNotContainsString( 'alert', $salida );
		$this->assertStringNotContainsString( 'background:', $salida, 'y la que se quedó sin valor, fuera' );
	}

	/**
	 * `@import` se lleva la regla entera: una plantilla no puede traerse CSS de fuera.
	 */
	public function test_el_import_se_elimina(): void {
		$salida = (string) $this->privado( 'sanitize_styles', '<p style="@import url(https://ejemplo.test/x.css);color:red">Hola</p>' );

		$this->assertStringNotContainsString( '@import', $salida );
		$this->assertStringNotContainsString( 'ejemplo.test', $salida );
		$this->assertStringContainsString( 'color:red', $salida );
	}

	/**
	 * **La plantilla pierde lo ejecutable y conserva su estructura**: es un documento, no una página web.
	 */
	public function test_la_plantilla_pierde_lo_ejecutable(): void {
		$plantilla = '<html><head><style>body{color:#333}</style></head><body>'
			. '<script>alert(1)</script><table cellspacing="0"><tr><td>Dato</td></tr></table>'
			. '<img src=x onerror="alert(1)"></body></html>';

		$salida = (string) $this->privado( 'sanitize_pdf_template', $plantilla );

		$this->assertStringContainsString( '<style>', $salida, 'el CSS del documento se conserva' );
		$this->assertStringContainsString( '<table', $salida );
		$this->assertStringContainsString( 'cellspacing', $salida, 'y sus atributos de maquetación' );
		$this->assertStringNotContainsString( '<script', $salida, 'sin scripts' );
		$this->assertStringNotContainsString( 'onerror', $salida, 'sin manejadores' );
	}

	/**
	 * **Un HTML mal formado no puede colar atributos**: con `style="background:url("javascript:…")"`, la comilla
	 * rompía el atributo y el resto salía como atributo nuevo de la etiqueta. Es una inyección, no un estilo feo.
	 */
	public function test_un_estilo_mal_formado_no_inyecta_atributos(): void {
		$plantilla = '<p style="background:url("javascript:alert(1)")">Hola</p>';

		$salida = (string) $this->privado( 'sanitize_pdf_template', $plantilla );

		$this->assertStringNotContainsString( 'javascript:', $salida, 'ni la URL ni su resto' );
		$this->assertStringNotContainsString( 'alert(1)', $salida );
		$this->assertStringContainsString( 'Hola', $salida, 'el contenido se conserva' );
	}

	/**
	 * Una plantilla vacía no es un error: se devuelve vacía.
	 */
	public function test_plantilla_vacia(): void {
		$this->assertSame( '', (string) $this->privado( 'sanitize_pdf_template', '' ) );
	}

	/**
	 * **Los límites se rechazan antes de renderizar**: tamaño, número de etiquetas, profundidad y base64.
	 */
	public function test_los_limites_de_tamano(): void {
		$this->assertFalse( $this->privado( 'validate_template', str_repeat( 'a', 500001 ) ), 'más de 500 KB' );
		$this->assertTrue( $this->privado( 'validate_template', '<p>' . str_repeat( 'a', 1000 ) . '</p>' ), 'lo normal pasa' );
	}

	/**
	 * Un número absurdo de etiquetas no se acepta (10.000 es el techo).
	 */
	public function test_el_limite_de_etiquetas(): void {
		$muchas = str_repeat( '<p>x</p>', 12000 );

		$this->assertFalse( $this->privado( 'validate_template', $muchas ) );
	}

	/**
	 * Un anidamiento de más de 30 niveles se rechaza: Dompdf se atraganta y el panel se queda colgado.
	 */
	public function test_el_limite_de_profundidad(): void {
		$hondo = str_repeat( '<div>', 40 ) . 'x' . str_repeat( '</div>', 40 );

		$this->assertFalse( $this->privado( 'validate_template', $hondo ) );
	}

	/**
	 * Un base64 enorme se rechaza: el PDF tardaría minutos y el proceso se comería la memoria.
	 */
	public function test_el_limite_de_base64(): void {
		// Sin comillas a propósito: con comillas lo rechazaría antes el límite de atributos de 500 caracteres y
		// esta comprobación no se estaría midiendo.
		$grande = '<img src=data:image/png;base64,' . str_repeat( 'A', 60000 ) . '>';

		$this->assertFalse( $this->privado( 'validate_template', $grande ) );
	}

	/**
	 * La primera vez, las plantillas se crean solas con sus cuatro documentos.
	 */
	public function test_la_primera_vez_crea_las_de_por_defecto(): void {
		$plantillas = Admin_Templates::get_templates();

		$this->assertSame(
			array( 'acuerdo_incorporacion', 'anexo_voluntariado', 'certificado', 'desvinculacion' ),
			array_keys( $plantillas ),
			'las cuatro de siempre'
		);
		foreach ( $plantillas as $clave => $dato ) {
			$this->assertArrayHasKey( 'name', $dato, "«{$clave}» tiene nombre" );
			$this->assertArrayHasKey( 'content', $dato, "«{$clave}» tiene contenido" );
		}
		$this->assertNotEmpty( $plantillas['certificado']['content'], 'y el contenido no está vacío' );
	}

	/**
	 * Si la opción está corrupta (no es un array), se aparta copia y se restauran: el panel no se queda sin nada.
	 */
	public function test_datos_corruptos_se_apartan_y_se_restaura(): void {
		$GLOBALS['_wp_stores']['options']['convoca_pdf_templates'] = 'esto no es una plantilla';

		$plantillas = Admin_Templates::get_templates();

		$this->assertIsArray( $plantillas );
		$this->assertArrayHasKey( 'certificado', $plantillas, 'se restauraron' );
	}

	/**
	 * Y si a una plantilla le falta el nombre, también: es dato corrupto, no una plantilla válida.
	 */
	public function test_una_plantilla_sin_nombre_es_corrupta(): void {
		$GLOBALS['_wp_stores']['options']['convoca_pdf_templates'] = array(
			'certificado' => array( 'content' => '<p>Hola</p>' ),
		);

		$plantillas = Admin_Templates::get_templates();

		$this->assertArrayHasKey( 'acuerdo_incorporacion', $plantillas, 'vuelven las de por defecto' );
	}

	/**
	 * `handle_actions()`, fuera de su página, no toca nada.
	 */
	public function test_fuera_de_su_pagina_no_hace_nada(): void {
		$_GET['page']                        = 'otra-pagina';
		$_POST['convoca_restore_templates'] = '1';

		Admin_Templates::handle_actions();

		$this->assertArrayNotHasKey( 'convoca_pdf_templates', $GLOBALS['_wp_stores']['options'], 'no restauró' );
	}

	/**
	 * Sin la capacidad, tampoco: mirar la URL no basta.
	 */
	public function test_sin_capacidad_no_hace_nada(): void {
		$_GET['page']                        = 'conv-pdf-templates';
		$_POST['convoca_restore_templates'] = '1';
		$GLOBALS['_test_caps']               = array( 'manage_convoca_templates' => false );

		Admin_Templates::handle_actions();

		$this->assertArrayNotHasKey( 'convoca_pdf_templates', $GLOBALS['_wp_stores']['options'], 'no restauró' );
	}

	/**
	 * Con permiso, restaurar deja las de por defecto y avisa.
	 */
	public function test_restaurar_con_permiso(): void {
		$_GET['page']                        = 'conv-pdf-templates';
		$_POST['convoca_restore_templates'] = '1';
		$GLOBALS['_test_caps']               = array( 'manage_convoca_templates' => true );

		Admin_Templates::handle_actions();

		$plantillas = (array) ( $GLOBALS['_wp_stores']['options']['convoca_pdf_templates'] ?? array() );
		$this->assertArrayHasKey( 'certificado', $plantillas, 'restauradas' );
	}

	/**
	 * Guardar **sin** `unfiltered_html` sanea: el HTML peligroso no llega a la opción.
	 */
	public function test_guardar_sanea_si_no_hay_unfiltered_html(): void {
		Admin_Templates::get_templates();
		$_GET['page']                     = 'conv-pdf-templates';
		$_POST['convoca_save_templates']  = '1';
		$_POST['template']                = array(
			'certificado' => '<p>Hola</p><script>alert(1)</script>',
		);
		$GLOBALS['_test_caps']            = array(
			'manage_convoca_templates' => true,
			'unfiltered_html'          => false,
		);

		Admin_Templates::handle_actions();

		$guardado = (string) $GLOBALS['_wp_stores']['options']['convoca_pdf_templates']['certificado']['content'];
		$this->assertStringContainsString( 'Hola', $guardado );
		$this->assertStringNotContainsString( '<script', $guardado, 'el script no se guarda' );
	}
}
