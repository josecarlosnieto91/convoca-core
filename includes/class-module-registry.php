<?php
/**
 * Convoca Core — Module Registry
 *
 * Registro y validación de módulos externos (marketplace).
 * Los módulos se registran vía el filtro `convoca_register_module`.
 *
 * @package Convoca\Core
 */

namespace Convoca\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registry for external modules (marketplace architecture).
 */
class Module_Registry {

	/**
	 * Registered modules.
	 *
	 * @var array<string,array>
	 */
	private static $modules = array();

	/**
	 * Whether registration has run.
	 *
	 * @var bool
	 */
	private static $booted = false;

	/**
	 * Init: collect registered modules and validate them.
	 */
	public static function init(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		/**
		 * Register external Convoca modules.
		 *
		 * @since 2.2.0
		 *
		 * @param array $modules Array of module definitions.
		 */
		$modules = (array) apply_filters( 'convoca_register_module', array() );

		foreach ( $modules as $slug => $definition ) {
			self::register( (string) $slug, $definition );
		}
	}

	/**
	 * Register a single module after validating its contract.
	 *
	 * @param string $slug       Module identifier (lowercase, hyphens).
	 * @param array  $definition Module definition.
	 */
	public static function register( string $slug, $definition ): void {
		$slug = sanitize_key( $slug );
		if ( '' === $slug ) {
			return;
		}

		$definition = wp_parse_args(
			$definition,
			array(
				'name'         => $slug,
				'version'      => '1.0.0',
				'core_min'     => '2.1.0',
				'features'     => array(),
				'capabilities' => array(),
				'bootstrap'    => null,
			)
		);

		self::$modules[ $slug ] = $definition;

		// Validate core compatibility.
		if ( ! self::is_core_compatible( $definition['core_min'] ) ) {
			self::log_incompatible( $slug, 'core_min', $definition['core_min'] );
			return;
		}

		// Validate required PRO features.
		foreach ( (array) $definition['features'] as $feature ) {
			if ( ! License_Manager::has_pro( $feature ) ) {
				self::log_incompatible( $slug, 'feature', $feature );
				return;
			}
		}

		// Validate capabilities (only when admin context, for non-privileged hooks).
		if ( is_admin() && ! empty( $definition['capabilities'] ) ) {
			foreach ( (array) $definition['capabilities'] as $cap ) {
				if ( ! current_user_can( $cap ) ) {
					self::log_incompatible( $slug, 'capability', $cap );
					return;
				}
			}
		}

		// Bootstrap the module.
		if ( is_callable( $definition['bootstrap'] ) ) {
			call_user_func( $definition['bootstrap'], $slug, $definition );
		}

		/**
		 * Fires after a Convoca module was successfully registered.
		 *
		 * @since 2.2.0
		 *
		 * @param string $slug       Module slug.
		 * @param array  $definition Module definition.
		 */
		do_action( 'convoca_module_registered', $slug, $definition );
	}

	/**
	 * Get registered modules.
	 *
	 * @return array<string,array>
	 */
	public static function get_modules(): array {
		return self::$modules;
	}

	/**
	 * Check if a module is registered and compatible.
	 *
	 * @param string $slug Module slug.
	 * @return bool
	 */
	public static function is_active( string $slug ): bool {
		return isset( self::$modules[ $slug ] );
	}

	/**
	 * Compare core version against module requirement.
	 *
	 * @param string $required Required core version.
	 * @return bool
	 */
	private static function is_core_compatible( string $required ): bool {
		$core_version = defined( 'CONVOCA_COMMON_VERSION' ) ? CONVOCA_COMMON_VERSION : '0.0.0';
		return version_compare( $core_version, $required, '>=' );
	}

	/**
	 * Log an incompatible module (admin notice + error log).
	 *
	 * @param string $slug  Module slug.
	 * @param string $type  Incompatibility type.
	 * @param string $value Expected value.
	 */
	private static function log_incompatible( string $slug, string $type, string $value ): void {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( "[Convoca] Module {$slug} incompatible ({$type}: {$value})" );
		if ( is_admin() ) {
			add_action(
				'admin_notices',
				function () use ( $slug, $type, $value ) {
					echo '<div class="notice notice-warning"><p>';
					echo esc_html(
						sprintf(
							/* translators: 1: module slug, 2: incompatibility type, 3: required value */
							__( 'Convoca module "%1$s" is incompatible (%2$s: %3$s) and will not run.', 'convoca-core' ),
							$slug,
							$type,
							$value
						)
					);
					echo '</p></div>';
				}
			);
		}
	}
}
