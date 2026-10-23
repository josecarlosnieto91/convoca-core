<?php
/**
 * El monitor de seguridad: lo que se vigila y lo que se avisa.
 *
 * Un monitor que no distingue una ruta ajena de una propia llena el registro de ruido; uno que avisa cada hora
 * del mismo incidente hace que nadie lea los avisos; y uno que se salta el enfriamiento convierte una alerta en
 * spam. Las tres cosas se prueban aquí.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use Convoca\Core\Security_Monitor;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Security_Monitor.php';

/**
 * Monitor de seguridad.
 */
final class SecurityMonitorTest extends TestCase {

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
		remove_all_filters( 'convoca_security_thresholds' );
	}

	/**
	 * Deja el arnés como estaba.
	 */
	private function limpiar(): void {
		unset( $GLOBALS['_wp_stores'], $GLOBALS['_wp_cron'], $_SERVER['REMOTE_ADDR'] );

		$GLOBALS['_wp_stores'] = array(
			'options'    => array(),
			'post_meta'  => array(),
			'transients' => array(),
			'posts'      => array(),
			'db_inserts' => array(),
			'emails'     => array(),
		);
		unset( $GLOBALS['_wp_cron'] );

		// El `Logger` guarda contadores estáticos por proceso: si otra prueba dejó la profundidad a medias o la
		// cuota agotada, esta prueba no vería sus propios avisos y parecería rota. Se parte de cero.
		$reflejo = new \ReflectionClass( \Convoca\Core\Logger::class );
		foreach ( array( 'log_depth' => 0, 'log_timestamps' => array() ) as $propiedad => $valor ) {
			if ( $reflejo->hasProperty( $propiedad ) ) {
				$reflejo->getProperty( $propiedad )->setValue( null, $valor );
			}
		}
	}

	/**
	 * Lo que se ha registrado en el log.
	 *
	 * @return array<int, string>
	 */
	private function avisos(): array {
		$salida = array();
		foreach ( (array) ( $GLOBALS['_wp_stores']['db_inserts'] ?? array() ) as $insert ) {
			if ( false !== strpos( (string) ( $insert['table'] ?? '' ), 'convoca_logs' ) ) {
				$salida[] = (string) ( $insert['data']['message'] ?? '' );
			}
		}

		return $salida;
	}

	/**
	 * Una respuesta REST.
	 *
	 * @param string $ruta   Ruta.
	 * @param int    $estado Código.
	 * @return \WP_REST_Response
	 */
	private function respuesta( string $ruta, int $estado ): \WP_REST_Response {
		$req = new \WP_REST_Request();
		$req->set_route( $ruta );

		$r = new \WP_REST_Response( array( 'ok' => false ), $estado );
		Security_Monitor::watch_rest_response( $r, null, $req );

		return $r;
	}

	/**
	 * Un 401 en una ruta de Convoca se registra con su código, su ruta y su IP.
	 */
	public function test_un_401_se_registra(): void {
		$_SERVER['REMOTE_ADDR'] = '198.51.100.7';

		$devuelta = $this->respuesta( '/convoca-members/v1/me', 401 );

		$this->assertSame( 401, $devuelta->get_status(), 'la respuesta se devuelve tal cual: sólo se mira' );
		$avisos = $this->avisos();
		$this->assertCount( 1, $avisos, 'un aviso' );
		$this->assertStringContainsString( '401', $avisos[0] );
		$this->assertStringContainsString( '/convoca-members/v1/me', $avisos[0] );
		$this->assertStringContainsString( '198.51.100.7', $avisos[0], 'y de dónde vino' );
	}

	/**
	 * Un 403 que llega como `WP_Error` (que es como llega a veces) también se registra.
	 */
	public function test_un_403_como_error(): void {
		$req = new \WP_REST_Request();
		$req->set_route( '/convoca-enroll/v1/inscripciones' );

		$error = new \WP_Error( 'rest_forbidden', 'Sin permiso', array( 'status' => 403 ) );
		Security_Monitor::watch_rest_response( $error, null, $req );

		$avisos = $this->avisos();
		$this->assertCount( 1, $avisos );
		// Con `WP_Error` lo que identifica el fallo es su **código**, no el número: el mensaje lleva
		// `rest_forbidden` y la ruta, que es lo que permite buscar el incidente después.
		$this->assertStringContainsString( 'rest_forbidden', $avisos[0] );
		$this->assertStringContainsString( '/convoca-enroll/v1/inscripciones', $avisos[0] );
	}

	/**
	 * Lo que **no** hay que registrar: rutas ajenas y respuestas buenas. Un monitor ruidoso no se lee.
	 */
	public function test_lo_que_no_se_registra(): void {
		$this->respuesta( '/wp/v2/posts', 401 );
		$this->respuesta( '/otro-plugin/v1/x', 403 );
		$this->respuesta( '/convoca-members/v1/me', 200 );
		$this->respuesta( '/convoca-members/v1/me', 500 );

		$this->assertSame( array(), $this->avisos(), 'ni una línea: ni rutas ajenas, ni 200, ni 500' );
	}

	/**
	 * Una contención de bloqueo se registra con su clave: es lo que dice que dos procesos se pisan.
	 */
	public function test_la_contencion_de_bloqueo(): void {
		Security_Monitor::lock_contended( 'convoca_members_daily_lock' );

		$avisos = $this->avisos();
		$this->assertCount( 1, $avisos );
		$this->assertStringContainsString( 'convoca_members_daily_lock', $avisos[0] );
	}

	/**
	 * El resumen diario no se manda si no se supera ningún umbral.
	 */
	public function test_el_resumen_sin_incidentes_no_avisa(): void {
		$GLOBALS['_wp_stores']['get_var'] = '0'; // todos los recuentos a cero

		Security_Monitor::run_digest();

		$this->assertSame( array(), $GLOBALS['_wp_stores']['emails'], 'sin incidentes no hay correo' );
		$this->assertSame( array(), $this->avisos(), 'ni línea en el registro' );
		$this->assertSame( 0, (int) get_option( 'convoca_security_digest_sent', 0 ), 'ni se marca el envío' );
	}

	/**
	 * Con incidentes por encima del umbral sí avisa, y **no repite** en 24 h.
	 */
	public function test_el_resumen_avisa_y_no_repite(): void {
		// Recuentos altos para superar los umbrales por defecto.
		$GLOBALS['_wp_stores']['get_var'] = '99';
		$GLOBALS['_wp_stores']['options']['admin_email'] = 'junta@example.test';

		Security_Monitor::run_digest();

		$this->assertNotSame( 0, (int) get_option( 'convoca_security_digest_sent', 0 ), 'queda marcado el envío' );
		$avisos = $this->avisos();
		$this->assertNotEmpty( $avisos, 'y se registra' );
		$this->assertStringContainsString( 'Digest de seguridad enviado', implode( ' ', $avisos ) );

		// Segunda pasada dentro de las 24 h: no vuelve a avisar.
		$GLOBALS['_wp_stores']['db_inserts'] = array();
		$GLOBALS['_wp_stores']['emails']     = array();
		Security_Monitor::run_digest();
		$this->assertSame( array(), $this->avisos(), 'el enfriamiento de 24 h evita el spam' );
	}

	/**
	 * Los umbrales son filtrables: cada instalación decide qué es «demasiado».
	 */
	public function test_los_umbrales_son_filtrables(): void {
		// Se suben **todos**: con 99 incidentes y los umbrales de serie habría aviso seguro; lo que se prueba es
		// que el filtro manda. (Subir sólo uno dejaba que otro umbral disparara el aviso y la prueba no medía nada.)
		add_filter(
			'convoca_security_thresholds',
			static function ( $umbrales ) {
				return array_map( static fn() => 500, $umbrales );
			}
		);
		$GLOBALS['_wp_stores']['get_var'] = '99';

		Security_Monitor::run_digest();

		$this->assertSame( 0, (int) get_option( 'convoca_security_digest_sent', 0 ), 'con los umbrales altos, no hay aviso' );
	}

	/**
	 * `init()` programa el resumen diario una sola vez.
	 */
	public function test_el_cron_del_resumen(): void {
		Security_Monitor::init();
		$this->assertNotFalse( wp_next_scheduled( Security_Monitor::CRON_DIGEST ), 'queda programado' );

		// Se fija una marca conocida y se vuelve a llamar: si reprogramara, la marca cambiaría. Comparar dos
		// veces seguidas no vale, porque `time()` puede devolver el mismo segundo y el fallo pasaría inadvertido.
		$GLOBALS['_wp_cron']['programados'][ Security_Monitor::CRON_DIGEST ] = 12345;
		Security_Monitor::init();
		$this->assertSame( 12345, (int) wp_next_scheduled( Security_Monitor::CRON_DIGEST ), 'sin duplicar ni reprogramar' );
	}
}
