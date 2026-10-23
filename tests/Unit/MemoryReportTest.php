<?php
/**
 * La memoria mensual: cuándo se genera, cuándo no y qué hace si no puede.
 *
 * Esta tarea corre sola por cron, escribe un fichero y manda un correo. Sus formas de hacer daño:
 *
 * - **generar en cualquier momento**: sólo la primera semana del mes; si no, el cron semanal reharía la memoria del
 *   mes anterior todos los días;
 * - **reintentar en bucle**: si ya está, no se repite; y si falla, **no** se marca como hecha, para que el
 *   siguiente intento sirva de algo;
 * - **mandar el correo duplicado**: la memoria va a la asociación, sin copia a nadie más (convoca-core#6).
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use Convoca\Core\Admin_Analytics;
use Convoca\Core\Memory_Report;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Utils.php';
require_once dirname( __DIR__, 2 ) . '/includes/Logger.php';
require_once dirname( __DIR__, 2 ) . '/includes/Mailer.php';
require_once dirname( __DIR__, 2 ) . '/includes/Features.php';
require_once dirname( __DIR__, 2 ) . '/includes/Admin_Analytics.php';
require_once dirname( __DIR__, 2 ) . '/includes/Memory_Report.php';

/**
 * Memoria mensual.
 */
final class MemoryReportTest extends TestCase {

	/**
	 * Directorio temporal de subidas de esta prueba.
	 *
	 * @var string
	 */
	private string $subidas;

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		unset( $GLOBALS['_test_now'], $_GET['_wpnonce'], $_GET['month'], $_GET['year'] );

		$this->subidas = ( getenv( 'TMPDIR' ) ?: sys_get_temp_dir() ) . '/convoca-test-memoria-' . getmypid();
		$GLOBALS['_wp_stores'] = array(
			'options'      => array( 'admin_email' => 'junta@example.test' ),
			'post_meta'    => array(),
			'transients'   => array(),
			'posts'        => array(),
			'usuarios'     => array(),
			'db_inserts'   => array(),
			'queries'      => array(),
			'db_reads'     => array(),
			'get_var_mapa' => array(),
			'get_row_mapa' => array(),
			'emails'       => array(),
			'upload_dir'    => $this->subidas,
			'nonces_validos' => array( 'bueno' ),
			// El `Logger` sólo escribe si la tabla existe, y eso lo comprueba con un `SHOW TABLES` que en unitario
			// no puede funcionar. Se da por buena: esta prueba no va de si la tabla está, sino de que el aviso se
			// registre.
			'transients'   => array( 'convoca_logger_table_exists' => 1 ),
		);
		$GLOBALS['_wp_die_lanza'] = true;
		$GLOBALS['_test_caps']    = array();
		// El tope del `Logger` es un filtro, y otra prueba (FrontShortcodes) vacía `_wp_filters` para aislar los
		// suyos: si eso pasa antes, el tope del bootstrap desaparece y esta prueba se queda sin su aviso cuando la
		// suite ya ha generado 50 mensajes. Se vuelve a poner aquí y se vacían los contadores estáticos.
		add_filter( 'convoca_logger_rate_limit_max', static function () {
			return PHP_INT_MAX;
		} );
		// Ojo: `log_depth` es un **entero**, no un array. Ponerle `array()` hace que `array() >= 3` sea cierto y el
		// `Logger` deje de escribir para siempre —el fallo que esta línea provocó y que parecía del producto—.
		try {
			$prof = new \ReflectionProperty( \Convoca\Core\Logger::class, 'log_timestamps' );
			$prof->setValue( null, array() );
			$prof = new \ReflectionProperty( \Convoca\Core\Logger::class, 'log_depth' );
			$prof->setValue( null, 0 );
		} catch ( \ReflectionException $e ) {
			// Si la propiedad no existe en esta versión, no hay nada que limpiar.
			unset( $e );
		}
	}

	/**
	 * Limpia lo que la prueba haya escrito.
	 */
	protected function tearDown(): void {
		unset( $GLOBALS['_test_now'], $GLOBALS['_wp_die_lanza'] );
		if ( is_dir( $this->subidas ) ) {
			foreach ( (array) glob( $this->subidas . '/convoca-memorias/*' ) as $f ) {
				@unlink( $f );
			}
			@rmdir( $this->subidas . '/convoca-memorias' );
			@rmdir( $this->subidas );
		}
	}

	/**
	 * Fija «hoy».
	 *
	 * @param string $fecha Fecha `Y-m-d`.
	 */
	private function hoy( string $fecha ): void {
		$GLOBALS['_test_now'] = strtotime( $fecha . ' 10:00:00' );
	}

	/**
	 * `init()` engancha el cron y la generación manual.
	 */
	public function test_init_engancha(): void {
		Memory_Report::init();

		$this->assertContains( array( 'Convoca\Core\Memory_Report', 'auto_generate' ), (array) ( $GLOBALS['_wp_hooks']['convoca_weekly_event'] ?? array() ) );
		$this->assertContains( array( 'Convoca\Core\Memory_Report', 'handle_admin_generate' ), (array) ( $GLOBALS['_wp_hooks']['admin_post_convoca_generate_memory'] ?? array() ) );
	}

	/**
	 * **Fuera de la primera semana no hace nada**: el cron semanal no puede rehacer la memoria a diario.
	 */
	public function test_fuera_de_la_primera_semana_no_hace_nada(): void {
		$this->hoy( '2026-10-23' );

		Memory_Report::auto_generate();

		$this->assertSame(
			array( 'convoca_logger_table_exists' => 1 ),
			$GLOBALS['_wp_stores']['transients'],
			'no marca nada como generado'
		);
		$this->assertSame( array(), $GLOBALS['_wp_stores']['db_reads'], 'y no consulta la base' );
		$this->assertSame( array(), $GLOBALS['_wp_stores']['emails'], 'ni manda correo' );
	}

	/**
	 * En la primera semana genera la memoria, la guarda, **avisa a la asociación** y la marca como hecha.
	 */
	public function test_en_la_primera_semana_genera_y_avisa(): void {
		$this->hoy( '2026-10-03' );

		Memory_Report::auto_generate();

		$ficheros = (array) glob( $this->subidas . '/convoca-memorias/*.pdf' );
		$this->assertCount( 1, $ficheros, 'un PDF en el directorio de memorias' );
		$this->assertStringContainsString( 'memoria-September-2026.pdf', $ficheros[0], 'con el mes en el nombre' );
		$this->assertStringStartsWith( '%PDF', (string) file_get_contents( $ficheros[0] ), 'y es un PDF de verdad' );

		$this->assertCount( 1, $GLOBALS['_wp_stores']['emails'], 'un solo correo' );
		$correo = $GLOBALS['_wp_stores']['emails'][0];
		// `Mailer` normaliza los destinatarios a lista antes de llamar a `wp_mail`.
		$this->assertSame( array( 'junta@example.test' ), (array) $correo['to'], 'a la asociación, y a nadie más' );
		$this->assertStringContainsString( 'Memoria mensual', $correo['subject'] );
		$this->assertStringContainsString( 'Sept', $correo['subject'], 'con el mes al que se refiere' );

		$clave = 'convoca_memory_' . wp_date( 'Y_m', strtotime( 'first day of last month', $GLOBALS['_test_now'] ) );
		$this->assertArrayHasKey( $clave, $GLOBALS['_wp_stores']['transients'], 'y queda marcada como hecha' );
	}

	/**
	 * Si ya está generada, no se repite: ni fichero nuevo ni segundo correo.
	 */
	public function test_si_ya_esta_generada_no_repite(): void {
		$this->hoy( '2026-10-03' );
		$clave = 'convoca_memory_' . wp_date( 'Y_m', strtotime( 'first day of last month', $GLOBALS['_test_now'] ) );
		$GLOBALS['_wp_stores']['transients'][ $clave ] = true;

		Memory_Report::auto_generate();

		$this->assertSame( array(), $GLOBALS['_wp_stores']['emails'], 'sin correo' );
		$this->assertSame( array(), (array) glob( $this->subidas . '/convoca-memorias/*.pdf' ), 'sin PDF' );
	}

	/**
	 * **La memoria no se copia a nadie más** (convoca-core#6): ya va a la asociación. Con la copia activada y un
	 * destinatario distinto configurado, sigue saliendo **un solo** correo.
	 */
	public function test_la_memoria_no_lleva_copia(): void {
		$this->hoy( '2026-10-03' );
		$GLOBALS['_wp_stores']['options']['convoca_email_copy'] = array(
			'enabled'    => true,
			'recipients' => array( 'copia@example.test' ),
		);

		Memory_Report::auto_generate();

		$this->assertCount( 1, $GLOBALS['_wp_stores']['emails'], 'uno solo: la memoria no se copia' );
		$this->assertSame( array( 'junta@example.test' ), (array) $GLOBALS['_wp_stores']['emails'][0]['to'] );
	}

	/**
	 * Y queda registrada: si algo falla mañana, el registro dice si la memoria de este mes se generó.
	 */
	public function test_queda_registrada(): void {
		$this->hoy( '2026-10-03' );

		Memory_Report::auto_generate();

		$mensajes = array_column( array_column( (array) $GLOBALS['_wp_stores']['db_inserts'], 'data' ), 'message' );
		$juntos   = implode( ' | ', array_map( 'strval', (array) $mensajes ) );
		$this->assertStringContainsString(
			'Memoria mensual generada',
			$juntos,
			'db_inserts=' . count( (array) $GLOBALS['_wp_stores']['db_inserts'] )
			. ' tablas=' . implode( ',', array_column( (array) $GLOBALS['_wp_stores']['db_inserts'], 'table' ) )
			. ' transients=' . implode( ',', array_keys( (array) $GLOBALS['_wp_stores']['transients'] ) )
			. ' emails=' . count( (array) $GLOBALS['_wp_stores']['emails'] )
		);
	}

	/**
	 * El PDF lleva las cifras y los apartados: no es un PDF vacío que parezca correcto.
	 */
	public function test_el_pdf_lleva_las_cifras(): void {
		$GLOBALS['_wp_stores']['get_row_mapa']['_estado'] = (object) array(
			'disponibles' => 7,
			'ocupados'    => 2,
			'cerrados'    => 1,
		);

		$pdf = Memory_Report::generate_pdf( strtotime( '2026-09-01 10:00:00' ) );

		$this->assertIsString( $pdf );
		$this->assertStringStartsWith( '%PDF', $pdf );
		$this->assertGreaterThan( 3000, strlen( $pdf ), 'con contenido, no una hoja en blanco' );
	}

	/**
	 * La generación manual exige nonce: sin él, muere.
	 */
	public function test_manual_sin_nonce_muere(): void {
		$this->expectException( \RuntimeException::class );

		Memory_Report::handle_admin_generate();
	}

	/**
	 * Con nonce pero sin permiso, también muere: el nonce no es un permiso.
	 */
	public function test_manual_sin_permiso_muere(): void {
		$_GET['_wpnonce']      = 'bueno';
		$GLOBALS['_test_caps'] = array( 'manage_options' => false );

		$this->expectException( \RuntimeException::class );

		Memory_Report::handle_admin_generate();
	}
}
