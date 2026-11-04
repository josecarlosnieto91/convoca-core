<?php
/**
 * Valores del sitio: redes, etiquetas, CTA y claves heredadas de los datos de evento.
 *
 * Son datos de ESTA instalación, no del producto. Vivían en un mu-plugin por sitio; aquí tienen una
 * pantalla (Convoca → Sitio) y una opción, y los filtros del tema se mantienen: un sitio que ya los
 * ponía por código sigue mandando.
 *
 * @package Convoca\Core
 */

namespace Convoca\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pantalla y filtros de los valores del sitio.
 */
class Site_Settings {

	/**
	 * Opción con todos los valores.
	 */
	public const OPTION = 'convoca_site_settings';

	/**
	 * Página del panel.
	 */
	public const PAGE = 'convoca-site-settings';

	/**
	 * Grupo de ajustes.
	 */
	public const GROUP = 'convoca_site_settings_group';

	/**
	 * Engancha pantalla, ajuste y filtros.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ), 20 );
		add_action( 'admin_init', array( __CLASS__, 'register_setting' ) );
		self::aplicar_filtros();
	}

	/**
	 * Añade la pantalla bajo el menú de Convoca.
	 *
	 * @return void
	 */
	public static function add_page(): void {
		add_submenu_page(
			'convoca-core',
			__( 'Sitio', 'convoca-core' ),
			__( 'Sitio', 'convoca-core' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Registra la opción con su saneado.
	 *
	 * @return void
	 */
	public static function register_setting(): void {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Los valores guardados.
	 *
	 * @return array<string, mixed>
	 */
	public static function datos(): array {
		$datos = get_option( self::OPTION, array() );

		return is_array( $datos ) ? $datos : array();
	}

	/**
	 * Un valor concreto, o cadena vacía.
	 *
	 * @param string $clave Clave.
	 * @return string
	 */
	public static function valor( string $clave ): string {
		$datos = self::datos();

		return isset( $datos[ $clave ] ) && is_scalar( $datos[ $clave ] ) ? (string) $datos[ $clave ] : '';
	}

	/**
	 * Los filtros del tema y de los datos de evento, alimentados por la opción.
	 *
	 * Se añaden siempre; si la opción está vacía devuelven el valor que traían (`$valor`), así que el
	 * comportamiento por defecto no cambia. Un filtro del sitio añadido después manda.
	 *
	 * @return void
	 */
	public static function aplicar_filtros(): void {
		$mapas = array(
			'convoca_theme_site_links'       => 'site_links',
			'convoca_theme_link_labels'      => 'link_labels',
			'convoca_event_meta_legacy_keys' => 'legacy_keys',
		);

		foreach ( $mapas as $gancho => $clave ) {
			add_filter(
				$gancho,
				static function ( $valor ) use ( $clave ) {
					$guardado = self::datos()[ $clave ] ?? array();

					return is_array( $guardado ) && array() !== $guardado ? $guardado : $valor;
				}
			);
		}

		$textos = array(
			'convoca_theme_cta_url'         => 'cta_url',
			'convoca_theme_cta_label'       => 'cta_label',
			'convoca_theme_cta_heading'     => 'cta_heading',
			'convoca_theme_cta_text'        => 'cta_text',
			'convoca_theme_volunteer_email' => 'volunteer_email',
			'convoca_theme_copyright_extra' => 'copyright_extra',
		);

		foreach ( $textos as $gancho => $clave ) {
			add_filter(
				$gancho,
				static function ( $valor ) use ( $clave ) {
					$guardado = self::valor( $clave );

					return '' !== $guardado ? $guardado : $valor;
				}
			);
		}
	}

	/**
	 * Sanea lo que llega del formulario.
	 *
	 * @param mixed $input Datos.
	 * @return array<string, mixed>
	 */
	public static function sanitize( mixed $input ): array {
		if ( ! is_array( $input ) ) {
			return array();
		}

		$input = wp_unslash( $input );
		$datos = array();

		$datos['site_links']      = self::mapa( (string) ( $input['site_links'] ?? '' ), true );
		$datos['social_links']    = self::mapa( (string) ( $input['social_links'] ?? '' ), true );
		$datos['link_labels']     = self::mapa( (string) ( $input['link_labels'] ?? '' ), false );
		$datos['cta_url']         = esc_url_raw( (string) ( $input['cta_url'] ?? '' ) );
		$datos['cta_label']       = sanitize_text_field( (string) ( $input['cta_label'] ?? '' ) );
		$datos['cta_heading']     = sanitize_text_field( (string) ( $input['cta_heading'] ?? '' ) );
		$datos['cta_text']        = sanitize_textarea_field( (string) ( $input['cta_text'] ?? '' ) );
		$datos['volunteer_email'] = sanitize_email( (string) ( $input['volunteer_email'] ?? '' ) );
		$datos['copyright_extra'] = wp_kses_post( (string) ( $input['copyright_extra'] ?? '' ) );

		// Las claves de legado no se editan a mano en la pantalla: las carga el comando de una pasada.
		$previas = self::datos();
		if ( isset( $previas['legacy_keys'] ) && is_array( $previas['legacy_keys'] ) ) {
			$datos['legacy_keys'] = $previas['legacy_keys'];
		}

		return $datos;
	}

	/**
	 * Convierte líneas «clave | valor» en un mapa.
	 *
	 * @param string $texto  Líneas.
	 * @param bool   $es_url Si el valor es una URL.
	 * @return array<string, string>
	 */
	private static function mapa( string $texto, bool $es_url ): array {
		$mapa = array();

		foreach ( (array) preg_split( '/\R/', $texto ) as $linea ) {
			$linea = trim( (string) $linea );
			if ( '' === $linea ) {
				continue;
			}

			$partes = array_map( 'trim', explode( '|', $linea, 2 ) );
			if ( count( $partes ) < 2 ) {
				continue;
			}

			$clave = sanitize_key( $partes[0] );
			$valor = $es_url ? esc_url_raw( $partes[1] ) : sanitize_text_field( $partes[1] );

			if ( '' !== $clave && '' !== $valor ) {
				$mapa[ $clave ] = $valor;
			}
		}

		return $mapa;
	}

	/**
	 * Pinta la pantalla.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos para ver esta página.', 'convoca-core' ) );
		}

		$datos = self::datos();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Sitio', 'convoca-core' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Valores de esta instalación: los enlaces y textos que el tema deja vacíos a propósito. Si un mu-plugin o el tema los pone por código, manda el código.', 'convoca-core' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="convoca-site-links"><?php esc_html_e( 'Redes y enlaces del sitio', 'convoca-core' ); ?></label></th>
						<td>
							<textarea id="convoca-site-links" name="<?php echo esc_attr( self::OPTION ); ?>[site_links]" rows="5" class="large-text code"><?php echo esc_textarea( self::a_mapa( $datos['site_links'] ?? array() ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Una por línea: «servicio | URL». El servicio es la clave (facebook, instagram, email…).', 'convoca-core' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="convoca-social-links"><?php esc_html_e( 'Redes sociales', 'convoca-core' ); ?></label></th>
						<td>
							<textarea id="convoca-social-links" name="<?php echo esc_attr( self::OPTION ); ?>[social_links]" rows="3" class="large-text code"><?php echo esc_textarea( self::a_mapa( $datos['social_links'] ?? array() ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Una por línea: «servicio | URL». Alimenta [convoca_socials] y los tokens del pie.', 'convoca-core' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="convoca-link-labels"><?php esc_html_e( 'Etiquetas de esos enlaces', 'convoca-core' ); ?></label></th>
						<td>
							<textarea id="convoca-link-labels" name="<?php echo esc_attr( self::OPTION ); ?>[link_labels]" rows="5" class="large-text code"><?php echo esc_textarea( self::a_mapa( $datos['link_labels'] ?? array() ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Una por línea: «servicio | etiqueta visible».', 'convoca-core' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="convoca-cta-url"><?php esc_html_e( 'Botón de la cabecera', 'convoca-core' ); ?></label></th>
						<td>
							<input type="url" id="convoca-cta-url" class="regular-text" name="<?php echo esc_attr( self::OPTION ); ?>[cta_url]" value="<?php echo esc_attr( self::valor( 'cta_url' ) ); ?>">
							<input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION ); ?>[cta_label]" value="<?php echo esc_attr( self::valor( 'cta_label' ) ); ?>" placeholder="<?php esc_attr_e( 'Texto del botón', 'convoca-core' ); ?>">
							<p class="description"><?php esc_html_e( 'URL y texto del botón de la cabecera.', 'convoca-core' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="convoca-cta-heading"><?php esc_html_e( 'Bloque CTA', 'convoca-core' ); ?></label></th>
						<td>
							<input type="text" id="convoca-cta-heading" class="regular-text" name="<?php echo esc_attr( self::OPTION ); ?>[cta_heading]" value="<?php echo esc_attr( self::valor( 'cta_heading' ) ); ?>">
							<textarea class="large-text" rows="3" name="<?php echo esc_attr( self::OPTION ); ?>[cta_text]"><?php echo esc_textarea( self::valor( 'cta_text' ) ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="convoca-volunteer-email"><?php esc_html_e( 'Correo de voluntariado', 'convoca-core' ); ?></label></th>
						<td>
							<input type="email" id="convoca-volunteer-email" class="regular-text" name="<?php echo esc_attr( self::OPTION ); ?>[volunteer_email]" value="<?php echo esc_attr( self::valor( 'volunteer_email' ) ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="convoca-copyright"><?php esc_html_e( 'Aviso del pie', 'convoca-core' ); ?></label></th>
						<td>
							<input type="text" id="convoca-copyright" class="large-text" name="<?php echo esc_attr( self::OPTION ); ?>[copyright_extra]" value="<?php echo esc_attr( self::valor( 'copyright_extra' ) ); ?>">
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Un mapa de vuelta a texto para el formulario.
	 *
	 * @param mixed $mapa Mapa.
	 * @return string
	 */
	private static function a_mapa( mixed $mapa ): string {
		$lineas = array();

		foreach ( (array) $mapa as $clave => $valor ) {
			if ( is_scalar( $valor ) && '' !== (string) $valor ) {
				$lineas[] = $clave . ' | ' . $valor;
			}
		}

		return implode( "\n", $lineas );
	}
}
