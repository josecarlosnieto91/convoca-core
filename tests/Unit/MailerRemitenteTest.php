<?php
/**
 * Ningún correo del ecosistema sale sin remitente.
 *
 * **Defecto que sujeta esta prueba** (auditoría independiente, pasada 10 del gauntlet):
 *
 * De 83 correos capturados en el sitio, **69 salían sin cabecera `From`**: el `Mailer` del núcleo
 * arrancaba con `headers => []` y solo añadía el `Content-Type`, así que el remitente quedaba en manos
 * de PHP (`wordpress@localhost`), que la mayoría de servidores rechazan — y el registro del plugin
 * decía «enviado», así que nadie se enteraba. Además, el ajuste del nombre del remitente se leía con
 * `?? get_bloginfo('name')`, que **no cae al respaldo si la clave existe vacía**: guardar el ajuste en
 * blanco producía `From:  <correo>`.
 *
 * Se arregla en el punto de salida único (el `Mailer`), no en cada plugin: siete sitios donde
 * olvidarlo son siete sitios donde se olvida.
 *
 * **Prueba en negativo**: quitando el bloque que añade el `From` cuando falta, la primera comprobación
 * falla; quitando el respaldo del nombre vacío, falla la tercera.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use PHPUnit\Framework\TestCase;
use Convoca\Core\Mailer;

class MailerRemitenteTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['_wp_stores']['options'] = array();
		$GLOBALS['_wp_stores']['emails']  = array();

		// Sin copia a la asociación: aquí se mide el correo original, no su copia.
		update_option( 'convoca_members_settings', array( 'copy_all_emails' => 0 ) );
		update_option( 'admin_email', 'secretaria@ejemplo.org' );
	}

	/** Manda un correo por el camino de verdad y devuelve las cabeceras con las que salió. */
	private function cabecerasDeUnEnvio(): array {
		Mailer::send( 'socia@ejemplo.org', 'Asunto', 'Cuerpo del correo' );

		$emails = (array) ( $GLOBALS['_wp_stores']['emails'] ?? array() );
		$this->assertNotEmpty( $emails, 'No salió ningún correo: la prueba no está midiendo nada.' );

		return (array) ( $emails[0]['headers'] ?? array() );
	}

	/** La cabecera From de un envío, o cadena vacía. */
	private function from( array $headers ): string {
		foreach ( $headers as $h ) {
			if ( is_string( $h ) && 0 === stripos( trim( $h ), 'from:' ) ) {
				return trim( $h );
			}
		}
		return '';
	}

	public function test_ningun_correo_sale_sin_remitente(): void {
		$from = $this->from( $this->cabecerasDeUnEnvio() );

		$this->assertNotSame( '', $from, 'El correo salió sin cabecera From: lo rechazan muchos servidores.' );
		$this->assertMatchesRegularExpression(
			'/<[^@\s]+@[^>\s]+>/',
			$from,
			'La cabecera From no lleva una dirección entre ángulos.'
		);
		$this->assertStringContainsString( 'secretaria@ejemplo.org', $from, 'Debería usar el correo de administración del sitio.' );
	}

	public function test_el_remitente_usa_el_ajuste_del_sitio_cuando_existe(): void {
		update_option( 'convoca_gateway_settings', array( 'email_sender_name' => 'Asociación de Prueba' ) );

		$this->assertStringContainsString( 'Asociación de Prueba', $this->from( $this->cabecerasDeUnEnvio() ) );
	}

	public function test_un_ajuste_en_blanco_no_deja_el_remitente_sin_nombre(): void {
		// El `??` de antes no caía al respaldo con la clave vacía: salía «From:  <correo>».
		update_option( 'convoca_gateway_settings', array( 'email_sender_name' => '   ' ) );

		$from = $this->from( $this->cabecerasDeUnEnvio() );

		$this->assertStringContainsString( 'Sitio de Prueba', $from, 'Con el ajuste en blanco tiene que caer al nombre del sitio.' );
		$this->assertDoesNotMatchRegularExpression( '/From:\s+</', $from, 'El remitente salió sin nombre.' );
	}

	public function test_sin_correo_de_administracion_el_remitente_es_del_dominio_del_sitio(): void {
		update_option( 'admin_email', '' );

		$from = $this->from( $this->cabecerasDeUnEnvio() );

		$this->assertStringContainsString(
			'noreply@example.com',
			$from,
			'Sin correo de administración hay que usar uno del dominio del sitio, no dejarlo vacío.'
		);
	}

	public function test_un_remitente_propio_no_se_duplica(): void {
		Mailer::send(
			'socia@ejemplo.org',
			'Asunto',
			'Cuerpo',
			array( 'headers' => array( 'From: Quien Escribe <quien@ejemplo.org>' ) )
		);

		$headers = (array) $GLOBALS['_wp_stores']['emails'][0]['headers'];
		$froms   = array_values( array_filter( $headers, static fn( $h ) => is_string( $h ) && 0 === stripos( trim( $h ), 'from:' ) ) );

		$this->assertCount( 1, $froms, 'Se ha añadido un segundo From al que ya traía el llamante.' );
		$this->assertStringContainsString( 'quien@ejemplo.org', $froms[0], 'Se ha pisado el remitente del llamante.' );
	}
}
