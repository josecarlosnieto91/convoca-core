<?php
/**
 * Convoca Setup Wizard — First-run configuration assistant.
 *
 * Guides the admin through required setup steps for the whole ecosystem.
 *
 * @package Convoca\Core
 */

namespace Convoca\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Setup_Wizard {

	const COMPLETED_OPTION = 'conv_setup_wizard_completed';
	const DISMISS_OPTION   = 'conv_setup_wizard_dismissed';
	const PROGRESS_OPTION  = 'conv_setup_wizard_progress';
	const SEEN_OPTION      = 'conv_setup_wizard_seen';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_init', array( $this, 'maybe_redirect' ) );
		add_action( 'admin_post_conv_wizard_save', array( $this, 'handle_save' ) );
		add_action( 'admin_post_conv_wizard_skip', array( $this, 'handle_skip' ) );
		add_action( 'admin_post_conv_wizard_create_pages', array( $this, 'handle_create_pages' ) );
		add_action( 'admin_post_conv_wizard_complete', array( $this, 'handle_complete' ) );
	}

	public function register_page(): void {
		add_submenu_page(
			null,
			__( 'Asistente de Configuración Convoca', 'convoca-core' ),
			__( 'Asistente', 'convoca-core' ),
			'manage_options',
			'bdv-setup-wizard',
			array( $this, 'render' )
		);
	}

	/**
	 * Redirect to the wizard on first activation.
	 */
	public function maybe_redirect(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( get_option( self::COMPLETED_OPTION ) || get_option( self::DISMISS_OPTION ) ) {
			return;
		}

		if ( wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		global $pagenow;
		if ( $pagenow === 'admin-ajax.php' ) {
			return;
		}
		if ( isset( $_GET['page'] ) && $_GET['page'] === 'bdv-setup-wizard' ) {
			return;
		}

		// Session-level guard to avoid infinite annoyance.
		$user_id = get_current_user_id();
		if ( get_transient( self::SEEN_OPTION . '_' . $user_id ) ) {
			return;
		}

		// If configuration is already verified complete, auto-mark and stop.
		if ( $this->is_config_complete() ) {
			update_option( self::COMPLETED_OPTION, 1 );
			return;
		}

		// Only redirect from dashboard or plugins page to be less intrusive.
		if ( ! in_array( $pagenow, array( 'index.php', 'plugins.php' ), true ) ) {
			return;
		}

		set_transient( self::SEEN_OPTION . '_' . $user_id, 1, 6 * HOUR_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=bdv-setup-wizard' ) );
		exit;
	}

	private function is_config_complete(): bool {
		global $wpdb;

		// 1. Tables
		foreach ( array( 'convoca_logs', 'convoca_locks' ) as $t ) {
			if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}{$t}'" ) !== $wpdb->prefix . $t ) {
				return false;
			}
		}

		// 2. Plugins
		if ( ! class_exists( '\\Convoca\\Core\\Utils' ) ) {
			return false;
		}

		// 3. Mandatory Pages
		foreach ( array( 'alta-socios', 'panel-socio', 'pago' ) as $slug ) {
			if ( ! get_page_by_path( $slug ) ) {
				return false;
			}
		}

		return true;
	}

	public function handle_skip(): void {
		check_admin_referer( 'conv_wizard_skip' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Access denied.' );
		}
		update_option( self::DISMISS_OPTION, 1 );
		wp_safe_redirect( admin_url() );
		exit;
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'No tienes permisos.', 'convoca-core' ) );
		}

		$saved_progress = (int) get_option( self::PROGRESS_OPTION, 1 );
		$step           = isset( $_GET['step'] ) ? min( 6, max( 1, (int) $_GET['step'] ) ) : $saved_progress;

		?>
		<div class="wrap" style="max-width:850px;margin:40px auto;">
			<div style="text-align:center;margin-bottom:30px;">
				<img src="<?php echo esc_url( CONVOCA_IMAGES_URL . 'logo.png' ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" style="width:80px;height:80px;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.1);">
				<h1 style="margin:15px 0 5px;"><?php esc_html_e( 'Configuración del Ecosistema', 'convoca-core' ); ?></h1>
				<p style="color:#666;font-size:1.1em;"><?php esc_html_e( 'Sigue los pasos para asegurar que tu plataforma Convoca esté lista.', 'convoca-core' ); ?></p>
			</div>

			<?php $this->render_steps( $step ); ?>

			<div style="background:#fff;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,0.08);padding:45px;margin-top:20px;border:1px solid #e2e8f0;">
				<?php
				match ( $step ) {
					1 => $this->step_database(),
					2 => $this->step_pages(),
					3 => $this->step_plans(),
					4 => $this->step_redsys(),
					5 => $this->step_turnos(),
					6 => $this->step_summary(),
					default => $this->step_database(),
				};
		?>
			</div>

			<div style="text-align:center;margin-top:30px;">
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=conv_wizard_skip' ), 'conv_wizard_skip' ) ); ?>" style="color:#94a3b8;text-decoration:none;font-size:13px;font-weight:500;">
					<?php esc_html_e( 'Omitir asistente y configurar manualmente', 'convoca-core' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	private function render_steps( int $current ): void {
		$labels = array(
			1 => __( 'Infraestructura', 'convoca-core' ),
			2 => __( 'Páginas', 'convoca-core' ),
			3 => __( 'Membresía', 'convoca-core' ),
			4 => __( 'Pagos', 'convoca-core' ),
			5 => __( 'Turnos', 'convoca-core' ),
			6 => __( 'Finalización', 'convoca-core' ),
		);
		echo '<div style="display:flex;justify-content:center;gap:0;margin-bottom:20px;background:#f8fafc;padding:20px;border-radius:12px;">';
		for ( $i = 1; $i <= 6; $i++ ) {
			$done    = $i < $current;
			$active  = $i === $current;
			$color   = $done ? '#10b981' : ( $active ? '#3b82f6' : '#94a3b8' );
			$opacity = ( $done || $active ) ? '1' : '0.5';
			echo '<div style="text-align:center;flex:1;position:relative;opacity:' . $opacity . ';">';
			if ( $i < 6 ) {
				echo '<div style="position:absolute;top:16px;left:50%;width:100%;height:2px;background:' . ( $done ? '#10b981' : '#e2e8f0' ) . ';z-index:0;"></div>';
			}
			echo '<div style="background:' . $color . ';color:#fff;width:34px;height:34px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-weight:bold;margin-bottom:8px;position:relative;z-index:1;box-shadow:0 0 0 4px #fff;">' . ( $done ? '✓' : $i ) . '</div>';
			echo '<div style="font-size:12px;color:' . ( $active ? '#1e293b' : '#64748b' ) . ';font-weight:' . ( $active ? '700' : '500' ) . ';">' . esc_html( $labels[ $i ] ) . '</div>';
			echo '</div>';
		}
		echo '</div>';
	}

	/* ── Step 1: Database & Dependencies ── */

	private function step_database(): void {
		global $wpdb;
		$checks = array();

		$checks[] = $this->check_item( __( 'Convoca Common', 'convoca-core' ), class_exists( '\\Convoca\\Core\\Utils' ), __( 'Plugin activo.', 'convoca-core' ), __( 'Activa Convoca Common.', 'convoca-core' ) );

		foreach ( array( 'convoca_logs', 'convoca_locks' ) as $t ) {
			$exists   = $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}{$t}'" ) === $wpdb->prefix . $t;
			$checks[] = $this->check_item( sprintf( __( 'Tabla %s', 'convoca-core' ), $t ), $exists, __( 'Correcto.', 'convoca-core' ), __( 'No encontrada.', 'convoca-core' ) );
		}

		$all_ok = true;
		echo '<h2>' . esc_html__( '1. Infraestructura y Base de Datos', 'convoca-core' ) . '</h2>';
		echo '<div style="margin:30px 0;border:1px solid #f1f5f9;border-radius:8px;overflow:hidden;">';
		foreach ( $checks as $c ) {
			if ( ! $c['ok'] ) {
				$all_ok = false;
			}
			echo '<div style="display:flex;align-items:center;gap:15px;padding:15px;background:' . ( $c['ok'] ? '#fff' : '#fef2f2' ) . ';border-bottom:1px solid #f1f5f9;">';
			echo '<span style="font-size:20px;' . ( $c['ok'] ? 'color:#10b981;' : 'color:#ef4444;' ) . '">' . ( $c['ok'] ? '✓' : '⚠' ) . '</span>';
			echo '<div style="flex:1;"><strong style="color:#334155;">' . esc_html( $c['label'] ) . '</strong><br><small style="color:#64748b;">' . esc_html( $c['msg'] ) . '</small></div>';
			echo '</div>';
		}
		echo '</div>';

		$this->step_nav( 1, $all_ok );
	}

	private function check_item( string $label, bool $ok, string $ok_msg, string $err_msg ): array {
		return array(
			'label' => $label,
			'ok'    => $ok,
			'msg'   => $ok ? $ok_msg : $err_msg,
		);
	}

	/* ── Step 2: Pages ── */

	private function step_pages(): void {
		echo '<h2>' . esc_html__( '2. Páginas del Sistema', 'convoca-core' ) . '</h2>';
		$pages = array(
			'alta-socios' => array(
				'title' => 'Alta de Socios',
				'sc'    => '[convoca_alta]',
				'req'   => true,
			),
			'panel-socio' => array(
				'title' => 'Mi Panel de Socio',
				'sc'    => '[mi_panel]',
				'req'   => true,
			),
			'pago'        => array(
				'title' => 'Página de Pago',
				'sc'    => '[convoca_pago]',
				'req'   => true,
			),
		);

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'conv_wizard_create_pages' );
		echo '<input type="hidden" name="action" value="conv_wizard_create_pages">';
		echo '<table style="width:100%;margin-bottom:20px;">';
		$all_mand = true;
		foreach ( $pages as $slug => $info ) {
			$exists = get_page_by_path( $slug );
			if ( ! $exists ) {
				$all_mand = false;
			}
			echo '<tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:15px 0;"><strong>' . esc_html( $info['title'] ) . '</strong></td>';
			echo '<td style="text-align:right;">' . ( $exists ? '✅' : '⚠' ) . '</td></tr>';
		}
		echo '</table>';
		if ( ! $all_mand ) {
			submit_button( __( 'Crear páginas faltantes', 'convoca-core' ), 'convoca-btn convoca-btn-primary', '', false );
		}
		echo '</form>';

		$this->step_nav( 2, $all_mand );
	}

	public function handle_create_pages(): void {
		check_admin_referer( 'conv_wizard_create_pages' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Access denied.' );
		}

		if ( ! \Convoca\Core\Utils::acquire_lock( 'conv_wizard_create_pages', 30 ) ) {
			wp_die( 'Otra operación de creación de páginas está en curso.' );
		}

		try {
			$pages = array(
				'alta-socios' => array(
					'title' => 'Alta de Socios',
					'sc'    => '[convoca_alta]',
				),
				'panel-socio' => array(
					'title' => 'Mi Panel de Socio',
					'sc'    => '[mi_panel]',
				),
				'pago'        => array(
					'title' => 'Pago',
					'sc'    => '[convoca_pago]',
				),
			);

			foreach ( $pages as $slug => $info ) {
				if ( ! get_page_by_path( $slug ) ) {
					$page_id = wp_insert_post(
						array(
							'post_title'   => $info['title'],
							'post_name'    => $slug,
							'post_content' => $info['sc'],
							'post_status'  => 'publish',
							'post_type'    => 'page',
						)
					);
					if ( is_wp_error( $page_id ) ) {
						\Convoca\Core\Logger::error(
							sprintf( 'Error creando pagina %s: %s', $slug, $page_id->get_error_message() ),
							'Common/SetupWizard'
						);
					}
				}
			}
		} finally {
			\Convoca\Core\Utils::release_lock( 'conv_wizard_create_pages' );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=bdv-setup-wizard&step=2' ) );
		exit;
	}

	/* ── Step 3: Membership ── */

	private function step_plans(): void {
		$plans = class_exists( '\\Convoca\\Members\\CPT_Miembro' ) ? \Convoca\Members\CPT_Miembro::get_plans() : array();
		?>
		<h2><?php esc_html_e( '3. Planes de Membresía', 'convoca-core' ); ?></h2>
		<?php if ( empty( $plans ) ) : ?>
			<p>⚠ <?php esc_html_e( 'No se han detectado planes. Configúralos en Ajustes → Miembros.', 'convoca-core' ); ?></p>
		<?php else : ?>
			<p><?php esc_html_e( 'Planes detectados:', 'convoca-core' ); ?></p>
			<ul>
			<?php
			foreach ( $plans as $p ) {
				echo '<li>' . esc_html( $p['label'] ) . '</li>';}
			?>
			</ul>
		<?php endif; ?>
		<?php $this->step_nav( 3, ! empty( $plans ) ); ?>
		<?php
	}

	/* ── Step 4: Redsys ── */

	private function step_redsys(): void {
		$settings = get_option( 'conv_gateway_settings', array() );
		?>
		<h2><?php esc_html_e( '4. Configuración de Redsys', 'convoca-core' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'conv_wizard_save' ); ?>
			<input type="hidden" name="action" value="conv_wizard_save">
			<input type="hidden" name="wizard_step" value="4">
			<p><label>Merchant Code:</label><input type="text" name="merchant_code" value="<?php echo esc_attr( $settings['merchant_code'] ?? '' ); ?>"></p>
			<p><label>Secret Key:</label><input type="password" name="secret_key" value="<?php echo esc_attr( $settings['secret_key'] ?? '' ); ?>"></p>
			<button type="submit" class="convoca-btn convoca-btn-primary"><?php esc_html_e( 'Guardar', 'convoca-core' ); ?></button>
		</form>
		<?php
		$this->step_nav( 4, true );
	}

	/* ── Step 5: Turnos ── */

	private function step_turnos(): void {
		?>
		<h2><?php esc_html_e( '5. Convoca Shifts', 'convoca-core' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'conv_wizard_save' ); ?>
			<input type="hidden" name="action" value="conv_wizard_save">
			<input type="hidden" name="wizard_step" value="5">
			<p><label>Hora Apertura:</label><input type="time" name="cst_apertura" value="<?php echo esc_attr( get_option( 'convoca_shifts_hora_apertura', '09:00' ) ); ?>"></p>
			<p><label>Hora Cierre:</label><input type="time" name="cst_cierre" value="<?php echo esc_attr( get_option( 'convoca_shifts_hora_cierre', '22:00' ) ); ?>"></p>
			<button type="submit" class="convoca-btn convoca-btn-primary"><?php esc_html_e( 'Guardar', 'convoca-core' ); ?></button>
		</form>
		<?php
		$this->step_nav( 5, true );
	}

	/* ── Step 6: Summary ── */

	private function step_summary(): void {
		$status = $this->get_completion_status();
		?>
		<h2><?php esc_html_e( '6. Finalización', 'convoca-core' ); ?></h2>
		<?php if ( $status['is_ready'] ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'conv_wizard_complete' ); ?>
				<input type="hidden" name="action" value="conv_wizard_complete">
				<button type="submit" class="convoca-btn convoca-btn-primary"><?php esc_html_e( 'Finalizar configuración', 'convoca-core' ); ?></button>
			</form>
		<?php else : ?>
			<p>⚠ <?php esc_html_e( 'Faltan requisitos obligatorios.', 'convoca-core' ); ?></p>
		<?php endif; ?>
		<?php
	}

	private function get_completion_status(): array {
		return array( 'is_ready' => $this->is_config_complete() );
	}

	public function handle_save(): void {
		check_admin_referer( 'conv_wizard_save' );
		$step = (int) $_POST['wizard_step'];
		if ( $step === 4 ) {
			$settings                  = get_option( 'conv_gateway_settings', array() );
			$settings['merchant_code'] = sanitize_text_field( $_POST['merchant_code'] );
			$settings['secret_key']    = sanitize_text_field( $_POST['secret_key'] );
			update_option( 'conv_gateway_settings', $settings );
		}
		if ( $step === 5 ) {
			update_option( 'convoca_shifts_hora_apertura', sanitize_text_field( $_POST['cst_apertura'] ) );
			update_option( 'convoca_shifts_hora_cierre', sanitize_text_field( $_POST['cst_cierre'] ) );
		}
		update_option( self::PROGRESS_OPTION, $step + 1 );
		wp_safe_redirect( admin_url( 'admin.php?page=bdv-setup-wizard&step=' . ( $step + 1 ) ) );
		exit;
	}

	public function handle_complete(): void {
		check_admin_referer( 'conv_wizard_complete' );
		update_option( self::COMPLETED_OPTION, 1 );
		delete_option( self::PROGRESS_OPTION );
		wp_safe_redirect( admin_url() );
		exit;
	}

	private function step_nav( int $current, bool $can_continue ): void {
		echo '<div style="margin-top:35px;display:flex;justify-content:space-between;">';
		if ( $current > 1 ) {
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=bdv-setup-wizard&step=' . ( $current - 1 ) ) ) . '" class="convoca-btn convoca-btn-outline">Anterior</a>';
		} else {
			echo '<div></div>';
		}
		if ( $can_continue && $current < 6 ) {
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=bdv-setup-wizard&step=' . ( $current + 1 ) ) ) . '" class="convoca-btn convoca-btn-primary">Siguiente</a>';
		}
		echo '</div>';
	}
}
