<?php

/**
 * Convoca Core
 *
 * @package    Convoca\Core
 * @subpackage Includes
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
 * Enlaces de correo: resolver la página real, no escribir la ruta.
 *
 * Un enlace escrito a mano (`home_url('/mi-area/')`) es un 404 esperando: en un
 * sitio cuyo panel se llama `/panel-socio/`, el CTA principal de TODOS los
 * correos —y el enlace del pie— llevaban a una página que no existe. Aquí se
 * resuelve la página que de verdad lleva el shortcode (o el slug esperado) y, si
 * no hay ninguna, el enlace **no se imprime**: mejor sin enlace que con un 404.
 *
 * Todo es sustituible por filtro, para que un sitio apunte donde quiera sin
 * tocar el plugin.
 *
 * @package Convoca\Core
 */

namespace Convoca\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Email_Links {

	/**
	 * Primera página publicada que lleva alguno de estos shortcodes, o que usa
	 * alguno de los slugs indicados.
	 *
	 * @param array<int,string> $shortcodes Tags a buscar, en orden de preferencia.
	 * @param array<int,string> $slugs      Slugs alternativos si no hay shortcode.
	 * @return \WP_Post|null
	 */
	private static function primera_pagina( array $shortcodes, array $slugs = array() ): ?object {
		foreach ( $shortcodes as $tag ) {
			$pages = get_posts(
				array(
					'post_type'      => 'page',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'orderby'        => 'ID',
					'order'          => 'ASC',
				)
			);

			foreach ( $pages as $page ) {
				if ( has_shortcode( $page->post_content, $tag ) ) {
					return $page;
				}
			}
		}

		foreach ( $slugs as $slug ) {
			$page = get_page_by_path( $slug );
			if ( $page && 'publish' === $page->post_status ) {
				return $page;
			}
		}

		return null;
	}

	/**
	 * URL del área privada del socio: lo que alimenta `{login_url}` y el CTA
	 * «Acceder a Mi Área».
	 *
	 * @return string Cadena vacía si el sitio no tiene ninguna página de panel.
	 */
	public static function panel(): string {
		$page = self::primera_pagina(
			array( 'convoca_mi_area', 'convoca_mi_perfil' ),
			array( 'panel-socio', 'mi-area', 'area-socios', 'mi-area-socio' )
		);

		$url = $page ? (string) get_permalink( $page->ID ) : '';

		/**
		 * Filtra la URL del área privada que usan los correos.
		 *
		 * @param string $url URL resuelta ('' si no hay ninguna página).
		 */
		return (string) apply_filters( 'convoca_email_panel_url', $url );
	}

	/**
	 * Enlaces del pie de los correos: etiqueta => URL.
	 *
	 * La etiqueta es el **título real** de la página encontrada, así que el pie
	 * nunca anuncia algo que no existe; los enlaces que no resuelven no aparecen.
	 *
	 * @return array<string,string>
	 */
	public static function footer(): array {
		$links = array();

		$panel = self::primera_pagina(
			array( 'convoca_mi_area', 'convoca_mi_perfil' ),
			array( 'panel-socio', 'mi-area', 'area-socios', 'mi-area-socio' )
		);
		if ( $panel ) {
			$links[ self::etiqueta( $panel, __( 'Mi Área', 'convoca-core' ) ) ] = (string) get_permalink( $panel->ID );
		}

		$candidatos = array(
			array( 'contacto', 'contacta', 'contactanos', 'contacto-y-directorio' ),
			array( 'aviso-legal', 'politica-de-privacidad', 'privacidad', 'politica-de-cookies-ue' ),
		);

		foreach ( $candidatos as $slugs ) {
			$page = self::primera_pagina( array(), $slugs );
			if ( $page ) {
				$links[ self::etiqueta( $page, '' ) ] = (string) get_permalink( $page->ID );
			}
		}

		/**
		 * Filtra los enlaces del pie de los correos.
		 *
		 * @param array<string,string> $links Etiqueta => URL, ya resueltos.
		 */
		return (array) apply_filters( 'convoca_email_footer_links', $links );
	}

	/**
	 * Etiqueta visible de una página del pie: su título, y si no lo tiene, el
	 * texto por defecto.
	 *
	 * @param object $page         Página resuelta.
	 * @param string $por_defecto  Texto si la página no tiene título.
	 * @return string
	 */
	private static function etiqueta( object $page, string $por_defecto ): string {
		$titulo = isset( $page->post_title ) ? trim( (string) $page->post_title ) : '';

		return '' !== $titulo ? $titulo : $por_defecto;
	}
}
