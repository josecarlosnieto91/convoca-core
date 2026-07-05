<?php declare(strict_types=1);

/**
 * Convoca Core Settings Helper
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

namespace Convoca\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 *
 * Centralizes option retrieval across the ecosystem to handle
 * nomenclature inconsistencies and aliases safely.
 */
class Settings_Helper {

	/**
	 * Map of legacy option names to their standardized equivalents.
	 * Format: 'legacy_key' => 'standard_key'
	 */
	private const ALIASES = array(
		'convoca_shifts_hora_apertura'     => 'convoca_centro_hora_apertura',
		'convoca_shifts_hora_cierre'       => 'convoca_centro_hora_cierre',
		'convoca_shifts_calendar_page_url' => 'convoca_centro_calendar_page_url',
		'convoca_gateway_settings' => 'convoca_gateway_settings',
		'convoca_enroll_settings'  => 'convoca_enroll_settings',
	);

	/**
	 * Gets a setting, resolving aliases if necessary.
	 */
	public static function get( string $key, $default = false ) {
		$actual_key = self::ALIASES[ $key ] ?? $key;
		return get_option( $actual_key, $default );
	}

	/**
	 * Updates a setting, resolving aliases if necessary.
	 */
	public static function update( string $key, $value ): bool {
		$actual_key = self::ALIASES[ $key ] ?? $key;
		return update_option( $actual_key, $value );
	}

	/**
	 * Deletes a setting, resolving aliases if necessary.
	 */
	public static function delete( string $key ): bool {
		$actual_key = self::ALIASES[ $key ] ?? $key;
		return delete_option( $actual_key );
	}

	/**
	 * Adds a setting, resolving aliases if necessary.
	 */
	public static function add( string $key, $value, $deprecated = '', $autoload = 'yes' ): bool {
		$actual_key = self::ALIASES[ $key ] ?? $key;
		return add_option( $actual_key, $value, $deprecated, $autoload );
	}
}
