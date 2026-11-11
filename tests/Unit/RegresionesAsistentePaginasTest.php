<?php
/**
 * Regresiones del asistente de configuración: páginas del sistema.
 *
 * R6 (#14) — El asistente busca las páginas del sistema **solo por slug**: si el panel del socio está
 * publicado en `/mi-cuenta/` con el shortcode correcto, avisa de que «no existe» y «Crear páginas»
 * duplica el área de socio. La detección tiene que resolverse por shortcode (y, si el slug no coincide,
 * ofrecer vincular la página existente en vez de crear otra).
 *
 * El caso ejecutable de este fichero vigila que las dos páginas de verificación (las del QR del carnet y
 * del certificado) no vuelvan a caerse de la lista: desaparecer de ahí fue el defecto #13, que dejaba el
 * escaneo en un 404.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use PHPUnit\Framework\TestCase;

final class RegresionesAsistentePaginasTest extends TestCase {

	private function fuentes(): string {
		$ruta = dirname( __DIR__, 2 ) . '/includes/Admin_Setup_Wizard.php';
		$this->assertFileExists( $ruta );
		return (string) file_get_contents( $ruta );
	}

	/**
	 * Las cinco páginas del sistema siguen declaradas con su shortcode.
	 */
	public function test_las_paginas_del_sistema_siguen_declaradas(): void {
		$src = $this->fuentes();

		$esperadas = array(
			'alta-socios'           => '[convoca_alta_socio]',
			'panel-socio'           => '[convoca_mi_area]',
			'pago'                  => '[convoca_pago]',
			'verificar-socio'       => '[convoca_verificar_socio]',
			'verificar-certificado' => '[convoca_verificar_certificado]',
		);

		foreach ( $esperadas as $slug => $shortcode ) {
			$this->assertStringContainsString( "'{$slug}'", $src, "La página «{$slug}» ha desaparecido del asistente." );
			$this->assertStringContainsString( "'{$shortcode}'", $src, "El shortcode de «{$slug}» cambió o se perdió." );
		}
	}

	/**
	 * Las dos páginas de verificación no son obligatorias (pueden faltar sin el plugin de socios), pero
	 * tienen que seguir en la lista: son los destinos de los QR.
	 */
	public function test_las_paginas_de_verificacion_no_son_obligatorias_pero_estan(): void {
		$src = $this->fuentes();

		$this->assertMatchesRegularExpression(
			"/'verificar-socio'\s*=>\s*array\([\s\S]{0,240}?'req'\s*=>\s*false/",
			$src,
			'La página del QR del carnet debe seguir en la lista y seguir siendo opcional.'
		);
		$this->assertMatchesRegularExpression(
			"/'verificar-certificado'\s*=>\s*array\([\s\S]{0,240}?'req'\s*=>\s*false/",
			$src,
			'La página del certificado debe seguir en la lista y seguir siendo opcional.'
		);
	}

	/**
	 * R6 — La detección no puede depender solo del slug.
	 */
	public function test_la_deteccion_de_paginas_no_depende_solo_del_slug(): void {
		$src = $this->fuentes();

		$this->assertStringContainsString(
			'buscar_pagina_del_sistema( $slug, $info[\'sc\'] )',
			$src,
			'El asistente debe resolver la página por slug y por shortcode.'
		);
		$this->assertStringContainsString(
			'post_content LIKE',
			$src,
			'La búsqueda por shortcode tiene que consultar el contenido, no solo el slug.'
		);
		$this->assertStringContainsString(
			'get_page_by_path( $slug )',
			$src,
			'El slug sigue siendo la primera vía de resolución.'
		);
	}
}
