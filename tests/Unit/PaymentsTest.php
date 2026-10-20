<?php
/**
 * Pruebas de la fachada de cobros.
 *
 * @package Convoca\Core
 */

namespace Convoca\Core\Tests;

use Convoca\Core\Payments;
use PHPUnit\Framework\TestCase;

/**
 * La fachada tiene que decir que NO hay pasarela cuando no la hay, y no inventarse un pago.
 */
class PaymentsTest extends TestCase {

	/**
	 * Sin backend instalado no hay pasarela.
	 *
	 * @return void
	 */
	public function test_no_backend_means_not_available(): void {
		$this->assertFalse( Payments::is_available() );
	}

	/**
	 * Sin backend, crear un pago devuelve un error, no un array vacío.
	 *
	 * @return void
	 */
	public function test_create_without_backend_returns_error(): void {
		$result = Payments::create(
			array(
				'amount_cents' => 2500,
				'origin'       => 'courses',
				'origin_id'    => 12,
			)
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'convoca_payments_unavailable', $result->get_error_code() );
	}

	/**
	 * Sin origen no se crea nada aunque hubiera backend.
	 *
	 * @return void
	 */
	public function test_create_requires_origin(): void {
		$result = Payments::create( array( 'amount_cents' => 100 ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Sin pasarela, leer un pago devuelve vacío en vez de reventar.
	 *
	 * @return void
	 */
	public function test_get_without_backend_is_empty(): void {
		$this->assertSame( array(), Payments::get( 123 ) );
		$this->assertSame( '', Payments::status( 123 ) );
	}

	/**
	 * Un identificador inválido tampoco consulta nada.
	 *
	 * @return void
	 */
	public function test_get_with_invalid_id_is_empty(): void {
		$this->assertSame( array(), Payments::get( 0 ) );
		$this->assertSame( '', Payments::receipt_url( 0 ) );
	}

	/**
	 * Sin pasarela no hay recibo.
	 *
	 * @return void
	 */
	public function test_receipt_url_without_backend_is_empty(): void {
		$this->assertSame( '', Payments::receipt_url( 123 ) );
	}

	/**
	 * La comparación de origen es exacta.
	 *
	 * @return void
	 */
	public function test_origin_comparison(): void {
		$this->assertTrue( Payments::is_origin( 'courses', 'courses' ) );
		$this->assertFalse( Payments::is_origin( 'members', 'courses' ) );
		$this->assertFalse( Payments::is_origin( '', 'courses' ) );
	}

	/**
	 * El importe se formatea en el núcleo, sin pasar por el gateway.
	 *
	 * Antes el formato vivía en `Gateway\CPT_Pago::format_amount()`, así que imprimir un importe
	 * obligaba a nombrar una clase de otro plugin. El resultado tiene que ser el mismo de siempre:
	 * punto de miles, coma decimal y el euro detrás.
	 *
	 * @return void
	 */
	public function test_format_amount(): void {
		$this->assertSame( '1.234,50 €', Payments::format_amount( 123450 ) );
		$this->assertSame( '0,05 €', Payments::format_amount( 5 ) );
		$this->assertSame( '0,00 €', Payments::format_amount( 0 ) );
		$this->assertSame( '12,00 €', Payments::format_amount( 1200 ) );
	}

	/**
	 * El backend se puede sustituir: es lo que permite que el núcleo no nombre al gateway.
	 *
	 * @return void
	 */
	public function test_backend_por_filtro(): void {
		add_filter(
			'convoca_payment_backend',
			static function () {
				return static function ( array $args ): array {
					return array(
						'pago_id'     => 99,
						'payment_url' => 'https://example.org/pago/99',
					);
				};
			}
		);

		$this->assertTrue( Payments::is_available() );

		$resultado = Payments::create(
			array(
				'amount_cents' => 1000,
				'origin'       => 'enroll',
				'origin_id'    => 7,
			)
		);

		$this->assertSame( 99, $resultado['pago_id'] );
		$this->assertSame( 'https://example.org/pago/99', $resultado['payment_url'] );
	}

	/**
	 * Y el error del backend se propaga tal cual.
	 *
	 * @return void
	 */
	public function test_error_del_backend_se_propaga(): void {
		add_filter(
			'convoca_payment_backend',
			static function () {
				return static function ( array $args ) {
					return new \WP_Error( 'invalid_origin', 'Origen de pago no válido.' );
				};
			}
		);

		$resultado = Payments::create(
			array(
				'origin'    => 'loquesea',
				'origin_id' => 7,
			)
		);

		$this->assertInstanceOf( \WP_Error::class, $resultado );
		$this->assertSame( 'invalid_origin', $resultado->get_error_code() );
	}

	/**
	 * El lector y el recibo también se registran por filtro.
	 *
	 * @return void
	 */
	public function test_lector_y_recibo_por_filtro(): void {
		add_filter(
			'convoca_payment_reader',
			static function ( $pago, $id ) {
				return array(
					'status'   => 'pagado',
					'order_id' => 'P-' . $id,
				);
			},
			10,
			2
		);
		add_filter(
			'convoca_payment_receipt',
			static function ( $url, $id ) {
				return 'https://example.org/recibo/' . $id;
			},
			10,
			2
		);

		$this->assertSame( 'pagado', Payments::status( 5 ) );
		$this->assertSame( 'P-5', Payments::get( 5 )['order_id'] );
		$this->assertSame( 'https://example.org/recibo/8', Payments::receipt_url( 8 ) );
	}

	/**
	 * Los filtros que registran estas pruebas no se filtran a las demás.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		remove_all_filters( 'convoca_payment_backend' );
		remove_all_filters( 'convoca_payment_reader' );
		remove_all_filters( 'convoca_payment_receipt' );
	}
}
