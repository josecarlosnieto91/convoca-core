<?php
/**
 * El sello de aceptación digital: el hash que demuestra quién firmó qué y cuándo.
 *
 * Si el hash no cambia cuando cambia el contenido, la firma no prueba nada; si el nombre del firmante se pinta
 * sin escapar, el «sello» es una vía de inyección en un documento que se descarga y se archiva; y si el directorio
 * de los PDF no queda cerrado, cualquiera se descarga los documentos de otros cambiando la URL.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use Convoca\Core\Signature;
use Convoca\Core\Utils;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Utils.php';
require_once dirname( __DIR__, 2 ) . '/includes/Signature.php';

/**
 * Sello de aceptación.
 */
final class SigiloAceptacionTest extends TestCase {

	/**
	 * Directorio de trabajo de esta prueba.
	 *
	 * @var string
	 */
	private string $dir;

	/**
	 * Firma.
	 *
	 * @var Signature
	 */
	private Signature $firma;

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		unset( $GLOBALS['_wp_stores'], $GLOBALS['_wp_cache'] );

		$GLOBALS['_wp_stores'] = array(
			'options'    => array(),
			'post_meta'  => array(),
			'transients' => array(),
		);

		// La clase usa la constante del plugin para localizar sus recursos; el arnés no la define porque no
		// carga el fichero principal.
		if ( ! defined( 'Convoca\\Core\\CONVOCA_COMMON_DIR' ) ) {
			define( 'Convoca\\Core\\CONVOCA_COMMON_DIR', dirname( __DIR__, 2 ) . '/' );
		}

		$this->dir = sys_get_temp_dir() . '/convoca-sigilo-' . bin2hex( random_bytes( 6 ) );
		mkdir( $this->dir, 0700, true );
		$this->firma = new Signature();
	}

	/**
	 * Recoge.
	 */
	protected function tearDown(): void {
		// Borrado **recursivo**: `glob('*')` no ve los ficheros que empiezan por punto y la protección del
		// directorio crea justo `.htaccess`. Con el borrado simple, la prueba dejaba basura en cada ejecución.
		if ( ! is_dir( $this->dir ) ) {
			return;
		}
		$it = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $this->dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $it as $fichero ) {
			$fichero->isDir() ? @rmdir( $fichero->getPathname() ) : @unlink( $fichero->getPathname() );
		}
		@rmdir( $this->dir );
	}

	/**
	 * El hash es SHA-256 y **cambia con cada uno de sus tres ingredientes**: contenido, IP y fecha.
	 */
	public function test_el_hash_depende_de_todo(): void {
		$base = $this->firma->create_hash( 'contrato', '10.0.0.1', 1700000000 );

		$this->assertMatchesRegularExpression( '/^[0-9a-f]{64}$/', $base, 'SHA-256 en hexadecimal' );
		$this->assertSame(
			$base,
			$this->firma->create_hash( 'contrato', '10.0.0.1', 1700000000 ),
			'con los mismos datos, el mismo hash: si no, no serviría para verificar'
		);
		$this->assertNotSame( $base, $this->firma->create_hash( 'contrato2', '10.0.0.1', 1700000000 ), 'otro contenido' );
		$this->assertNotSame( $base, $this->firma->create_hash( 'contrato', '10.0.0.2', 1700000000 ), 'otra IP' );
		$this->assertNotSame( $base, $this->firma->create_hash( 'contrato', '10.0.0.1', 1700000001 ), 'otra fecha' );
	}

	/**
	 * Y depende de la **sal persistente** del sitio: sin ella, el hash se podría recalcular desde fuera.
	 */
	public function test_el_hash_lleva_la_sal_del_sitio(): void {
		$primero = $this->firma->create_hash( 'contrato', '10.0.0.1', 1700000000 );

		update_option( 'convoca_persistent_salt', 'otra-sal-distinta' );

		$this->assertNotSame(
			$primero,
			$this->firma->create_hash( 'contrato', '10.0.0.1', 1700000000 ),
			'con otra sal, otro hash: la sal entra en el cálculo'
		);
		$this->assertNotSame( '', (string) get_option( 'convoca_persistent_salt' ) );
	}

	/**
	 * La sal se crea sola la primera vez y luego **no cambia**: si cambiara, todas las firmas antiguas dejarían
	 * de verificar.
	 */
	public function test_la_sal_es_persistente(): void {
		$una = Utils::get_persistent_salt();
		$dos = Utils::get_persistent_salt();

		$this->assertSame( 64, strlen( $una ), 'sesenta y cuatro caracteres' );
		$this->assertSame( $una, $dos, 'la misma siempre' );
	}

	/**
	 * El sello en HTML: nombre escapado, fecha legible, IP y el hash que corresponde.
	 */
	public function test_el_sello_html(): void {
		$sello = $this->firma->get_acceptance_stamp_html( 'Ada <script>alert(1)</script>', '10.0.0.1', 1700000000, 'contrato' );

		$this->assertStringContainsString( 'Ada &lt;script&gt;', $sello, 'el nombre va escapado: no se inyecta en el PDF' );
		$this->assertStringNotContainsString( '<script>', $sello, 'ni una etiqueta viva' );
		$this->assertStringContainsString( '10.0.0.1', $sello, 'la IP' );
		$this->assertStringContainsString( '2023', $sello, 'y la fecha del sello' );
		$this->assertStringContainsString(
			$this->firma->create_hash( 'contrato', '10.0.0.1', 1700000000 ),
			$sello,
			'con el hash de esos datos'
		);
	}

	/**
	 * El fichero `.sig` que acompaña al PDF deja constancia de quién, desde dónde y cuándo.
	 */
	public function test_el_fichero_de_firma(): void {
		$pdf = $this->dir . '/contrato.pdf';
		file_put_contents( $pdf, '%PDF-1.4' );

		$devuelto = $this->firma->add_acceptance_stamp( $pdf, 'Ada Lovelace', '10.0.0.1', 1700000000 );

		$this->assertSame( $pdf, $devuelto, 'devuelve la ruta del PDF' );
		$this->assertFileExists( $pdf . '.sig', 'y deja el sello al lado' );
		$contenido = (string) file_get_contents( $pdf . '.sig' );
		$this->assertStringContainsString( 'Ada Lovelace', $contenido );
		$this->assertStringContainsString( '10.0.0.1', $contenido );
		$this->assertStringContainsString( '2023', $contenido, 'con la fecha' );
	}

	/**
	 * El directorio de documentos queda cerrado al web: motor PHP apagado y sin listado.
	 */
	public function test_la_proteccion_del_directorio(): void {
		$uploads = $this->dir . '/uploads';
		mkdir( $uploads );

		$this->assertTrue( $this->firma->ensure_upload_protection( $uploads ), 'la protección se crea' );

		$htaccess = (string) file_get_contents( $uploads . '/.htaccess' );
		$this->assertStringContainsString( 'php_flag engine off', $htaccess, 'el PHP no se ejecuta ahí' );
		$this->assertStringContainsString( 'Deny from all', $htaccess, 'y nadie entra directo' );

		$index = (string) file_get_contents( $uploads . '/index.php' );
		$this->assertStringContainsString( 'Silence is golden', $index, 'sin listado de directorio' );

		// Idempotente: llamarla otra vez no rompe ni cambia lo que ya está.
		$this->assertTrue( $this->firma->ensure_upload_protection( $uploads ), 'la segunda vez también va bien' );
		$this->assertSame( $htaccess, (string) file_get_contents( $uploads . '/.htaccess' ), 'y no lo reescribe' );
	}

	/**
	 * Y **no pisa** un `.htaccess` que ya existe: si alguien añadió una regla a mano, se queda.
	 */
	public function test_no_pisa_lo_que_ya_esta(): void {
		$uploads = $this->dir . '/uploads-ajeno';
		mkdir( $uploads );
		file_put_contents( $uploads . '/.htaccess', "# regla puesta a mano\nDeny from 203.0.113.7\n" );

		$this->assertTrue( $this->firma->ensure_upload_protection( $uploads ) );

		$htaccess = (string) file_get_contents( $uploads . '/.htaccess' );
		$this->assertStringContainsString( 'Deny from 203.0.113.7', $htaccess, 'la regla de casa sigue ahí' );
		$this->assertStringNotContainsString(
			'php_flag engine off',
			$htaccess,
			'y no se le añade lo de serie encima: si ya estaba protegido, se respeta'
		);
	}
}
