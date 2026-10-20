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

		$origin    = isset( $args['origin'] ) ? (string) $args['origin'] : '';
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
	 * Lee un pago.
	 *
	 * Devuelve lo que el backend sepa del pago (importe, estado, origen, fecha). Si no hay pasarela o
	 * el pago no existe, devuelve un array vacío: quien llama decide qué hacer, no recibe un fatal.
	 *
	 * @param int $pago_id Identificador del pago.
	 * @return array<string, mixed>
	 */
	public static function get( int $pago_id ): array {
		if ( $pago_id <= 0 ) {
			return array();
		}

		/**
		 * Lector de pagos.
		 *
		 * @param array<string, mixed>|null $payment Pago leído, o null si no hay backend.
		 * @param int                       $pago_id Identificador.
		 */
		$payment = apply_filters( 'convoca_payment_reader', null, $pago_id );

		if ( is_array( $payment ) ) {
			return $payment;
		}

		if ( class_exists( '\Convoca\Gateway\CPT_Pago' ) && method_exists( '\Convoca\Gateway\CPT_Pago', 'get_meta' ) ) {
			$meta = \Convoca\Gateway\CPT_Pago::get_meta( $pago_id );
			return is_array( $meta ) ? $meta : array();
		}

		return array();
	}

	/**
	 * Estado de un pago.
	 *
	 * @param int $pago_id Identificador.
	 * @return string Vacío si no se puede leer.
	 */
	public static function status( int $pago_id ): string {
		$payment = self::get( $pago_id );
		return isset( $payment['status'] ) ? (string) $payment['status'] : '';
	}

	/**
	 * Importe con formato de moneda.
	 *
	 * El formato vivía en el gateway (`CPT_Pago::format_amount`), así que imprimir un importe obligaba a
	 * nombrar una clase de otro plugin. Es presentación, no cobro: se queda en el núcleo.
	 *
	 * @param int $cents Importe en céntimos.
	 * @return string Por ejemplo `1.234,50 €`.
	 */
	public static function format_amount( int $cents ): string {
		return number_format( $cents / 100, 2, ',', '.' ) . ' €';
	}

	/**
	 * URL pública del recibo de un pago.
	 *
	 * @param int $pago_id Identificador.
	 * @return string Vacío si no hay recibo disponible.
	 */
	public static function receipt_url( int $pago_id ): string {
		if ( $pago_id <= 0 ) {
			return '';
		}

		/**
		 * Generador de la URL del recibo.
		 *
		 * @param string $url     URL del recibo (vacía si no hay).
		 * @param int    $pago_id Identificador.
		 */
		$url = (string) apply_filters( 'convoca_payment_receipt', '', $pago_id );

		if ( '' !== $url ) {
			return $url;
		}

		if ( class_exists( '\Convoca\Gateway\Receipt_Generator' ) && method_exists( '\Convoca\Gateway\Receipt_Generator', 'receipt_url' ) ) {
			return (string) \Convoca\Gateway\Receipt_Generator::receipt_url( $pago_id );
		}

		return '';
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
