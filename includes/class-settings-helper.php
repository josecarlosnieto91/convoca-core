<?php

declare(strict_types=1);

namespace Convoca\Core;

/**
 * Class Settings_Helper
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
		'cst_hora_apertura'     => 'bdv_centro_hora_apertura',
		'cst_hora_cierre'       => 'bdv_centro_hora_cierre',
		'cst_calendar_page_url' => 'bdv_centro_calendar_page_url',
		'bdg_settings'          => 'bdv_gateway_settings',
		'bde_settings'          => 'bdv_enroll_settings',
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
