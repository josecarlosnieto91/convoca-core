<?php
/**
 * Webhooks: el alta, la edición y la cola de reintentos.
 *
 * Un webhook es una promesa a un sistema ajeno: si el alta se escribe a medias o dos procesos la hacen a la vez, el
 * destinatario recibe el mismo evento dos veces (o ninguna). Y si la cola de reintentos no se vacía, el evento se
 * repite para siempre. Aquí se prueba lo aburrido y lo que arregla: el cerrojo del alta, el saneado, la edición
 * parcial y el vaciado de la cola.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use Convoca\Core\Webhook_Manager;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Utils.php';
require_once dirname( __DIR__, 2 ) . '/includes/Logger.php';
require_once dirname( __DIR__, 2 ) . '/includes/Webhook_Manager.php';

/**
 * Alta, edición y reintentos de webhooks.
 */
final class WebhookCrudTest extends TestCase {

	/**
	 * Estado global previo, para devolverlo al terminar.
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
		$GLOBALS['_wp_http_posts'] = array();
		$GLOBALS['_wp_stores']  = array(
			'options'       => array(),
			'transients'    => array(),
			'locks'         => array(),
			'queries'       => array(),
			'db_reads'      => array(),
			'db_inserts'    => array(),
			'db_deletes'    => array(),
			'results'       => array(),
			'post_meta'     => array(),
			'posts'         => array(),
			'usuarios'      => array(),
			'http_response' => array(
				'response' => array( 'code' => 200 ),
				'body'     => 'ok',
			),
		);
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores'] = $this->previo;
	}

	/**
	 * Un alta escribe su webhook con el id, la URL saneada y activo.
	 */
	public function test_el_alta(): void {
		$id = Webhook_Manager::add_webhook(
			array(
				'url'    => ' https://destino.example.test/hook ',
				'secret' => '  secreto ',
				'events' => array( 'member.created', ' <b>pago.ok</b> ' ),
				'label'  => '  Mi integración ',
			)
		);

		$this->assertNotSame( '', $id );
		$guardados = Webhook_Manager::get_webhooks();
		$this->assertCount( 1, $guardados );
		$wh = $guardados[0];
		$this->assertSame( $id, $wh['id'] );
		$this->assertSame( 'https://destino.example.test/hook', $wh['url'] );
		$this->assertSame( 'secreto', $wh['secret'] );
		$this->assertSame( 'Mi integración', $wh['label'] );
		$this->assertTrue( $wh['active'] );
		$this->assertSame( array( 'member.created', 'pago.ok' ), $wh['events'], 'las etiquetas se van' );
		$this->assertArrayHasKey( 'created_at', $wh );
	}

	/**
	 * Si otro proceso tiene el cerrojo del alta, **no se escribe nada**: devuelve vacío y no toca la opción.
	 */
	public function test_el_alta_no_escribe_con_el_cerrojo_tomado(): void {
		$GLOBALS['_wp_stores']['locks']['convoca_lock_webhook_crud'] = time() + 30;

		$id = Webhook_Manager::add_webhook( array( 'url' => 'https://destino.example.test/a' ) );

		$this->assertSame( '', $id );
		$this->assertSame( array(), Webhook_Manager::get_webhooks() );
	}

	/**
	 * Buscar por id, y no encontrar.
	 */
	public function test_buscar_por_id(): void {
		$id = Webhook_Manager::add_webhook( array( 'url' => 'https://destino.example.test/a' ) );

		$this->assertSame( $id, Webhook_Manager::get_webhook( $id )['id'] );
		$this->assertNull( Webhook_Manager::get_webhook( 'no-existe' ) );
	}

	/**
	 * La edición cambia **solo** lo que se le pasa: mandar el secreto no borra la etiqueta ni la URL.
	 */
	public function test_la_edicion_es_parcial(): void {
		$id = Webhook_Manager::add_webhook(
			array(
				'url'    => 'https://destino.example.test/a',
				'secret' => 'viejo',
				'label'  => 'Original',
				'events' => array( 'member.created' ),
			)
		);

		$this->assertTrue( Webhook_Manager::update_webhook( $id, array( 'secret' => 'nuevo' ) ) );

		$wh = Webhook_Manager::get_webhook( $id );
		$this->assertSame( 'nuevo', $wh['secret'] );
		$this->assertSame( 'Original', $wh['label'], 'lo que no se manda no se toca' );
		$this->assertSame( 'https://destino.example.test/a', $wh['url'] );
		$this->assertSame( array( 'member.created' ), $wh['events'] );

		$this->assertTrue( Webhook_Manager::update_webhook( $id, array( 'active' => false ) ) );
		$this->assertFalse( Webhook_Manager::get_webhook( $id )['active'] );

		$this->assertFalse( Webhook_Manager::update_webhook( 'no-existe', array( 'label' => 'x' ) ) );
	}

	/**
	 * Editar tampoco escribe con el cerrojo tomado.
	 */
	public function test_la_edicion_respeta_el_cerrojo(): void {
		$id = Webhook_Manager::add_webhook( array( 'url' => 'https://destino.example.test/a', 'label' => 'Antes' ) );
		$GLOBALS['_wp_stores']['locks']['convoca_lock_webhook_crud'] = time() + 30;

		$this->assertFalse( Webhook_Manager::update_webhook( $id, array( 'label' => 'Después' ) ) );
		$this->assertSame( 'Antes', Webhook_Manager::get_webhook( $id )['label'] );
	}

	/**
	 * Borrar quita el webhook y limpia su registro de entregas; borrar uno que no está devuelve false.
	 */
	public function test_el_borrado(): void {
		$id = Webhook_Manager::add_webhook( array( 'url' => 'https://destino.example.test/a' ) );

		$this->assertTrue( Webhook_Manager::delete_webhook( $id ) );
		$this->assertSame( array(), Webhook_Manager::get_webhooks() );
		$this->assertFalse( Webhook_Manager::delete_webhook( $id ), 'ya no está' );
	}

	/**
	 * Los eventos de dominio despachan con su nombre y su carga: el destinatario recibe lo que espera.
	 */
	public function test_los_eventos_despachan(): void {
		Webhook_Manager::add_webhook(
			array(
				'url'    => 'https://destino.example.test/hook',
				'events' => array( 'member.created', 'payment.completed', 'enrollment.created' ),
			)
		);

		$emisor = new Webhook_Manager();
		$emisor->on_member_created( 77 );
		$emisor->on_payment_completed( 5, 'members', 77, array( 'amount' => 3000 ) );
		$emisor->on_enrollment_created( 9, 3 );

		$enviados = array_column( $GLOBALS['_wp_http_posts'], 'url' );
		$this->assertCount( 3, $enviados );
		$this->assertSame( array( 'https://destino.example.test/hook' ), array_values( array_unique( $enviados ) ) );

		$eventos = array_map(
			static function ( array $p ): string {
				return (string) ( $p['args']['headers']['X-Convoca-Event'] ?? '' );
			},
			$GLOBALS['_wp_http_posts']
		);
		$this->assertSame( array( 'member.created', 'payment.completed', 'enrollment.created' ), $eventos );
	}

	/**
	 * Un reintento vencido que se entrega bien **sale de la cola**: si se quedara, el destinatario recibiría el mismo
	 * evento cada hora.
	 */
	public function test_la_cola_entrega_y_borra(): void {
		$tabla = 'wp_convoca_webhook_retries';
		$GLOBALS['_wp_stores']['results'] = array(
			(object) array(
				'id'             => 1,
				'webhook_id'     => 'wh-1',
				'webhook_url'    => 'https://destino.example.test/uno',
				'webhook_secret' => 's',
				'payload'        => (string) wp_json_encode(
					array(
						'event' => 'member.created',
						'data'  => array( 'id' => 1 ),
					)
				),
				'attempt'        => 2,
			),
		);

		Webhook_Manager::process_retries();

		$this->assertCount( 1, $GLOBALS['_wp_http_posts'] );
		$borrados = array_values(
			array_map(
				static fn( array $d ): int => (int) ( $d['where']['id'] ?? 0 ),
				array_filter( $GLOBALS['_wp_stores']['db_deletes'], static fn( array $d ): bool => ( $d['table'] ?? '' ) === $tabla )
			)
		);
		$this->assertSame( array( 1 ), $borrados );
	}

	/**
	 * Un intento que falla y ya no tiene siguiente se descarta con aviso; uno que aún puede reintentarse se queda
	 * programado con el intento siguiente (no se borra).
	 */
	public function test_la_cola_descarta_lo_agotado_y_conserva_lo_reintentable(): void {
		$tabla = 'wp_convoca_webhook_retries';
		$GLOBALS['_wp_stores']['http_response'] = array(
			'response' => array( 'code' => 500 ),
			'body'     => 'error',
		);
		$base = static function ( int $id, int $attempt ): object {
			return (object) array(
				'id'             => $id,
				'webhook_id'     => 'wh-' . $id,
				'webhook_url'    => 'https://destino.example.test/' . $id,
				'webhook_secret' => '',
				'payload'        => (string) wp_json_encode( array( 'event' => 'member.created' ) ),
				'attempt'        => $attempt,
			);
		};

		// Uno agotado (sin siguiente intento) y uno que todavía puede reintentarse.
		$GLOBALS['_wp_stores']['results'] = array( $base( 1, 3 ), $base( 2, 1 ) );
		Webhook_Manager::process_retries();

		$borrados = array_values(
			array_map(
				static fn( array $d ): int => (int) ( $d['where']['id'] ?? 0 ),
				array_filter( $GLOBALS['_wp_stores']['db_deletes'], static fn( array $d ): bool => ( $d['table'] ?? '' ) === $tabla )
			)
		);
		$this->assertSame( array( 1 ), $borrados, 'sólo el agotado sale de la cola' );

		// El que se queda vuelve a «pendiente» con el intento siguiente (se actualiza, no se inserta).
		$updates = array_values(
			array_filter( $GLOBALS['_wp_stores']['db_updates'], static fn( array $d ): bool => ( $d['table'] ?? '' ) === $tabla )
		);
		// Primero se marca «processing»; la reprogramación es el UPDATE que lo devuelve a «pending».
		$reprogramado = array_values(
			array_filter(
				$updates,
				static fn( array $d ): bool => 2 === (int) ( $d['where']['id'] ?? 0 ) && 'pending' === ( $d['data']['status'] ?? '' )
			)
		);
		$this->assertNotEmpty( $reprogramado, 'el reintentable se reprograma' );
		$this->assertSame( 'pending', $reprogramado[0]['data']['status'] );
		$this->assertSame( 2, $reprogramado[0]['data']['attempt'], 'sube el intento' );
	}

	/**
	 * Con el cerrojo de la cola tomado no se procesa nada (dos crons a la vez entregarían dos veces).
	 */
	public function test_la_cola_respeta_su_cerrojo(): void {
		$GLOBALS['_wp_stores']['transients']['convoca_webhook_retry_lock'] = true;
		$GLOBALS['_wp_stores']['results'] = array(
			(object) array(
				'id'             => 1,
				'webhook_id'     => 'wh-1',
				'webhook_url'    => 'https://destino.example.test/uno',
				'webhook_secret' => '',
				'payload'        => (string) wp_json_encode( array( 'event' => 'x' ) ),
				'attempt'        => 1,
			),
		);

		Webhook_Manager::process_retries();

		$this->assertSame( array(), $GLOBALS['_wp_http_posts'] );
		$this->assertSame( array(), $GLOBALS['_wp_stores']['db_deletes'] );
	}

	/**
	 * Sin nada vencido no se toca la red ni la tabla.
	 */
	public function test_la_cola_vacia(): void {
		Webhook_Manager::process_retries();

		$this->assertSame( array(), $GLOBALS['_wp_http_posts'] );
		$this->assertArrayNotHasKey( 'convoca_webhook_retry_lock', $GLOBALS['_wp_stores']['transients'], 'el cerrojo se suelta' );
	}
}
