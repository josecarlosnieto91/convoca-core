<?php
/**
 * Punto de salida único de los correos: envoltorio, copia y canal.
 *
 * Contrato (issue convoca-core#6): un correo que va a una persona sale con la identidad de
 * Convoca y, si el ajuste está marcado, con copia a la asociación. Los correos internos se
 * pueden mandar sin copia. El canal es inyectable.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use PHPUnit\Framework\TestCase;
use Convoca\Core\Mailer;

class MailerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['_wp_stores']['options'] = array();
		$GLOBALS['_wp_stores']['emails']  = array();
		update_option( 'convoca_members_settings', array( 'copy_all_emails' => 1, 'admin_email' => 'secretaria@ejemplo.org' ) );
	}

	/** Correos capturados por el simulador. */
	private function enviados(): array {
		return (array) ( $GLOBALS['_wp_stores']['emails'] ?? array() );
	}

	/** Un aviso de turno, tal como lo manda Shifts: texto plano con saltos. */
	private function avisoDeTurno(): bool {
		return Mailer::send(
			'voluntaria@ejemplo.org',
			'Recordatorio de turno',
			"Hola Ana,\n\nTe recordamos que tienes un turno asignado.\n\n¡Gracias por tu voluntariado!",
			array(
				'plugin'    => 'convoca-shifts',
				'template'  => 'recordatorio_turno',
				'entity_id' => 77,
			)
		);
	}

	public function test_el_correo_a_una_persona_sale_con_la_identidad_de_convoca(): void {
		$this->assertTrue( $this->avisoDeTurno() );
		$mails = $this->enviados();
		$this->assertNotEmpty( $mails );
		$original = $mails[0];
		$this->assertSame( array( 'voluntaria@ejemplo.org' ), (array) $original['to'] );
		$this->assertStringContainsString( '<!DOCTYPE html', (string) $original['message'], 'Debe llevar el envoltorio de Convoca.' );
		$this->assertStringContainsString( 'Recordatorio de turno', (string) $original['message'] );
		$this->assertStringContainsString( 'Gracias por tu voluntariado', (string) $original['message'], 'El texto no puede perderse al envolverlo.' );
	}

	public function test_y_se_copia_a_la_asociacion(): void {
		$this->avisoDeTurno();
		$mails = $this->enviados();
		$this->assertCount( 2, $mails, 'Original y copia.' );

		$copia = $mails[1];
		$this->assertContains( 'secretaria@ejemplo.org', (array) $copia['to'] );
		$this->assertStringStartsWith( '[Copia]', (string) $copia['subject'] );
		$this->assertStringContainsString( 'Copia informativa', (string) $copia['message'] );
		$this->assertStringContainsString( 'convoca-shifts', (string) $copia['message'], 'La copia dice de dónde viene.' );
	}

	public function test_con_el_ajuste_apagado_no_hay_copia(): void {
		update_option( 'convoca_members_settings', array( 'copy_all_emails' => 0, 'admin_email' => 'secretaria@ejemplo.org' ) );
		$this->avisoDeTurno();
		$mails = $this->enviados();
		$this->assertCount( 1, $mails, 'Solo el correo a la persona.' );
		$this->assertSame( array( 'voluntaria@ejemplo.org' ), (array) $mails[0]['to'] );
	}

	public function test_un_correo_interno_puede_ir_sin_copia(): void {
		Mailer::send(
			'coordinacion@ejemplo.org',
			'Aviso interno',
			'<p>Algo que solo concierne a la asociación.</p>',
			array(
				'plugin' => 'convoca-shifts',
				'copy'   => false,
			)
		);
		$this->assertCount( 1, $this->enviados(), 'Los correos internos no se copian a sí mismos.' );
	}

	public function test_el_texto_plano_se_convierte_en_parrafos_sin_cambiar_ni_una_palabra(): void {
		$this->avisoDeTurno();
		$cuerpo = (string) $this->enviados()[0]['message'];
		$this->assertStringContainsString( '<p>Hola Ana,</p>', $cuerpo, 'Cada párrafo, el suyo.' );
		$this->assertStringContainsString( 'Te recordamos que tienes un turno asignado.', $cuerpo );
	}

	public function test_el_canal_inyectado_se_usa_y_wp_mail_no(): void {
		$usados = array();
		$canal  = static function ( $to, $subject, $body, $headers, $attachments ) use ( &$usados ) {
			$usados[] = array( 'to' => $to, 'subject' => $subject );
			return true;
		};
		Mailer::send( 'voluntaria@ejemplo.org', 'Recordatorio de turno', 'Hola.', array( 'plugin' => 'convoca-shifts', 'channel' => $canal ) );
		$this->assertCount( 2, $usados, 'Original y copia por el canal inyectado.' );
		$this->assertEmpty( $this->enviados(), 'No debe tocar wp_mail si hay canal propio.' );
	}

	public function test_la_copia_hereda_el_remitente_del_original(): void {
		Mailer::send(
			'quien-paga@ejemplo.org',
			'Tu enlace de pago',
			'<p>Importe: 30,00 €</p>',
			array(
				'plugin'  => 'convoca-gateway',
				'headers' => array( 'Content-Type: text/html; charset=UTF-8', 'From: Asociación <asociacion@ejemplo.org>' ),
			)
		);
		$mails = $this->enviados();
		$this->assertCount( 2, $mails );
		$cabeceras = (array) $mails[1]['headers'];
		$this->assertContains( 'From: Asociación <asociacion@ejemplo.org>', $cabeceras, 'La copia sale del mismo remitente que el original.' );
	}

	public function test_un_destinatario_invalido_no_manda_nada(): void {
		$this->assertFalse( Mailer::send( 'no-es-un-correo', 'Asunto', 'Cuerpo', array( 'plugin' => 'convoca-core' ) ) );
		$this->assertEmpty( $this->enviados() );
	}
}
