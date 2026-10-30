<?php
/**
 * Recuento y cobertura de las pruebas de salud (core#30 y core#31).
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use Convoca\Core\Admin_Entrada;
use PHPUnit\Framework\TestCase;

/**
 * Un test de Site Health es un método, un callable suelto o un callable en array.
 */
final class SaludCoberturaTest extends TestCase {

	/**
	 * Carga la clase.
	 */
	private function clase(): string {
		$clase = '\Convoca\Core\Admin_Entrada';

		if ( ! class_exists( $clase ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/Admin_Entrada.php';
		}

		return $clase;
	}

	/**
	 * Las tres formas de registrar una prueba se ejecutan, y la que no se puede se declara.
	 */
	public function test_las_tres_formas_y_la_que_no_se_puede(): void {
		$clase = $this->clase();
		$this->assertTrue( class_exists( $clase ), 'sin la clase la prueba no comprobaría nada' );

		$salud = new class() {
			/**
			 * Prueba normal del núcleo.
			 *
			 * @return array<string,string>
			 */
			public function get_test_wordpress_version(): array {
				return array( 'status' => 'good', 'label' => 'Versión' );
			}

			/**
			 * Prueba registrada como callable en array.
			 *
			 * @return array<string,string>
			 */
			public function wp_consent_api_test(): array {
				return array( 'status' => 'recommended', 'label' => 'Consentimiento' );
			}
		};

		$directas = array(
			'wordpress_version' => array( 'test' => 'wordpress_version', 'label' => 'Versión' ),
			'wp-consent-api'    => array( 'test' => array( $salud, 'wp_consent_api_test' ), 'label' => 'Consentimiento' ),
			'perflab_imagenes'  => array( 'test' => 'perflab_no_existe_este_metodo', 'label' => 'Imágenes' ),
		);

		$evaluacion = $clase::evaluar_pruebas( $salud, $directas );

		$this->assertSame( 3, $evaluacion['total'], 'las tres están registradas' );
		$this->assertSame( 2, $evaluacion['miradas'], 'las dos ejecutables se han mirado' );
		$this->assertSame( 1, $evaluacion['omitidas'], 'la que no se puede ejecutar se declara' );
		$this->assertSame( 1, $evaluacion['cuenta']['good'] );
		$this->assertSame( 1, $evaluacion['cuenta']['recommended'] );
	}

	/**
	 * Un callback en array no dispara `Array to string conversion`.
	 */
	public function test_el_callback_en_array_no_provoca_warning(): void {
		$clase = $this->clase();

		$salud = new class() {
			/**
			 * Callable en array.
			 *
			 * @return array<string,string>
			 */
			public function prueba(): array {
				return array( 'status' => 'good', 'label' => 'OK' );
			}
		};

		$avisos = array();
		set_error_handler(
			static function ( $errno, $errstr ) use ( &$avisos ) {
				$avisos[] = $errstr;
				return true;
			}
		);

		$clase::evaluar_pruebas(
			$salud,
			array( 'wp-consent-api' => array( 'test' => array( $salud, 'prueba' ) ) )
		);

		restore_error_handler();

		$this->assertSame( array(), $avisos, 'con un array no puede haber "Array to string conversion"' );
	}

	/**
	 * Las exclusiones siguen funcionando.
	 */
	public function test_las_exclusiones_se_respetan(): void {
		$clase = $this->clase();

		$salud = new class() {
			/**
			 * Prueba.
			 *
			 * @return array<string,string>
			 */
			public function get_test_debug(): array {
				return array( 'status' => 'critical', 'label' => 'Depuración' );
			}
		};

		$evaluacion = $clase::evaluar_pruebas(
			$salud,
			array( 'debug_enabled' => array( 'test' => 'debug' ) ),
			array( 'debug' )
		);

		$this->assertSame( 0, $evaluacion['miradas'], 'la excluida no se mira' );
		$this->assertSame( 0, $evaluacion['omitidas'], 'y tampoco cuenta como omitida' );
		$this->assertSame( 0, $evaluacion['total'] );
	}
}
