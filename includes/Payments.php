<?php
/**
 * Cobros del ecosistema: una sola puerta para crear y leer pagos.
 *
 * @package Convoca\Core
 */

namespace Convoca\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fachada de pagos.
 *
 * Hoy el backend real es Convoca Gateway. Existe para que los módulos que cobran (inscripciones,
 * cuotas, cursos) dependan de **una** firma del núcleo y no de las clases y funciones de otro plugin:
 * así el día que cambie el gateway —o haya dos— no hay que tocar trece sitios repartidos por tres
 * repositorios. El backend se puede sustituir con el filtro `convoca_payment_backend`.
 */
class Payments {

	/**
	 * ¿Hay pasarela operativa?
	 *
	 * @return bool
	 */
	public static function is_available(): bool {
		return null !== self::backend();
	}

	/**
	 * Backend activo, o null si no hay ninguno.
	 *
	 * @return callable|null
	 */
	private static function backend(): ?callable {
		$gateway = function_exists( '\Convoca\Gateway\convoca_gateway_create_payment' ) ? '\Convoca\Gateway\convoca_gateway_create_payment' : null;

		/**
		 * Backend de cobros.
		 *
		 * @param callable|null $backend Función que crea un pago. Recibe el array de datos y devuelve
		 *                               `array{pago_id:int, payment_url:string}` o `WP_Error`.
		 */
		$backend = apply_filters( 'convoca_payment_backend', $gateway );

		return is_callable( $backend ) ? $backend : null;
	}

	/**
	 * Crea un pago.
	 *
	 * Los datos son los del contrato publicado por el gateway: `amount_cents`, `origin`,
	 * `origin_id`, `product_desc`, `method` y `payer_email`.
	 *
	 * @param array<string, mixed> $args Datos del pago.
	 * @return array{pago_id: int, payment_url: string}|\WP_Error
	 */
	public static function create( array $args ) {
		$backend = self::backend();

		if ( null === $backend ) {
			return new \WP_Error(
				'convoca_payments_unavailable',
				__( 'No hay ninguna pasarela de pago activa.', 'convoca-core' )
			);
		}

		$origin   = isset( $args['origin'] ) ? (string) $args['origin'] : '';
		$origin_id = isset( $args['origin_id'] ) ? (int) $args['origin_id'] : 0;

		if ( '' === $origin || $origin_id <= 0 ) {
			return new \WP_Error(
				'convoca_payments_origin',
				__( 'Falta el origen del pago o su identificador.', 'convoca-core' )
			);
		}

		$result = call_user_func( $backend, $args );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		/**
		 * Pago creado.
		 *
		 * @param array<string, mixed> $args   Datos con los que se pidió.
		 * @param array<string, mixed> $result Resultado del backend.
		 */
		do_action( 'convoca_payment_created', $args, $result );

		return $result;
	}

	/**
	 * ¿Este origen de pago es de este módulo?
	 *
	 * @param string $origin    Origen recibido.
	 * @param string $expected  Origen propio.
	 * @return bool
	 */
	public static function is_origin( string $origin, string $expected ): bool {
		return $origin === $expected;
	}
}
