<?php

/**
 * Convoca Core
 *
 * @package    Convoca\Core
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 */

/**
 * Datos de evento de una entrada («este contenido es un evento») y su acceso.
 *
 * Vive en Core, no en el theme: son datos y su formulario de edición, no
 * presentación. El theme sigue publicando el schema.org y pintando las fechas,
 * pero lee los datos por aquí.
 *
 * Nota de convivencia con instalaciones antiguas: las claves heredadas
 * (`_biodevas_event_*`) NO se cablean aquí. El sitio que las tenga declara su
 * mapa con el filtro `convoca_event_meta_legacy_keys`, de modo que Convoca no
 * arrastra el nombre de ninguna asociación.
 *
 * @package Convoca\Core
 */

namespace Convoca\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Claves de meta que forman el evento de una entrada.
 *
 * @return array<int,string>
 */
function event_meta_keys(): array {
	return array(
		'_convoca_has_event',
		'_convoca_event_start_date',
		'_convoca_event_end_date',
		'_convoca_event_address',
		'_convoca_event_price',
	);
}

/**
 * Normaliza un precio escrito por quien edita.
 *
 * Devuelve el precio con dos decimales y punto (`60.00`, `60.50`) o cadena vacía
 * si no es un número válido o es negativo. Se acepta la coma decimal porque es lo
 * que escribe media España: sin esto, «60,50» se descartaba en silencio y el
 * evento acababa publicado como gratuito en los datos estructurados.
 *
 * @param string $valor Valor tal cual llega del formulario.
 * @return string Precio normalizado, o '' si no sirve.
 */
function event_price_sanitize( string $valor ): string {
	$valor = trim( str_replace( ',', '.', $valor ) );

	if ( '' === $valor || ! is_numeric( $valor ) || (float) $valor < 0 ) {
		return '';
	}

	return number_format( (float) $valor, 2, '.', '' );
}

/**
 * Lee una meta de evento, con caída a las claves heredadas que declare el sitio.
 *
 * Lee PRIMERO la clave canónica y, si está vacía, la heredada que devuelva el
 * filtro `convoca_event_meta_legacy_keys` (mapa clave canónica => clave antigua).
 * Al guardar desde el formulario se escribe siempre la clave canónica, así el
 * dato se migra solo la próxima vez que se edita la entrada.
 *
 * @param int    $post_id  Identificador de la entrada.
 * @param string $meta_key Clave canónica (p. ej. '_convoca_event_start_date').
 * @return string Valor, o cadena vacía si no hay dato.
 */
function event_meta( int $post_id, string $meta_key ): string {
	$value = get_post_meta( $post_id, $meta_key, true );
	if ( '' !== (string) $value ) {
		return (string) $value;
	}

	$legacy = (array) apply_filters( 'convoca_event_meta_legacy_keys', array() );
	if ( isset( $legacy[ $meta_key ] ) && '' !== (string) $legacy[ $meta_key ] ) {
		return (string) get_post_meta( $post_id, (string) $legacy[ $meta_key ], true );
	}

	return '';
}

/**
 * Formatea la fecha del evento de una entrada.
 *
 * Sin fecha de evento devuelve cadena vacía: la fecha de publicación NO se
 * disfraza de fecha de actividad.
 *
 * @param int $post_id Identificador de la entrada.
 * @return string Texto de la fecha, o cadena vacía.
 */
function event_when( int $post_id ): string {
	$ini = event_meta( $post_id, '_convoca_event_start_date' );
	if ( '' === $ini ) {
		return '';
	}

	$fin  = event_meta( $post_id, '_convoca_event_end_date' );
	$ts   = strtotime( $ini );
	$text = $ts ? date_i18n( 'j \d\e F, H:i', $ts ) : $ini;

	if ( '' !== $fin && substr( $fin, 0, 10 ) !== substr( $ini, 0, 10 ) ) {
		$tsf  = strtotime( $fin );
		$text = sprintf( '%s – %s', $text, $tsf ? date_i18n( 'j \d\e F', $tsf ) : $fin );
	}

	return $text;
}

/**
 * Lugar del evento de una entrada.
 *
 * Se queda con el municipio/lugar: lo que va tras la primera coma suele ser la
 * dirección postal completa, que no aporta en una tarjeta.
 *
 * @param int $post_id Identificador de la entrada.
 * @return string Lugar, o cadena vacía.
 */
function event_where( int $post_id ): string {
	$dir = event_meta( $post_id, '_convoca_event_address' );
	$dir = trim( (string) preg_replace( '/\s+/', ' ', $dir ) );

	if ( strlen( $dir ) > 60 ) {
		$partes = explode( ',', $dir );
		$dir    = trim( $partes[0] );
	}

	return $dir;
}

/**
 * Registra el formulario «Evento» en la ficha de edición de la entrada.
 */
function event_meta_box(): void {
	add_meta_box(
		'convoca_event_meta',
		__( 'Event', 'convoca-core' ),
		__NAMESPACE__ . '\\event_meta_box_html',
		'post',
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes', __NAMESPACE__ . '\\event_meta_box' );

/**
 * Pinta el bloque de evento en la ficha de edición.
 *
 * @param \WP_Post $post Entrada que se está editando.
 * @return void
 */
function event_meta_box_html( $post ): void {
	wp_nonce_field( 'convoca_event_meta', 'convoca_event_meta_nonce' );

	$has_event = event_meta( (int) $post->ID, '_convoca_has_event' );
	$inicio    = event_meta( (int) $post->ID, '_convoca_event_start_date' );
	$fin       = event_meta( (int) $post->ID, '_convoca_event_end_date' );
	$direccion = event_meta( (int) $post->ID, '_convoca_event_address' );
	$precio    = event_meta( (int) $post->ID, '_convoca_event_price' );
	?>
	<p>
		<label for="convoca_has_event">
			<input type="checkbox" id="convoca_has_event" name="convoca_has_event" value="1" <?php checked( $has_event, '1' ); ?>>
			<?php esc_html_e( 'This content is an event', 'convoca-core' ); ?>
		</label>
	</p>
	<p>
		<label for="convoca_event_start_date"><?php esc_html_e( 'Start date and time', 'convoca-core' ); ?></label>
		<input type="datetime-local" id="convoca_event_start_date" name="convoca_event_start_date"
			value="<?php echo esc_attr( $inicio ); ?>" style="width:100%">
	</p>
	<p>
		<label for="convoca_event_end_date"><?php esc_html_e( 'End date and time', 'convoca-core' ); ?></label>
		<input type="datetime-local" id="convoca_event_end_date" name="convoca_event_end_date"
			value="<?php echo esc_attr( $fin ); ?>" style="width:100%">
	</p>
	<p>
		<label for="convoca_event_address"><?php esc_html_e( 'Address / location', 'convoca-core' ); ?></label>
		<input type="text" id="convoca_event_address" name="convoca_event_address"
			value="<?php echo esc_attr( $direccion ); ?>" placeholder="<?php esc_attr_e( 'e.g. Main Street 1, Your Town, Spain', 'convoca-core' ); ?>" style="width:100%">
	</p>
	<p>
		<label for="convoca_event_price"><?php esc_html_e( 'Price per person (EUR)', 'convoca-core' ); ?></label>
		<input type="number" id="convoca_event_price" name="convoca_event_price" step="0.01" min="0"
			value="<?php echo esc_attr( $precio ); ?>" placeholder="0.00" style="width:100%">
		<span style="display:block;color:#666;font-size:12px;">
			<?php esc_html_e( 'Leave empty for free events. Without a price the event is published as free.', 'convoca-core' ); ?>
		</span>
	</p>
	<p style="color:#666;font-size:12px;margin-top:8px;">
		<?php esc_html_e( 'Fill in these fields only if you want search engines to index this content as an event with structured data.', 'convoca-core' ); ?>
	</p>
	<?php
}

/**
 * Guarda los datos del evento.
 *
 * Escribe siempre las claves canónicas; las heredadas se siguen leyendo hasta
 * que el contenido se vuelve a editar.
 *
 * @param int $post_id Identificador de la entrada.
 * @return void
 */
function event_meta_save( $post_id ): void {
	if ( ! isset( $_POST['convoca_event_meta_nonce'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['convoca_event_meta_nonce'] ) ), 'convoca_event_meta' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	// `save_post` es global: fuera revisiones, autoguardados y tipos donde el
	// campo ni se muestra.
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	if ( 'post' !== get_post_type( $post_id ) ) {
		return;
	}

	update_post_meta( $post_id, '_convoca_has_event', isset( $_POST['convoca_has_event'] ) ? '1' : '0' );

	foreach ( array(
		'convoca_event_start_date' => '_convoca_event_start_date',
		'convoca_event_end_date'   => '_convoca_event_end_date',
		'convoca_event_address'    => '_convoca_event_address',
	) as $field => $meta_key ) {
		if ( ! isset( $_POST[ $field ] ) ) {
			continue;
		}
		$value = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
		if ( '' !== $value ) {
			update_post_meta( $post_id, $meta_key, $value );
		} else {
			delete_post_meta( $post_id, $meta_key );
		}
	}

	// El precio no es texto libre: se normaliza aparte. Vacío o inválido borra el
	// meta (evento gratuito), que es el comportamiento de siempre.
	if ( isset( $_POST['convoca_event_price'] ) ) {
		$precio = event_price_sanitize( sanitize_text_field( wp_unslash( $_POST['convoca_event_price'] ) ) );
		if ( '' !== $precio ) {
			update_post_meta( $post_id, '_convoca_event_price', $precio );
		} else {
			delete_post_meta( $post_id, '_convoca_event_price' );
		}
	}
}
add_action( 'save_post', __NAMESPACE__ . '\\event_meta_save' );
