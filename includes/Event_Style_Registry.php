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
 * Event Style Registry — visual identity for activity types.
 *
 * @package Convoca\Enroll\Media
 */

namespace Convoca\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central registry for activity type visual styles.
 * Each type has: label, color, icon (emoji), text_color.
 */
class Event_Style_Registry {

	const DEFAULT_STYLE = array(
		'label'      => 'Actividad',
		'color'      => '#ff8700',
		'text_color' => '#ffffff',
		'icon'       => '📌',
		'accent'     => '#ff8700',
	);

	/**
	 * All registered styles.
	 *
	 * @return array
	 */
	public static function all(): array {
		return array(
			'naturaleza'   => array(
				'label' => __( 'Naturaleza', 'convoca-core' ), 'color' => '#4caf50', 'text_color' => '#ffffff', 'icon' => '🌿', 'accent' => '#2e7d32'
			),
			'familiar'     => array(
				'label' => __( 'Familiar', 'convoca-core' ), 'color' => '#ff9800', 'text_color' => '#ffffff', 'icon' => '👨‍👩‍👧‍👦', 'accent' => '#e65100'
			),
			'formacion'    => array(
				'label' => __( 'Formación', 'convoca-core' ), 'color' => '#2196f3', 'text_color' => '#ffffff', 'icon' => '🎓', 'accent' => '#1565c0'
			),
			'adultos'      => array(
				'label' => __( 'Adultos', 'convoca-core' ), 'color' => '#9c27b0', 'text_color' => '#ffffff', 'icon' => '🧑', 'accent' => '#6a1b9a'
			),
			'voluntariado' => array(
				'label' => __( 'Voluntariado', 'convoca-core' ), 'color' => '#009688', 'text_color' => '#ffffff', 'icon' => '🤝', 'accent' => '#00695c'
			),
			'infantil'     => array(
				'label' => __( 'Infantil', 'convoca-core' ), 'color' => '#ff5722', 'text_color' => '#ffffff', 'icon' => '🧒', 'accent' => '#bf360c'
			),
			'online'       => array(
				'label' => __( 'Online', 'convoca-core' ), 'color' => '#607d8b', 'text_color' => '#ffffff', 'icon' => '💻', 'accent' => '#37474f'
			),
			'ruta'         => array(
				'label' => __( 'Ruta interpretada', 'convoca-core' ), 'color' => '#8bc34a', 'text_color' => '#ffffff', 'icon' => '🚶', 'accent' => '#558b2f'
			),
			'taller'       => array(
				'label' => __( 'Taller', 'convoca-core' ), 'color' => '#795548', 'text_color' => '#ffffff', 'icon' => '🔧', 'accent' => '#4e342e'
			),
			'socios'       => array(
				'label' => __( 'Exclusiva socios', 'convoca-core' ), 'color' => '#e91e63', 'text_color' => '#ffffff', 'icon' => '⭐', 'accent' => '#c2185b'
			),
			'especial'     => array(
				'label' => __( 'Especial', 'convoca-core' ), 'color' => '#f44336', 'text_color' => '#ffffff', 'icon' => '🎉', 'accent' => '#d32f2f'
			),
		);
	}

	/**
	 * Get style for a type slug.
	 *
	 * @param string $type Activity type slug.
	 * @return array
	 */
	public static function get( string $type ): array {
		$all = self::all();
		return $all[ $type ] ?? self::DEFAULT_STYLE;
	}

	/**
	 * Get a color for a type.
	 *
	 * @param string $type Type slug.
	 * @return string Hex color.
	 */
	public static function color( string $type ): string {
		return self::get( $type )['color'] ?? self::DEFAULT_STYLE['color'];
	}

	/**
	 * Get icon for a type.
	 *
	 * @param string $type Type slug.
	 * @return string Emoji icon.
	 */
	public static function icon( string $type ): string {
		return self::get( $type )['icon'] ?? self::DEFAULT_STYLE['icon'];
	}
}
