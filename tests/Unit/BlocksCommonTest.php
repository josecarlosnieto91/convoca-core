<?php
/**
 * El bloque de metadatos: lo que se pinta de una entrada en la portada.
 *
 * Este bloque lee de la base de datos y escribe en la página, así que sus dos riesgos son claros:
 *
 * - **XSS**: el valor puede llevar HTML (lo escribió alguien en un campo). Se pinta con `wp_kses_post` y no crudo;
 * - **mentir con el formato**: un precio de `1234.5` tiene que salir `1.234,50 €`, y una fecha, en el formato del
 *   sitio. Un `0` es un valor, no un hueco: no puede desaparecer por confundirlo con «vacío».
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use Convoca\Core\Blocks_Common;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Utils.php';
require_once dirname( __DIR__, 2 ) . '/includes/Blocks_Common.php';

/**
 * Bloque de metadatos.
 */
final class BlocksCommonTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		unset( $GLOBALS['post'] );
		$GLOBALS['_wp_stores'] = array(
			'options'    => array( 'date_format' => 'j \d\e F \d\e Y' ),
			'post_meta'  => array(),
			'transients' => array(),
			'posts'      => array(),
			'usuarios'   => array(),
			'db_inserts' => array(),
			'queries'    => array(),
			'blocks'     => array(),
			'meta_reads' => array(),
		);
	}

	/**
	 * Pinta el bloque con unos atributos y un post dado.
	 *
	 * @param array $atributos Atributos del bloque.
	 * @param int   $post_id   Entrada en contexto.
	 * @return string
	 */
	private function pintar( array $atributos, int $post_id = 42 ): string {
		$bloque = (object) array( 'context' => array( 'postId' => $post_id ) );

		return (string) Blocks_Common::render_post_meta_field(
			array_merge(
				array(
					'metaField' => '_convoca_campo',
					'type'      => 'text',
					'prefix'    => '',
					'suffix'    => '',
				),
				$atributos
			),
			'',
			$bloque
		);
	}

	/**
	 * Las categorías del editor: las cinco de Convoca, con el nombre del sitio delante.
	 */
	public function test_las_categorias_del_editor(): void {
		$existentes = array(
			array(
				'slug'  => 'text',
				'title' => 'Texto',
			),
		);

		$salida = Blocks_Common::add_custom_categories( $existentes, null );

		$slugs = array_column( $salida, 'slug' );
		$this->assertSame( array( 'convoca-enroll', 'convoca-members', 'convoca-turnos', 'convoca-gateway', 'convoca-core', 'text' ), $slugs );
		$this->assertStringStartsWith( get_bloginfo( 'name' ) . ': ', $salida[0]['title'], 'con el nombre del sitio' );
		$this->assertSame( 'clipboard', $salida[0]['icon'] );
	}

	/**
	 * Una categoría que ya existe no se duplica: dos entradas iguales en el desplegable del editor.
	 */
	public function test_no_duplica_una_categoria_que_ya_esta(): void {
		$existentes = array(
			array(
				'slug'  => 'convoca-core',
				'title' => 'Otra cosa',
			),
		);

		$salida = Blocks_Common::add_custom_categories( $existentes, null );

		$this->assertSame( 1, count( array_keys( array_column( $salida, 'slug' ), 'convoca-core' ) ), 'una sola vez' );
		$this->assertSame( 'Otra cosa', array_values( array_filter( $salida, static function ( $c ) {
			return 'convoca-core' === $c['slug'];
		} ) )[0]['title'], 'y se respeta la que ya estaba' );
	}

	/**
	 * El bloque se registra con su devolución de pintado y sus atributos.
	 */
	public function test_el_bloque_se_registra(): void {
		Blocks_Common::register_blocks();

		$bloque = $GLOBALS['_wp_stores']['blocks']['convoca-common/post-meta-field'] ?? null;

		$this->assertIsArray( $bloque, 'registrado' );
		$this->assertSame( 3, $bloque['apiVersion'] );
		$this->assertSame( 'convoca-core', $bloque['category'], 'en su categoría' );
		$this->assertSame( array( 'Convoca\Core\Blocks_Common', 'render_post_meta_field' ), $bloque['render_callback'] );
		$this->assertArrayHasKey( 'metaField', $bloque['attributes'], 'y sabe qué campo leer' );
		$this->assertSame( 'text', $bloque['attributes']['type']['default'] );
	}

	/**
	 * Sin campo declarado no hay nada que pintar, **y no se consulta la base**: la guarda tiene que cortar antes.
	 */
	public function test_sin_campo_no_pinta(): void {
		$GLOBALS['_wp_stores']['post_meta'][42] = array( '' => 'valor con clave vacía' );

		$this->assertSame( '', $this->pintar( array( 'metaField' => '' ) ) );
		$this->assertSame( array(), $GLOBALS['_wp_stores']['meta_reads'], 'ni una lectura' );
	}

	/**
	 * Sin entrada en contexto no se lee la base: no existe la entrada 0.
	 */
	public function test_sin_entrada_no_pinta(): void {
		$GLOBALS['_wp_stores']['post_meta'][0] = array( '_convoca_campo' => 'de nadie' );

		$this->assertSame( '', $this->pintar( array(), 0 ) );
		$this->assertSame( array(), $GLOBALS['_wp_stores']['meta_reads'], 'ni una lectura' );
	}

	/**
	 * Valor vacío no deja hueco ni prefijo suelto.
	 */
	public function test_valor_vacio_no_pinta(): void {
		$GLOBALS['_wp_stores']['post_meta'][42] = array( '_convoca_campo' => '' );

		$this->assertSame( '', $this->pintar( array( 'prefix' => 'Desde ' ) ), 'ni el prefijo' );
	}

	/**
	 * **Un cero es un valor**: si se confundiera con vacío, un precio de 0 € desaparecería.
	 */
	public function test_el_cero_se_pinta(): void {
		$GLOBALS['_wp_stores']['post_meta'][42] = array( '_convoca_campo' => '0' );

		$this->assertSame( '0', $this->pintar( array() ), 'el cero sale' );
		$this->assertSame( '0,00 €', $this->pintar( array( 'type' => 'price' ) ) );
	}

	/**
	 * El precio sale en formato español, con sus miles y su coma.
	 */
	public function test_el_precio(): void {
		$GLOBALS['_wp_stores']['post_meta'][42] = array( '_convoca_campo' => '1234.5' );

		$this->assertSame( '1.234,50 €', $this->pintar( array( 'type' => 'price' ) ) );
	}

	/**
	 * Un número sin decimales, con separador de miles.
	 */
	public function test_el_numero(): void {
		$GLOBALS['_wp_stores']['post_meta'][42] = array( '_convoca_campo' => '1234' );

		$this->assertSame( '1.234', $this->pintar( array( 'type' => 'number' ) ) );
	}

	/**
	 * La fecha, en el formato que haya configurado el sitio (y no en uno fijo).
	 */
	public function test_la_fecha(): void {
		$GLOBALS['_wp_stores']['post_meta'][42] = array( '_convoca_campo' => '2026-11-07' );

		$this->assertSame( '7 de November de 2026', $this->pintar( array( 'type' => 'date' ) ) );

		$GLOBALS['_wp_stores']['options']['date_format'] = 'd/m/Y';
		$this->assertSame( '07/11/2026', $this->pintar( array( 'type' => 'date' ) ), 'el sitio manda' );
	}

	/**
	 * Una fecha que no se puede interpretar se deja tal cual: mejor eso que un hueco.
	 */
	public function test_una_fecha_ilegible_se_deja(): void {
		$GLOBALS['_wp_stores']['post_meta'][42] = array( '_convoca_campo' => 'sin fecha' );

		$this->assertSame( 'sin fecha', $this->pintar( array( 'type' => 'date' ) ) );
	}

	/**
	 * **El texto no se pinta crudo**: un `<script>` guardado en el campo no puede ejecutarse en la portada.
	 */
	public function test_el_texto_no_se_pinta_crudo(): void {
		$GLOBALS['_wp_stores']['post_meta'][42] = array(
			'_convoca_campo' => '<b>Hola</b> <script>alert(1)</script>',
		);

		$salida = $this->pintar( array() );

		$this->assertStringContainsString( '<b>Hola</b>', $salida, 'lo permitido se conserva' );
		$this->assertStringNotContainsString( '<script', $salida, 'y el script no llega' );
	}

	/**
	 * Una URL suelta se convierte en enlace (el campo es texto libre).
	 */
	public function test_una_url_se_convierte_en_enlace(): void {
		$GLOBALS['_wp_stores']['post_meta'][42] = array( '_convoca_campo' => 'Ver https://example.test/actividad' );

		$salida = $this->pintar( array() );

		$this->assertStringContainsString( '<a href="https://example.test/actividad"', $salida );
	}

	/**
	 * Prefijo y sufijo se escapan: los pone quien edita el bloque, y no son HTML.
	 */
	public function test_el_prefijo_se_escapa(): void {
		$GLOBALS['_wp_stores']['post_meta'][42] = array( '_convoca_campo' => '10' );

		$salida = $this->pintar(
			array(
				'prefix' => '<b>Desde ',
				'suffix' => '</b>',
			)
		);

		$this->assertStringContainsString( '&lt;b&gt;Desde ', $salida, 'el prefijo sale escapado' );
		$this->assertStringContainsString( '&lt;/b&gt;', $salida, 'y el sufijo' );
		$this->assertStringNotContainsString( '<b>Desde', $salida );
	}
}
