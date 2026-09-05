<?php
/**
 * Plugin Name:       Convoca Core
 * Plugin URI:        https://getconvoca.app
 * Description:       Common functions, validation, logging, and shared infrastructure.
 * Version:           2.2.4
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Tested up to:      7.0
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
	define( 'CONVOCA_COMMON_VERSION', '2.1.4' );
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
		\Convoca\Core\Security_Monitor::init();
	},
	20
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
			Admin_Templates::init();
			new Admin_Setup_Wizard();
			new Admin_Backup();
			add_filter( 'screen_options_show_screen', 'Convoca\Core\convoca_hide_screen_options', 10, 2 );
			add_filter( 'admin_footer_text', 'Convoca\Core\convoca_admin_footer', 999 );
			add_filter( 'update_footer', 'Convoca\Core\convoca_admin_footer_version', 999 );
			add_action( 'admin_head', 'Convoca\Core\convoca_remove_help_tab' );
			add_action( 'admin_bar_menu', 'Convoca\Core\convoca_add_wizard_link', 999 );
		}

		// Initialize common blocks.
		Blocks_Common::init();

		// Initialize notifications.
		Notifications::init();

		// Initialize memory report generator.
		Memory_Report::init();

		// Initialize license manager.
		License_Manager::init();

		// Initialize module registry (marketplace).
		Module_Registry::init();

		// Initialize performance probe (?convoca_probe=1).
		Performance_Probe::init();
	}
);

/* ── Global Convoca menu → extracted to includes/admin-global-menu.php ── */
require_once __DIR__ . '/includes/admin-global-menu.php';

/* ── Module Registry (marketplace) ── */
require_once __DIR__ . '/includes/class-module-registry.php';

/* ── Admin Appearance → extracted to includes/admin-appearance.php ── */
require_once __DIR__ . '/includes/admin-appearance.php';


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

/** Build metrics data for REST response. */
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
