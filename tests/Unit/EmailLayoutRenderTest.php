<?php
/**
 * El layout no puede imprimir texto propio.
 *
 * Contrato: `render()` envuelve el cuerpo y le añade su cabecera y su pie. Todo
 * el texto visible del documento pertenece al cuerpo, al pie o a la marca del
 * sitio — nada más. Un `?>.` de más en el fichero mete un punto suelto en la
 * cabecera y en el cuerpo de TODOS los correos del ecosistema, y eso no se ve
 * en un test que solo compruebe que el cuerpo está.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use PHPUnit\Framework\TestCase;
use Convoca\Core\Email_Layout;
use Convoca\Core\Utils;

class EmailLayoutRenderTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['_wp_stores']['options'] = array();
	}

	/** Texto visible de un fragmento de HTML. */
	private function texto( string $html ): string {
		return trim( wp_strip_all_tags( $html ) );
	}

	public function test_la_cabecera_no_imprime_nada_que_no_sea_la_marca(): void {
		$html = Email_Layout::render( '<p>Contenido</p>', 'Asunto' );

		$this->assertSame( 1, preg_match( '#<td class="email-header">(.*?)</td>#s', $html, $m ), 'El layout debe traer cabecera.' );

		$this->assertSame(
			$this->texto( Utils::get_branding_html( 'email' ) ),
			$this->texto( $m[1] ),
			'La cabecera solo puede contener la marca del sitio.'
		);
	}

	public function test_el_cuerpo_no_arrastra_caracteres_pegados(): void {
		$html = Email_Layout::render( '<p>FIN</p>', 'Asunto' );

		$this->assertStringContainsString( '<p>FIN</p>', $html );
		$this->assertStringNotContainsString(
			'FIN</p>.',
			$html,
			'No puede quedar un carácter suelto pegado al final del cuerpo.'
		);
	}

	public function test_el_cuerpo_llega_una_sola_vez(): void {
		$html = Email_Layout::render( '<p>MARCA-CUERPO</p>', 'Asunto' );

		$this->assertSame( 1, substr_count( $html, 'MARCA-CUERPO' ) );
	}

	public function test_el_boton_conserva_el_placeholder_hasta_la_sustitucion(): void {
		// El cuerpo se monta antes de sustituir: si aquí se escapara, el
		// placeholder quedaría destruido y el botón viajaría a «http://link_pago».
		$boton = Email_Layout::button_html( '{link_pago}', 'Pagar ahora' );

		$this->assertStringContainsString( 'href="{link_pago}"', $boton );
		$this->assertStringNotContainsString( 'http://link_pago', $boton );
	}

	public function test_render_escapa_el_enlace_ya_sustituido(): void {
		$cuerpo = str_replace( '{link_pago}', 'https://example.org/pago/?a=1&b=2', Email_Layout::button_html( '{link_pago}', 'Pagar ahora' ) );
		$html   = Email_Layout::render( $cuerpo, 'Asunto' );

		$this->assertStringContainsString( 'class="email-btn"', $html );
		$this->assertStringNotContainsString( 'href="{link_pago}"', $html );

		preg_match( '#<a href="([^"]*)" class="email-btn"#', $html, $m );
		// El contrato es «el enlace pasa por esc_url», no las reglas concretas de
		// WordPress (que cambian entre versiones): se compara con su resultado.
		$this->assertSame( esc_url( 'https://example.org/pago/?a=1&b=2' ), $m[1] ?? '', 'El enlace debe quedar escapado en el render.' );
	}

	public function test_un_enlace_ya_literal_no_se_estropea_al_escaparlo_dos_veces(): void {
		$cuerpo = Email_Layout::button_html( 'https://example.org/panel/', 'Ir al panel' );

		$this->assertSame( $cuerpo, Email_Layout::prune_empty_html( $cuerpo ) );
		$this->assertStringContainsString( 'href="https://example.org/panel/"', $cuerpo );
	}

	/**
	 * @dataProvider placeholders
	 */
	public function test_que_es_un_placeholder( string $valor, bool $esperado ): void {
		$this->assertSame( $esperado, Email_Layout::is_placeholder( $valor ), $valor );
	}

	/** @return array<string, array{0: string, 1: bool}> */
	public static function placeholders(): array {
		return array(
			'link_pago'        => array( '{link_pago}', true ),
			'panel_reservas'   => array( '{panel_reservas}', true ),
			'con espacios'     => array( ' {login_url} ', true ),
			'url real'         => array( 'https://example.org/x/', false ),
			'texto suelto'     => array( 'link_pago', false ),
			'dos juntos'       => array( '{a}{b}', false ),
		);
	}

	public function test_el_boton_del_layout_no_sale_sin_destino(): void {
		$html = Email_Layout::render( '<p>Contenido</p>', 'Asunto', array( 'button_url' => '—', 'button_text' => 'Pulsar' ) );

		$this->assertStringNotContainsString( 'Pulsar', $html, 'Un botón sin destino no se pinta.' );
		$this->assertStringNotContainsString( 'href="—"', $html );
	}

	public function test_el_boton_del_layout_sale_cuando_tiene_destino(): void {
		$html = Email_Layout::render(
			'<p>Contenido</p>',
			'Asunto',
			array(
				'button_url'  => 'https://example.org/panel/',
				'button_text' => 'Ir al panel',
			)
		);

		$this->assertStringContainsString( 'href="https://example.org/panel/"', $html );
		$this->assertStringContainsString( 'Ir al panel', $html );
	}
}
