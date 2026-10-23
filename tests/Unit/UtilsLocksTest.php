<?php
/**
 * Cerrojos y límites de intentos.
 *
 * Son dos mecanismos que, si fallan, fallan en silencio y siempre en la misma dirección: un cerrojo que no se suelta
 * deja el sistema parado para siempre; uno que no protege deja pasar dos procesos a la vez. Y un listado de bloqueos
 * que enseña contadores que no son bloqueos miente sobre cuánta gente hay cortada.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use Convoca\Core\Utils;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Utils.php';
require_once dirname( __DIR__, 2 ) . '/includes/Logger.php';

/**
 * Cerrojos y rate limit.
 */
final class UtilsLocksTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$GLOBALS['_wp_filters'] = array();
		$GLOBALS['_wp_stores']  = array(
			'options'    => array(),
			'transients' => array(),
			'locks'      => array(),
			'queries'    => array(),
			'db_reads'   => array(),
			'db_deletes' => array(),
			'post_meta'  => array(),
			'posts'      => array(),
			'usuarios'   => array(),
		);
	}

	/**
	 * El nombre de la tabla de cerrojos sale del prefijo del sitio, no de una constante.
	 */
	public function test_la_tabla_de_cerrojos(): void {
		$reflejo = new \ReflectionMethod( Utils::class, 'locks_table' );

		$this->assertSame( 'wp_convoca_locks', $reflejo->invoke( null ) );
	}

	/**
	 * Un cerrojo se toma una vez; mientras esté vivo, el segundo intento no lo consigue y **no** le cambia el
	 * vencimiento (si se lo cambiara, el cerrojo no caducaría nunca y el proceso que lo tiene perdería la propiedad).
	 */
	public function test_un_cerrojo_vivo_no_se_cede(): void {
		$this->assertTrue( Utils::acquire_lock( 'informe' ) );
		$vencimiento = $GLOBALS['_wp_stores']['locks']['convoca_lock_informe'];
		$this->assertGreaterThan( time(), (int) $vencimiento );

		$this->assertFalse( Utils::acquire_lock( 'informe' ) );
		$this->assertSame( $vencimiento, $GLOBALS['_wp_stores']['locks']['convoca_lock_informe'], 'el vencimiento no se mueve' );
	}

	/**
	 * Uno caducado no bloquea para siempre: se vuelve a tomar y con vencimiento nuevo.
	 */
	public function test_un_cerrojo_caducado_se_puede_tomar(): void {
		$GLOBALS['_wp_stores']['locks']['convoca_lock_informe'] = time() - 30;

		$this->assertTrue( Utils::acquire_lock( 'informe', 120 ) );
		$this->assertGreaterThan( time() + 100, (int) $GLOBALS['_wp_stores']['locks']['convoca_lock_informe'] );
	}

	/**
	 * Soltarlo lo suelta de verdad: después se puede volver a tomar.
	 */
	public function test_soltar_permite_volver_a_tomar(): void {
		Utils::acquire_lock( 'envio' );
		$this->assertTrue( Utils::release_lock( 'envio' ) );

		$this->assertArrayNotHasKey( 'convoca_lock_envio', $GLOBALS['_wp_stores']['locks'] );
		$this->assertTrue( Utils::acquire_lock( 'envio' ) );
	}

	/**
	 * La limpieza se lleva los caducados y deja los vivos.
	 */
	public function test_la_limpieza_solo_se_lleva_los_caducados(): void {
		$GLOBALS['_wp_stores']['locks']['convoca_lock_viejo'] = time() - 10;
		$GLOBALS['_wp_stores']['locks']['convoca_lock_nuevo'] = time() + 60;

		$borrados = Utils::cleanup_expired_locks();

		$this->assertGreaterThanOrEqual( 1, $borrados );
		$this->assertArrayNotHasKey( 'convoca_lock_viejo', $GLOBALS['_wp_stores']['locks'] );
		$this->assertArrayHasKey( 'convoca_lock_nuevo', $GLOBALS['_wp_stores']['locks'] );
	}

	/**
	 * Un bloqueo se codifica con su vencimiento en los bits altos: el listado lo tiene que descifrar igual.
	 *
	 * @param string $clave  Nombre del contador.
	 * @param int    $expira Vencimiento.
	 * @param int    $intentos Intentos.
	 * @return int
	 */
	private function valor( string $clave, int $expira, int $intentos ): int {
		$GLOBALS['_wp_stores']['options'][ $clave ] = (string) ( ( $expira << 20 ) | $intentos );

		return (int) $GLOBALS['_wp_stores']['options'][ $clave ];
	}

	/**
	 * El listado enseña los bloqueos vivos y **sólo** los que tienen etiqueta: un contador por debajo del tope no es
	 * una persona cortada.
	 */
	public function test_el_listado_de_bloqueos(): void {
		$ahora = time();
		$this->valor( 'convoca_rl_login_ip_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', $ahora + 300, 12 );
		$GLOBALS['_wp_stores']['options']['convoca_rl_quien_login_ip_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'] = array(
			'etiqueta' => '1.2.3.4',
			'tipo'     => 'ip',
			'expira'   => $ahora + 300,
		);

		// Un contador sin etiqueta: va por debajo del tope, no está bloqueando a nadie.
		$this->valor( 'convoca_rl_login_ip_bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', $ahora + 300, 3 );

		// Uno caducado: no sale y además se limpia al pasar.
		$this->valor( 'convoca_rl_login_ip_cccccccccccccccccccccccccccccccc', $ahora - 10, 20 );

		$bloqueos = Utils::rate_limit_blocks();

		$this->assertCount( 1, $bloqueos, 'sólo el vivo y etiquetado' );
		$uno = $bloqueos[0];
		$this->assertSame( 'convoca_rl_login_ip_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', $uno['clave'] );
		$this->assertSame( 'login', $uno['accion'], 'la acción, sin el prefijo ni el hash' );
		$this->assertSame( '1.2.3.4', $uno['quien'] );
		$this->assertSame( 12, $uno['intentos'] );
		$this->assertSame( $ahora + 300, $uno['bloqueado_hasta'] );
		$this->assertFalse( $uno['es_persona'], 'una IP no es una persona' );

		$this->assertArrayNotHasKey(
			'convoca_rl_login_ip_cccccccccccccccccccccccccccccccc',
			$GLOBALS['_wp_stores']['options'],
			'el caducado se limpia al listar'
		);
		// Y la etiqueta del bloqueo vivo **sobrevive**: si el listado la barriera (su `option_value` es un array y
		// se leería como vencimiento 0), se llevaría por delante el nombre de quien está cortado.
		$this->assertArrayHasKey(
			'convoca_rl_quien_login_ip_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
			$GLOBALS['_wp_stores']['options'],
			'la etiqueta no se toca al listar'
		);
	}

	/**
	 * Un bloqueo por id de usuario se distingue: es una persona.
	 */
	public function test_un_bloqueo_por_persona(): void {
		$ahora = time();
		$clave = 'convoca_rl_login_id_' . str_repeat( 'd', 32 );
		$this->valor( $clave, $ahora + 100, 9 );
		$GLOBALS['_wp_stores']['options'][ str_replace( 'convoca_rl_', 'convoca_rl_quien_', $clave ) ] = array(
			'etiqueta' => 'ana@example.test',
			'tipo'     => 'id',
			'expira'   => $ahora + 100,
		);

		$bloqueos = Utils::rate_limit_blocks();

		$this->assertTrue( $bloqueos[0]['es_persona'] );
		$this->assertSame( 'ana@example.test', $bloqueos[0]['quien'] );
	}

	/**
	 * Se ordenan por lo que falta para que se levanten: primero el que más tarda.
	 */
	public function test_el_orden_del_listado(): void {
		$ahora = time();
		foreach ( array( 'e' => 60, 'f' => 600, 'a' => 300 ) as $letra => $extra ) {
			$clave = 'convoca_rl_login_ip_' . str_repeat( $letra, 32 );
			$this->valor( $clave, $ahora + $extra, 20 );
			$GLOBALS['_wp_stores']['options'][ str_replace( 'convoca_rl_', 'convoca_rl_quien_', $clave ) ] = array(
				'etiqueta' => '1.2.3.' . $extra,
				'tipo'     => 'ip',
				'expira'   => $ahora + $extra,
			);
		}

		$bloqueos = Utils::rate_limit_blocks();

		$this->assertCount( 3, $bloqueos );
		$this->assertSame( $ahora + 600, $bloqueos[0]['bloqueado_hasta'], 'el que más tarda va primero' );
		$this->assertSame( $ahora + 60, $bloqueos[2]['bloqueado_hasta'] );
	}

	/**
	 * El filtro por acción: sólo los de esa acción.
	 */
	public function test_el_filtro_por_accion(): void {
		$ahora = time();
		// Las claves llevan hash hexadecimal: una letra fuera de `[a-f0-9]` no se puede limpiar del nombre.
		foreach ( array( 'login' => 'a', 'pago' => 'b' ) as $accion => $letra ) {
			$clave = 'convoca_rl_' . $accion . '_ip_' . str_repeat( $letra, 32 );
			$this->valor( $clave, $ahora + 100, 20 );
			$GLOBALS['_wp_stores']['options'][ str_replace( 'convoca_rl_', 'convoca_rl_quien_', $clave ) ] = array(
				'etiqueta' => '9.9.9.9',
				'tipo'     => 'ip',
				'expira'   => $ahora + 100,
			);
		}

		$solo_pago = Utils::rate_limit_blocks( 'pago' );

		$this->assertCount( 1, $solo_pago );
		$this->assertSame( 'pago', $solo_pago[0]['accion'] );
	}

	/**
	 * Levantar un bloqueo: borra el contador **y** su etiqueta, y no acepta una clave que no sea suya.
	 */
	public function test_levantar_un_bloqueo(): void {
		$clave = 'convoca_rl_login_ip_' . str_repeat( 'i', 32 );
		$this->valor( $clave, time() + 100, 20 );
		$GLOBALS['_wp_stores']['options'][ str_replace( 'convoca_rl_', 'convoca_rl_quien_', $clave ) ] = array( 'etiqueta' => '1.2.3.4' );

		$this->assertTrue( Utils::rate_limit_clear( $clave ) );
		$this->assertArrayNotHasKey( $clave, $GLOBALS['_wp_stores']['options'], 'se va el contador' );
		$this->assertArrayNotHasKey( str_replace( 'convoca_rl_', 'convoca_rl_quien_', $clave ), $GLOBALS['_wp_stores']['options'], 'y su etiqueta' );

		$this->assertFalse( Utils::rate_limit_clear( $clave ), 'ya no había nada' );
		$this->assertFalse( Utils::rate_limit_clear( 'otra_cosa' ), 'no es una clave de bloqueo' );
		$this->assertFalse( Utils::rate_limit_clear( '' ), 'ni vacía' );
	}

	/**
	 * La etiqueta privada: un correo o una IP, enmascarados para los registros.
	 */
	public function test_la_etiqueta_privada(): void {
		$reflejo = new \ReflectionMethod( Utils::class, 'etiqueta_privada' );

		$this->assertSame( 'a***@example.test', $reflejo->invoke( null, 'ana@example.test' ) );
		$this->assertSame( '1.2.3.x', $reflejo->invoke( null, '1.2.3.4' ) );
		$this->assertSame( '(conexión)', $reflejo->invoke( null, 'sin-forma' ) );
	}
}
