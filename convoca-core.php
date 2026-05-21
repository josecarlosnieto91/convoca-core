<?php
/**
 * Plugin Name: Convoca Core
 * Plugin URI: https://example.com.
 * Description: Common functions, validation, and logging.
 * Author: Jose Carlos Nieto Ramos
 * Author URI: https://example.com.
  * Version: 2.1.3
 */


namespace Convoca\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'BDV_COMMON_VERSION' ) ) {
	define( 'BDV_COMMON_VERSION', '2.1.3' );
}
if ( ! defined( 'BDV_COMMON_DB_VERSION' ) ) {
	define( 'BDV_COMMON_DB_VERSION', '1.1.0' );
}
if ( ! defined( 'BDV_COMMON_DIR' ) ) {
	define( 'BDV_COMMON_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'BDV_COMMON_URL' ) ) {
	define( 'BDV_COMMON_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'CONVOCA_IMAGES_URL' ) ) {
	define( 'CONVOCA_IMAGES_URL', BDV_COMMON_URL . 'assets/images/' );
}

/* ── Composer autoload (Dompdf, etc.) ──────────── */
$composer_autoload = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $composer_autoload ) ) {
	require_once $composer_autoload;
}

/**
 * Basic Autoloader.
 * Maps Convoca\Core\Class_Name to includes/class-class-name.php.
 */
spl_autoload_register(
	function ( string $class ): void {
		$prefix = 'Convoca\\Core\\';
		if ( ! str_starts_with( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$relative = strtolower( str_replace( array( '\\', '_' ), array( '/', '-' ), $relative ) );

		$file = BDV_COMMON_DIR . 'includes/class-' . $relative . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		} else {
			// Also check admin directory for admin classes.
			$admin_file = BDV_COMMON_DIR . 'admin/class-' . $relative . '.php';
			if ( file_exists( $admin_file ) ) {
				require_once $admin_file;
			}
		}
	}
);

/** ── Activation / Deactivation ────────────────────────────── */

register_activation_hook(
	__FILE__,
	function (): void {
		Installer::db_init();
		add_option( 'bdv_common_db_version', BDV_COMMON_DB_VERSION, '', false );

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
		wp_clear_scheduled_hook( 'bdv_log_cleanup' );
		wp_clear_scheduled_hook( 'bdv_log_purge' );
		wp_clear_scheduled_hook( 'bdv_continue_access_codes' );
	}
);

// Cron: log cleanup (90-day retention).
add_action( 'bdv_log_cleanup', array( '\Convoca\Core\Installer', 'run_cleanup' ) );

// Cron: log purge (60-day retention).
add_action( 'bdv_log_purge', array( '\Convoca\Core\Installer', 'run_purge' ) );

// Cron: continue access code generation if interrupted during activation.
add_action(
	'bdv_continue_access_codes',
	function () {
		\Convoca\Core\Installer::continue_access_codes();
	}
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
	}
);

/* ── Global Biodevas menu ────────────────────── */
add_action( 'admin_menu', 'Convoca\Core\convoca_register_global_menu' );
function convoca_register_global_menu(): void {
	add_menu_page(
		'Biodevas',
		'Biodevas',
		'manage_options',
		'convoca-core',
		'Convoca\Core\convoca_health_page',
		'dashicons-admin-site-alt3',
		3
	);

	add_submenu_page(
		'convoca-core',
		__( 'Salud del Sistema', 'convoca-core' ),
		__( 'Salud del Sistema', 'convoca-core' ),
		'manage_options',
		'convoca-core',
		'Convoca\Core\convoca_health_page'
	);

	// El submenu 'bdv-setup-wizard' se registra desde Admin_Setup_Wizard.
	add_submenu_page(
		'convoca-core',
		__( 'Panel de Control', 'convoca-core' ),
		__( 'Panel de Control', 'convoca-core' ),
		'manage_options',
		'bdv-dashboard',
		'Convoca\Core\convoca_dashboard_page'
	);

	add_submenu_page(
		'convoca-core',
		__( 'Registros del Sistema', 'convoca-core' ),
		__( 'Registros', 'convoca-core' ),
		'common_view_logs',
		'bdv-logs-central',
		'Convoca\Core\convoca_logs_page'
	);
}


/**
 * Consolidated System Health page.
 */
function convoca_health_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'No tienes permisos.', 'convoca-core' ) );
	}

	$force = isset( $_GET['force'] ) && $_GET['force'] === '1';

	echo '<div class="wrap">';
	echo '<h1>🔍 ' . esc_html__( 'Salud del Sistema Biodevas', 'convoca-core' ) . '</h1>';
	echo '<p>' . esc_html__( 'Diagnóstico consolidado de todos los plugins del ecosistema.', 'convoca-core' ) . '</p>';
	echo '<p><a href="' . esc_url( add_query_arg( 'force', '1' ) ) . '" class="convoca-btn convoca-btn-outline">🔄 ' . esc_html__( 'Forzar comprobación', 'convoca-core' ) . '</a></p>';
	echo '<hr>';

	$all_checks   = array();
	$plugin_names = array();

	// ── Members ──
	if ( class_exists( '\\Convoca\\Members\\Admin_Settings' ) ) {
		try {
			$ref = new \ReflectionMethod( '\\Convoca\\Members\\Admin_Settings', 'get_system_checks' );
			$ref->setAccessible( true );
			$settings = new \Convoca\Members\Admin_Settings();
			$checks   = $ref->invoke( $settings, $force );
			if ( is_array( $checks ) ) {
				$all_checks              = array_merge( $all_checks, $checks );
				$plugin_names['members'] = __( 'Biodevas Members', 'convoca-core' );
			}
		} catch ( \Exception $e ) {
			$all_checks[] = array(
				'title'   => 'Biodevas Members',
				'status'  => 'error',
				'message' => $e->getMessage(),
			);
		}
	} else {
		$all_checks[] = array(
			'title'   => 'Biodevas Members',
			'status'  => 'warning',
			'message' => __( 'Plugin no activo.', 'convoca-core' ),
		);
	}

	// ── Enroll ──
	if ( class_exists( '\\Convoca\\Enroll\\Admin_Settings' ) ) {
		try {
			$ref = new \ReflectionMethod( '\\Convoca\\Enroll\\Admin_Settings', 'get_system_checks' );
			$ref->setAccessible( true );
			$checks = $ref->invoke( null, $force );
			if ( is_array( $checks ) ) {
				$all_checks             = array_merge( $all_checks, $checks );
				$plugin_names['enroll'] = __( 'Biodevas Enroll', 'convoca-core' );
			}
		} catch ( \Exception $e ) {
			$all_checks[] = array(
				'title'   => 'Biodevas Enroll',
				'status'  => 'error',
				'message' => $e->getMessage(),
			);
		}
	} else {
		$all_checks[] = array(
			'title'   => 'Biodevas Enroll',
			'status'  => 'warning',
			'message' => __( 'Plugin no activo.', 'convoca-core' ),
		);
	}

	// ── Gateway ──
	if ( class_exists( '\\Convoca\\Gateway\\Diagnostic' ) ) {
		$results = \Convoca\Gateway\Diagnostic::run_all( $force );
		foreach ( $results as $r ) {
			$all_checks[] = array(
				'title'   => ( $plugin_names['gateway'] ?? 'Gateway' ) . ': ' . $r['title'],
				'status'  => $r['severity'] === 'ok' ? 'ok' : $r['severity'],
				'message' => $r['message'],
				'fix'     => $r['fix'] ?? '',
			);
		}
		$plugin_names['gateway'] = __( 'Biodevas Gateway', 'convoca-core' );
	} else {
		$all_checks[] = array(
			'title'   => 'Biodevas Gateway',
			'status'  => 'warning',
			'message' => __( 'Plugin no activo.', 'convoca-core' ),
		);
	}

	// ── Turnos ──
	if ( function_exists( 'cst_get_system_checks' ) ) {
		$checks = cst_get_system_checks( $force );
		if ( is_array( $checks ) ) {
			foreach ( $checks as $c ) {
				$all_checks[] = array(
					'title'   => 'Turnos: ' . $c['title'],
					'status'  => $c['status'],
					'message' => $c['message'],
					'fix'     => $c['fix'] ?? '',
				);
			}
			$plugin_names['turnos'] = __( 'Centro Social Turnos', 'convoca-core' );
		}
	} else {
		$all_checks[] = array(
			'title'   => 'Centro Social Turnos',
			'status'  => 'warning',
			'message' => __( 'Plugin no activo o sin función de diagnóstico.', 'convoca-core' ),
		);
	}

	// ── Common self-check ──
	$all_checks[] = array(
		'title'   => 'Biodevas Common — Versión',
		'status'  => 'ok',
		'message' => 'v' . BDV_COMMON_VERSION,
	);
	global $wpdb;
	$tables = array( 'convoca_logs', 'convoca_locks', 'convoca_webhook_retries' );
	foreach ( $tables as $t ) {
		$exists       = $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}{$t}'" ) === $wpdb->prefix . $t;
		$all_checks[] = array(
			'title'   => sprintf( __( 'Tabla %s', 'convoca-core' ), $t ),
			'status'  => $exists ? 'ok' : 'error',
			'message' => $exists ? __( 'OK', 'convoca-core' ) : __( 'No encontrada. Desactiva y reactiva Biodevas Common.', 'convoca-core' ),
		);
	}

	\Convoca\Core\Utils::render_diagnostic_panel( $all_checks, __( 'Diagnóstico completo del ecosistema', 'convoca-core' ) );

	echo '</div>';
}

// Notifications list page.
add_action( 'admin_menu', 'Convoca\Core\convoca_notifications_menu' );
function convoca_notifications_menu(): void {
	add_submenu_page(
		null,
		__( 'Notificaciones', 'convoca-core' ),
		__( 'Notificaciones', 'convoca-core' ),
		'manage_options',
		'bdv-notificaciones',
		'Convoca\Core\convoca_notifications_page'
	);
}

function convoca_notifications_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Access denied.' );
	}
	$all      = get_user_meta( get_current_user_id(), Notifications::META_KEY, true ) ?: array();
	$paged    = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
	$per_page = 20;
	$total    = count( $all );
	$items    = array_slice( $all, ( $paged - 1 ) * $per_page, $per_page );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Notificaciones', 'convoca-core' ); ?></h1>
		<p><?php printf( esc_html__( 'Total: %d notificaciones', 'convoca-core' ), $total ); ?></p>
		<table class="wp-list-table widefat fixed striped">
			<thead><tr><th><?php esc_html_e( 'Tipo', 'convoca-core' ); ?></th><th><?php esc_html_e( 'Mensaje', 'convoca-core' ); ?></th><th><?php esc_html_e( 'Fecha', 'convoca-core' ); ?></th><th><?php esc_html_e( 'Estado', 'convoca-core' ); ?></th></tr></thead>
			<tbody>
				<?php if ( empty( $items ) ) : ?>
				<tr><td colspan="4"><?php esc_html_e( 'No hay notificaciones.', 'convoca-core' ); ?></td></tr>
					<?php
				else :
					foreach ( $items as $n ) :
						$icon = match ( $n['type'] ?? 'info' ) {
							'success' => '✅', 'warning' => '⚠️', 'error' => '❌', default => 'ℹ️' };
						?>
				<tr>
					<td><?php echo $icon; ?></td>
					<td><a href="<?php echo esc_url( $n['url'] ); ?>"><?php echo esc_html( $n['title'] ); ?></a></td>
					<td><?php echo esc_html( $n['time'] ); ?></td>
					<td><?php echo empty( $n['read'] ) ? '<span style="color:#d63638;font-weight:bold;">' . __( 'No leída', 'convoca-core' ) . '</span>' : '<span style="color:#46b450;">' . __( 'Leída', 'convoca-core' ) . '</span>'; ?></td>
				</tr>
									<?php
				endforeach;
endif;
				?>
			</tbody>
		</table>
		<?php if ( $total > $per_page ) : ?>
		<div class="tablenav"><div class="tablenav-pages">
			<?php
			echo paginate_links(
				array(
					'base'    => add_query_arg( 'paged', '%#%' ),
					'format'  => '',
					'total'   => ceil( $total / $per_page ),
					'current' => $paged,
				)
			);
			?>
		</div></div>
		<?php endif; ?>
	</div>
	<?php
}

// Enqueue common assets (Public).
function convoca_common_enqueue_assets(): void {
	wp_enqueue_style(
		'convoca-core',
		BDV_COMMON_URL . 'assets/css/convoca-common.css',
		array(),
		BDV_COMMON_VERSION
	);

	wp_enqueue_script(
		'convoca-common-js',
		BDV_COMMON_URL . 'assets/js/convoca-common.js',
		array(),
		BDV_COMMON_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'Convoca\\Core\\convoca_common_enqueue_assets' );

// Enqueue common assets (Admin).
function convoca_common_enqueue_admin_assets(): void {
	// Also load the CSS on admin.
	convoca_common_enqueue_assets();

	wp_enqueue_script(
		'convoca-common-admin-js',
		BDV_COMMON_URL . 'assets/js/convoca-common-admin.js',
		array(),
		BDV_COMMON_VERSION,
		true
	);
}
add_action( 'admin_enqueue_scripts', 'Convoca\\Core\\convoca_common_enqueue_admin_assets' );

/* ── Admin Appearance (Prompts 19, 20) ────────────────── */

/**
 * Hide Screen Options tab on Biodevas custom admin pages.
 */
function convoca_hide_screen_options( bool $show_screen, \WP_Screen $screen ): bool {
	$convoca_slugs = array( 'bdv-', 'bde-', 'bdg-', 'cst-' );
	foreach ( $convoca_slugs as $prefix ) {
		if ( str_contains( $screen->id, $prefix ) || str_contains( $screen->base ?? '', $prefix ) ) {
			return false;
		}
	}
	return $show_screen;
}

/**
 * Custom admin footer text.
 */
function convoca_admin_footer( string $text ): string {
	return sprintf(
		'© %d <a href="%s" target="_blank">Biodevas</a> — %s',
		wp_date( 'Y' ),
		'https://getconvoca.app', 
		.
		__( 'Plataforma de gestión de la asociación.', 'convoca-core' )
	);
}

/**
 * Replace WP version in footer with Biodevas version.
 */
function convoca_admin_footer_version( string $version ): string {
	return 'Biodevas v' . BDV_COMMON_VERSION;
}

/**
 * Remove the Help tab on Biodevas admin pages.
 */
function convoca_remove_help_tab(): void {
	$screen = get_current_screen();
	if ( ! $screen ) {
		return;
	}
	$convoca_slugs = array( 'bdv-', 'bde-', 'bdg-', 'cst-' );
	foreach ( $convoca_slugs as $prefix ) {
		if ( str_contains( $screen->id, $prefix ) || str_contains( $screen->base ?? '', $prefix ) ) {
			$screen->remove_help_tabs();
			return;
		}
	}
}

/**
 * Remove the submitdiv metabox from all managed CPTs.
 */
function convoca_remove_submitdiv(): void {
	$cpts = array( 'miembro', 'actividad', 'inscripcion', 'pago', 'centro_turno', 'bdv_evaluacion', 'proyecto', 'registro_hora', 'bdv_documento' );
	foreach ( $cpts as $cpt ) {
		remove_meta_box( 'submitdiv', $cpt, 'side' );
	}
}
add_action( 'admin_head', 'Convoca\\Core\\convoca_remove_submitdiv' );

/**
 * Customize the "New" admin bar menu to point to custom editors.
 */
add_action( 'admin_bar_menu', 'Convoca\\Core\\convoca_customize_new_menu', 999 );

function convoca_customize_new_menu( \WP_Admin_Bar $wp_admin_bar ): void {
	$custom_links = array(
		'new-miembro'       => admin_url( 'admin.php?page=bdv-member-editor' ),
		'new-actividad'     => admin_url( 'admin.php?page=bde-actividad-editor' ),
		'new-proyecto'      => admin_url( 'admin.php?page=bdv-proyecto-editor' ),
		'new-registro_hora' => admin_url( 'admin.php?page=bdv-horas-editor' ),
		'new-centro_turno'  => admin_url( 'edit.php?post_type=centro_turno&page=cst_turno_rapido' ),
	);

	$remove_nodes = array( 'new-inscripcion', 'new-pago', 'new-bdv_evaluacion', 'new-bdv_documento' );

	foreach ( $custom_links as $node_id => $url ) {
		$node = $wp_admin_bar->get_node( $node_id );
		if ( $node ) {
			$node->href = $url;
			$wp_admin_bar->add_node( $node );
		}
	}

	foreach ( $remove_nodes as $node_id ) {
		$wp_admin_bar->remove_node( $node_id );
	}
}

/**
 * Add wizard link to admin bar.
 */
function convoca_add_wizard_link( \WP_Admin_Bar $wp_admin_bar ): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$wp_admin_bar->add_node(
		array(
			'id'     => 'bdv-wizard',
			'title'  => '🔧 ' . __( 'Asistente Biodevas', 'convoca-core' ),
			'href'   => admin_url( 'admin.php?page=bdv-setup-wizard' ),
			'parent' => 'top-secondary',
		)
	);
}

/**
 * Generate and stream a PDF from a list table.
 *
 * @param string $title   Title of the report.
 * @param array  $headers Column headers.
 * @param array  $rows    Data rows (arrays of strings).
 * @param string $filename Output filename (without .pdf).
 */
function convoca_export_pdf( string $title, array $headers, array $rows, string $filename ): void {
	if ( ! class_exists( 'Dompdf\Dompdf' ) ) {
		wp_die( __( 'La librería Dompdf no está instalada. Contacta con el administrador.', 'convoca-core' ) );
	}

	$html  = '<html><head><meta charset="UTF-8"><style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10pt; color: #333; }
        h1 { font-size: 18pt; color: #320028; margin-bottom: 5px; }
        .subtitle { color: #666; font-size: 9pt; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #320028; color: #fff; padding: 8px 10px; text-align: left; font-size: 9pt; }
        td { padding: 6px 10px; border-bottom: 1px solid #e0e0e0; font-size: 9pt; }
        tr:nth-child(even) td { background: #f8f8f8; }
        .footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 8pt; color: #999; border-top: 1px solid #e0e0e0; padding-top: 5px; }
    </style></head><body>';
	$html .= '<h1>' . esc_html( $title ) . '</h1>';
	$html .= '<div class="subtitle">' . esc_html__( 'Generado el', 'convoca-core' ) . ' ' . wp_date( 'd/m/Y H:i' ) . '</div>';
	$html .= '<table><thead><tr>';
	foreach ( $headers as $h ) {
		$html .= '<th>' . esc_html( $h ) . '</th>';
	}
	$html .= '</tr></thead><tbody>';
	foreach ( $rows as $row ) {
		$html .= '<tr>';
		foreach ( $row as $cell ) {
			$html .= '<td>' . esc_html( $cell ) . '</td>';
		}
		$html .= '</tr>';
	}
	$html .= '</tbody></table>';
	$html .= '<div class="footer">' . get_bloginfo( 'name' ) . ' — Biodevas — ' . wp_date( 'Y' ) . '</div>';
	$html .= '</body></html>';

	$dompdf_options = new \Dompdf\Options();
	$dompdf_options->set( 'defaultFont', 'Helvetica' );
	$dompdf_options->set( 'isRemoteEnabled', false );
	$dompdf_options->set( 'isPhpEnabled', false );
	$dompdf_options->set( 'isJavascriptEnabled', false );

	try {
		$dompdf = new \Dompdf\Dompdf( $dompdf_options );
		$dompdf->loadHtml( $html );
		$dompdf->setPaper( 'A4', 'landscape' );
		$dompdf->render();
	} catch ( \Throwable $e ) {
		\Convoca\Core\Logger::error( 'Error generando PDF export: ' . $e->getMessage(), 'System' );
		wp_die( __( 'Error al generar el PDF. Contacta con el administrador.', 'convoca-core' ) );
	}

	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '-' . wp_date( 'Y-m-d' ) . '.pdf"' );
	header( 'Pragma: no-cache' );
	echo $dompdf->output();
	exit;
}

/**
 * Admin POST handler: Export payments as PDF.
 */
add_action(
	'admin_post_bdg_export_payments_pdf',
	function () {
		if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'bdg_export_payments_pdf' ) ) {
			wp_die( __( 'Nonce inválido.', 'convoca-core' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'No tienes permisos.', 'convoca-core' ) );
		}

		$posts   = get_posts(
			array(
				'post_type'      => 'pago',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
			)
		);
		$headers = array( __( 'Order ID', 'convoca-core' ), __( 'Importe', 'convoca-core' ), __( 'Estado', 'convoca-core' ), __( 'Método', 'convoca-core' ), __( 'Fecha', 'convoca-core' ) );
		$rows    = array();

		if ( class_exists( '\\Convoca\\Gateway\\CPT_Pago' ) ) {
			foreach ( $posts as $p ) {
				$m      = \Convoca\Gateway\CPT_Pago::get_meta( $p->ID );
				$rows[] = array(
					$m['order_id'] ?? '',
					\Convoca\Gateway\CPT_Pago::format_amount( (int) ( $m['amount_cents'] ?? 0 ) ),
					$m['status'] ?? '',
					$m['method'] ?? '',
					$m['created_at'] ?? '',
				);
			}
		}

		convoca_export_pdf( __( 'Listado de Pagos', 'convoca-core' ), $headers, $rows, 'pagos-convoca' );
	}
);

/**
 * Centralized Logs page.
 */
function convoca_logs_page(): void {
	if ( ! current_user_can( 'manage_convoca_logs' ) && ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'No tienes permisos.', 'convoca-core' ) );
	}
	require_once __DIR__ . '/admin/class-admin-logs-list.php';
	$table = new \Convoca\Core\Admin_Logs_List();
	$table->prepare_items();
	?>
	<div class="wrap">
		<h1>📋 <?php esc_html_e( 'Registros del Sistema', 'convoca-core' ); ?></h1>
		<?php if ( $table->approx_total ) : ?>
			<p class="description"><?php esc_html_e( '⚠️ El contador de registros es aproximado para tablas grandes (>10.000 filas). Usa filtros para obtener un recuento exacto.', 'convoca-core' ); ?></p>
		<?php endif; ?>
		<form method="get">
			<input type="hidden" name="page" value="bdv-logs-central">
			<?php $table->search_box( __( 'Buscar en logs', 'convoca-core' ), 'log_search' ); ?>
			<?php $table->display(); ?>
		</form>
	</div>
	<div id="bdv-log-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:100000;align-items:center;justify-content:center;" onclick="this.style.display='none'">
		<div style="background:#fff;border-radius:8px;max-width:600px;width:90%;max-height:80vh;overflow:auto;padding:24px;box-shadow:0 4px 24px rgba(0,0,0,0.2);" onclick="event.stopPropagation()">
			<h3 style="margin-top:0;"><?php esc_html_e( 'Detalle del Log', 'convoca-core' ); ?></h3>
			<pre id="bdv-log-message-content" style="white-space:pre-wrap;word-break:break-word;background:#f8fafc;padding:12px;border-radius:4px;font-size:13px;line-height:1.5;"></pre>
			<button style="margin-top:12px;" class="button" onclick="document.getElementById('bdv-log-modal').style.display='none'"><?php esc_html_e( 'Cerrar', 'convoca-core' ); ?></button>
		</div>
	</div>
	<script>
	(function(){
		var modal = document.getElementById('bdv-log-modal');
		var content = document.getElementById('bdv-log-message-content');
		document.querySelectorAll('.view-log-detail').forEach(function(el){
			el.style.color = '#3b82f6';
			el.style.cursor = 'pointer';
			el.addEventListener('click', function(e){
				e.preventDefault();
				content.textContent = el.getAttribute('data-log-message');
				modal.style.display = 'flex';
			});
		});
	})();
	</script>
	<?php
}

/**

/**
 * Unified Dashboard page.
 */
function convoca_dashboard_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'No tienes permisos.', 'convoca-core' ) );
	}

	$force = isset( $_GET['refresh'] );
	$data  = \Convoca\Core\Admin_Analytics::get_all( $force );
	$m     = $data['members'];
	$i     = $data['inscriptions'];
	$p     = $data['payments'];
	$t     = $data['turnos'];
	$trend = $data['trends'];

	\Convoca\Core\Admin_Analytics::enqueue_chartjs();

	$cards = array(
		array(
			'icon'  => '👥',
			'label' => __( 'Socios activos', 'convoca-core' ),
			'value' => $m['activos'],
			'total' => $m['total'],
			'url'   => admin_url( 'admin.php?page=bdv-members' ),
		),
		array(
			'icon'  => '🆕',
			'label' => __( 'Altas este mes', 'convoca-core' ),
			'value' => $m['nuevos_mes'],
			'total' => '',
			'url'   => admin_url( 'admin.php?page=bdv-members' ),
		),
		array(
			'icon'  => '📝',
			'label' => __( 'Inscripciones confirmadas', 'convoca-core' ),
			'value' => $i['confirmadas'],
			'total' => $i['total'],
			'url'   => admin_url( 'admin.php?page=bde-inscripciones' ),
		),
		array(
			'icon'  => '⏳',
			'label' => __( 'En lista de espera', 'convoca-core' ),
			'value' => $i['lista_espera'],
			'total' => '',
			'url'   => admin_url( 'admin.php?page=bde-inscripciones' ),
		),
		array(
			'icon'  => '💰',
			'label' => __( 'Ingresos este mes', 'convoca-core' ),
			'value' => $p['total_mes_fmt'],
			'total' => '',
			'url'   => admin_url( 'edit.php?post_type=pago' ),
		),
		array(
			'icon'  => '🟡',
			'label' => __( 'Turnos sin cubrir', 'convoca-core' ),
			'value' => $t['disponibles'],
			'total' => $t['total'],
			'url'   => admin_url( 'edit.php?post_type=centro_turno' ),
		),
	);
	?>
	<div class="wrap">
		<h1>📊 <?php esc_html_e( 'Panel de Control Biodevas', 'convoca-core' ); ?></h1>
		<p>
			<a href="<?php echo esc_url( add_query_arg( 'refresh', '1' ) ); ?>" class="convoca-btn convoca-btn-outline">🔄 <?php esc_html_e( 'Actualizar datos', 'convoca-core' ); ?></a>
			<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=bdv_generate_memory' ), 'bdv_generate_memory' ) ); ?>" class="convoca-btn convoca-btn-primary" style="margin-left:8px;">📄 <?php esc_html_e( 'Generar memoria PDF', 'convoca-core' ); ?></a>
		</p>

		<div class="bdv-analytics-cards">
			<?php foreach ( $cards as $card ) : ?>
				<a href="<?php echo esc_url( $card['url'] ); ?>" class="bdv-analytics-card">
					<div class="bdv-card-icon"><?php echo $card['icon']; ?></div>
					<div class="bdv-card-value"><?php echo esc_html( $card['value'] ); ?></div>
					<?php
					if ( $card['total'] ) :
						?>
						<div class="bdv-card-total">/ <?php echo esc_html( $card['total'] ); ?> total</div><?php endif; ?>
					<div class="bdv-card-label"><?php echo esc_html( $card['label'] ); ?></div>
				</a>
			<?php endforeach; ?>
		</div>

		<div class="bdv-analytics-charts">
			<div class="bdv-chart-card">
				<h3>📈 <?php esc_html_e( 'Tendencia (6 meses)', 'convoca-core' ); ?></h3>
				<?php
				echo \Convoca\Core\Admin_Analytics::render_chart(
					'chartTrends',
					'line',
					$trend['labels'],
					array(
						array(
							'label'  => 'Altas socios',
							'data'   => $trend['members'],
							'color'  => '#320028',
							'border' => '#320028',
						),
						array(
							'label'  => 'Inscripciones',
							'data'   => $trend['inscriptions'],
							'color'  => 'rgba(255,135,0,0.2)',
							'border' => '#FF8700',
						),
					)
				);
				?>
			</div>
			<div class="bdv-chart-card">
				<h3>💰 <?php esc_html_e( 'Ingresos mensuales', 'convoca-core' ); ?></h3>
				<?php
				echo \Convoca\Core\Admin_Analytics::render_chart(
					'chartPayments',
					'bar',
					$trend['labels'],
					array(
						array(
							'label'  => 'Ingresos (€)',
							'data'   => $trend['payments'],
							'color'  => '#059669',
							'border' => '#059669',
						),
					)
				);
				?>
			</div>
			<div class="bdv-chart-card">
				<h3>📊 <?php esc_html_e( 'Inscripciones por estado', 'convoca-core' ); ?></h3>
				<?php
				echo \Convoca\Core\Admin_Analytics::render_chart(
					'chartInscriptionStates',
					'doughnut',
					array( 'Confirmadas', 'Pendientes', 'Espera', 'Canceladas' ),
					array(
						array(
							'label' => 'Estado',
							'data'  => array( $i['confirmadas'], $i['pendientes'], $i['lista_espera'], $i['canceladas'] ),
							'color' => array( '#059669', '#f59e0b', '#3b82f6', '#ef4444' ),
						),
					)
				);
				?>
			</div>
			<div class="bdv-chart-card">
				<h3>💳 <?php esc_html_e( 'Métodos de pago (mes)', 'convoca-core' ); ?></h3>
				<?php
				echo \Convoca\Core\Admin_Analytics::render_chart(
					'chartPayMethods',
					'doughnut',
					array( 'Tarjeta', 'Bizum', 'Otros' ),
					array(
						array(
							'label' => 'Método',
							'data'  => array( $p['methods_pct']['tarjeta'], $p['methods_pct']['bizum'], $p['methods_pct']['otros'] ?? 0 ),
							'color' => array( '#0073aa', '#46b450', '#94a3b8' ),
						),
					)
				);
				?>
			</div>
			<div class="bdv-chart-card bdv-chart-card--wide">
				<h3>📋 <?php esc_html_e( 'Últimos 7 días — Pagos', 'convoca-core' ); ?></h3>
				<?php
				echo \Convoca\Core\Admin_Analytics::render_chart(
					'chart7days',
					'bar',
					array_column( $p['last_7_days'], 'label' ),
					array(
						array(
							'label'  => 'Importe (€)',
							'data'   => array_map( fn( $d ) => round( $d['total'] / 100, 2 ), $p['last_7_days'] ),
							'color'  => '#FF8700',
							'border' => '#e67a00',
						),
					),
					array( 'height' => '160px' )
				);
				?>
			</div>
		</div>
	</div>

	<style>
	.bdv-analytics-cards {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
		gap: 16px;
		margin: 20px 0;
	}
	.bdv-analytics-card {
		display: block;
		background: #fff;
		border-radius: 12px;
		padding: 20px;
		box-shadow: 0 2px 8px rgba(0,0,0,.06);
		border: 1px solid #e2e8f0;
		text-decoration: none;
		transition: transform .2s, box-shadow .2s;
	}
	.bdv-analytics-card:hover {
		transform: translateY(-3px);
		box-shadow: 0 6px 16px rgba(0,0,0,.1);
	}
	.bdv-card-icon { font-size: 28px; margin-bottom: 6px; }
	.bdv-card-value { font-size: 26px; font-weight: 800; color: #320028; line-height: 1.2; }
	.bdv-card-total { font-size: 13px; color: #94a3b8; }
	.bdv-card-label { font-size: 12px; color: #64748b; margin-top: 4px; }

	.bdv-analytics-charts {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
		gap: 20px;
		margin-top: 24px;
	}
	.bdv-chart-card {
		background: #fff;
		border-radius: 12px;
		padding: 20px;
		box-shadow: 0 2px 8px rgba(0,0,0,.06);
		border: 1px solid #e2e8f0;
	}
	.bdv-chart-card h3 {
		margin: 0 0 16px;
		font-size: 15px;
		color: #320028;
		border-bottom: 2px solid #FF8700;
		padding-bottom: 8px;
	}
	.bdv-chart-card--wide { grid-column: 1 / -1; }
	@media (max-width: 600px) {
		.bdv-analytics-charts { grid-template-columns: 1fr; }
		.bdv-analytics-cards { grid-template-columns: repeat(2, 1fr); }
	}
	</style>
	<?php
}

function convoca_build_metrics(): array {
	global $wpdb;
	$metrics = array();

	// Members.
	if ( class_exists( '\Convoca\Members\CPT_Miembro' ) && class_exists( '\Convoca\Members\Estados' ) ) {
		$counts = array();
		foreach ( \Convoca\Members\Estados::LABELS as $slug => $label ) {
			$counts[ $slug ] = ( new \WP_Query(
				array(
					'post_type'      => 'miembro',
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_query'     => array(
						array(
							'key'   => '_bdv_estado_miembro',
							'value' => $slug,
						),
					),
				)
			) )->found_posts;
		}
		$metrics['members_total']   = array_sum( $counts );
		$metrics['members_activos'] = $counts['activo'] ?? 0;
		$metrics['members_url']     = admin_url( 'admin.php?page=bdv-members' );
	}

	$metrics['members_new_month'] = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'miembro' AND post_status = 'publish' AND MONTH(post_date) = MONTH(NOW()) AND YEAR(post_date) = YEAR(NOW())"
		)
	);

	if ( class_exists( '\Convoca\Enroll\CPT_Inscripcion' ) ) {
		$pendientes_pago                          = new \WP_Query(
			array(
				'post_type'      => 'inscripcion',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => '_bde_estado',
						'value' => 'pendiente_pago',
					),
				),
			)
		);
		$metrics['inscripciones_pendientes_pago'] = $pendientes_pago->found_posts;
		$metrics['inscripciones_url']             = admin_url( 'admin.php?page=convoca-core-enroll' );
	}

	if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}postmeta'" ) ) {
		$metrics['payments_month'] = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(meta_value + 0) FROM {$wpdb->postmeta} pm 
             JOIN {$wpdb->posts} p ON p.ID = pm.post_id 
             WHERE pm.meta_key = '_bdg_amount_cents' AND p.post_type = 'pago' 
             AND MONTH(p.post_date) = MONTH(NOW()) AND YEAR(p.post_date) = YEAR(NOW())"
			)
		) / 100;
		$metrics['payments_url']   = admin_url( 'admin.php?page=bdg-payments' );
	}

	$metrics['turnos_sin_cubrir'] = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} pm 
         JOIN {$wpdb->posts} p ON p.ID = pm.post_id 
         WHERE pm.meta_key = '_estado' AND pm.meta_value = 'abierto_disponible' 
         AND p.post_type = 'centro_turno' AND p.post_status = 'publish'
         AND p.post_date >= NOW()"
		)
	);
	$metrics['turnos_url']        = admin_url( 'edit.php?post_type=centro_turno&page=cst-turnos-list' );

	return $metrics;
}


/* ── REST endpoint: /convoca/v1/admin/metrics ── */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'convoca/v1',
			'/admin/metrics',
			array(
				'methods'             => 'GET',
				'callback'            => 'Convoca\\Core\\assoc_rest_metrics',
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}
);

function convoca_rest_metrics(): \WP_REST_Response {
	$cache_key = 'bdv_rest_metrics';
	$data      = get_transient( $cache_key );

	if ( ! $data ) {
		$data = assoc_build_metrics();
		set_transient( $cache_key, $data, 30 );
	}

	return new \WP_REST_Response( $data, 200 );
}
