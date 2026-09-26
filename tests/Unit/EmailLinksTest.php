<?php
/**
 * Los enlaces de los correos apuntan a páginas que existen.
 *
 * Contrato: ningún enlace que genera Convoca puede escribirse a mano. En un sitio
 * cuyo panel se llama `/panel-socio/`, el CTA principal de todos los correos y el
 * enlace «Mi Área» del pie llevaban a `/mi-area/` — 404. Aquí se resuelve la
 * página REAL (por el shortcode que la identifica o por sus slugs) y, si no hay
 * ninguna, el enlace **no se imprime**: mejor sin enlace que con un 404.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use PHPUnit\Framework\TestCase;
use Convoca\Core\Email_Links;

class EmailLinksTest extends TestCase {

	/** Crea una página en el almacén de pruebas. */
	private function pagina( int $id, string $slug, string $titulo, string $contenido = '', string $estado = 'publish' ): void {
		$p              = new \stdClass();
		$p->ID          = $id;
		$p->post_type   = 'page';
		$p->post_name   = $slug;
		$p->post_title  = $titulo;
		$p->post_status = $estado;
		$p->post_content = $contenido;

		$GLOBALS['_wp_stores']['posts'][ $id ] = $p;
	}

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['_wp_stores']['posts'] = array();
	}

	public function test_el_area_de_socio_se_resuelve_por_su_shortcode_no_por_la_ruta(): void {
		// El sitio llama «panel-socio» a su panel: nada de /mi-area/.
		$this->pagina( 10, 'panel-socio', 'Mi Panel de Socio', '<p>Texto</p>[convoca_mi_area]' );

		$this->assertStringContainsString( '?p=10', Email_Links::panel() );
		$this->assertStringNotContainsString( 'mi-area', Email_Links::panel() );
	}

	public function test_si_no_hay_shortcode_se_busca_por_los_slugs_habituales(): void {
		$this->pagina( 11, 'mi-area', 'Mi área' );

		$this->assertStringContainsString( '?p=11', Email_Links::panel() );
	}

	public function test_sin_ninguna_pagina_el_area_de_socio_queda_vacia(): void {
		$this->assertSame( '', Email_Links::panel(), 'Sin panel no se puede inventar una URL.' );
	}

	public function test_el_admin_puede_apuntar_el_area_de_socio_donde_quiera(): void {
		add_filter( 'convoca_email_panel_url', static fn(): string => 'https://example.org/a-medida/' );

		$this->assertSame( 'https://example.org/a-medida/', Email_Links::panel() );

		remove_all_filters( 'convoca_email_panel_url' );
	}

	// ── El pie ───────────────────────────────────────────────────────

	public function test_el_pie_no_imprime_enlaces_a_paginas_que_no_existen(): void {
		$links = Email_Links::footer();

		$this->assertArrayNotHasKey( 'Contacto', $links, 'No hay página de contacto: no se inventa el enlace.' );
		$this->assertArrayNotHasKey( 'Aviso Legal', $links );
		$this->assertSame( array(), $links );
	}

	public function test_el_pie_usa_los_titulos_reales_de_las_paginas(): void {
		$this->pagina( 20, 'panel-socio', 'Mi Panel de Socio', '[convoca_mi_area]' );
		$this->pagina( 21, 'politica-de-privacidad', 'Política de privacidad' );

		$links = Email_Links::footer();

		$this->assertArrayHasKey( 'Mi Panel de Socio', $links );
		$this->assertArrayHasKey( 'Política de privacidad', $links );
		$this->assertArrayNotHasKey( 'Aviso Legal', $links, 'El rótulo sale del título de la página, no de una lista fija.' );
		$this->assertStringContainsString( '?p=21', $links['Política de privacidad'] );
	}

	public function test_una_pagina_en_borrador_no_cuenta(): void {
		$this->pagina( 30, 'politica-de-privacidad', 'Política de privacidad', '', 'draft' );

		$this->assertArrayNotHasKey( 'Política de privacidad', Email_Links::footer() );
	}

	public function test_el_admin_puede_sobreescribir_el_pie(): void {
		$this->pagina( 40, 'panel-socio', 'Mi Panel de Socio', '[convoca_mi_area]' );

		add_filter(
			'convoca_email_footer_links',
			static function ( array $links ): array {
				$links['Aviso legal del centro'] = 'https://example.org/legal/';
				return $links;
			}
		);

		$links = Email_Links::footer();

		$this->assertSame( 'https://example.org/legal/', $links['Aviso legal del centro'] );
		$this->assertArrayHasKey( 'Mi Panel de Socio', $links, 'Lo resuelto se conserva salvo que el sitio lo cambie.' );

		remove_all_filters( 'convoca_email_footer_links' );
	}

	public function test_el_pie_se_imprime_en_el_correo_solo_con_los_enlaces_resueltos(): void {
		$this->pagina( 50, 'panel-socio', 'Mi Panel de Socio', '[convoca_mi_area]' );

		$html = \Convoca\Core\Email_Layout::render( '<p>Contenido</p>', 'Asunto' );

		$this->assertStringContainsString( '?p=50', $html );
		$this->assertStringNotContainsString( '/mi-area/', $html, 'El pie no puede llevar una ruta escrita a mano.' );
		$this->assertStringNotContainsString( '/aviso-legal/', $html );
	}
}
