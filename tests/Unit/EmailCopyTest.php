<?php
/**
 * Copia al administrador (o al monitor) de los correos de Convoca.
 *
 * Contrato: todo correo que Convoca envía a una persona se copia a la asociación —
 * a los monitores si el correo es de una actividad, y al correo de administración
 * en cualquier otro caso. La copia es informativa (asunto marcado) y nunca puede
 * alterar el envío original ni duplicarse sobre el mismo destinatario.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use PHPUnit\Framework\TestCase;
use Convoca\Core\Email_Copy;

class EmailCopyTest extends TestCase {

	private const ACTIVIDAD = 501;

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['_wp_stores']['options']     = array();
		$GLOBALS['_wp_stores']['post_meta']   = array();
		$GLOBALS['_wp_stores']['emails']      = array();
		$GLOBALS['_wp_stores']['post_types']  = array( self::ACTIVIDAD => 'actividad' );
		$GLOBALS['_test_users']               = array();
	}

	/** Crea un usuario con correo para el mock de get_userdata(). */
	private function usuario( int $id, string $email ): void {
		$u               = new \WP_User();
		$u->ID           = $id;
		$u->display_name = 'Monitor ' . $id;
		$u->user_email   = $email;
		$GLOBALS['_test_users'][ $id ] = $u;
	}

	public function test_la_copia_esta_activada_por_defecto(): void {
		$this->assertTrue( Email_Copy::enabled(), 'Sin configuración, la copia debe estar puesta.' );
	}

	public function test_el_ajuste_de_la_interfaz_permite_apagarla(): void {
		update_option( 'convoca_members_settings', array( 'copy_all_emails' => 0 ) );
		$this->assertFalse( Email_Copy::enabled() );
	}

	public function test_los_monitores_de_la_actividad_reciben_la_copia(): void {
		$this->usuario( 11, 'monitor1@ejemplo.org' );
		$this->usuario( 12, 'monitor2@ejemplo.org' );
		update_post_meta( self::ACTIVIDAD, '_convoca_responsables', '11,12' );

		$this->assertSame(
			array( 'monitor1@ejemplo.org', 'monitor2@ejemplo.org' ),
			Email_Copy::recipients( array( 'actividad_id' => self::ACTIVIDAD ) )
		);
	}

	public function test_una_actividad_sin_responsables_no_pierde_el_aviso(): void {
		update_option( 'convoca_members_settings', array( 'admin_email' => 'secretaria@ejemplo.org' ) );

		$this->assertSame(
			array( 'secretaria@ejemplo.org' ),
			Email_Copy::recipients( array( 'actividad_id' => self::ACTIVIDAD ) )
		);
	}

	public function test_sin_correo_configurado_cae_al_de_wordpress(): void {
		update_option( 'admin_email', 'webmaster@ejemplo.org' );

		$this->assertSame( array( 'webmaster@ejemplo.org' ), Email_Copy::recipients() );
	}

	public function test_un_correo_ya_dirigido_a_la_administracion_no_se_copia(): void {
		update_option( 'convoca_members_settings', array( 'admin_email' => 'secretaria@ejemplo.org' ) );

		$this->assertFalse(
			Email_Copy::maybe_copy(
				array(
					'to'      => array( 'secretaria@ejemplo.org' ),
					'subject' => 'Prueba',
					'body'    => 'Cuerpo',
				)
			)
		);
		$this->assertEmpty( $GLOBALS['_wp_stores']['emails'], 'No debe salir ningún correo duplicado.' );
	}

	public function test_la_copia_sale_marcada_y_con_el_cuerpo_original(): void {
		update_option( 'convoca_members_settings', array( 'admin_email' => 'secretaria@ejemplo.org' ) );

		$this->assertTrue(
			Email_Copy::maybe_copy(
				array(
					'to'       => array( 'socio@ejemplo.org' ),
					'subject'  => 'Tu certificado',
					'body'     => '<p>Cuerpo del correo original</p>',
					'plugin'   => 'convoca-members',
					'template' => 'objetivo_voluntariado_completado',
				)
			)
		);

		$enviados = $GLOBALS['_wp_stores']['emails'];
		$this->assertCount( 1, $enviados );
		$this->assertSame( array( 'secretaria@ejemplo.org' ), $enviados[0]['to'] );
		$this->assertStringContainsString( '[Copia]', $enviados[0]['subject'] );
		$this->assertStringContainsString( 'Tu certificado', $enviados[0]['subject'] );
		$this->assertStringContainsString( 'Cuerpo del correo original', $enviados[0]['message'] );
		$this->assertStringContainsString( 'socio@ejemplo.org', $enviados[0]['message'], 'La copia dice a quién se envió.' );
		$this->assertStringContainsString( 'objetivo_voluntariado_completado', $enviados[0]['message'] );
	}

	public function test_apagada_no_genera_ninguna_copia(): void {
		update_option( 'convoca_members_settings', array( 'copy_all_emails' => 0, 'admin_email' => 'secretaria@ejemplo.org' ) );

		$this->assertFalse(
			Email_Copy::maybe_copy(
				array(
					'to'      => array( 'socio@ejemplo.org' ),
					'subject' => 'Prueba',
					'body'    => 'Cuerpo',
				)
			)
		);
		$this->assertEmpty( $GLOBALS['_wp_stores']['emails'] );
	}

	public function test_sin_destinatario_valido_no_envia_pero_no_revienta(): void {
		update_option( 'admin_email', '' );
		update_option( 'convoca_members_settings', array( 'admin_email' => 'no-es-un-correo' ) );

		$this->assertFalse(
			Email_Copy::maybe_copy(
				array(
					'to'      => array( 'socio@ejemplo.org' ),
					'subject' => 'Prueba',
					'body'    => 'Cuerpo',
				)
			)
		);
		$this->assertEmpty( $GLOBALS['_wp_stores']['emails'] );
	}

	public function test_el_canal_de_envio_lo_decide_quien_invoca(): void {
		update_option( 'convoca_members_settings', array( 'admin_email' => 'secretaria@ejemplo.org' ) );

		$usado = array();
		Email_Copy::maybe_copy(
			array(
				'to'      => array( 'socio@ejemplo.org' ),
				'subject' => 'Prueba',
				'body'    => 'Cuerpo',
				'send'    => static function ( $to, $subject, $body, $headers ) use ( &$usado ) {
					$usado = array( $to, $subject );
					return true;
				},
			)
		);

		$this->assertSame( array( 'secretaria@ejemplo.org' ), $usado[0], 'La copia sale por el canal del plugin.' );
		$this->assertEmpty( $GLOBALS['_wp_stores']['emails'], 'No debe usar wp_mail cuando hay canal propio.' );
	}
}
