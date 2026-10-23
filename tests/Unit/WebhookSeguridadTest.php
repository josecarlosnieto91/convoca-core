<?php
/**
 * Los webhooks del núcleo: qué sale, a quién y con qué firma.
 *
 * Esto es la puerta por la que los datos de socios salen del sistema hacia otros servicios. Tres cosas se prueban
 * aquí y las tres fallan en silencio si se rompen:
 *
 * - **la firma HMAC**: quien recibe el webhook sólo puede comprobar que viene de aquí si el cuerpo se firma con
 *   su secreto. Sin firma (o firmando otro cuerpo) el receptor no lo nota hasta que alguien lo falsifica;
 * - **el filtro por evento y el estado activo**: un webhook suscrito a «pago completado» no debe recibir altas de
 *   socios, y uno desactivado no debe recibir nada;
 * - **el reintento**: se reintenta con un retardo creciente y se **para**; un backoff mal calculado reintenta en
 *   bucle y tumba al receptor.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use Convoca\Core\Webhook_Manager;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Webhook_Manager.php';

/**
 * Webhooks.
 */
final class WebhookSeguridadTest extends TestCase {

	/**
	 * Gestor.
	 *
	 * @var Webhook_Manager
	 */
	private Webhook_Manager $gestor;

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->limpiar();
		$this->gestor = new Webhook_Manager();
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$this->limpiar();
		remove_all_filters( 'convoca_webhook_retry_backoff' );
		remove_all_filters( 'convoca_common_webhook_sslverify' );
	}

	/**
	 * Deja el arnés como estaba.
	 */
	private function limpiar(): void {
		unset( $GLOBALS['_wp_stores'], $GLOBALS['_wp_http_posts'] );

		$GLOBALS['_wp_stores'] = array(
			'options'    => array(),
			'post_meta'  => array(),
			'transients' => array(),
			'posts'      => array(),
			'db_inserts' => array(),
		);
		$GLOBALS['_wp_http_posts'] = array();
	}

	/**
	 * Configura webhooks.
	 *
	 * @param array $webhooks Lista.
	 */
	private function webhooks( array $webhooks ): void {
		// `OPTION` es privada y hace bien: la clave es un detalle de dentro. La prueba usa el nombre real de la
		// opción, que es el contrato con quien configura los webhooks.
		$GLOBALS['_wp_stores']['options']['convoca_webhooks'] = $webhooks;
	}

	/**
	 * Las peticiones que salieron.
	 *
	 * @return array
	 */
	private function salidas(): array {
		return (array) ( $GLOBALS['_wp_http_posts'] ?? array() );
	}

	/**
	 * El cuerpo y las cabeceras de la primera petición.
	 *
	 * @return array{body: string, headers: array}
	 */
	private function primera(): array {
		$una = $this->salidas()[0] ?? array( 'args' => array() );

		return array(
			'body'    => (string) ( $una['args']['body'] ?? '' ),
			'headers' => (array) ( $una['args']['headers'] ?? array() ),
		);
	}

	/**
	 * El cuerpo va firmado con el secreto de **ese** webhook: es lo único que permite al receptor comprobar que
	 * viene de aquí.
	 */
	public function test_la_firma_hmac(): void {
		$this->webhooks(
			array(
				array(
					'id'     => 'uno',
					'url'    => 'https://destino.example.test/hook',
					'secret' => 'secreto-de-prueba',
					'events' => array(),
					'active' => true,
				),
			)
		);

		$this->gestor->dispatch( 'member_created', array( 'member_id' => 55 ) );

		$peticion = $this->primera();
		$this->assertNotSame( '', $peticion['body'], 'sale un cuerpo' );
		$this->assertSame(
			hash_hmac( 'sha256', $peticion['body'], 'secreto-de-prueba' ),
			(string) ( $peticion['headers']['X-Convoca-Signature'] ?? '' ),
			'la firma es del cuerpo exacto que se envía'
		);
		$this->assertSame( 'member_created', (string) ( $peticion['headers']['X-Convoca-Event'] ?? '' ) );
		$this->assertNotSame( '', (string) ( $peticion['headers']['X-Convoca-Delivery'] ?? '' ), 'con identificador de entrega' );
	}

	/**
	 * Sin secreto configurado no se inventa una firma vacía: no se manda la cabecera.
	 */
	public function test_sin_secreto_no_hay_firma(): void {
		$this->webhooks(
			array(
				array(
					'id'     => 'uno',
					'url'    => 'https://destino.example.test/hook',
					'events' => array(),
					'active' => true,
				),
			)
		);

		$this->gestor->dispatch( 'member_created', array( 'member_id' => 55 ) );

		$this->assertArrayNotHasKey( 'X-Convoca-Signature', $this->primera()['headers'], 'sin secreto no hay firma' );
	}

	/**
	 * Un webhook suscrito a otros eventos no recibe este.
	 */
	public function test_el_filtro_por_evento(): void {
		$this->webhooks(
			array(
				array(
					'id'     => 'solo-pagos',
					'url'    => 'https://destino.example.test/pagos',
					'events' => array( 'payment_completed' ),
					'active' => true,
				),
			)
		);

		$this->gestor->dispatch( 'member_created', array( 'member_id' => 55 ) );
		$this->assertSame( array(), $this->salidas(), 'no está suscrito: no se le manda nada' );

		$this->gestor->dispatch( 'payment_completed', array( 'pago_id' => 201 ) );
		$this->assertCount( 1, $this->salidas(), 'y sí el suyo' );
	}

	/**
	 * Uno desactivado no recibe nada, aunque esté suscrito.
	 */
	public function test_el_desactivado_no_recibe(): void {
		$this->webhooks(
			array(
				array(
					'id'     => 'apagado',
					'url'    => 'https://destino.example.test/hook',
					'events' => array(),
					'active' => false,
				),
			)
		);

		$this->gestor->dispatch( 'member_created', array( 'member_id' => 55 ) );

		$this->assertSame( array(), $this->salidas(), 'desactivado es desactivado' );
	}

	/**
	 * El mismo evento con el mismo contenido no se manda dos veces en diez segundos.
	 */
	public function test_el_dedup(): void {
		$this->webhooks(
			array(
				array(
					'id'     => 'uno',
					'url'    => 'https://destino.example.test/hook',
					'events' => array(),
					'active' => true,
				),
			)
		);

		$this->gestor->dispatch( 'member_created', array( 'member_id' => 55 ) );
		$this->gestor->dispatch( 'member_created', array( 'member_id' => 55 ) );
		$this->assertCount( 1, $this->salidas(), 'el duplicado se corta' );

		// Pero otro contenido del mismo evento sí pasa.
		$this->gestor->dispatch( 'member_created', array( 'member_id' => 56 ) );
		$this->assertCount( 2, $this->salidas(), 'no es el mismo contenido' );
	}

	/**
	 * Una URL que no es una URL no se llama: no se manda nada a cualquier sitio.
	 */
	public function test_la_url_invalida(): void {
		$this->webhooks(
			array(
				array(
					'id'     => 'roto',
					'url'    => 'no-es-una-url',
					'events' => array(),
					'active' => true,
				),
			)
		);

		$this->gestor->dispatch( 'member_created', array( 'member_id' => 55 ) );

		$this->assertSame( array(), $this->salidas(), 'no se llama a una URL inválida' );
	}

	/**
	 * Los reintentos: retardos crecientes, el último se repite y se **para** en el máximo.
	 */
	public function test_el_reintento_no_es_infinito(): void {
		$this->assertSame( 60, Webhook_Manager::get_retry_delay( 1 ), 'el primero, al minuto' );
		$this->assertSame( 120, Webhook_Manager::get_retry_delay( 2 ) );
		$this->assertNull( Webhook_Manager::get_retry_delay( 3 ), 'al llegar al máximo se deja de reintentar' );
		$this->assertNull( Webhook_Manager::get_retry_delay( 9 ), 'y más allá, también' );

		// Con un calendario más corto que el número de intentos, se repite el último configurado.
		$this->assertSame( 30, Webhook_Manager::get_retry_delay( 1, array( 30 ) ) );
		$this->assertSame( 30, Webhook_Manager::get_retry_delay( 2, array( 30 ) ), 'no se queda sin retardo' );
	}

	/**
	 * El calendario de reintentos es filtrable, y si alguien devuelve basura se usan los valores por defecto.
	 */
	public function test_el_calendario_es_filtrable(): void {
		add_filter(
			'convoca_webhook_retry_backoff',
			static function () {
				return array( 5, 10 );
			}
		);
		$this->assertSame( array( 5, 10 ), Webhook_Manager::get_backoff() );

		remove_all_filters( 'convoca_webhook_retry_backoff' );
		add_filter(
			'convoca_webhook_retry_backoff',
			static function () {
				return 'no es un array';
			}
		);
		$this->assertSame(
			array( 60, 120, 240 ),
			Webhook_Manager::get_backoff(),
			'una configuración rota no deja los reintentos sin calcular'
		);
	}

	/**
	 * `sslverify` se puede apagar por filtro —para un destino interno con certificado propio— y por defecto va
	 * ENCENDIDO: no se manda la firma ni los datos de socios por una conexión sin verificar.
	 */
	public function test_sslverify(): void {
		$this->webhooks(
			array(
				array(
					'id'     => 'uno',
					'url'    => 'https://destino.example.test/hook',
					'events' => array(),
					'active' => true,
				),
			)
		);

		$this->gestor->dispatch( 'member_created', array( 'member_id' => 55 ) );
		$this->assertTrue( (bool) ( $this->salidas()[0]['args']['sslverify'] ?? false ), 'por defecto se verifica' );

		$GLOBALS['_wp_http_posts'] = array();
		$GLOBALS['_wp_stores']['transients'] = array();
		add_filter(
			'convoca_common_webhook_sslverify',
			static function () {
				return false;
			}
		);
		$this->gestor->dispatch( 'member_created', array( 'member_id' => 55 ) );
		$this->assertFalse( (bool) ( $this->salidas()[0]['args']['sslverify'] ?? true ), 'el filtro lo apaga' );
	}
}
