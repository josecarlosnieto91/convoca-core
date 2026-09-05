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

	const COMPLETED_OPTION = 'convoca_setup_wizard_completed';
	const DISMISS_OPTION   = 'convoca_setup_wizard_dismissed';
	const PROGRESS_OPTION  = 'convoca_setup_wizard_progress';
	const SEEN_OPTION      = 'convoca_setup_wizard_seen';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_init', array( $this, 'maybe_redirect' ) );
		add_action( 'admin_post_convoca_wizard_save', array( $this, 'handle_save' ) );
		add_action( 'admin_post_convoca_wizard_skip', array( $this, 'handle_skip' ) );
		add_action( 'admin_post_convoca_wizard_create_pages', array( $this, 'handle_create_pages' ) );
		add_action( 'admin_post_convoca_wizard_complete', array( $this, 'handle_complete' ) );
	}

	public function register_page(): void {
		add_submenu_page(
			null,
			__( 'Asistente de Configuración Convoca', 'convoca-core' ),
			__( 'Asistente', 'convoca-core' ),
			'manage_options',
			'conv-setup-wizard',
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
		if ( isset( $_GET['page'] ) && $_GET['page'] === 'conv-setup-wizard' ) {
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
		wp_safe_redirect( admin_url( 'admin.php?page=conv-setup-wizard' ) );
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
		check_admin_referer( 'convoca_wizard_skip' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Acceso denegado.', 'convoca-core' ) );
		}
		update_option( self::DISMISS_OPTION, 1 );
		wp_safe_redirect( admin_url() );
		exit;
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos.', 'convoca-core' ) );
		}

		$saved_progress = (int) get_option( self::PROGRESS_OPTION, 1 );
		$step           = isset( $_GET['step'] ) ? min( 7, max( 1, (int) $_GET['step'] ) ) : $saved_progress;

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
					6 => $this->step_ecosystem(),
					7 => $this->step_summary(),
					default => $this->step_database(),
				};
		?>
			</div>

			<div style="text-align:center;margin-top:30px;">
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=convoca_wizard_skip' ), 'convoca_wizard_skip' ) ); ?>" style="color:#94a3b8;text-decoration:none;font-size:13px;font-weight:500;">
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
			6 => __( 'Ecosistema', 'convoca-core' ),
			7 => __( 'Finalización', 'convoca-core' ),
		);
		$base_url = admin_url( 'admin.php?page=conv-setup-wizard' );
		echo '<div style="display:flex;justify-content:center;gap:0;margin-bottom:20px;background:#f8fafc;padding:20px;border-radius:12px;">';
		for ( $i = 1; $i <= 7; $i++ ) {
			$done      = $i < $current;
			$active    = $i === $current;
			$reachable = $i <= $current; // Se puede volver a cualquier paso ya recorrido.
			$color     = $done ? '#10b981' : ( $active ? '#3b82f6' : '#94a3b8' );
			$opacity   = ( $done || $active ) ? '1' : '0.5';
			$cursor    = $reachable && ! $active ? 'cursor:pointer;' : 'cursor:default;';
			$tag_open  = $reachable && ! $active ? '<a href="' . esc_url( $base_url . '&step=' . $i ) . '" style="text-decoration:none;display:block;" title="' . esc_attr__( 'Volver a este paso', 'convoca-core' ) . '">' : '<div style="display:block;">';
			$tag_close = $reachable && ! $active ? '</a>' : '</div>';
			echo '<div style="text-align:center;flex:1;position:relative;opacity:' . esc_attr( $opacity ) . ';">';
			if ( $i < 7 ) {
				echo '<div style="position:absolute;top:16px;left:50%;width:100%;height:2px;background:' . ( $done ? '#10b981' : '#e2e8f0' ) . ';z-index:0;"></div>';
			}
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML construido con esc_url()/esc_attr(); cierre literal seguro.
			echo $tag_open;
			echo '<div style="background:' . esc_attr( $color ) . ';color:#fff;width:34px;height:34px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-weight:bold;margin-bottom:8px;position:relative;z-index:1;box-shadow:0 0 0 4px #fff;' . esc_attr( $cursor ) . '">' . esc_html( $done ? '✓' : $i ) . '</div>';
			echo '<div style="font-size:12px;color:' . ( $active ? '#1e293b' : '#64748b' ) . ';font-weight:' . ( $active ? '700' : '500' ) . ';">' . esc_html( $labels[ $i ] ) . '</div>';
			echo $tag_close;
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
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
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- SHOW TABLES LIKE requires a literal string
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . $t ) ) === $wpdb->prefix . $t;
			/* translators: %s: database table name */
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
				'sc'    => '[convoca_alta_socio]',
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
		wp_nonce_field( 'convoca_wizard_create_pages' );
		echo '<input type="hidden" name="action" value="convoca_wizard_create_pages">';
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
			submit_button( __( 'Crear páginas faltantes', 'convoca-core' ), 'primary', '', false );
		}
		echo '</form>';

		$this->step_nav( 2, $all_mand );
	}

	public function handle_create_pages(): void {
		check_admin_referer( 'convoca_wizard_create_pages' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Acceso denegado.', 'convoca-core' ) );
		}

		if ( ! \Convoca\Core\Utils::acquire_lock( 'convoca_wizard_create_pages', 30 ) ) {
			wp_die( esc_html__( 'Otra operación de creación de páginas está en curso.', 'convoca-core' ) );
		}

		try {
			$pages = array(
				'alta-socios' => array(
					'title' => 'Alta de Socios',
					'sc'    => '[convoca_alta_socio]',
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
					if ( ! $page_id ) {
						\Convoca\Core\Logger::error(
							sprintf( 'Error creando página %s (wp_insert_post devolvió 0).', $slug ),
							'Common/SetupWizard'
						);
					}
				}
			}
		} finally {
			\Convoca\Core\Utils::release_lock( 'convoca_wizard_create_pages' );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=conv-setup-wizard&step=2' ) );
		exit;
	}

	/* ── Step 3: Membership ── */

	private function step_plans(): void {
		$plans = class_exists( '\Convoca\Members\CPT_Miembro' ) ? \Convoca\Members\CPT_Miembro::get_plans() : array();
		// Solo modalidades reales de pago (Numerario/Familiar/Juvenil) — excluye artefactos 'Virtual'.
		$real_mods = array( 'Numerario', 'Familiar', 'Juvenil' );
		$editable  = array();
		foreach ( $plans as $key => $p ) {
			$mod = $p['modalidad'] ?? 'Numerario';
			if ( in_array( $mod, $real_mods, true ) ) {
				$editable[ $key ] = $p;
			}
		}
		?>
		<h2><?php esc_html_e( '3. Planes de Membresía', 'convoca-core' ); ?></h2>
		<?php if ( empty( $editable ) ) : ?>
			<p>⚠ <?php esc_html_e( 'No se han detectado planes. Activa Convoca Members para configurarlos.', 'convoca-core' ); ?></p>
		<?php else : ?>
			<p><?php esc_html_e( 'Marca los planes disponibles, edita su nombre, modalidad y precio anual:', 'convoca-core' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'convoca_wizard_save' ); ?>
				<input type="hidden" name="action" value="convoca_wizard_save">
				<input type="hidden" name="wizard_step" value="3">
				<table class="widefat striped" style="max-width:1000px;">
					<thead>
						<tr>
							<th style="width:45px;"><?php esc_html_e( 'Activo', 'convoca-core' ); ?></th>
							<th><?php esc_html_e( 'Nombre visible', 'convoca-core' ); ?></th>
							<th style="width:140px;"><?php esc_html_e( 'ID corto', 'convoca-core' ); ?></th>
							<th style="width:125px;"><?php esc_html_e( 'Modalidad', 'convoca-core' ); ?></th>
							<th style="width:100px;"><?php esc_html_e( 'Precio (€)', 'convoca-core' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $editable as $key => $p ) : ?>
						<?php
						$checked = ( isset( $p['active'] ) && false === $p['active'] ) ? false : true;
						$mod     = $p['modalidad'] ?? 'Numerario';
						$p_label = $p['label'] ?? $key;
						// Los defaults incluyen el emoji al inicio del label ("🥉 Bronce").
						// Si además existe el campo emoji separado, lo anteponemos.
						$p_emoji_sep = $p['emoji'] ?? '';
						if ( '' !== $p_emoji_sep && ! str_starts_with( $p_label, $p_emoji_sep ) ) {
							$p_label = $p_emoji_sep . ' ' . $p_label;
						}
						?>
						<tr>
							<td><input type="checkbox" name="convoca_plans[<?php echo esc_attr( $key ); ?>][active]" value="1" <?php checked( $checked ); ?>></td>
							<td>
								<input type="text" name="convoca_plans[<?php echo esc_attr( $key ); ?>][label]" value="<?php echo esc_attr( $p_label ); ?>" class="regular-text" style="width:100%;">
							</td>
							<td>
								<input type="text" name="convoca_plans[<?php echo esc_attr( $key ); ?>][slug]" value="<?php echo esc_attr( $key ); ?>" style="width:100%;" pattern="[a-z0-9-]+" title="<?php esc_attr_e( 'Solo minúsculas, números y guiones', 'convoca-core' ); ?>">
							</td>
							<td>
								<select name="convoca_plans[<?php echo esc_attr( $key ); ?>][modalidad]" style="width:100%;">
									<?php foreach ( $real_mods as $rm ) : ?>
										<option value="<?php echo esc_attr( $rm ); ?>" <?php selected( $mod, $rm ); ?>><?php echo esc_html( $rm ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
							<td><input type="number" step="0.01" min="0" name="convoca_plans[<?php echo esc_attr( $key ); ?>][price]" value="<?php echo esc_attr( $p['price'] ?? 0 ); ?>" style="width:100%;"></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p style="margin-top:15px;">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Guardar planes', 'convoca-core' ); ?></button>
				</p>
			</form>
			<p style="color:#64748b;font-size:13px;"><?php esc_html_e( 'Los planes se guardan en Convoca Members (Ajustes → Miembros → Planes) y se aplican al formulario de alta.', 'convoca-core' ); ?></p>
		<?php endif; ?>
		<?php $this->step_nav( 3, ! empty( $editable ) ); ?>
		<?php
	}

	/* ── Step 4: Redsys ── */

	private function step_redsys(): void {
		$settings = get_option( 'convoca_gateway_settings', array() );
		?>
		<h2><?php esc_html_e( '4. Configuración de Redsys', 'convoca-core' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'convoca_wizard_save' ); ?>
			<input type="hidden" name="action" value="convoca_wizard_save">
			<input type="hidden" name="wizard_step" value="4">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="merchant_code"><?php esc_html_e( 'Merchant Code', 'convoca-core' ); ?></label></th>
					<td><input type="text" id="merchant_code" name="merchant_code" class="regular-text" value="<?php echo esc_attr( $settings['merchant_code'] ?? '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="secret_key"><?php esc_html_e( 'Secret Key', 'convoca-core' ); ?></label></th>
					<td><input type="password" id="secret_key" name="secret_key" class="regular-text" value="<?php echo esc_attr( $settings['secret_key'] ?? '' ); ?>"></td>
				</tr>
			</table>
			<?php submit_button( __( 'Guardar', 'convoca-core' ) ); ?>
		</form>
		<?php
		$this->step_nav( 4, true );
	}

	/* ── Step 5: Turnos ── */

	private function step_turnos(): void {
		?>
		<h2><?php esc_html_e( '5. Convoca Shifts', 'convoca-core' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'convoca_wizard_save' ); ?>
			<input type="hidden" name="action" value="convoca_wizard_save">
			<input type="hidden" name="wizard_step" value="5">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="convoca_apertura"><?php esc_html_e( 'Hora de apertura', 'convoca-core' ); ?></label></th>
					<td><input type="time" id="convoca_apertura" name="convoca_apertura" value="<?php echo esc_attr( get_option( 'convoca_shifts_hora_apertura', '09:00' ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="convoca_cierre"><?php esc_html_e( 'Hora de cierre', 'convoca-core' ); ?></label></th>
					<td><input type="time" id="convoca_cierre" name="convoca_cierre" value="<?php echo esc_attr( get_option( 'convoca_shifts_hora_cierre', '22:00' ) ); ?>"></td>
				</tr>
			</table>
			<?php submit_button( __( 'Guardar', 'convoca-core' ) ); ?>
		</form>
		<?php
		$this->step_nav( 5, true );
	}

	/* ── Step 6: Ecosystem ── */

	private function step_ecosystem(): void {
		// Detección robusta de módulos: usa is_plugin_active() con las rutas reales
		// (las clases PHP varían entre versiones; la ruta del plugin es estable).
		$hc_module_active = static function ( string $slug ): bool {
			return is_plugin_active( "convoca-{$slug}/convoca-{$slug}.php" );
		};
		$modules = array(
			'members'   => array(
				'label' => __( 'Convoca Members', 'convoca-core' ),
				'desc'  => __( 'Fichas de socios, cuotas, carnets digitales y área personal.', 'convoca-core' ),
				'check' => $hc_module_active( 'members' ),
			),
			'enroll'    => array(
				'label' => __( 'Convoca Enroll', 'convoca-core' ),
				'desc'  => __( 'Inscripciones a actividades, listas de espera y asistencia.', 'convoca-core' ),
				'check' => $hc_module_active( 'enroll' ),
			),
			'gateway'   => array(
				'label' => __( 'Convoca Gateway', 'convoca-core' ),
				'desc'  => __( 'Cobro de cuotas con tarjeta o Bizum vía Redsys.', 'convoca-core' ),
				'check' => $hc_module_active( 'gateway' ),
			),
			'shifts'    => array(
				'label' => __( 'Convoca Shifts', 'convoca-core' ),
				'desc'  => __( 'Turnos de voluntariado con calendario y check-in.', 'convoca-core' ),
				'check' => $hc_module_active( 'shifts' ),
			),
			'publisher' => array(
				'label' => __( 'Convoca Publisher', 'convoca-core' ),
				'desc'  => __( 'Publica en 7 redes sociales desde una sola entrada.', 'convoca-core' ),
				'check' => $hc_module_active( 'publisher' ),
			),
			'assistant' => array(
				'label' => __( 'Convoca Assistant', 'convoca-core' ),
				'desc'  => __( 'Asistente conversacional local, sin IA, sin enviar datos.', 'convoca-core' ),
				'check' => $hc_module_active( 'assistant' ),
			),
		);
		?>
		<h2><?php esc_html_e( '6. El Ecosistema Convoca', 'convoca-core' ); ?></h2>
		<p style="color:#64748b;">
			<?php esc_html_e( 'Cada módulo es un plugin independiente: funciona solo o en conjunto con el resto. Todo es open source y los datos viven en tu propia web.', 'convoca-core' ); ?>
		</p>
		<div style="margin:24px 0;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;">
			<?php foreach ( $modules as $slug => $m ) : ?>
				<div style="display:flex;align-items:center;gap:14px;padding:14px 18px;border-bottom:1px solid #f1f5f9;">
					<span style="font-size:18px;width:24px;text-align:center;"><?php echo $m['check'] ? '✅' : '⬜'; ?></span>
					<div style="flex:1;">
						<strong style="color:#1e293b;"><?php echo esc_html( $m['label'] ); ?></strong>
						<div style="font-size:13px;color:#64748b;"><?php echo esc_html( $m['desc'] ); ?></div>
					</div>
					<span style="font-size:12px;font-weight:600;padding:4px 10px;border-radius:20px;background:<?php echo $m['check'] ? '#ecfdf5' : '#f8fafc'; ?>;color:<?php echo $m['check'] ? '#059669' : '#94a3b8'; ?>;">
						<?php echo $m['check'] ? esc_html__( 'Activo', 'convoca-core' ) : esc_html__( 'No instalado', 'convoca-core' ); ?>
					</span>
				</div>
			<?php endforeach; ?>
		</div>
		<p style="color:#64748b;">
			<?php esc_html_e( '💡 Importar tus socios actuales: ve a Miembros → Importar CSV y arrastra tu hoja de Excel. En segundos tendrás todas las fichas digitales.', 'convoca-core' ); ?>
		</p>
		<p style="color:#64748b;">
			<?php esc_html_e( '¿Necesitas instalar algún módulo? Encuéntralos en WordPress.org o en getconvoca.app — los módulos base son gratuitos para siempre.', 'convoca-core' ); ?>
		</p>
		<?php
		$this->step_nav( 6, true );
	}

	/* ── Step 7: Summary ── */

	private function step_summary(): void {
		$status = $this->get_completion_status();
		?>
		<h2><?php esc_html_e( '7. Finalización', 'convoca-core' ); ?></h2>

		<div style="margin:30px 0;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">

			<?php
			$rows = $this->summary_rows();
			foreach ( $rows as $r ) :
				$ok  = $r['ok'];
				$ico = $ok ? '✓' : '⚠';
				$col = $ok ? '#10b981' : '#f59e0b';
				?>
				<div style="display:flex;align-items:flex-start;gap:15px;padding:16px 18px;background:<?php echo $ok ? '#ffffff' : '#fffbeb'; ?>;border-bottom:1px solid #f1f5f9;">
					<span style="font-size:18px;line-height:1.4;color:<?php echo esc_attr( $col ); ?>;"><?php echo esc_html( $ico ); ?></span>
					<div style="flex:1;">
						<strong style="color:#1e293b;"><?php echo esc_html( $r['title'] ); ?></strong>
						<div style="margin-top:4px;color:#475569;font-size:13px;line-height:1.6;">
							<?php
							foreach ( $r['detail'] as $line ) {
								echo '<div>' . esc_html( $line ) . '</div>';
							}
							if ( ! $ok && ! empty( $r['missing'] ) ) {
								echo '<div style="color:#b45309;margin-top:2px;">⚠ ' . esc_html( $r['missing'] ) . '</div>';
							}
							?>
						</div>
					</div>
				</div>
			<?php endforeach; ?>

		</div>

		<?php if ( $status['is_ready'] ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:15px;">
				<?php wp_nonce_field( 'convoca_wizard_complete' ); ?>
				<input type="hidden" name="action" value="convoca_wizard_complete">
				<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Finalizar configuración', 'convoca-core' ); ?></button>
			</form>
		<?php else : ?>
			<p>⚠ <?php esc_html_e( 'Faltan requisitos obligatorios. Revisa los pasos marcados en ámbar.', 'convoca-core' ); ?></p>
		<?php endif; ?>
		<?php $this->step_nav( 7, true ); ?>
		<?php
	}

	/**
	 * Construye las filas del resumen: un bloque por paso del asistente.
	 *
	 * @return array[] Cada fila: title, ok, detail[], missing.
	 */
	private function summary_rows(): array {
		global $wpdb;
		$rows = array();

		// ── 1. Infraestructura ──
		$tables_ok = true;
		foreach ( array( 'convoca_logs', 'convoca_locks' ) as $t ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}{$t}'" ) !== $wpdb->prefix . $t ) {
				$tables_ok = false;
			}
		}
		$rows['1'] = array(
			'title'   => __( '1. Infraestructura', 'convoca-core' ),
			'ok'      => $tables_ok && class_exists( '\Convoca\Core\Utils' ),
			'detail'  => array(
				class_exists( '\Convoca\Core\Utils' ) ? __( 'Convoca Common activo.', 'convoca-core' ) : __( 'Convoca Common NO activo.', 'convoca-core' ),
				$tables_ok ? __( 'Tablas de base de datos creadas.', 'convoca-core' ) : __( 'Faltan tablas de base de datos.', 'convoca-core' ),
			),
			'missing' => $tables_ok ? '' : __( 'Ejecuta el paso 1 para crear la infraestructura.', 'convoca-core' ),
		);

		// ── 2. Páginas ──
		$page_defs = array(
			'alta-socios' => __( 'Alta de Socios', 'convoca-core' ),
			'panel-socio' => __( 'Mi Panel de Socio', 'convoca-core' ),
			'pago'        => __( 'Página de Pago', 'convoca-core' ),
		);
		$pages_ok   = true;
		$pages_line = array();
		foreach ( $page_defs as $slug => $title ) {
			$p = get_page_by_path( $slug );
			if ( ! $p ) {
				$pages_ok = false;
			}
			$pages_line[] = ( $p ? '✅' : '⚠' ) . ' ' . $title;
		}
		$rows['2'] = array(
			'title'   => __( '2. Páginas del Sistema', 'convoca-core' ),
			'ok'      => $pages_ok,
			'detail'  => $pages_line,
			'missing' => $pages_ok ? '' : __( 'Crea las páginas pendientes en el paso 2.', 'convoca-core' ),
		);

		// ── 3. Planes de membresía ──
		$plans     = class_exists( '\Convoca\Members\CPT_Miembro' ) ? \Convoca\Members\CPT_Miembro::get_plans() : array();
		$real_mods = array( 'Numerario', 'Familiar', 'Juvenil' );
		$active_plans = array();
		foreach ( $plans as $key => $p ) {
			$mod = $p['modalidad'] ?? 'Numerario';
			if ( ! in_array( $mod, $real_mods, true ) ) {
				continue; // Selectores de modalidad, no planes de cobro.
			}
			$is_active = ! isset( $p['active'] ) || false !== $p['active'];
			if ( $is_active ) {
				$price         = isset( $p['price'] ) ? (float) $p['price'] : 0;
				$active_plans[] = ( $p['label'] ?? $key ) . ' · ' . number_format_i18n( $price, 2 ) . ' €';
			}
		}
		$rows['3'] = array(
			'title'  => __( '3. Planes de Membresía', 'convoca-core' ),
			'ok'     => ! empty( $active_plans ),
			'detail' => empty( $active_plans )
				? array( __( 'Sin planes activos.', 'convoca-core' ) )
				: array_merge(
					array( /* translators: %d: número de planes activos */ sprintf( __( '%d planes activos:', 'convoca-core' ), count( $active_plans ) ) ),
					$active_plans
				),
			'missing' => empty( $active_plans ) ? __( 'Activa al menos un plan en el paso 3.', 'convoca-core' ) : '',
		);

		// ── 4. Redsys / Pagos ──
		$gw      = get_option( 'convoca_gateway_settings', array() );
		$mc      = $gw['merchant_code'] ?? '';
		$gw_ok   = ! empty( $mc );
		$rows['4'] = array(
			'title'   => __( '4. Pagos (Redsys)', 'convoca-core' ),
			'ok'      => $gw_ok,
			'detail'  => array( $gw_ok ? __( 'Merchant Code configurado.', 'convoca-core' ) : __( 'Merchant Code vacío.', 'convoca-core' ) ),
			'missing' => $gw_ok ? '' : __( 'Completa la configuración de Redsys en el paso 4.', 'convoca-core' ),
		);

		// ── 5. Turnos ──
		$ap = get_option( 'convoca_shifts_hora_apertura', '' );
		$ci = get_option( 'convoca_shifts_hora_cierre', '' );
		$rows['5'] = array(
			'title'  => __( '5. Turnos de Voluntariado', 'convoca-core' ),
			'ok'     => ! empty( $ap ) && ! empty( $ci ),
			'detail' => array( sprintf( /* translators: 1: opening time, 2: closing time. */ __( 'Horario del centro: %1$s – %2$s', 'convoca-core' ), $ap ?: '--:--', $ci ?: '--:--' ) ),
			'missing' => ( empty( $ap ) || empty( $ci ) ) ? __( 'Define el horario en el paso 5.', 'convoca-core' ) : '',
		);

		// ── 6. Ecosistema de módulos ──
		$hc_module_active = static function ( string $slug ): bool {
			return is_plugin_active( "convoca-{$slug}/convoca-{$slug}.php" );
		};
		$mod_defs = array(
			'members'   => __( 'Convoca Members', 'convoca-core' ),
			'enroll'    => __( 'Convoca Enroll', 'convoca-core' ),
			'gateway'   => __( 'Convoca Gateway', 'convoca-core' ),
			'shifts'    => __( 'Convoca Shifts', 'convoca-core' ),
			'publisher' => __( 'Convoca Publisher', 'convoca-core' ),
			'assistant' => __( 'Convoca Assistant', 'convoca-core' ),
		);
		$active_mods = array();
		foreach ( $mod_defs as $slug => $label ) {
			if ( $hc_module_active( $slug ) ) {
				$active_mods[] = $label;
			}
		}
		$all_active = count( $active_mods ) === count( $mod_defs );
		$rows['6'] = array(
			'title'  => __( '6. Ecosistema Convoca', 'convoca-core' ),
			'ok'     => $all_active,
			'detail' => array(
				$all_active
					? __( 'Los 6 módulos están instalados y activos.', 'convoca-core' )
					/* translators: %d: número de módulos activos de 6 */
					: sprintf( __( '%d de 6 módulos activos.', 'convoca-core' ), count( $active_mods ) ),
				$all_active ? '' : __( 'Faltan: ', 'convoca-core' ) . implode( ', ', array_diff( array_values( $mod_defs ), $active_mods ) ),
			),
			'missing' => $all_active ? '' : __( 'Activa los módulos restantes en el paso 6.', 'convoca-core' ),
		);

		return $rows;
	}

	private function get_completion_status(): array {
		return array( 'is_ready' => $this->is_config_complete() );
	}

	public function handle_save(): void {
		check_admin_referer( 'convoca_wizard_save' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Acceso denegado.', 'convoca-core' ) );
		}
		$step = (int) $_POST['wizard_step'];
		if ( $step === 3 ) {
			// Guardar planes de membresía: activo, nombre visible, id corto (slug),
			// modalidad y precio. Al renombrar un slug se migran los miembros que lo usan.
			if ( class_exists( '\Convoca\Members\CPT_Miembro' ) ) {
				$plans = \Convoca\Members\CPT_Miembro::get_plans();
				$post  = isset( $_POST['convoca_plans'] ) ? (array) wp_unslash( $_POST['convoca_plans'] ) : array();
				// Los planes editables son los de modalidad real (Numerario/Familiar/Juvenil).
				$real_mods   = array( 'Numerario', 'Familiar', 'Juvenil' );
				$new_plans   = array();
				$slug_moves  = array(); // slug_viejo => slug_nuevo para migrar metas.
				$used_slugs  = array( 'familiar', 'juvenil' ); // Selectores internos: reservados.
				$collision   = '';

				foreach ( $plans as $key => $plan ) {
					$mod = $plan['modalidad'] ?? 'Numerario';
					if ( ! in_array( $mod, $real_mods, true ) ) {
						$new_plans[ $key ] = $plan; // Selectores: intactos.
						continue;
					}
					if ( ! isset( $post[ $key ] ) ) {
						$plan['active'] = false; // Checkbox desmarcado → no viaja → desactivar.
						$new_plans[ $key ] = $plan;
						continue;
					}
					$data = $post[ $key ];
					// Slug nuevo: validar formato y unicidad.
					$new_slug = isset( $data['slug'] ) ? sanitize_key( $data['slug'] ) : $key;
					if ( '' === $new_slug ) {
						$new_slug = $key;
					}
					if ( $new_slug !== $key ) {
						if ( in_array( $new_slug, $used_slugs, true ) || isset( $new_plans[ $new_slug ] ) || isset( $plans[ $new_slug ] ) ) {
							$collision = $new_slug;
							break;
						}
						$slug_moves[ $key ] = $new_slug;
					}
					$used_slugs[] = $new_slug;

					$plan['active'] = ! empty( $data['active'] );
					if ( isset( $data['label'] ) ) {
						$plan['label'] = sanitize_text_field( $data['label'] );
					}
					if ( isset( $data['modalidad'] ) && in_array( $data['modalidad'], $real_mods, true ) ) {
						$plan['modalidad'] = $data['modalidad'];
					}
					if ( isset( $data['price'] ) && is_numeric( $data['price'] ) ) {
						$plan['price'] = (float) $data['price'];
					}
					$new_plans[ $new_slug ] = $plan;
				}

				if ( '' !== $collision ) {
					// No guardar; volver al paso 3 con aviso.
					add_settings_error( 'convoca_wizard', 'slug_collision', sprintf( /* translators: %s: slug en conflicto */ __( 'El ID corto «%s» ya está en uso. Elige otro.', 'convoca-core' ), $collision ), 'error' );
					set_transient( 'convoca_wizard_plans_notice', __( 'No se guardó: el ID corto introducido ya existe.', 'convoca-core' ), 30 );
					wp_safe_redirect( admin_url( 'admin.php?page=conv-setup-wizard&step=3&plans_error=1' ) );
					exit;
				}

				// Migrar miembros que referencien el slug antiguo.
				if ( ! empty( $slug_moves ) && function_exists( 'update_post_meta' ) ) {
					global $wpdb;
					foreach ( $slug_moves as $old => $new ) {
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- migración puntual de metas.
						$wpdb->query(
							$wpdb->prepare(
								"UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE meta_key IN ('_convoca_plan','_convoca_sub_plan') AND meta_value = %s",
								$new,
								$old
							)
						);
					}
				}

				update_option( 'convoca_members_plans', $new_plans );
			}
		}
		if ( $step === 4 ) {
			$settings                  = get_option( 'convoca_gateway_settings', array() );
			$settings['merchant_code'] = sanitize_text_field( wp_unslash( $_POST['merchant_code'] ) );
			$settings['secret_key']    = sanitize_text_field( wp_unslash( $_POST['secret_key'] ) );
			update_option( 'convoca_gateway_settings', $settings );
		}
		if ( $step === 5 ) {
			update_option( 'convoca_shifts_hora_apertura', sanitize_text_field( wp_unslash( $_POST['convoca_apertura'] ) ) );
			update_option( 'convoca_shifts_hora_cierre', sanitize_text_field( wp_unslash( $_POST['convoca_cierre'] ) ) );
		}
		update_option( self::PROGRESS_OPTION, $step + 1 );
		wp_safe_redirect( admin_url( 'admin.php?page=conv-setup-wizard&step=' . ( $step + 1 ) ) );
		exit;
	}

	public function handle_complete(): void {
		check_admin_referer( 'convoca_wizard_complete' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Acceso denegado.', 'convoca-core' ) );
		}
		update_option( self::COMPLETED_OPTION, 1 );
		delete_option( self::PROGRESS_OPTION );
		wp_safe_redirect( admin_url() );
		exit;
	}

	private function step_nav( int $current, bool $can_continue ): void {
		echo '<div style="margin-top:35px;display:flex;justify-content:space-between;">';
		if ( $current > 1 ) {
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=conv-setup-wizard&step=' . ( $current - 1 ) ) ) . '" class="button button-secondary">' . esc_html__( 'Anterior', 'convoca-core' ) . '</a>';
		} else {
			echo '<div></div>';
		}
		if ( $can_continue && $current < 7 ) {
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=conv-setup-wizard&step=' . ( $current + 1 ) ) ) . '" class="button button-primary">' . esc_html__( 'Siguiente', 'convoca-core' ) . '</a>';
		}
		echo '</div>';
	}
}
