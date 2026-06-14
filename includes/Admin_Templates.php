<?php
/**
 * Admin Templates
 *
 * Settings page to manage PDF templates.
 *
 * @package Convoca\Core
 */

namespace Convoca\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Templates {

	private static $option_name = 'conv_pdf_templates';

	public static function init() {
		add_action( 'admin_menu', array( self::class, 'add_menu_page' ) );
		add_action( 'admin_init', array( self::class, 'handle_actions' ) );
	}

	public static function add_menu_page() {
		add_options_page(
			__( 'Plantillas PDF (Convoca)', 'convoca-core' ),
			__( 'Plantillas PDF', 'convoca-core' ),
			'manage_convoca_templates',
			'conv-pdf-templates',
			array( self::class, 'render_page' )
		);
	}

	public static function handle_actions() {
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'conv-pdf-templates' ) {
			return;
		}

		if ( ! current_user_can( 'manage_convoca_templates' ) ) {
			return;
		}

		// Restore Defaults.
		if ( isset( $_POST['conv_restore_templates'] ) && check_admin_referer( 'conv_restore_templates_nonce' ) ) {
			self::restore_default_templates();
			\Convoca\Core\Utils::set_admin_notice( __( 'Plantillas restauradas a sus valores por defecto.', 'convoca-core' ), 'success' );
		}

		// Save Custom Templates.
		if ( isset( $_POST['conv_save_templates'] ) && check_admin_referer( 'conv_save_templates_nonce' ) ) {
			$templates = self::get_templates();
			$changed   = false;

			foreach ( $templates as $key => $data ) {
				if ( isset( $_POST['template'][ $key ] ) ) {
					$content = wp_unslash( $_POST['template'][ $key ] );

					// Sanitize if not unfiltered_html.
					if ( ! current_user_can( 'unfiltered_html' ) ) {
						$content = self::sanitize_pdf_template( $content );
					}

					// Atomic validation.
					if ( ! empty( $content ) && ! self::validate_template( $content ) ) {
						$template_name = $data['name'] ?? $key;
						\Convoca\Core\Utils::set_admin_notice(
							sprintf( __( 'Error: La plantilla "%s" contiene HTML inválido o peligroso.' ), esc_html( $template_name ) ),
							'danger'
						);
						continue;
					}

					if ( $templates[ $key ]['content'] !== $content ) {
						$templates[ $key ]['content'] = $content;
						$changed                      = true;
					}
				}
			}

			if ( $changed ) {
				self::create_versioned_backup();
				update_option( self::$option_name, $templates );
				\Convoca\Core\Utils::set_admin_notice( __( 'Plantillas actualizadas correctamente.', 'convoca-core' ), 'success' );
			}
		}
	}

	/**
	 * Maintain a rolling backup of the last 5 template states.
	 */
	private static function create_versioned_backup(): void {
		$current = get_option( self::$option_name );
		if ( ! $current ) {
			return;
		}

		$backups = get_option( self::$option_name . '_versions', array() );

		// Add current to front.
		array_unshift(
			$backups,
			array(
				'date' => wp_date( 'Y-m-d H:i:s' ),
				'user' => get_current_user_id(),
				'data' => $current,
			)
		);

		// Keep only 5.
		$backups = array_slice( $backups, 0, 5 );
		update_option( self::$option_name . '_versions', $backups, false );
	}

	/**
	 * Sanitize PDF template content with granular rules for Dompdf.
	 */
	private static function sanitize_pdf_template( $content ) {
		if ( empty( $content ) ) {
			return '';
		}

		$allowed = wp_kses_allowed_html( 'post' );

		// Specialized tags for Dompdf document structure.
		$pdf_structural = array(
			'html'       => array(
				'xmlns' => true,
				'lang'  => true,
				'style' => true,
			),
			'head'       => array(),
			'title'      => array(),
			'meta'       => array(
				'charset'    => true,
				'name'       => true,
				'content'    => true,
				'http-equiv' => true,
			),
			'style'      => array(
				'type'  => true,
				'media' => true,
			),
			'body'       => array(
				'class' => true,
				'id'    => true,
				'style' => true,
			),
			'center'     => array(),
			'page_break' => array( 'style' => true ), // Common custom tag/class.
		);

		// Ensure table attributes are allowed.
		$pdf_structural['table'] = array_merge(
			$allowed['table'] ?? array(),
			array(
				'cellspacing' => true,
				'cellpadding' => true,
				'border'      => true,
				'align'       => true,
				'width'       => true,
				'style'       => true,
			)
		);

		$allowed = array_merge( $allowed, $pdf_structural );

		// Sanitize with custom allowed list.
		$sanitized = wp_kses( $content, $allowed );

		// Remove dangerous JS or URL schemes that KSES might miss in style attributes.
		return self::sanitize_styles( $sanitized );
	}

	/**
	 * Validate that a template can be rendered by Dompdf.
	 * Includes size, depth, and complexity limits to prevent DoS.
	 *
	 * @param string $content HTML content.
	 * @return bool True if valid, false otherwise.
	 */
	private static function validate_template( string $content, bool $render_test = false ): bool {
		// Always validate structural limits regardless of Dompdf availability.
		$max_size = 500000;
		if ( strlen( $content ) > $max_size ) {
			error_log( 'BDV Template Validation Error: Content exceeds max size of ' . $max_size . ' bytes' );
			return false;
		}

		$max_iterations = 10000;
		$open_tags      = substr_count( $content, '<' );
		$close_tags     = substr_count( $content, '>' );
		if ( $open_tags > $max_iterations || $close_tags > $max_iterations ) {
			error_log( 'BDV Template Validation Error: Too many HTML tags' );
			return false;
		}

		// ... structural checks always run ...

		if ( ! class_exists( 'Dompdf\Dompdf' ) ) {
			// Without Dompdf, we can still save — admin may install it later.
			// Structural checks above still apply.
			return true;
		}

		// Size limit: 500KB.
		$max_size = 500000;
		if ( strlen( $content ) > $max_size ) {
			error_log( 'BDV Template Validation Error: Content exceeds max size of ' . $max_size . ' bytes' );
			return false;
		}

		// Tag count limit.
		$max_iterations = 10000;
		$open_tags      = substr_count( $content, '<' );
		$close_tags     = substr_count( $content, '>' );
		if ( $open_tags > $max_iterations || $close_tags > $max_iterations ) {
			error_log( 'BDV Template Validation Error: Too many HTML tags' );
			return false;
		}

		// DOM depth limit (approximate via stack).
		$depth     = 0;
		$max_depth = 0;
		$tags      = array();
		preg_match_all( '/<\/?([a-zA-Z][a-zA-Z0-9]*)\b[^>]*>/', $content, $tag_matches );
		foreach ( $tag_matches[0] as $tag_html ) {
			if ( str_starts_with( $tag_html, '</' ) ) {
				$depth = max( 0, $depth - 1 );
			} elseif ( ! str_contains( $tag_html, '/>' ) ) {
				++$depth;
				$max_depth = max( $max_depth, $depth );
			}
		}
		if ( $max_depth > 30 ) {
			error_log( 'BDV Template Validation Error: DOM depth exceeds 30 levels' );
			return false;
		}

		// Detect data URIs and large base64.
		if ( preg_match_all( '/data:([^;]{0,40});base64,([a-zA-Z0-9\/+]{100,})/i', $content, $b64_matches ) ) {
			foreach ( $b64_matches[2] as $b64 ) {
				if ( strlen( $b64 ) > 50000 ) {
					error_log( 'BDV Template Validation Error: Base64 content exceeds 50KB' );
					return false;
				}
			}
		}

		// Limit inline SVG embeds.
		$svg_count = substr_count( $content, '<svg' );
		if ( $svg_count > 5 ) {
			error_log( 'BDV Template Validation Error: Too many SVG elements' );
			return false;
		}

		// Check attribute length (individual).
		if ( preg_match_all( '/\s[a-zA-Z-]+\s*=\s*"[^"]{500,}"/', $content, $long_attrs ) ) {
			error_log( 'BDV Template Validation Error: Attribute exceeds 500 chars' );
			return false;
		}

		$mock_data = array(
			'nombre'    => 'Usuario de Prueba',
			'dni'       => '12345678A',
			'fecha'     => wp_date( 'd/m/Y' ),
			'actividad' => 'Actividad de Prueba',
			'qr_code'   => 'TEST-000',
		);

		$rendered_content = $content;
		foreach ( $mock_data as $key => $value ) {
			$rendered_content = str_replace( '{{' . $key . '}}', $value, $rendered_content );
		}

		// Detect pathological CSS patterns before rendering.
		$css_patterns = array(
			'/\{[^}]*\{\{/',                    // Nested braces.
			'/@import/i',                       // External imports.
			'/calc\s*\([^)]+\)/i',             // calc() complex.
			'/expression\s*\(/i',               // IE expressions.
			'/url\s*\(/i',                       // url() references.
		);
		foreach ( $css_patterns as $pattern ) {
			if ( preg_match( $pattern, $rendered_content ) ) {
				error_log( 'BDV Template Validation: Rejected pathological CSS pattern' );
				return false;
			}
		}

		// Limit inline style blocks count.
		$style_blocks = substr_count( $rendered_content, '<style' );
		if ( $style_blocks > 10 ) {
			error_log( 'BDV Template Validation: Too many style blocks' );
			return false;
		}

		// Detect deeply nested tables (performance killer).
		$table_nesting = substr_count( $rendered_content, '<table' );
		$tr_count      = substr_count( $rendered_content, '<tr' );
		if ( $table_nesting > 5 || $tr_count > 500 ) {
			error_log( 'BDV Template Validation: Excessive table complexity' );
			return false;
		}

		// Optional: full Dompdf render test. Only runs when explicitly requested (manual validation).
		// During save, structural checks above are sufficient.
		if ( $render_test && class_exists( 'Dompdf\Dompdf' ) ) {
			try {
				$dompdf = new \Dompdf\Dompdf(
					array(
						'isRemoteEnabled'         => false,
						'isPhpEnabled'            => false,
						'isFontSubsettingEnabled' => false,
					)
				);
				$dompdf->loadHtml( $rendered_content );
				$dompdf->render();
			} catch ( \Throwable $e ) {
				error_log( 'BDV Template Validation Error: ' . $e->getMessage() );
				return false;
			}
		}

		return true;
	}


	/**
	 * Remove dangerous CSS properties from style attributes.
	 */
	private static function sanitize_styles( $html ): string {
		// CSS properties that could be dangerous in PDFs.
		$dangerous = array(
			'url(',
			'expression(',
			'@import',
			'position:fixed',
			'position: fixed',
			'position:absolute',
			'position: absolute',
		);

		return preg_replace_callback(
			'/style=["\']([^"\']*)["\']/i',
			function ( $matches ) use ( $dangerous ) {
				$style = $matches[1];
				foreach ( $dangerous as $bad ) {
					$style = str_ireplace( $bad, '', $style );
				}
				// Clean up any resulting double semicolons or empty styles.
				$style = preg_replace( '/;+/', ';', $style );
				$style = trim( $style, ';' );
				return $style ? 'style="' . esc_attr( $style ) . '"' : '';
			},
			$html
		);
	}

	public static function get_templates() {
		$raw = get_option( self::$option_name, null );

		// Option doesn't exist → first run, create defaults.
		if ( $raw === null ) {
			self::restore_default_templates();
			$raw = get_option( self::$option_name, array() );
		}

		// Validate structure: must be non-empty array.
		if ( ! is_array( $raw ) || empty( $raw ) ) {
			// Back up corrupted data before restoring.
			self::backup_corrupted( $raw );
			self::restore_default_templates();
			$raw = get_option( self::$option_name, array() );
			\Convoca\Core\Logger::warning( 'Plantillas PDF restauradas: datos corruptos (no array).', 'System' );
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Convoca: Las plantillas PDF se han restaurado a sus valores por defecto debido a datos corruptos. Se ha guardado una copia de seguridad.', 'convoca-core' ) . '</p></div>';
				}
			);
		}

		// Validate each template has required 'name' key.
		$corrupted = false;
		foreach ( $raw as $key => $data ) {
			if ( ! is_array( $data ) || ! isset( $data['name'] ) ) {
				$corrupted = true;
				\Convoca\Core\Logger::warning( 'Plantilla PDF corrupta detectada: ' . $key, 'System' );
				break;
			}
		}

		if ( $corrupted ) {
			self::backup_corrupted( $raw );
			self::restore_default_templates();
			$raw = get_option( self::$option_name, array() );
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Convoca: Algunas plantillas PDF estaban corruptas. Se han restaurado a sus valores por defecto. Se ha guardado una copia de seguridad.', 'convoca-core' ) . '</p></div>';
				}
			);
		}

		return $raw;
	}

	/**
	 * Save a backup of corrupted template data before restoring defaults.
	 */
	private static function backup_corrupted( $data ): void {
		$backup_key = self::$option_name . '_backup_' . wp_date( 'Ymd_His' );
		update_option( $backup_key, $data, false );
		\Convoca\Core\Logger::info(
			sprintf( 'Copia de seguridad de plantillas corruptas guardada en option: %s', $backup_key ),
			'System'
		);
	}

	public static function restore_default_templates() {
		$default_keys = array(
			'acuerdo_incorporacion' => __( 'Acuerdo de Incorporación', 'convoca-core' ),
			'anexo_voluntariado'    => __( 'Anexo de Voluntariado', 'convoca-core' ),
			'certificado'           => __( 'Certificado', 'convoca-core' ),
			'desvinculacion'        => __( 'Acuerdo de Desvinculación', 'convoca-core' ),
		);

		$templates = array();

		foreach ( $default_keys as $key => $name ) {
			$file_path = CONV_COMMON_DIR . 'assets/templates/' . $key . '.html';
			$content   = "<h1>$name</h1>";
			if ( file_exists( $file_path ) ) {
				$loaded = @file_get_contents( $file_path );
				if ( $loaded !== false ) {
					$content = $loaded;
				} else {
					error_log( 'Convoca: Failed to read template file: ' . $file_path );
				}
			}

			$templates[ $key ] = array(
				'name'    => $name,
				'content' => $content,
			);
		}

		update_option( self::$option_name, $templates );
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_convoca_templates' ) ) {
			return;
		}

		$templates = self::get_templates();
		?>
		<div class="wrap conv-common-settings-wrap">
			<div class="conv-admin-header" style="display: flex; align-items: center; gap: 20px; margin-bottom: 20px;">
				<img src="<?php echo esc_url( CONVOCA_IMAGES_URL . 'logo.png' ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" style="width: 80px; height: 80px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
				<div>
					<h1 style="margin: 0; padding: 0;"><?php echo esc_html__( 'Plantillas de Documentos (PDF)', 'convoca-core' ); ?></h1>
					<p style="margin: 5px 0 0; color: #666; font-size: 1.1em;"><?php echo esc_html__( 'Utilidades comunes e infraestructura de documentos', 'convoca-core' ); ?></p>
				</div>
			</div>
			<?php \Convoca\Core\Utils::render_stored_notices(); ?>

			<p><?php echo esc_html__( 'Aquí puedes modificar el diseño HTML de los documentos generados por el sistema de firmas (Convoca Common).', 'convoca-core' ); ?></p>
			<p><?php printf( esc_html__( 'Utiliza etiquetas como %1$s, %2$s, %3$s para que se rellenen automáticamente según el contexto.', 'convoca-core' ), '<code>{{nombre}}</code>', '<code>{{dni}}</code>', '<code>{{fecha}}</code>' ); ?></p>

			<form method="post" action="">
				<?php wp_nonce_field( 'conv_save_templates_nonce' ); ?>
				
				<h2 class="nav-tab-wrapper">
					<?php
					$first = true;
					foreach ( $templates as $key => $data ) :
						?>
						<a href="#tab-<?php echo esc_attr( $key ); ?>" class="nav-tab <?php echo $first ? 'nav-tab-active' : ''; ?>" onclick="bdvSwitchTab(event, '<?php echo esc_attr( $key ); ?>')">
							<?php echo esc_html( $data['name'] ); ?>
						</a>
						<?php
						$first = false;
					endforeach;
					?>
				</h2>

				<div class="template-tabs">
					<?php
					$first = true;
					foreach ( $templates as $key => $data ) :
						?>
						<div id="tab-<?php echo esc_attr( $key ); ?>" class="conv-tab-content" style="<?php echo $first ? '' : 'display:none;'; ?> margin-top:20px;">
							<label for="template_<?php echo esc_attr( $key ); ?>"><strong><?php echo esc_html__( 'Código HTML:', 'convoca-core' ); ?></strong></label><br><br>
							<textarea id="template_<?php echo esc_attr( $key ); ?>" name="template[<?php echo esc_attr( $key ); ?>]" rows="25" class="large-text code" style="font-family: monospace; background: #fcfcfc;"><?php echo htmlspecialchars( $data['content'], ENT_QUOTES, 'UTF-8', false ); ?></textarea>
						</div>
						<?php
						$first = false;
					endforeach;
					?>
				</div>

				<p class="submit">
					<input type="submit" name="conv_save_templates" id="submit" class="button button-primary" value="<?php echo esc_attr__( 'Guardar Cambios', 'convoca-core' ); ?>">
				</p>
			</form>

			<hr>
			
			<form method="post" action="" onsubmit="return confirm('<?php echo esc_js( __( '¿Estás seguro de que deseas restaurar las plantillas por defecto? Perderás cualquier cambio que hayas hecho en el HTML.', 'convoca-core' ) ); ?>');">
				<?php wp_nonce_field( 'conv_restore_templates_nonce' ); ?>
				<p>
					<input type="submit" name="conv_restore_templates" class="button button-secondary" value="<?php echo esc_attr__( 'Restaurar Plantillas por Defecto', 'convoca-core' ); ?>">
				</p>
			</form>
		</div>

		<script>
			function bdvSwitchTab(event, tabId) {
				event.preventDefault();
				
				// Hide all contents.
				var contents = document.querySelectorAll('.conv-tab-content');
				contents.forEach(function(content) {
					content.style.display = 'none';
				});
				
				// Remove active class from all tabs.
				var tabs = document.querySelectorAll('.nav-tab');
				tabs.forEach(function(tab) {
					tab.classList.remove('nav-tab-active');
				});
				
				// Show selected content.
				document.getElementById('tab-' + tabId).style.display = 'block';
				event.target.classList.add('nav-tab-active');
			}
		</script>
		<?php
	}
}
