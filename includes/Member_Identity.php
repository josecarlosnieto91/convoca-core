<?php
/**
 * Convoca Core — Identidad del socio.
 *
 * @package    Convoca\Core
 * @subpackage Includes
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 */

namespace Convoca\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Quién es el socio que está mirando la web, sin que el núcleo sepa de qué plugin es.
 *
 * El núcleo necesita saber si hay un socio con la sesión abierta para inscribir, para pintar el formulario
 * y para decidir si una inscripción tiene prioridad por estar al día. Antes eso se resolvía llamando
 * directamente a las clases de Convoca Members desde otros plugins (`Member_Auth`, `Process_Member`), con un
 * `class_exists` delante y otro código de reserva detrás: dos caminos que había que mantener sincronizados y
 * que se comportaban distinto según qué estuviera instalado.
 *
 * Ahora hay **un solo camino**: el núcleo pregunta aquí y quien gestiona los socios contesta con el filtro.
 * Los valores por defecto son los de hoy cuando no hay gestión de socios: nadie autenticado, ningún socio y
 * prioridad concedida (que es lo que hacía el código de antes: sin cuota que verificar, `es_socio = 1` ya
 * daba prioridad).
 *
 * La respuesta se guarda **durante la petición** y no más: la sesión del socio vive en un transient y
 * caduca. Tras un `login()` o un `logout()` en la misma carga hay que llamar a `olvidar()`.
 *
 * @since 2.3.47
 */
class Member_Identity {

	/**
	 * Una sola pregunta por petición: el filtro se llama una vez y se reutiliza.
	 *
	 * @var array{autenticado: bool, member_id: int, datos: array}|null
	 */
	private static $identidad = null;

	/**
	 * ¿Hay un socio con la sesión abierta?
	 *
	 * @return bool
	 */
	public static function is_authenticated(): bool {
		return self::datos()['autenticado'];
	}

	/**
	 * Id de la ficha del socio con la sesión abierta, o 0.
	 *
	 * @return int
	 */
	public static function current_member_id(): int {
		return self::datos()['member_id'];
	}

	/**
	 * Datos de la ficha del socio con la sesión abierta, o `array()`.
	 *
	 * Devuelve `array()` y no `null` a propósito: quien llama trata «vacío» como «no hay socio», y así no
	 * tiene que distinguir los dos casos.
	 *
	 * @return array<string, mixed>
	 */
	public static function current_member_data(): array {
		return self::datos()['datos'];
	}

	/**
	 * ¿Esta inscripción tiene prioridad por ser de un socio al día?
	 *
	 * Por defecto **sí**: es lo que hacía el código de antes cuando no había gestión de socios (no hay cuota
	 * que verificar, así que marcar «soy socio» bastaba). Quien gestiona los socios contesta resolviendo la
	 * ficha y mirando su estado de cuota.
	 *
	 * @param int $inscripcion_id Inscripción.
	 * @return bool
	 */
	public static function prioridad_de_socio( int $inscripcion_id ): bool {
		/**
		 * ¿La inscripción tiene prioridad de socio?
		 *
		 * @param bool $prioridad      Por defecto sí.
		 * @param int  $inscripcion_id Inscripción.
		 */
		return (bool) apply_filters( 'convoca_member_priority', true, $inscripcion_id );
	}

	/**
	 * Olvida la respuesta guardada (después de iniciar o cerrar sesión en la misma petición).
	 */
	public static function olvidar(): void {
		self::$identidad = null;
	}

	/**
	 * La respuesta del filtro, normalizada.
	 *
	 * Se normaliza aquí y no en cada llamador porque un plugin puede contestar cualquier cosa: lo que no
	 * encaje se convierte en «no hay socio», que es el camino seguro.
	 *
	 * @return array{autenticado: bool, member_id: int, datos: array}
	 */
	private static function datos(): array {
		if ( null !== self::$identidad ) {
			return self::$identidad;
		}

		/**
		 * Identidad del socio con la sesión abierta.
		 *
		 * @param array{autenticado: bool, member_id: int, datos: array} $identidad Por defecto, nadie.
		 */
		$crudo = apply_filters(
			'convoca_member_identity',
			array(
				'autenticado' => false,
				'member_id'   => 0,
				'datos'       => array(),
			)
		);

		$crudo = is_array( $crudo ) ? $crudo : array();

		self::$identidad = array(
			'autenticado' => ! empty( $crudo['autenticado'] ),
			'member_id'   => isset( $crudo['member_id'] ) ? (int) $crudo['member_id'] : 0,
			'datos'       => isset( $crudo['datos'] ) && is_array( $crudo['datos'] ) ? $crudo['datos'] : array(),
		);

		return self::$identidad;
	}
}
