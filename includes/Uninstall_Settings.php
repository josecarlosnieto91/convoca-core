<?php
/**
 * Ajuste de desinstalación: qué hacer con los datos.
 *
 * Vive en core y no en cada plugin para no repetirlo seis veces. Los uninstall.php de
 * todos los plugins leen esta misma opción (convoca_uninstall_keep_data), de modo que la
 * decisión se toma una vez y vale para todo el ecosistema.
 *
 * Prioridad: la constante CONVOCA_KEEP_DATA_ON_UNINSTALL en wp-config.php manda sobre
 * este ajuste (es la vía para automatizar despliegues).
 *
 * @package Convoca\Core
 */

namespace Convoca\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Nombre de la opción compartida por todos los uninstall.php.
 */
const CONVOCA_KEEP_DATA_OPTION = 'convoca_uninstall_keep_data';

/**
 * Registra la página de ajustes en el menú de Convoca.
 */
function convoca_register_uninstall_settings(): void {
	add_submenu_page(
		'convoca-core',
		__( 'Desinstalación', 'convoca-core' ),
		__( 'Desinstalación', 'convoca-core' ),
		'manage_options',
		'convoca-uninstall',
		'Convoca\Core\convoca_uninstall_settings_page'
	);
}
add_action( 'admin_menu', 'Convoca\Core\convoca_register_uninstall_settings', 20 );

/**
 * Guarda el ajuste. Se hace a mano (sin register_setting) para que quede explícito qué
 * valores se aceptan y no depender del saneado por defecto.
 */
function convoca_maybe_save_uninstall_settings(): void {
	if ( ! isset( $_POST['convoca_uninstall_nonce'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['convoca_uninstall_nonce'] ) ), 'convoca_uninstall_save' ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'No tienes permisos.', 'convoca-core' ) );
	}

	$conservar = isset( $_POST['convoca_uninstall_keep_data'] ) ? 1 : 0;
	update_option( CONVOCA_KEEP_DATA_OPTION, $conservar );
}
add_action( 'admin_init', 'Convoca\Core\convoca_maybe_save_uninstall_settings' );

/**
 * Página de ajustes.
 */
function convoca_uninstall_settings_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'No tienes permisos.', 'convoca-core' ) );
	}

	$conservar    = 1 === (int) get_option( CONVOCA_KEEP_DATA_OPTION, 0 );
	$por_constante = defined( 'CONVOCA_KEEP_DATA_ON_UNINSTALL' ) && CONVOCA_KEEP_DATA_ON_UNINSTALL;
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Desinstalación de Convoca', 'convoca-core' ); ?></h1>

		<p>
			<?php esc_html_e( 'Qué debe pasar con los datos cuando se desinstale un plugin de Convoca.', 'convoca-core' ); ?>
		</p>

		<?php if ( $por_constante ) : ?>
			<div class="notice notice-warning inline">
				<p>
					<strong><?php esc_html_e( 'Aviso:', 'convoca-core' ); ?></strong>
					<?php esc_html_e( 'la constante CONVOCA_KEEP_DATA_ON_UNINSTALL está definida en wp-config.php y fuerza «conservar los datos». Mientras esté puesta, este ajuste no tiene efecto.', 'convoca-core' ); ?>
				</p>
			</div>
		<?php endif; ?>

		<form method="post">
			<?php wp_nonce_field( 'convoca_uninstall_save', 'convoca_uninstall_nonce' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Al desinstalar', 'convoca-core' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="checkbox" name="convoca_uninstall_keep_data" value="1" <?php checked( $conservar ); ?> />
								<?php esc_html_e( 'Conservar los datos (recomendado si vas a reinstalar)', 'convoca-core' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Marcado: se borran los ficheros del plugin pero se quedan sus opciones, tablas, inscripciones y ficheros subidos. Sin marcar: la desinstalación borra todo y no se puede deshacer.', 'convoca-core' ); ?>
							</p>
						</fieldset>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
