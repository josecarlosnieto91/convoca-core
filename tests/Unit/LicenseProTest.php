<?php
/**
 * La licencia PRO: qué se abre, qué no, y qué pasa cuando el servidor de licencias no contesta.
 *
 * Tres cosas de aquí son dinero o seguridad, y las tres fallan en silencio:
 *
 * - **una licencia caducada no puede abrir nada**, aunque sea «unlimited»;
 * - la petición de activación va **firmada** (HMAC con la propia clave, nonce y marca de tiempo): sin eso, el
 *   servidor no puede distinguir una activación de un reintento copiado;
 * - y si la red falla, la licencia **no se degrada**: una licencia activa que deja de funcionar por un corte de
 *   red es una caída de las funciones de pago del cliente.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use Convoca\Core\License_Manager;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/License_Manager.php';

/**
 * Licencia y funciones PRO.
 */
final class LicenseProTest extends TestCase {

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
		remove_all_filters( 'convoca_license_api_url' );
	}

	/**
	 * Deja el arnés como estaba.
	 */
	private function limpiar(): void {
		unset( $GLOBALS['_wp_stores'], $GLOBALS['_wp_http_posts'], $GLOBALS['_wp_scheduled'] );

		$GLOBALS['_wp_stores'] = array(
			'options'    => array(),
			'post_meta'  => array(),
			'transients' => array(),
			'posts'      => array(),
		);
		$GLOBALS['_wp_http_posts'] = array();
		$GLOBALS['_wp_scheduled']  = array();
	}

	/**
	 * Guarda una licencia.
	 *
	 * @param array $datos Datos.
	 */
	private function licencia( array $datos ): void {
		$GLOBALS['_wp_stores']['options'][ License_Manager::OPTION_KEY ] = array_merge(
			array(
				'key'      => 'CLAVE-DE-PRUEBA',
				'status'   => 'active',
				'type'     => 'single',
				'features' => array(),
				'expires'  => '',
				'email'    => '',
			),
			$datos
		);
	}

	/**
	 * La respuesta que dará la API.
	 *
	 * @param array $respuesta Respuesta simulada.
	 */
	private function respuesta( array $respuesta ): void {
		$GLOBALS['_wp_stores']['http_response'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => (string) wp_json_encode( $respuesta ),
		);
	}

	/**
	 * Sin licencia no hay PRO: es el modo gratuito.
	 */
	public function test_sin_licencia_no_hay_pro(): void {
		foreach ( array_keys( License_Manager::pro_features() ) as $feature ) {
			$this->assertFalse( License_Manager::has_pro( $feature ), "sin licencia, «{$feature}» no se abre" );
		}
		$this->assertArrayHasKey( 'members', License_Manager::pro_features(), 'y la lista de funciones existe' );
	}

	/**
	 * Sin clave **no hay PRO ni aunque quede el tipo «unlimited» apuntado**: es lo que pasa si alguien borra la
	 * clave de la opción a mano, o si queda el rastro de una licencia anterior. Sin esta comprobación, ese
	 * estado inconsistente abriría todas las funciones de pago.
	 */
	public function test_sin_clave_no_hay_pro_ni_con_tipo_ilimitado(): void {
		$this->licencia(
			array(
				'key'  => '',
				'type' => 'unlimited',
			)
		);

		$this->assertFalse( License_Manager::has_pro( 'members' ), 'sin clave no se abre nada' );
		$this->assertFalse( License_Manager::has_pro( 'cualquier-cosa' ) );
	}

	/**
	 * Una licencia «unlimited» activa lo abre todo; una de una sola función, sólo la suya.
	 */
	public function test_que_abre_cada_licencia(): void {
		$this->licencia( array( 'type' => 'unlimited' ) );
		$this->assertTrue( License_Manager::has_pro( 'members' ) );
		$this->assertTrue( License_Manager::has_pro( 'cualquier-cosa' ) );

		$this->licencia( array( 'type' => 'single', 'features' => array( 'members', 'shifts' ) ) );
		$this->assertTrue( License_Manager::has_pro( 'members' ) );
		$this->assertTrue( License_Manager::has_pro( 'shifts' ) );
		$this->assertFalse( License_Manager::has_pro( 'enroll' ), 'la que no está, no se abre' );
	}

	/**
	 * **Caducada no abre nada**, ni siendo «unlimited»: es lo que se paga por un año.
	 */
	public function test_una_licencia_caducada_no_abre_nada(): void {
		$this->licencia(
			array(
				'type'    => 'unlimited',
				'expires' => '2020-01-01',
			)
		);

		$this->assertFalse( License_Manager::has_pro( 'members' ), 'caducada es caducada' );
	}

	/**
	 * Y una que caduca más adelante sí abre.
	 */
	public function test_una_licencia_en_vigor(): void {
		$this->licencia(
			array(
				'type'    => 'unlimited',
				'expires' => gmdate( 'Y-m-d', time() + 30 * 86400 ),
			)
		);

		$this->assertTrue( License_Manager::has_pro( 'members' ) );
	}

	/**
	 * Las etiquetas de estado, para que quien administra sepa qué pasa.
	 */
	public function test_las_etiquetas_de_estado(): void {
		$esperado = array(
			'active'    => 'Activa',
			'expired'   => 'Expirada',
			'invalid'   => 'Invalida',
			'lo-que-sea' => 'Inactiva',
		);
		foreach ( $esperado as $estado => $etiqueta ) {
			$this->licencia( array( 'status' => $estado ) );
			$this->assertSame( $etiqueta, License_Manager::get_status_label(), "para «{$estado}»" );
		}
	}

	/**
	 * La activación va firmada: clave, sitio, acción, nonce HMAC y marca de tiempo.
	 */
	public function test_la_activacion_va_firmada(): void {
		$this->respuesta(
			array(
				'success' => true,
				'data'    => array(
					'status'   => 'active',
					'type'     => 'unlimited',
					'features' => array( 'members' ),
					'expires'  => '2030-01-01',
					'email'    => 'quien@example.test',
				),
			)
		);

		$resultado = License_Manager::validate_key( 'CLAVE-SECRETA' );

		$this->assertTrue( $resultado['success'] );
		$peticion = $GLOBALS['_wp_http_posts'][0]['args'] ?? array();
		$cuerpo   = (array) json_decode( (string) ( $peticion['body'] ?? '' ), true );

		$this->assertSame( 'CLAVE-SECRETA', $cuerpo['license_key'] ?? '' );
		$this->assertSame( 'activate', $cuerpo['action'] ?? '' );
		$this->assertSame( home_url(), $cuerpo['site_url'] ?? '' );
		$this->assertNotSame( '', (string) ( $cuerpo['timestamp'] ?? '' ) );

		// El nonce se puede recalcular con la clave: es lo que impide reenviar una activación copiada.
		$esperado = hash_hmac(
			'sha256',
			'CLAVE-SECRETA|' . home_url() . '|activate|' . (int) $cuerpo['timestamp'],
			'CLAVE-SECRETA'
		);
		$this->assertSame( $esperado, (string) ( $cuerpo['nonce'] ?? '' ), 'nonce HMAC con la clave' );

		// Y lo que contesta el servidor queda guardado.
		$guardada = License_Manager::get_license();
		$this->assertSame( 'CLAVE-SECRETA', $guardada['key'] );
		$this->assertSame( 'unlimited', $guardada['type'] );
		$this->assertSame( array( 'members' ), $guardada['features'] );
		$this->assertSame( '2030-01-01', $guardada['expires'] );
		$this->assertTrue( License_Manager::has_pro( 'members' ), 'y la función se abre' );
	}

	/**
	 * Si el servidor dice que no, se dice y **no se guarda nada**.
	 */
	public function test_una_respuesta_negativa_no_guarda(): void {
		$this->respuesta(
			array(
				'success' => false,
				'message' => 'La clave no existe.',
			)
		);

		$resultado = License_Manager::validate_key( 'CLAVE-MALA' );

		$this->assertFalse( $resultado['success'] );
		$this->assertSame( 'La clave no existe.', $resultado['message'] );
		$this->assertSame( '', (string) License_Manager::get_license()['key'], 'no se guarda una clave rechazada' );
	}

	/**
	 * **Sin red, la licencia activa no se degrada**: se guarda la clave y se mantiene lo que ya había.
	 */
	public function test_sin_red_no_se_degrada_la_licencia(): void {
		$this->licencia(
			array(
				'type'     => 'unlimited',
				'features' => array( 'members', 'courses' ),
				'status'   => 'active',
			)
		);
		// `is_wp_error()` verdadero: se simula un fallo de red sin respuesta.
		$GLOBALS['_wp_stores']['http_response'] = new \WP_Error( 'http_request_failed', 'sin red' );

		$resultado = License_Manager::validate_key( 'CLAVE-SECRETA' );

		$this->assertTrue( $resultado['success'], 'se guarda y se avisa de que la validación queda pendiente' );
		$guardada = License_Manager::get_license();
		$this->assertSame( 'CLAVE-SECRETA', $guardada['key'] );
		$this->assertSame( 'active', $guardada['status'], 'el estado activo no se toca por un corte de red' );
		$this->assertSame( 'unlimited', $guardada['type'], 'ni el tipo' );
		$this->assertSame( array( 'members', 'courses' ), $guardada['features'], 'ni las funciones' );
		$this->assertTrue( License_Manager::has_pro( 'courses' ), 'el cliente sigue trabajando' );
	}

	/**
	 * Desactivar: sin clave no llama a nadie; con clave avisa al servidor y borra lo guardado.
	 */
	public function test_desactivar(): void {
		$sin = License_Manager::deactivate();
		$this->assertTrue( $sin['success'] );
		$this->assertSame( 'Sin licencia activa.', $sin['message'], 'sin licencia no hay nada que desactivar' );
		$this->assertSame( array(), $GLOBALS['_wp_http_posts'], 'y no se llama al servidor' );

		$this->licencia( array( 'key' => 'CLAVE-SECRETA' ) );
		set_transient( 'convoca_license_check_' . md5( 'CLAVE-SECRETA' ), array( 'success' => true ), 3600 );

		$con = License_Manager::deactivate();

		$this->assertSame( 'Licencia desactivada.', $con['message'] );
		$cuerpo = (array) json_decode( (string) ( $GLOBALS['_wp_http_posts'][0]['args']['body'] ?? '' ), true );
		$this->assertSame( 'deactivate', $cuerpo['action'] ?? '', 'se avisa al servidor' );
		$this->assertSame( 'CLAVE-SECRETA', $cuerpo['license_key'] ?? '' );
		$this->assertSame( '', (string) License_Manager::get_license()['key'], 'y se borra la licencia de aquí' );
		$this->assertFalse(
			get_transient( 'convoca_license_check_' . md5( 'CLAVE-SECRETA' ) ),
			'y la caché de validación'
		);
	}

	/**
	 * La revisión periódica: se programa semanal con licencia y se retira cuando no hay.
	 */
	public function test_la_revision_periodica(): void {
		$this->licencia( array( 'key' => 'CLAVE-SECRETA' ) );
		License_Manager::ensure_cron( 1000 );

		$programado = wp_next_scheduled( 'convoca_license_validate' );
		$this->assertSame( 1000 + HOUR_IN_SECONDS, (int) $programado, 'la primera revisión, dentro de una hora' );

		// Ya programada: no se duplica.
		License_Manager::ensure_cron( 5000 );
		$this->assertSame( 1000 + HOUR_IN_SECONDS, (int) wp_next_scheduled( 'convoca_license_validate' ), 'sin duplicar' );

		// Sin licencia, se retira.
		$GLOBALS['_wp_stores']['options'][ License_Manager::OPTION_KEY ]['key'] = '';
		License_Manager::ensure_cron( 9000 );
		$this->assertFalse( wp_next_scheduled( 'convoca_license_validate' ), 'sin licencia no hay nada que revisar' );
	}

	/**
	 * `validate_remote()` no hace nada si no hay clave (el cron no debe llamar a la API en vano).
	 */
	public function test_la_revision_sin_clave_no_llama(): void {
		License_Manager::validate_remote();

		$this->assertSame( array(), $GLOBALS['_wp_http_posts'], 'sin clave no se llama al servidor' );
	}

	/**
	 * La URL de la API es filtrable: se puede apuntar a un servidor propio sin tocar el código.
	 */
	public function test_la_url_es_filtrable(): void {
		add_filter(
			'convoca_license_api_url',
			static function () {
				return 'https://licencias.example.test/api';
			}
		);

		$this->assertSame( 'https://licencias.example.test/api', License_Manager::get_api_url() );
	}
}
