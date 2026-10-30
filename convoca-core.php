<?php
/**
 * Plugin Name:       Convoca Core
 * Plugin URI:        https://getconvoca.app
 * Description:       Common functions, validation, logging, and shared infrastructure.
 * Version: 2.3.51
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Tested up to:      7.1.3
 * Author:            Jose Carlos Nieto Ramos
 * Author URI:        https://getconvoca.app
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       convoca-core
 * Domain Path:       /languages
 */

namespace Convoca\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load translations.
add_action(
	'init',
	function () {
		wp_set_script_translations( 'convoca-core-scripts', 'convoca-core', plugin_dir_path( __FILE__ ) . 'languages/' );
		load_plugin_textdomain( 'convoca-core', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
);

if ( ! defined( 'CONVOCA_COMMON_VERSION' ) ) {
	// Cache-buster de los assets comunes. Debe seguir la versión del plugin: si se
	// queda fija, la caché del servidor y la del navegador sirven JS/CSS viejos
	// indefinidamente para esa misma URL (?ver=...).
	$convoca_core_header  = get_file_data( __FILE__, array( 'Version' => 'Version' ), 'plugin' );
	$convoca_core_version = ! empty( $convoca_core_header['Version'] ) ? $convoca_core_header['Version'] : '2.2.5';
	define( 'CONVOCA_COMMON_VERSION', $convoca_core_version );
}
if ( ! defined( 'CONVOCA_COMMON_DB_VERSION' ) ) {
	define( 'CONVOCA_COMMON_DB_VERSION', '1.1.0' );
}
if ( ! defined( 'CONVOCA_COMMON_DIR' ) ) {
	define( 'CONVOCA_COMMON_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'CONVOCA_COMMON_URL' ) ) {
	define( 'CONVOCA_COMMON_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'CONVOCA_IMAGES_URL' ) ) {
	define( 'CONVOCA_IMAGES_URL', CONVOCA_COMMON_URL . 'assets/images/' );
}

/* ── Composer autoload (Dompdf, etc.) ──────────── */
$composer_autoload = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $composer_autoload ) ) {
	require_once $composer_autoload;
}

/*
 * Endurecimiento de la API REST: se carga explícitamente, y antes de cualquier uso.
 *
 * El cargador de clases que hay instalado en los sitios es un mapa autoritativo y no conoce las
 * clases nuevas; sin este require, la clase no se encuentra y la web entera responde 500
 * (ocurrió el 13/10/2026 al desplegar este cambio). Va aquí arriba, no junto al enganche, para que
 * valga aunque más adelante alguien llame a la clase al cargar el fichero en vez de en un enganche.
 */

/**
 * Carga un módulo del núcleo sin tumbar el sitio si falta el fichero.
 *
 * Un `rsync` a medias dejó un sitio del ecosistema con 8 fatales entre las 00:18 y las 00:26 del
 * 14/10/2026: el código llamaba a `Rest_Hardening` y el fichero aún no había llegado. Un sitio de una
 * asociación no puede caerse entero porque un despliegue vaya por la mitad (core#32).
 *
 * @param string $relativo Ruta dentro de `includes/`.
 * @return bool
 */
function convoca_core_require( string $relativo ): bool {
	$ruta = __DIR__ . '/includes/' . ltrim( $relativo, '/' );

	if ( file_exists( $ruta ) ) {
		require_once $ruta;
		return true;
	}

	// Se registra con la versión que se está ejecutando: distingue «despliegue a medias» de «fichero
	// borrado» en un minuto. Y se usa error_log porque el propio Log puede ser lo que falte.
	$mensaje = sprintf(
		'[convoca-core %s] Falta includes/%s: el sitio sigue sirviendo sin ese módulo. Parece un despliegue a medias.',
		defined( 'CONVOCA_COMMON_VERSION' ) ? CONVOCA_COMMON_VERSION : '?',
		$relativo
	);

	try {
		Logger::warning( $mensaje, 'Core/Boot' );
	} catch ( \Throwable $e ) {
		// Si el propio Logger es lo que falta, el aviso queda al menos en el registro de PHP.
		error_log( $mensaje ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- el registro propio puede ser lo que falte.
	}

	return false;
}

/**
 * Arranca un módulo solo si su clase está cargada.
 *
 * @param string $clase Nombre completo de la clase.
 * @return bool
 */
function convoca_core_init( string $clase ): bool {
	$clase = ltrim( $clase, '\\' );

	if ( class_exists( $clase ) && method_exists( $clase, 'init' ) ) {
		$clase::init();
		return true;
	}

	$mensaje = sprintf(
		'[convoca-core %s] Módulo ausente en el arranque: %s',
		defined( 'CONVOCA_COMMON_VERSION' ) ? CONVOCA_COMMON_VERSION : '?',
		$clase
	);

	try {
		Logger::warning( $mensaje, 'Core/Boot' );
	} catch ( \Throwable $e ) {
		// Si el propio Logger es lo que falta, el aviso queda al menos en el registro de PHP.
		error_log( $mensaje ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- el registro propio puede ser lo que falte.
	}

	return false;
}

convoca_core_require( 'Rest_Hardening.php' );

/**
 * Basic Autoloader fallback.
 * Supports Convoca\Core\ClassName mapped to includes/ClassName.php
 * (PSR-4 naming). Composer autoloader handles primary resolution.
 */
// PSR-4 autoloading handled by Composer (vendor/autoload.php)

/** ── Activation / Deactivation ────────────────────────────── */

register_activation_hook(
	__FILE__,
	function (): void {
		Installer::db_init();
		add_option( 'convoca_common_db_version', CONVOCA_COMMON_DB_VERSION, '', false );

		// Ensure new granular capabilities are assigned to admin role.
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			if ( ! $admin_role->has_cap( 'manage_convoca_templates' ) ) {
				$admin_role->add_cap( 'manage_convoca_templates' );
			}
			if ( ! $admin_role->has_cap( 'manage_convoca_logs' ) ) {
				$admin_role->add_cap( 'manage_convoca_logs' );
			}
		}
	}
);

register_deactivation_hook(
	__FILE__,
	function (): void {
		wp_clear_scheduled_hook( 'convoca_log_cleanup' );
		wp_clear_scheduled_hook( 'convoca_log_purge' );
		wp_clear_scheduled_hook( 'convoca_continue_access_codes' );
		wp_clear_scheduled_hook( 'convoca_security_digest' );
	}
);

// Cron: log cleanup (90-day retention).
add_action( 'convoca_log_cleanup', array( '\Convoca\Core\Installer', 'run_cleanup' ) );

// Cron: log purge (60-day retention).
add_action( 'convoca_log_purge', array( '\Convoca\Core\Installer', 'run_purge' ) );

// Cron: continue access code generation if interrupted during activation.
add_action(
	'convoca_continue_access_codes',
	function () {
		\Convoca\Core\Installer::continue_access_codes();
	}
);

// Security Monitor: observabilidad de eventos críticos (digest diario).
add_action(
	'plugins_loaded',
	function (): void {
		convoca_core_init( Security_Monitor::class );
	},
	20
);

// Endurecimiento de la API REST: sin sesión no se enumeran usuarios (ni por ruta ni por ?rest_route=).
add_action(
	'plugins_loaded',
	function (): void {
		convoca_core_init( Rest_Hardening::class );
		convoca_core_init( Postura_HTTP::class );
	},
	21
);

// Initialize basic hooks if needed.
add_action(
	'plugins_loaded',
	function () {
		// Initialize Webhook Manager (registers hooks for event dispatching).
		new Webhook_Manager();

		// Initialize Upgrade Manager (checks for DB version upgrades).
		new Common_Upgrade_Manager();

		// Ensure granular capabilities exist on admin roles (handles upgrades without re-activation).
		Capabilities::ensure();

		if ( is_admin() ) {
			$admin_role = get_role( 'administrator' );
			if ( $admin_role ) {
				if ( ! $admin_role->has_cap( 'manage_convoca_templates' ) ) {
					$admin_role->add_cap( 'manage_convoca_templates' );
				}
				if ( ! $admin_role->has_cap( 'manage_convoca_logs' ) ) {
					$admin_role->add_cap( 'manage_convoca_logs' );
				}
			}
			convoca_core_init( Admin_Templates::class );
			new Admin_Setup_Wizard();
			convoca_core_init( Admin_Entrada::class );
			new Admin_Backup();
			add_filter( 'screen_options_show_screen', 'Convoca\Core\convoca_hide_screen_options', 10, 2 );
			add_filter( 'admin_footer_text', 'Convoca\Core\convoca_admin_footer', 999 );
			add_filter( 'update_footer', 'Convoca\Core\convoca_admin_footer_version', 999 );
			add_action( 'admin_head', 'Convoca\Core\convoca_remove_help_tab' );
			add_action( 'admin_bar_menu', 'Convoca\Core\convoca_add_wizard_link', 999 );
		}

		// Initialize common blocks.
		convoca_core_init( Blocks_Common::class );

		// Initialize notifications.
		convoca_core_init( Notifications::class );

		// Initialize memory report generator.
		convoca_core_init( Memory_Report::class );

		// Initialize license manager.
		convoca_core_init( License_Manager::class );

		// Initialize module registry (marketplace).
		convoca_core_init( Module_Registry::class );

		// Initialize performance probe (?convoca_probe=1).
		convoca_core_init( Performance_Probe::class );
	}
);

/* ── Global Convoca menu → extracted to includes/admin-global-menu.php ── */
convoca_core_require( 'admin-global-menu.php' );

/* ── Module Registry (marketplace) ── */
convoca_core_require( 'class-module-registry.php' );

/* ── Admin Appearance → extracted to includes/admin-appearance.php ── */
convoca_core_require( 'admin-appearance.php' );
convoca_core_require( 'Admin_Entrada.php' );

/* ── Ajuste de desinstalación: qué hacer con los datos (compartido por los uninstall.php) ── */
convoca_core_require( 'Uninstall_Settings.php' );

/* ── Datos de evento de una entrada (metabox, guardado y acceso) ── */
convoca_core_require( 'event-meta.php' );

/* ── Shortcodes de front comunes a cualquier instalación (menú, redes, relacionadas, cifras) ── */
convoca_core_require( 'front-shortcodes.php' );


/* ── REST endpoint: /convoca/v1/admin/metrics ── */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'convoca/v1',
			'/admin/metrics',
			array(
				'methods'             => 'GET',
				'callback'            => function () {
					return new \WP_REST_Response(
						array(
							'timestamp'   => current_time( 'mysql' ),
							'php_version' => PHP_VERSION,
							'wp_version'  => get_bloginfo( 'version' ),
						),
						200
					);
				},
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}
);

/**
 * Build metrics data for REST response.
 *
 * @return array<string, mixed>
 */
function assoc_build_metrics(): array {
	$metrics = array(
		'timestamp'   => current_time( 'mysql' ),
		'php_version' => PHP_VERSION,
		'wp_version'  => get_bloginfo( 'version' ),
	);
	return $metrics;
}

function convoca_rest_metrics(): \WP_REST_Response {
	$cache_key = 'convoca_rest_metrics';
	$data      = get_transient( $cache_key );

	if ( ! $data ) {
		$data = assoc_build_metrics();
		set_transient( $cache_key, $data, 30 );
	}

	return new \WP_REST_Response( $data, 200 );
}
