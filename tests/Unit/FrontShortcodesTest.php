<?php
/**
 * Los shortcodes de la portada: cifras, fecha, lugar, redes y menú.
 *
 * Son lo que ve el visitante, y tienen tres formas de mentir que aquí se vigilan:
 *
 * - **cifras inventadas**: `site_stats()` descarta lo que un filtro meta mal formado, así que lo que se pinta
 *   siempre tiene valor y etiqueta; y si no hay cifras, no se pinta un hueco vacío;
 * - **una fecha que no es del evento**: sin fecha de evento el shortcode devuelve cadena vacía, no la fecha de
 *   publicación;
 * - **un enlace o un texto sin escapar**: la dirección de un evento y las redes vienen de la base de datos.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Utils.php';
require_once dirname( __DIR__, 2 ) . '/includes/event-meta.php';
require_once dirname( __DIR__, 2 ) . '/includes/front-shortcodes.php';

/**
 * Shortcodes de la portada.
 */
final class FrontShortcodesTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		unset( $GLOBALS['post'] );
		// Los filtros del arnés viven en un almacén global: sin limpiarlos, un filtro de una prueba anterior
		// seguía metiendo cifras en la siguiente y la prueba de «sin cifras» nunca veía el hueco.
		$GLOBALS['_wp_filters'] = array();
		$GLOBALS['_wp_stores'] = array(
			'options'    => array(),
			'post_meta'  => array(),
			'transients' => array(),
			'posts'      => array(),
			'usuarios'   => array(),
			'db_inserts' => array(),
			'queries'    => array(),
			'nav_menus'  => array(),
			'nav_html'   => array(),
		);
	}

	/**
	 * Siembra entradas publicadas con su fecha.
	 *
	 * @param array<int,string> $fechas Fechas de publicación (`Y-m-d H:i:s`).
	 */
	private function sembrar_entradas( array $fechas ): void {
		foreach ( $fechas as $i => $fecha ) {
			$GLOBALS['_wp_stores']['posts'][ 100 + $i ] = (object) array(
				'ID'           => 100 + $i,
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_title'   => 'Entrada ' . $i,
				'post_date'    => $fecha,
				'post_content' => '',
			);
		}
	}

	/**
	 * Las cifras del sitio: publicaciones, las de este año y los años de trabajo.
	 */
	public function test_las_cifras_del_sitio(): void {
		$this->sembrar_entradas( array( '2022-03-01 10:00:00', '2026-01-10 10:00:00', '2026-06-05 10:00:00' ) );

		$stats = \Convoca\Core\site_stats();

		$this->assertSame( '+3', $stats['publicaciones']['value'], 'las publicaciones, con su signo' );
		$this->assertSame( '2', $stats['este_ano']['value'], 'las de este año' );
		$this->assertStringContainsString( '2026', $stats['este_ano']['label'] );
		$this->assertSame( '4', $stats['anos']['value'], 'los años desde la más antigua' );
	}

	/**
	 * Sin entradas no hay cifras: no se pinta un cero de relleno.
	 */
	public function test_sin_entradas_no_hay_cifras(): void {
		$this->assertSame( array(), \Convoca\Core\site_stats() );
	}

	/**
	 * **Lo mal formado se descarta en la fuente**: quien pinte las cifras no tiene que comprobarlas.
	 */
	public function test_el_filtro_no_puede_meter_basura(): void {
		$this->sembrar_entradas( array( '2026-01-10 10:00:00' ) );

		add_filter(
			'convoca_site_stats',
			static function ( array $stats ): array {
				$stats['texto']     = 'no soy una cifra';
				$stats['sin_label'] = array( 'value' => '9' );
				$stats['sin_valor'] = array( 'label' => 'Nueve' );
				$stats['buena']     = array(
					'value' => '9',
					'label' => 'Nueve',
				);

				return $stats;
			}
		);

		$stats = \Convoca\Core\site_stats();

		$this->assertArrayNotHasKey( 'texto', $stats );
		$this->assertArrayNotHasKey( 'sin_label', $stats, 'sin etiqueta no se puede pintar' );
		$this->assertArrayNotHasKey( 'sin_valor', $stats, 'sin valor tampoco' );
		$this->assertSame( 'Nueve', $stats['buena']['label'], 'y la que viene bien se conserva' );
	}

	/**
	 * El shortcode pinta las cifras, escapa lo que venga del filtro y no pasa de cuatro.
	 */
	public function test_el_shortcode_de_cifras(): void {
		add_filter(
			'convoca_site_stats',
			static function (): array {
				$stats = array();
				for ( $i = 1; $i <= 6; $i++ ) {
					$stats[ 'c' . $i ] = array(
						'value' => (string) $i,
						'label' => 1 === $i ? '<b>uno</b>' : 'Etiqueta ' . $i,
					);
				}

				return $stats;
			}
		);

		$html = \Convoca\Core\shortcode_stats();

		$this->assertSame( 4, substr_count( $html, 'class="convoca-dato"' ), 'cuatro cifras, no seis' );
		$this->assertStringContainsString( '&lt;b&gt;uno&lt;/b&gt;', $html, 'la etiqueta del filtro sale escapada' );
		$this->assertStringNotContainsString( '<b>uno</b>', $html, 'y no viva' );
		$this->assertStringContainsString( '>4<', $html, 'la cuarta cifra sí está' );
	}

	/**
	 * Sin cifras el shortcode no deja un hueco en la página.
	 */
	public function test_el_shortcode_de_cifras_vacio(): void {
		$this->assertSame( '', \Convoca\Core\shortcode_stats() );
	}

	/**
	 * La fecha del evento se pinta con su ISO en el `datetime`.
	 */
	public function test_la_fecha_del_evento(): void {
		$GLOBALS['post'] = (object) array( 'ID' => 42 );
		$GLOBALS['_wp_stores']['post_meta'][42] = array(
			'_convoca_event_start_date' => '2026-11-07 18:30:00',
			'_convoca_event_end_date'   => '2026-11-07 21:00:00',
		);

		$html = \Convoca\Core\shortcode_when();

		$this->assertStringContainsString( 'datetime="2026-11-07 18:30:00"', $html, 'la máquina lee la fecha exacta' );
		$this->assertStringContainsString( '7 de November, 18:30', $html, 'y la persona, en castellano de la zona del sitio' );
	}

	/**
	 * Un evento de dos días enseña el rango: si no, parece que acaba el mismo día que empieza.
	 */
	public function test_el_rango_de_dos_dias(): void {
		$GLOBALS['post'] = (object) array( 'ID' => 42 );
		$GLOBALS['_wp_stores']['post_meta'][42] = array(
			'_convoca_event_start_date' => '2026-11-07 18:30:00',
			'_convoca_event_end_date'   => '2026-11-08 14:00:00',
		);

		$html = \Convoca\Core\shortcode_when();

		$this->assertStringContainsString( '18:30', $html );
		$this->assertStringContainsString( '–', $html, 'con su raya de rango' );
		$this->assertStringContainsString( '8 de November', $html, 'y el día de fin' );
	}

	/**
	 * Un fin sin inicio no es un evento: no se pinta un rango con el principio en blanco.
	 */
	public function test_fin_sin_inicio_no_pinta_nada(): void {
		$GLOBALS['post'] = (object) array( 'ID' => 42 );
		$GLOBALS['_wp_stores']['post_meta'][42] = array( '_convoca_event_end_date' => '2026-11-08 14:00:00' );

		$this->assertSame( '', \Convoca\Core\shortcode_when() );
	}

	/**
	 * **Sin fecha de evento no se pinta la de publicación**: una entrada no es una actividad.
	 */
	public function test_sin_fecha_de_evento_no_hay_fecha(): void {
		$GLOBALS['post'] = (object) array( 'ID' => 42 );

		$this->assertSame( '', \Convoca\Core\shortcode_when() );
	}

	/**
	 * Sin contenido en curso (archivo, 404) el shortcode no revienta, **aunque haya meta en el id 0**: no existe la
	 * entrada 0, y pintar su fecha sería leer datos de nadie.
	 */
	public function test_sin_contenido_no_hay_fecha(): void {
		$GLOBALS['_wp_stores']['post_meta'][0] = array( '_convoca_event_start_date' => '2026-11-07 18:30:00' );

		$this->assertSame( '', \Convoca\Core\shortcode_when() );
	}

	/**
	 * El lugar: se pinta escapado y se queda con el municipio, no con la dirección completa.
	 */
	public function test_el_lugar(): void {
		$GLOBALS['post'] = (object) array( 'ID' => 42 );
		$GLOBALS['_wp_stores']['post_meta'][42] = array(
			// Más de 60 caracteres a propósito: es cuando el producto se queda con el municipio.
			'_convoca_event_address' => 'Plaza <script>alert(1)</script>, Calle Mayor 1, Bajo B, 33003, Oviedo',
		);

		$html = \Convoca\Core\shortcode_where();

		$this->assertStringNotContainsString( '<script>', $html, 'la dirección sale escapada' );
		$this->assertStringContainsString( 'Plaza &lt;script&gt;', $html );
		// En una tarjeta se enseña el municipio, no la dirección postal: el código postal no aporta y ocupa.
		$this->assertStringNotContainsString( '33003', $html, 'sin la dirección postal completa' );
	}

	/**
	 * Sin lugar no se pinta el bloque.
	 */
	public function test_sin_lugar_no_hay_bloque(): void {
		$GLOBALS['post'] = (object) array( 'ID' => 42 );

		$this->assertSame( '', \Convoca\Core\shortcode_where() );
	}

	/**
	 * Las redes: sin URLs no se pinta nada; las vacías se descartan; las buenas van con su servicio.
	 */
	public function test_las_redes(): void {
		$this->assertSame( '', \Convoca\Core\shortcode_socials(), 'sin URLs configuradas no hay nada' );

		add_filter(
			'convoca_social_links',
			static function (): array {
				return array(
					'instagram' => 'https://instagram.com/ejemplo',
					'facebook'  => '   ',
					'mastodon'  => 'https://mastodon.example.test/@ejemplo?a=1&b=2',
				);
			}
		);

		$html = \Convoca\Core\shortcode_socials();

		$this->assertStringContainsString( '"service":"instagram"', $html );
		$this->assertStringNotContainsString( 'facebook', $html, 'la vacía no se pinta' );
		$this->assertStringContainsString( 'wp:social-links', $html, 'y el bloque de redes se renderiza' );

		// Reentrada: el bloque que renderiza las redes vuelve a pedir el shortcode. Sin la guarda, el HTML que se
		// contiene a sí mismo gira hasta agotar la CPU (y la página se queda en blanco).
		$GLOBALS['_wp_stores']['blocks_callback'] = static function (): string {
			return \Convoca\Core\shortcode_socials();
		};
		$this->assertSame( '', \Convoca\Core\shortcode_socials(), 'la reentrada devuelve vacío, no recursión' );
		unset( $GLOBALS['_wp_stores']['blocks_callback'] );
		// `esc_url_raw()` no convierte `&` en entidad (correcto en un bloque), pero sí codifica lo que no es
		// válido en una URL: un espacio tiene que salir como `%20` y no romper el JSON del comentario.
		add_filter(
			'convoca_social_links',
			static function (): array {
				return array( 'raro' => 'https://example.test/a b' );
			}
		);
		$this->assertStringContainsString( 'a%20b', \Convoca\Core\shortcode_socials(), 'la URL se sanea' );
	}

	/**
	 * Los años de trabajo nunca son cero: una web que lleva menos de un año lleva uno, no ninguno.
	 */
	public function test_los_anos_nunca_son_cero(): void {
		$this->sembrar_entradas( array( (string) gmdate( 'Y' ) . '-01-15 10:00:00' ) );

		$this->assertSame( '1', \Convoca\Core\site_stats()['anos']['value'] );
	}

	/**
	 * El menú: sin menú en esa ubicación no se pinta; con menú se devuelve su HTML.
	 */
	public function test_el_menu(): void {
		// Hay HTML en la ubicación pero **no** hay menú asignado: sin la comprobación se pintaría un menú fantasma.
		$GLOBALS['_wp_stores']['nav_html']['top'] = '<nav class="convoca-nav"><ul><li>Fantasma</li></ul></nav>';
		$this->assertSame( '', \Convoca\Core\shortcode_menu( array( 'location' => 'top' ) ), 'sin menú no hay nav vacío' );

		$GLOBALS['_wp_stores']['nav_menus']['top'] = true;
		$GLOBALS['_wp_stores']['nav_html']['top']  = '<nav class="convoca-nav"><ul><li>Inicio</li></ul></nav>';

		$html = \Convoca\Core\shortcode_menu( array( 'location' => 'top' ) );

		$this->assertStringContainsString( '<nav', $html );
		$this->assertStringContainsString( 'Inicio', $html );
	}

	/**
	 * Los shortcodes quedan registrados con su etiqueta: si no, en la página se lee el texto crudo entre corchetes.
	 */
	public function test_los_shortcodes_quedan_registrados(): void {
		foreach ( array( 'convoca_menu', 'convoca_socials', 'convoca_cuando', 'convoca_donde', 'convoca_stats' ) as $tag ) {
			$this->assertTrue( \shortcode_exists( $tag ), "«{$tag}» registrado" );
		}
	}
}
