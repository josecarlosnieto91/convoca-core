<?php
/**
 * Poda de datos ausentes en el cuerpo de un correo.
 *
 * Contrato: las plantillas se montan ANTES de sustituir las variables, así que
 * una fila cuyo valor venía vacío se imprime igual y el destinatario lee
 * «Nueva fecha renovación: —»; un botón cuyo enlace era un placeholder llega
 * con un href que no lleva a ninguna parte. `prune_empty_html()` limpia eso
 * sobre el cuerpo ya sustituido, y `is_missing()` decide qué cuenta como
 * ausente — con una regla que NO se puede equivocar: un `0` es un dato real.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use PHPUnit\Framework\TestCase;
use Convoca\Core\Email_Layout;

class EmailLayoutPruneTest extends TestCase {

	/** Fila de la tabla de datos tal y como la produce meta_table(). */
	private function fila( string $label, string $valor ): string {
		return '<tr><td class="label">' . $label . '</td><td class="value"><strong>' . $valor . '</strong></td></tr>';
	}

	/** Caja de datos tal y como la produce meta_table(). */
	private function caja( array $filas ): string {
		return '<div class="email-meta"><table role="presentation" cellpadding="0" cellspacing="0">'
			. implode( '', $filas ) . '</table></div>';
	}

	/** Botón tal y como lo produce button_html(). */
	private function boton( string $url, string $texto ): string {
		return '<p style="text-align:center;margin:24px 0 0"><a href="' . $url . '" class="email-btn">' . $texto . '</a></p>';
	}

	public function test_una_fila_sin_valor_desaparece(): void {
		$html = $this->caja( array( $this->fila( 'Nueva fecha renovación', '—' ) ) );

		$this->assertStringNotContainsString( 'Nueva fecha renovación', Email_Layout::prune_empty_html( $html ) );
	}

	public function test_la_caja_entera_desaparece_si_se_queda_sin_filas(): void {
		$html = $this->caja( array( $this->fila( 'Nueva fecha renovación', '—' ) ) );

		$this->assertStringNotContainsString( 'email-meta', Email_Layout::prune_empty_html( $html ) );
	}

	public function test_las_filas_con_dato_se_conservan(): void {
		$html = $this->caja(
			array(
				$this->fila( 'Nº Socio', '1234' ),
				$this->fila( 'Nueva fecha renovación', '—' ),
				$this->fila( 'Estado', 'Activo' ),
			)
		);

		$limpio = Email_Layout::prune_empty_html( $html );

		$this->assertStringContainsString( 'Nº Socio', $limpio );
		$this->assertStringContainsString( '1234', $limpio );
		$this->assertStringContainsString( 'Estado', $limpio );
		$this->assertStringNotContainsString( 'Nueva fecha renovación', $limpio );
		$this->assertSame( 2, substr_count( $limpio, '<td class="label">' ) );
	}

	/**
	 * El caso que motivó todo esto: 0 horas SÍ es un dato.
	 *
	 * Si la poda tratase el cero como ausente, el correo dejaría de decir lo
	 * que de verdad pasó y el arreglo sería peor que el defecto.
	 */
	public function test_un_cero_no_es_un_dato_ausente(): void {
		$html = $this->caja( array( $this->fila( 'Horas completadas', '0h' ) ) );

		$limpio = Email_Layout::prune_empty_html( $html );

		$this->assertStringContainsString( 'Horas completadas', $limpio );
		$this->assertStringContainsString( '0h', $limpio );
		$this->assertFalse( Email_Layout::is_missing( '0' ) );
		$this->assertFalse( Email_Layout::is_missing( '0h' ) );
	}

	public function test_un_boton_sin_destino_desaparece_con_su_parrafo(): void {
		$html = 'Antes' . $this->boton( '—', 'Descargar Certificado' ) . 'Después';

		$limpio = Email_Layout::prune_empty_html( $html );

		$this->assertStringNotContainsString( 'Descargar Certificado', $limpio );
		$this->assertStringNotContainsString( 'email-btn', $limpio );
		$this->assertStringNotContainsString( '<p', $limpio );
		$this->assertSame( 'AntesDespués', $limpio );
	}

	public function test_un_boton_con_destino_real_se_conserva(): void {
		$html = $this->boton( 'https://example.org/mi-area/', 'Acceder a Mi Área' );

		$this->assertSame( $html, Email_Layout::prune_empty_html( $html ) );
	}

	public function test_un_parrafo_etiquetado_sin_valor_desaparece(): void {
		$html = '<p style="font-size:13px">ID del certificado: —</p>';

		$this->assertSame( '', Email_Layout::prune_empty_html( $html ) );
	}

	public function test_un_parrafo_con_texto_no_se_toca(): void {
		$html = '<p>Adjunto encontrarás tu tarjeta de socio/a actualizada.</p>';

		$this->assertSame( $html, Email_Layout::prune_empty_html( $html ) );
	}

	/**
	 * La poda no puede ser un reformateador: sobre un cuerpo sin nada ausente
	 * devuelve exactamente lo mismo.
	 */
	public function test_un_cuerpo_completo_no_cambia_ni_un_byte(): void {
		$html = '<h1>Hola {nombre}</h1>'
			. '<p>Tu cuota se ha procesado.</p>'
			. $this->caja( array( $this->fila( 'Plan', 'Socio numerario' ), $this->fila( 'Importe', '50€' ) ) )
			. $this->boton( 'https://example.org/panel/', 'Ver mi carnet' );

		$this->assertSame( $html, Email_Layout::prune_empty_html( $html ) );
	}

	public function test_el_texto_plano_no_se_come(): void {
		$plano = "Hola,\n\nEsto es texto plano sin etiquetas.";

		$this->assertSame( $plano, Email_Layout::prune_empty_html( $plano ) );
	}

	/**
	 * @dataProvider valores
	 */
	public function test_que_cuenta_como_ausente( string $valor, bool $ausente ): void {
		$this->assertSame( $ausente, Email_Layout::is_missing( $valor ), "«{$valor}»" );
	}

	/** @return array<string, array{0: string, 1: bool}> */
	public static function valores(): array {
		return array(
			'vacío'        => array( '', true ),
			'raya'         => array( '—', true ),
			'guion'        => array( '-', true ),
			'doble guion'  => array( '--', true ),
			'almohadilla'  => array( '#', true ),
			'cero'         => array( '0', false ),
			'cero con h'   => array( '0h', false ),
			'importe'      => array( '50€/año', false ),
			'fecha'        => array( '15/01/2026', false ),
		);
	}
}
