<?php
/**
 * Diagnóstico compartido: comprobaciones genéricas y pantalla de estado.
 *
 * Por qué existe: Members y Shifts tenían dos `Admin_Status` gemelos —el mismo HTML y las
 * mismas tres comprobaciones— que solo se diferenciaban en los datos. Este contrato fija
 * que la mecánica vive aquí y que **no da por bueno lo que no lo está**.
 *
 * Prueba en negativo: si `tablas()` devolviera siempre `ok`, el segundo caso de
 * `test_tablas_avisa_cuando_falta_una` cae; si `pintar()` no pintara las filas, cae
 * `test_pintar_muestra_cada_comprobacion`.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use Convoca\Core\Admin\Diagnostico;
use PHPUnit\Framework\TestCase;

/**
 * Comprobaciones y pintado compartidos.
 */
final class DiagnosticoTest extends TestCase {

	/**
	 * Guarda el `$wpdb` del arnés para dejarlo como estaba.
	 *
	 * @var mixed
	 */
	private $wpdb_original;

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->wpdb_original      = $GLOBALS['wpdb'] ?? null;
		$GLOBALS['_wp_cron']      = array( 'programados' => array() );
		$GLOBALS['_wp_stores']    = array();
	}

	/**
	 * Deja el arnés como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['wpdb'] = $this->wpdb_original;
	}

	/**
	 * Sustituye `$wpdb` por uno que dice qué tablas existen.
	 *
	 * @param array<int, string> $existentes Tablas que debe encontrar.
	 */
	private function simular_tablas( array $existentes ): void {
		$GLOBALS['wpdb'] = new class( $existentes ) {
			/**
			 * Prefijo de las tablas.
			 *
			 * @var string
			 */
			public $prefix = 'wp_';

			/**
			 * Tablas que existen.
			 *
			 * @var array<int, string>
			 */
			private $existentes;

			/**
			 * Guarda las tablas que existen.
			 *
			 * @param array<int, string> $existentes Tablas existentes.
			 */
			public function __construct( array $existentes ) {
				$this->existentes = $existentes;
			}

			/**
			 * Devuelve el nombre si la tabla existe, como WordPress.
			 *
			 * @param string $consulta Consulta SQL.
			 *
			 * @return string|null
			 */
			public function get_var( $consulta = null ) {
				foreach ( $this->existentes as $tabla ) {
					if ( false !== strpos( (string) $consulta, $tabla ) ) {
						return $tabla;
					}
				}
				return null;
			}

			/**
			 * Prepara una consulta: aquí no hay motor, se sustituyen los marcadores.
			 *
			 * @param string $consulta Consulta con marcadores.
			 * @param mixed  ...$args  Valores de los marcadores.
			 *
			 * @return string
			 */
			public function prepare( $consulta, ...$args ) {
				return $args ? vsprintf( str_replace( '%s', "'%s'", (string) $consulta ), $args ) : (string) $consulta;
			}
		};
	}

	/**
	 * Con PHP 8 o superior, verde y con la versión en el mensaje.
	 */
	public function test_php_en_verde(): void {
		$r = Diagnostico::php( '8.0' );

		$this->assertSame( 'php', $r['id'] );
		$this->assertSame( Diagnostico::SEVERITY_OK, $r['severity'] );
		$this->assertStringContainsString( PHP_VERSION, $r['message'] );
	}

	/**
	 * Tablas: todas existen -> verde.
	 */
	public function test_tablas_en_verde(): void {
		$this->simular_tablas( array( 'wp_convoca_logs', 'wp_convoca_locks' ) );

		$r = Diagnostico::tablas( array( 'wp_convoca_logs', 'wp_convoca_locks' ) );

		$this->assertSame( 'tables', $r['id'] );
		$this->assertSame( Diagnostico::SEVERITY_OK, $r['severity'] );
	}

	/**
	 * Tablas: falta una -> error que nombra la que falta y el arreglo del plugin.
	 */
	public function test_tablas_avisa_cuando_falta_una(): void {
		$this->simular_tablas( array( 'wp_convoca_logs' ) );

		$r = Diagnostico::tablas(
			array( 'wp_convoca_logs', 'wp_convoca_member_sequence' ),
			'Reactiva el plugin.'
		);

		$this->assertSame( Diagnostico::SEVERITY_ERROR, $r['severity'] );
		$this->assertStringContainsString( 'wp_convoca_member_sequence', $r['message'] );
		$this->assertStringNotContainsString( 'wp_convoca_logs,', $r['message'] );
		$this->assertSame( 'Reactiva el plugin.', $r['fix'] );
	}

	/**
	 * Cron: sin eventos programados -> aviso, no error (se reprograman solos).
	 */
	public function test_cron_avisa_si_faltan_eventos(): void {
		$r = Diagnostico::cron( array( 'convoca_daily_event' ) );

		$this->assertSame( Diagnostico::SEVERITY_WARNING, $r['severity'] );
		$this->assertStringContainsString( 'convoca_daily_event', $r['message'] );
	}

	/**
	 * Cron: con el evento programado -> verde.
	 */
	public function test_cron_en_verde(): void {
		$GLOBALS['_wp_cron']['programados']['convoca_daily_event'] = time();

		$r = Diagnostico::cron( array( 'convoca_daily_event' ) );

		$this->assertSame( Diagnostico::SEVERITY_OK, $r['severity'] );
	}

	/**
	 * `resultado()` mantiene la forma que consumen los plugins.
	 */
	public function test_resultado_tiene_la_forma_esperada(): void {
		$r = Diagnostico::resultado( 'x', 'Etiqueta', 'Mensaje', Diagnostico::SEVERITY_OK, 'Arreglo' );

		$this->assertSame( array( 'id', 'label', 'message', 'severity', 'fix' ), array_keys( $r ) );
	}

	/**
	 * La pantalla pinta el título, una fila por comprobación y la severidad de cada una.
	 */
	public function test_pintar_muestra_cada_comprobacion(): void {
		$resultados = array(
			Diagnostico::resultado( 'uno', 'Primera', 'Todo bien', Diagnostico::SEVERITY_OK ),
			Diagnostico::resultado( 'dos', 'Segunda', 'Falta algo', Diagnostico::SEVERITY_ERROR, 'Arréglalo' ),
		);

		ob_start();
		Diagnostico::pintar( $resultados, 'Estado de prueba' );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Estado de prueba', $html );
		$this->assertStringContainsString( 'Primera', $html );
		$this->assertStringContainsString( 'Segunda', $html );
		$this->assertStringContainsString( 'Arréglalo', $html );
		$this->assertSame( 2, substr_count( $html, 'convoca-diagnostic-row' ) );
		$this->assertStringContainsString( 'Se encontraron errores', $html );
	}
}
