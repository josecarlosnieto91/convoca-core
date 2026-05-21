<?php

declare(strict_types=1);

namespace Convoca\Core;

/**
 * Class Features
 *
 * Centralizes feature detection across the Biodevas ecosystem to avoid
 * scattered class_exists or function_exists checks that make refactoring hard.
 */
class Features {

	/**
	 * Comprueba si el plugin Centro Social Turnos está activo.
	 */
	public static function is_cst_active(): bool {
		return function_exists( 'cst_log_activity' ) || defined( 'CST_PLUGIN_VERSION' );
	}

	/**
	 * Comprueba si el plugin Biodevas Gateway está activo.
	 */
	public static function is_gateway_active(): bool {
		return class_exists( '\Convoca\Gateway\Payment_Handler' ) || defined( 'BDG_VERSION' );
	}

	/**
	 * Comprueba si el plugin Biodevas Members está activo.
	 */
	public static function is_members_active(): bool {
		return class_exists( '\Convoca\Members\Member_Auth' ) || defined( 'BDV_MEMBERS_VERSION' );
	}

	/**
	 * Comprueba si el plugin Biodevas Enroll (Inscripciones) está activo.
	 */
	public static function is_enroll_active(): bool {
		return defined( 'BDE_VERSION' );
	}

	/**
	 * Comprueba si el theme activo es biodevas-theme.
	 */
	public static function is_biodevas_theme_active(): bool {
		$theme = wp_get_theme();
		return $theme->get_template() === 'biodevas-theme';
	}

	/**
	 * Devuelve información de dependencias faltantes para un contexto dado.
	 * Útil para mostrar avisos en el admin de WP.
	 */
	public static function get_missing_dependencies( array $required_features ): array {
		$missing = array();

		foreach ( $required_features as $feature => $name ) {
			$method = "is_{$feature}_active";
			if ( method_exists( self::class, $method ) && ! self::$method() ) {
				$missing[] = $name;
			}
		}

		return $missing;
	}
}
