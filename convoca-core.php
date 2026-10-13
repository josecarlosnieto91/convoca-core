<?php
/*
 * Endurecimiento de la API REST: se carga explícitamente.
 *
 * El cargador de clases de los sitios está generado como mapa autoritativo y no conoce las
 * clases nuevas; sin este require, la clase no se encuentra y la web entera responde 500
 * (ocurrió el 13/10/2026 al desplegar este cambio).
 */
require_once __DIR__ . '/includes/Rest_Hardening.php';

/* ── Global Convoca menu → extracted to includes/admin-global-menu.php ── */
require_once __DIR__ . '/includes/admin-global-menu.php';

/* ── Module Registry (marketplace) ── */
require_once __DIR__ . '/includes/class-module-registry.php';

/* ── Admin Appearance → extracted to includes/admin-appearance.php ── */
require_once __DIR__ . '/includes/admin-appearance.php';

/* ── Ajuste de desinstalación: qué hacer con los datos (compartido por los uninstall.php) ── */
require_once __DIR__ . '/includes/Uninstall_Settings.php';

/* ── Datos de evento de una entrada (metabox, guardado y acceso) ── */
require_once __DIR__ . '/includes/event-meta.php';

/* ── Shortcodes de front comunes a cualquier instalación (menú, redes, relacionadas, cifras) ── */
require_once __DIR__ . '/includes/front-shortcodes.php';


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
