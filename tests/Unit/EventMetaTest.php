<?php
/**
 * Los datos de evento de una entrada: precio, fecha, lugar y guardado.
 *
 * El precio se escribe con coma en medio España: «60,50» se descartaba en silencio y el evento salía publicado como
 * gratuito. Y el guardado tiene que saber cuándo **no** tocar nada (revisión, autoguardado, otro tipo de entrada), que
 * es la mitad de los `save_post` que pasan por el panel.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Utils.php';
require_once dirname( __DIR__, 2 ) . '/includes/Logger.php';
require_once dirname( __DIR__, 2 ) . '/includes/event-meta.php';

/**
 * Datos de evento.
 */
final class EventMetaTest extends TestCase {

	/**
	 * Estado global previo.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->previo           = $GLOBALS['_wp_stores'] ?? array();
		$GLOBALS['_wp_filters'] = array();
		$GLOBALS['_test_caps']  = array();
		$GLOBALS['_wp_stores']  = array(
			'options'    => array(),
			'post_meta'  => array(),
			'transients' => array(),
			'posts'      => array(),
			'usuarios'   => array(),
			'queries'    => array(),
			'db_inserts' => array(),
			'revisiones' => array(),
			'autosaves'  => array(),
		);
		$_POST = array();
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores'] = $this->previo;
		unset( $GLOBALS['_test_caps'] );
		$_POST = array();
	}

	/**
	 * Un precio con coma se entiende; lo que no es número, no.
	 */
	public function test_el_precio(): void {
		$this->assertSame( '60.50', \Convoca\Core\event_price_sanitize( '60,50' ), 'la coma decimal de media España' );
		$this->assertSame( '60.00', \Convoca\Core\event_price_sanitize( '60' ) );
		$this->assertSame( '12.50', \Convoca\Core\event_price_sanitize( ' 12,5 ' ) );
		$this->assertSame( '', \Convoca\Core\event_price_sanitize( '' ) );
		$this->assertSame( '', \Convoca\Core\event_price_sanitize( 'gratis' ) );
		$this->assertSame( '', \Convoca\Core\event_price_sanitize( '-5' ), 'un precio negativo no es un precio' );
	}

	/**
	 * La fecha: sin fecha de evento **no** se disfraza la de publicación.
	 */
	public function test_la_fecha(): void {
		$this->assertSame( '', \Convoca\Core\event_when( 10 ) );

		$GLOBALS['_wp_stores']['post_meta'][10]['_convoca_event_start_date'] = '2026-11-07 18:30:00';
		$texto = \Convoca\Core\event_when( 10 );
		// El nombre del mes depende del locale del sitio; lo que sí es fijo es el formato «7 de <mes>, HH:MM».
		$this->assertStringStartsWith( '7 de ', $texto );
		$this->assertStringContainsString( '18:30', $texto );
		$this->assertStringNotContainsString( '–', $texto, 'un solo día no es un rango' );

		// El mismo día por la mañana tampoco es un rango.
		$GLOBALS['_wp_stores']['post_meta'][10]['_convoca_event_end_date'] = '2026-11-07 22:00:00';
		$this->assertStringNotContainsString( '–', \Convoca\Core\event_when( 10 ) );

		$GLOBALS['_wp_stores']['post_meta'][10]['_convoca_event_end_date'] = '2026-11-09 22:00:00';
		$this->assertStringContainsString( '–', \Convoca\Core\event_when( 10 ) );

		// Una fecha ilegible se devuelve tal cual, no se pierde.
		$GLOBALS['_wp_stores']['post_meta'][11]['_convoca_event_start_date'] = 'un día de mayo';
		$this->assertSame( 'un día de mayo', \Convoca\Core\event_when( 11 ) );
	}

	/**
	 * El lugar: se queda con lo de antes de la primera coma si es una dirección larga.
	 */
	public function test_el_lugar(): void {
		$this->assertSame( '', \Convoca\Core\event_where( 12 ) );

		$GLOBALS['_wp_stores']['post_meta'][12]['_convoca_event_address'] = '  Plaza   Mayor  ';
		$this->assertSame( 'Plaza Mayor', \Convoca\Core\event_where( 12 ), 'espacios de más fuera' );

		$larga = 'Centro Social de La Madriguera, Calle de la Independencia 12, 33004 Oviedo, Asturias';
		$GLOBALS['_wp_stores']['post_meta'][12]['_convoca_event_address'] = $larga;
		$this->assertSame( 'Centro Social de La Madriguera', \Convoca\Core\event_where( 12 ), 'sin la dirección postal' );
	}

	/**
	 * Las claves heredadas se declaran con un filtro: el núcleo no arrastra el nombre de ninguna asociación.
	 */
	public function test_las_claves_heredadas(): void {
		$GLOBALS['_wp_stores']['post_meta'][13]['vieja_clave_lugar'] = 'Lugg';

		$this->assertSame( '', \Convoca\Core\event_where( 13 ), 'sin el mapa no se lee nada' );

		add_filter( 'convoca_event_meta_legacy_keys', static fn(): array => array( '_convoca_event_address' => 'vieja_clave_lugar' ) );
		$this->assertSame( 'Lugg', \Convoca\Core\event_where( 13 ), 'con el mapa, sí' );
	}

	/**
	 * El guardado: sin nonce no toca nada, y con nonce guarda los cuatro campos y normaliza el precio.
	 */
	public function test_el_guardado(): void {
		$GLOBALS['_test_caps'] = array( 'edit_post' => true );

		\Convoca\Core\event_meta_save( 20 );
		$this->assertArrayNotHasKey( '_convoca_has_event', (array) ( $GLOBALS['_wp_stores']['post_meta'][20] ?? array() ), 'sin nonce no se toca' );

		$_POST['convoca_event_meta_nonce'] = 'bueno';
		$_POST['convoca_has_event']        = '1';
		$_POST['convoca_event_start_date'] = '2026-11-07 18:30:00';
		$_POST['convoca_event_address']    = 'Plaza Mayor';
		$_POST['convoca_event_price']      = '60,50';
		$GLOBALS['_wp_stores']['nonces_validos'] = array( 'bueno' );
		$p = new \WP_Post();
		$p->ID          = 20;
		$p->post_type   = 'post';
		$p->post_status = 'publish';
		$p->post_title  = 'Fiesta';
		$GLOBALS['_wp_stores']['posts'][20] = $p;

		\Convoca\Core\event_meta_save( 20 );

		$meta = (array) $GLOBALS['_wp_stores']['post_meta'][20];
		$this->assertSame( '1', $meta['_convoca_has_event'] );
		$this->assertSame( '2026-11-07 18:30:00', $meta['_convoca_event_start_date'] );
		$this->assertSame( 'Plaza Mayor', $meta['_convoca_event_address'] );
		$this->assertSame( '60.50', $meta['_convoca_event_price'], 'la coma se normaliza' );
		$this->assertArrayNotHasKey( '_convoca_event_end_date', $meta, 'un campo que no viaja no se toca' );
	}

	/**
	 * Y no guarda en una revisión, un autoguardado o una entrada que no es `post`.
	 */
	public function test_el_guardado_no_toca_lo_que_no_es_la_entrada(): void {
		$GLOBALS['_test_caps'] = array( 'edit_post' => true );
		$GLOBALS['_wp_stores']['nonces_validos'] = array( 'bueno' );
		$_POST['convoca_event_meta_nonce'] = 'bueno';
		$_POST['convoca_has_event']        = '1';

		$p = new \WP_Post();
		$p->ID          = 21;
		$p->post_type   = 'page';
		$p->post_status = 'publish';
		$GLOBALS['_wp_stores']['posts'][21] = $p;

		\Convoca\Core\event_meta_save( 21 );
		$this->assertArrayNotHasKey( '_convoca_has_event', (array) ( $GLOBALS['_wp_stores']['post_meta'][21] ?? array() ), 'una página no es una entrada' );

		$p->post_type = 'post';
		$GLOBALS['_wp_stores']['revisiones'] = array( 21 );
		\Convoca\Core\event_meta_save( 21 );
		$this->assertArrayNotHasKey( '_convoca_has_event', (array) ( $GLOBALS['_wp_stores']['post_meta'][21] ?? array() ), 'una revisión tampoco' );

		$GLOBALS['_wp_stores']['revisiones'] = array();
		$GLOBALS['_wp_stores']['autosaves']  = array( 21 );
		\Convoca\Core\event_meta_save( 21 );
		$this->assertArrayNotHasKey( '_convoca_has_event', (array) ( $GLOBALS['_wp_stores']['post_meta'][21] ?? array() ), 'ni un autoguardado' );
	}
}
