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
		add_action( 'wp_head', array( __CLASS__, 'imprimir_colores' ), 999 );
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

		// Colores de los componentes (#65): el sitio decide su acento. Vacío = el valor neutro del plugin.
		foreach ( array( 'color_acento', 'color_acento_fuerte' ) as $clave ) {
			// `input type=color` siempre manda un valor: para volver al neutro hay una casilla.
			if ( ! empty( $input[ 'limpiar_' . $clave ] ) ) {
				$datos[ $clave ] = '';
				continue;
			}
			$color           = sanitize_hex_color( (string) ( $input[ $clave ] ?? '' ) );
			$datos[ $clave ] = $color ? $color : '';
		}

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
	 * La forma del formulario son muchas líneas de marcado y pocas de lógica: PHPMD cuenta el HTML como
	 * si fuera código y da un falso positivo. Se silencia aquí, no en el resto de la clase.
	 *
	 * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
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
			<p class="description"><?php esc_html_e( 'Valores de esta instalación: los enlaces y textos que el tema deja vacíos a propósito. Si un mu-plugin o el tema los ponen por código, manda el código.', 'convoca-core' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>
				<table class="form-table" role="presentation">
					<?php
					self::fila_mapa( 'site_links', __( 'Redes y enlaces del sitio', 'convoca-core' ), __( 'Una por línea: «servicio | URL». El servicio es la clave (facebook, instagram, email…).', 'convoca-core' ), 5 );
					self::fila_mapa( 'social_links', __( 'Redes sociales', 'convoca-core' ), __( 'Una por línea: «servicio | URL». Alimenta [convoca_socials] y los tokens del pie.', 'convoca-core' ), 3 );
					self::fila_mapa( 'link_labels', __( 'Etiquetas de esos enlaces', 'convoca-core' ), __( 'Una por línea: «servicio | etiqueta visible».', 'convoca-core' ), 5 );
					self::fila_texto( 'cta_url', __( 'Botón de la cabecera', 'convoca-core' ), 'url' );
					self::fila_texto( 'cta_label', __( 'Texto del botón', 'convoca-core' ), 'text' );
					self::fila_texto( 'cta_heading', __( 'Titular del bloque CTA', 'convoca-core' ), 'text' );
					self::fila_area( 'cta_text', __( 'Texto del bloque CTA', 'convoca-core' ) );
					self::fila_texto( 'volunteer_email', __( 'Correo de voluntariado', 'convoca-core' ), 'email' );
					self::fila_texto( 'copyright_extra', __( 'Aviso del pie', 'convoca-core' ), 'text', 'large-text' );
					self::fila_color( 'color_acento', __( 'Color de acento', 'convoca-core' ), __( 'Botones, enlaces y realces de los componentes de Convoca. Vacío = el valor neutro del producto.', 'convoca-core' ) );
					self::fila_color( 'color_acento_fuerte', __( 'Acento fuerte', 'convoca-core' ), __( 'Para el texto sobre el acento y los estados activos. Vacío = el valor neutro.', 'convoca-core' ) );
					?>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Una fila de mapa (líneas «clave | valor»).
	 *
	 * @param string               $clave       Clave de la opción.
	 * @param string               $titulo      Etiqueta de la fila.
	 * @param string               $ayuda       Texto de ayuda.
	 * @param int                  $filas       Altura.
	 * @return void
	 */
	private static function fila_mapa( string $clave, string $titulo, string $ayuda, int $filas ): void {
		$datos = self::datos();
		$id    = 'convoca-' . str_replace( '_', '-', $clave );
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $titulo ); ?></label></th>
			<td>
				<textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( self::OPTION ); ?>[<?php echo esc_attr( $clave ); ?>]" rows="<?php echo esc_attr( (string) $filas ); ?>" class="large-text code"><?php echo esc_textarea( self::a_mapa( $datos[ $clave ] ?? array() ) ); ?></textarea>
				<p class="description"><?php echo esc_html( $ayuda ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Una fila de texto corto.
	 *
	 * @param string               $clave  Clave de la opción.
	 * @param string               $titulo Etiqueta.
	 * @param string               $tipo   Tipo de input.
	 * @param string               $clase  Clase del input.
	 * @return void
	 */
	private static function fila_texto( string $clave, string $titulo, string $tipo, string $clase = 'regular-text' ): void {
		$id = 'convoca-' . str_replace( '_', '-', $clave );
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $titulo ); ?></label></th>
			<td><input type="<?php echo esc_attr( $tipo ); ?>" id="<?php echo esc_attr( $id ); ?>" class="<?php echo esc_attr( $clase ); ?>" name="<?php echo esc_attr( self::OPTION ); ?>[<?php echo esc_attr( $clave ); ?>]" value="<?php echo esc_attr( self::valor( $clave ) ); ?>"></td>
		</tr>
		<?php
	}

	/**
	 * Una fila de color.
	 *
	 * @param string $clave  Clave de la opción.
	 * @param string $titulo Etiqueta de la fila.
	 * @param string $ayuda  Texto de ayuda.
	 * @return void
	 */
	private static function fila_color( string $clave, string $titulo, string $ayuda ): void {
		$valor = (string) self::valor( $clave );
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $clave ); ?>"><?php echo esc_html( $titulo ); ?></label></th>
			<td>
				<input type="color" id="<?php echo esc_attr( $clave ); ?>" name="<?php echo esc_attr( self::OPTION . '[' . $clave . ']' ); ?>" value="<?php echo esc_attr( '' !== $valor ? $valor : '#2271b1' ); ?>">
				<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION . '[limpiar_' . $clave . ']' ); ?>" value="1"> <?php esc_html_e( 'Usar el valor neutro', 'convoca-core' ); ?></label>
				<p class="description"><?php echo esc_html( $ayuda ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Imprime los tokens de color del sitio.
	 *
	 * Solo si hay color configurado: sin él, cada plugin usa su valor neutro y el tema, si es el de
	 * Convoca, pone su paleta.
	 *
	 * @return void
	 */
	public static function imprimir_colores(): void {
		$acento = (string) self::valor( 'color_acento' );
		$fuerte = (string) self::valor( 'color_acento_fuerte' );
		$pares  = array();

		if ( '' !== $acento ) {
			$pares[] = '--conv-accent: ' . $acento;
		}
		if ( '' !== $fuerte ) {
			$pares[] = '--conv-accent-strong: ' . $fuerte;
		}
		if ( array() === $pares ) {
			return;
		}

		// `:root:root` gana al `:root` de las hojas del plugin sin depender del orden de carga: los estilos
		// encolados se imprimen después de este enganche y, con la misma especificidad, ganaban ellos.
		echo '<style id="convoca-site-colors">:root:root{' . esc_html( implode( ';', $pares ) ) . ';}</style>' . "\n";
	}

	/**
	 * Una fila de texto largo.
	 *
	 * @param string               $clave  Clave de la opción.
	 * @param string               $titulo Etiqueta.
	 * @return void
	 */
	private static function fila_area( string $clave, string $titulo ): void {
		$id = 'convoca-' . str_replace( '_', '-', $clave );
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $titulo ); ?></label></th>
			<td><textarea id="<?php echo esc_attr( $id ); ?>" class="large-text" rows="3" name="<?php echo esc_attr( self::OPTION ); ?>[<?php echo esc_attr( $clave ); ?>]"><?php echo esc_textarea( self::valor( $clave ) ); ?></textarea></td>
		</tr>
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
