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
 * Shortcodes de front que necesita cualquier instalación de Convoca.
 *
 * Responsabilidad: componentes de interfaz y de contenido comunes a cualquier
 * sitio (menú, redes, relacionadas, cifras) y los datos de evento del contenido.
 * Vivían en el mu-plugin privado de Biodevas, que hacía imposible instalar
 * plugins + theme sin ese fichero; ahora los registra Core, que es el plugin
 * base del producto.
 *
 * Ninguno inventa datos: si no hay menú, redes, relacionadas, cifras o fecha de
 * evento, devuelven cadena vacía en vez de pintar corchetes o ceros.
 *
 * @package Convoca\Core
 */

namespace Convoca\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ── [convoca_menu] ─────────────────────────────────────────────────────── */

/**
 * Pinta un menú clásico de WordPress dentro de una plantilla FSE.
 *
 * Los ficheros .html de templates/ y parts/ no ejecutan PHP, así que el menú
 * (que se gestiona desde el escritorio) se pinta con este shortcode.
 *
 * @param array<string,string>|string $atts Atributos: location, class.
 * @return string HTML del menú, o cadena vacía si no hay menú en esa ubicación.
 */
function shortcode_menu( $atts ): string {
	$atts = shortcode_atts(
		array(
			'location' => 'top',
			'class'    => 'convoca-nav',
		),
		$atts,
		'convoca_menu'
	);

	if ( ! has_nav_menu( $atts['location'] ) ) {
		return '';
	}

	$html = wp_nav_menu(
		array(
			'theme_location'  => $atts['location'],
			'container'       => 'nav',
			'container_class' => $atts['class'],
			'menu_class'      => $atts['class'] . '__list',
			'depth'           => 2,
			'echo'            => false,
			'fallback_cb'     => false,
		)
	);

	return $html ? $html : '';
}
add_shortcode( 'convoca_menu', __NAMESPACE__ . '\\shortcode_menu' );

/* ── [convoca_socials] ──────────────────────────────────────────────────── */

/**
 * Redes sociales del sitio.
 *
 * Las URLs las declara la instalación con el filtro `convoca_social_links`
 * (array servicio => URL); sin URLs no se pinta nada.
 *
 * @return string HTML de los enlaces, o cadena vacía.
 */
function shortcode_socials(): string {
	// El shortcode se resuelve dentro de bloques que a su vez se renderizan:
	// sin este guard, un HTML que se contenga a sí mismo gira en CPU.
	static $busy = false;
	if ( $busy ) {
		return '';
	}

	$socials = array_filter(
		(array) apply_filters( 'convoca_social_links', array() ),
		static function ( $url ): bool {
			return '' !== trim( (string) $url );
		}
	);

	if ( empty( $socials ) ) {
		return '';
	}

	$inner = '';
	foreach ( $socials as $service => $url ) {
		$inner .= sprintf(
			'<!-- wp:social-link {"url":"%s","service":"%s"} /-->',
			esc_url_raw( (string) $url ),
			esc_attr( (string) $service )
		);
	}

	$markup = sprintf(
		'<!-- wp:social-links {"iconColor":"blanco","iconColorValue":"#ffffff"} --><ul class="wp-block-social-links has-icon-color">%s</ul><!-- /wp:social-links -->',
		$inner
	);

	$busy = true;
	try {
		$html = do_blocks( $markup );
	} finally {
		$busy = false;
	}

	return $html;
}
add_shortcode( 'convoca_socials', __NAMESPACE__ . '\\shortcode_socials' );

/* ── [convoca_cuando] / [convoca_donde] ─────────────────────────────────── */

/**
 * Fecha del evento del contenido que se está viendo.
 *
 * @return string
 */
function shortcode_when(): string {
	$id = get_the_ID();
	if ( ! $id || ! function_exists( __NAMESPACE__ . '\\event_when' ) ) {
		return '';
	}

	$texto = event_when( (int) $id );
	if ( '' === $texto ) {
		return '';
	}

	$iso = (string) event_meta( (int) $id, '_convoca_event_start_date' );

	return sprintf(
		'<span class="convoca-dato-evento convoca-dato-evento--cuando"><time datetime="%1$s">%2$s</time></span>',
		esc_attr( $iso ),
		esc_html( $texto )
	);
}
add_shortcode( 'convoca_cuando', __NAMESPACE__ . '\\shortcode_when' );

/**
 * Lugar del evento del contenido que se está viendo.
 *
 * @return string
 */
function shortcode_where(): string {
	$id = get_the_ID();
	if ( ! $id ) {
		return '';
	}

	$texto = event_where( (int) $id );

	return $texto ? '<span class="convoca-dato-evento convoca-dato-evento--donde">' . esc_html( $texto ) . '</span>' : '';
}
add_shortcode( 'convoca_donde', __NAMESPACE__ . '\\shortcode_where' );

/* ── [convoca_relacionadas] ─────────────────────────────────────────────── */

/**
 * Entradas de la MISMA categoría que la que se está viendo.
 *
 * @param array<string,string>|string $atts Atributos: number (cuántas).
 * @return string HTML de la lista, o cadena vacía.
 */
function shortcode_related( $atts ): string {
	$atts = shortcode_atts( array( 'number' => 3 ), $atts, 'convoca_relacionadas' );

	$id = get_the_ID();
	if ( ! $id ) {
		return '';
	}

	$cats = wp_get_post_categories( $id );
	$q    = new \WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => max( 1, (int) $atts['number'] ),
			'post__not_in'        => array( $id ),
			'ignore_sticky_posts' => true,
			'category__in'        => $cats ? $cats : array(),
		)
	);

	if ( ! $q->have_posts() ) {
		wp_reset_postdata();
		return '';
	}

	$salida = '<ul class="convoca-grid-actividades convoca-relacionadas__lista">';
	while ( $q->have_posts() ) {
		$q->the_post();
		$salida .= '<li class="convoca-card">';
		if ( has_post_thumbnail() ) {
			$salida .= '<a class="convoca-card__media" href="' . esc_url( get_permalink() ) . '" tabindex="-1" aria-hidden="true">'
				. get_the_post_thumbnail( null, 'medium_large', array( 'loading' => 'lazy' ) ) . '</a>';
		}
		$salida .= '<h3 class="convoca-card__titulo"><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></h3>';
		$salida .= '<span class="convoca-card__fecha">' . esc_html( get_the_date( 'j \d\e F, Y' ) ) . '</span>';
		$salida .= '</li>';
	}
	$salida .= '</ul>';
	wp_reset_postdata();

	return $salida;
}
add_shortcode( 'convoca_relacionadas', __NAMESPACE__ . '\\shortcode_related' );

/* ── [convoca_stats] ────────────────────────────────────────────────────── */

/**
 * Cifras reales del sitio (publicaciones, año en curso, antigüedad).
 *
 * Se pueden ajustar o ampliar con el filtro `convoca_site_stats`. Lo que no
 * venga bien formado se descarta aquí, así que quien lo pinte puede darlo por
 * bueno sin comprobaciones.
 *
 * @return array<string,array{value:string,label:string}>
 */
function site_stats(): array {
	$stats = array();
	$year  = (int) gmdate( 'Y' );

	$published = (int) wp_count_posts( 'post' )->publish;
	if ( $published > 0 ) {
		$stats['publicaciones'] = array(
			'value' => '+' . number_format_i18n( $published ),
			'label' => __( 'Posts', 'convoca-core' ),
		);
	}

	$this_year = new \WP_Query(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'date_query'     => array( array( 'year' => $year ) ),
		)
	);
	if ( $this_year->found_posts > 0 ) {
		$stats['este_ano'] = array(
			'value' => (string) number_format_i18n( $this_year->found_posts ),
			'label' => sprintf( /* translators: %s: year of the archive. */ __( 'Posts in %s', 'convoca-core' ), $year ),
		);
	}

	$oldest = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'order'          => 'ASC',
			'orderby'        => 'date',
		)
	);
	if ( ! empty( $oldest ) ) {
		$years         = max( 1, $year - (int) get_the_date( 'Y', $oldest[0] ) );
		$stats['anos'] = array(
			'value' => (string) number_format_i18n( $years ),
			'label' => __( 'Years of work', 'convoca-core' ),
		);
	}

	/**
	 * El sitio puede ajustar estas cifras con su filtro, así que pueden llegar
	 * con otra forma. Se ensancha el tipo a propósito.
	 *
	 * @var array<string, mixed> $stats
	 */
	$stats = apply_filters( 'convoca_site_stats', $stats );

	return array_filter(
		$stats,
		static function ( $dato ): bool {
			return is_array( $dato ) && isset( $dato['value'], $dato['label'] );
		}
	);
}

/**
 * Pinta las cifras del sitio como lista de datos.
 *
 * @return string HTML, o cadena vacía si no hay cifras.
 */
function shortcode_stats(): string {
	$salida = '';
	foreach ( array_slice( site_stats(), 0, 4, true ) as $clave => $dato ) {
		if ( empty( $dato['value'] ) ) {
			continue;
		}
		// El filtro ya garantiza 'value' y 'label' (lo que no viene bien formado se
		// descarta en la fuente), así que aquí no hay nada que comprobar.
		$salida .= sprintf(
			'<p class="convoca-dato"><span class="convoca-dato__cifra">%s</span><span class="convoca-dato__etiqueta">%s</span></p>',
			esc_html( (string) $dato['value'] ),
			esc_html( (string) $dato['label'] )
		);
	}

	return $salida;
}
add_shortcode( 'convoca_stats', __NAMESPACE__ . '\\shortcode_stats' );
