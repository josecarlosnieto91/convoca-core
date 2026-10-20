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
	 * La comparación de origen es exacta.
	 *
	 * @return void
	 */
	public function test_origin_comparison(): void {
		$this->assertTrue( Payments::is_origin( 'courses', 'courses' ) );
		$this->assertFalse( Payments::is_origin( 'members', 'courses' ) );
		$this->assertFalse( Payments::is_origin( '', 'courses' ) );
	}
}
