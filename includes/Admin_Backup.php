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

namespace Convoca\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Backup {

	private const MAX_ZIP_SIZE = 524288000; // 500MB
	private const IMPORT_DIR   = 'convoca-imports';
	private const PREVIEW_TTL  = 600;

	/** Mapa entidad CSV export/import → CPT destino. */
	private const ENTITY_CPT_MAP = array(
		'miembros'      => 'miembro',
		'proyectos'     => 'proyecto',
		'inscripciones' => 'inscripcion',
		'turnos'        => 'centro_turno',
	);

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_post_convoca_export_backup', array( $this, 'handle_export' ) );
		add_action( 'admin_post_convoca_import_backup', array( $this, 'handle_import_preview' ) );
		add_action( 'admin_post_convoca_import_backup_run', array( $this, 'handle_import_run' ) );
	}

	public function register_page(): void {
		add_submenu_page( 'convoca-core', __( 'Copia de Seguridad', 'convoca-core' ), __( 'Copia de Seguridad', 'convoca-core' ), 'common_manage_backup', 'conv-backup', array( $this, 'render' ) );
	}

	public function render(): void {
		if ( ! current_user_can( 'common_manage_backup' ) ) {
			wp_die( esc_html__( 'No tienes permisos.', 'convoca-core' ) );
		}
		$preview = get_transient( 'convoca_import_preview_' . get_current_user_id() );
		?>
		<div class="wrap" style="max-width:900px;">
			<div class="conv-admin-header" style="display:flex;align-items:center;gap:20px;margin-bottom:20px;">
				<img src="<?php echo esc_url( CONVOCA_IMAGES_URL . 'logo.png' ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" style="width:70px;height:70px;border-radius:10px;box-shadow:0 4px 10px rgba(0,0,0,0.1);">
				<div>
					<h1 style="margin:0;"><?php esc_html_e( 'Copia de Seguridad y Mantenimiento', 'convoca-core' ); ?></h1>
					<p style="margin:5px 0 0;color:#64748b;"><?php esc_html_e( 'Gestión de datos masivos e infraestructura.', 'convoca-core' ); ?></p>
				</div>
			</div>

			<?php $import_result = sanitize_text_field( wp_unslash( $_GET['import_result'] ?? '' ) ); ?>
			<?php if ( $import_result ) : ?>
				<div class="convoca-alert <?php echo str_contains( strtolower( $import_result ), 'error' ) ? 'convoca-alert--danger' : 'convoca-alert--success'; ?>" style="display:block;margin-bottom:20px;">
					<p><?php echo esc_html( $import_result ); ?></p>
				</div>
			<?php endif; ?>

			<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
				<div class="convoca-box" style="background:#fff;border-radius:12px;padding:30px;border:1px solid #e2e8f0;">
					<h2>📤 <?php esc_html_e( 'Exportar Datos', 'convoca-core' ); ?></h2>
					<p><?php esc_html_e( 'Genera un archivo ZIP con CSVs de todas las entidades y un JSON con la configuración.', 'convoca-core' ); ?></p>
					<div style="margin-top:20px;">
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=convoca_export_backup' ), 'convoca_export_backup' ) ); ?>" class="convoca-btn convoca-btn-primary">⬇️ <?php esc_html_e( 'Descargar ZIP de Seguridad', 'convoca-core' ); ?></a>
					</div>
				</div>

				<div class="convoca-box" style="background:#fff;border-radius:12px;padding:30px;border:1px solid #e2e8f0;">
					<h2>📥 <?php esc_html_e( 'Importar Datos', 'convoca-core' ); ?></h2>
					<p><?php esc_html_e( 'Restaura entidades o configuraciones desde un archivo ZIP oficial.', 'convoca-core' ); ?></p>

					<?php if ( ! $preview ) : ?>
					<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:20px;">
						<?php wp_nonce_field( 'convoca_import_backup' ); ?>
						<input type="hidden" name="action" value="convoca_import_backup">
						<div class="convoca-field">
							<input type="file" name="backup_zip" accept=".zip" required style="width:100%;">
						</div>
						<button type="submit" class="convoca-btn convoca-btn-primary">🔍 <?php esc_html_e( 'Validar archivo y previsualizar', 'convoca-core' ); ?></button>
					</form>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( $preview ) : ?>
			<div class="convoca-box" style="background:#fff;border-radius:12px;padding:30px;margin-top:20px;border:1px solid #e2e8f0;box-shadow:0 10px 25px rgba(0,0,0,0.05);">
				<div class="convoca-alert convoca-alert--warning" style="display:block;margin-bottom:25px;">
					<p>⚠️ <strong><?php esc_html_e( 'Modo de Importación Atómica:', 'convoca-core' ); ?></strong> <?php esc_html_e( 'Se crearán nuevos registros. Los IDs originales se perderán y se generarán nuevos. El sistema intentará remapear las relaciones internas.', 'convoca-core' ); ?></p>
				</div>
				
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'convoca_import_backup_run' ); ?>
					<input type="hidden" name="action" value="convoca_import_backup_run">
					<input type="hidden" name="session_token" value="<?php echo esc_attr( $preview['token'] ); ?>">
					
					<h3 style="margin-bottom:15px;"><?php esc_html_e( 'Contenido Detectado', 'convoca-core' ); ?></h3>
					<table class="wp-list-table widefat fixed striped" style="border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">
						<thead><tr><th style="width:40px;padding:12px;text-align:center;"><input type="checkbox" id="select-all-entities" checked></th><th style="padding:12px;"><?php esc_html_e( 'Entidad', 'convoca-core' ); ?></th><th style="padding:12px;"><?php esc_html_e( 'Registros', 'convoca-core' ); ?></th></tr></thead>
						<tbody>
							<?php foreach ( $preview['entities'] as $key => $info ) : ?>
							<tr>
								<td style="padding:12px;text-align:center;"><input type="checkbox" name="entities[]" value="<?php echo esc_attr( $key ); ?>" checked></td>
								<td style="padding:12px;"><strong><?php echo esc_html( $info['label'] ); ?></strong></td>
								<td style="padding:12px;"><?php echo (int) $info['count']; ?> registros</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<div style="margin-top:30px;display:flex;gap:15px;align-items:center;">
						<button type="submit" class="convoca-btn convoca-btn--danger" style="padding:12px 30px;" onclick="return confirm('<?php echo esc_js( __( '¿Confirmas la importación de los datos seleccionados?', 'convoca-core' ) ); ?>');">✅ <?php esc_html_e( 'Ejecutar Importación', 'convoca-core' ); ?></button>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=conv-backup' ) ); ?>" style="color:#64748b;text-decoration:none;font-weight:500;"><?php esc_html_e( 'Cancelar y borrar temporal', 'convoca-core' ); ?></a>
					</div>
				</form>
			</div>
			<script>
				document.getElementById('select-all-entities').addEventListener('change', function() {
					var checkboxes = document.querySelectorAll('input[name="entities[]"]');
					for (var checkbox of checkboxes) { checkbox.checked = this.checked; }
				});
			</script>
			<?php endif; ?>
		</div>
		<?php
	}

	/* ── Export ── */

	public function handle_export(): void {
		if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'convoca_export_backup' ) ) {
			wp_die( esc_html__( 'Nonce inválido.', 'convoca-core' ) );
		}
		if ( ! current_user_can( 'common_manage_backup' ) ) {
			wp_die( esc_html__( 'No tienes permisos.', 'convoca-core' ) );
		}
		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( esc_html__( 'ZipArchive no disponible.', 'convoca-core' ) );
		}

		@set_time_limit( 300 );
		@ini_set( 'memory_limit', '512M' );

		$temp_dir = get_temp_dir() . 'conv-export-' . wp_generate_password( 16, false ) . '/';
		wp_mkdir_p( $temp_dir );
		$tmp_file = $temp_dir . 'convoca-backup.zip';
		$cleanup  = function () use ( $temp_dir ) {
			if ( is_dir( $temp_dir ) ) {
				$files = new \RecursiveIteratorIterator(
					new \RecursiveDirectoryIterator( $temp_dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
					\RecursiveIteratorIterator::CHILD_FIRST
				);
				foreach ( $files as $file ) {
					// phpcs:disable WordPress.WP.AlternativeFunctions -- temp cleanup in system temp dir
					$file->isDir() ? rmdir( $file->getRealPath() ) : unlink( $file->getRealPath() );
					// phpcs:enable
				}
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- temp cleanup in system temp dir
				rmdir( $temp_dir );
			}
		};

		$zip = new \ZipArchive();
		if ( $zip->open( $tmp_file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) !== true ) {
			$cleanup();
			wp_die( esc_html__( 'No se pudo crear el archivo temporal ZIP.', 'convoca-core' ) );
		}

		$add_csv = function ( $name, $headers, $rows ) use ( $zip ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- temp stream for CSV in memory
			$handle = fopen( 'php://temp', 'r+' );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- writing BOM + CSV data to temp stream
			fwrite( $handle, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) ); // BOM for UTF-8.
			fputcsv( $handle, $headers, ',', '"' );
			foreach ( $rows as $row ) {
				fputcsv( $handle, $row, ',', '"' );
			}
			rewind( $handle );
			$zip->addFromString( $name . '.csv', stream_get_contents( $handle ) );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- temp stream cleanup
			fclose( $handle );
		};

		$batch_size = 500;

		// Helper to export all posts of a type in batches.
		$export_all = function ( $post_type, $headers, $fields_fn ) use ( $add_csv, $batch_size ) {
			$all_rows = array();
			$page     = 1;
			do {
				$posts = get_posts(
					array(
						'post_type'      => $post_type,
						'posts_per_page' => $batch_size,
						'paged'          => $page,
						'post_status'    => 'any',
						'fields'         => 'all',
					)
				);
				if ( empty( $posts ) ) {
					break;
				}
				$ids = wp_list_pluck( $posts, 'ID' );
				update_meta_cache( 'post', $ids );
				$titles = wp_list_pluck( $posts, 'post_title', 'ID' );
				foreach ( $ids as $id ) {
					$all_rows[] = $fields_fn( $id, $titles[ $id ] ?? '' );
				}
				++$page;
			} while ( count( $ids ) === $batch_size );
			$add_csv( $post_type, $headers, $all_rows );
		};

		// Members.
		$export_all(
			'miembro',
			array( 'ID', 'Nombre', 'Email', 'DNI', 'Plan', 'Estado', 'Vencimiento' ),
			function ( $id, $title ) {
				return array(
					$id,
					$title,
					get_post_meta( $id, '_convoca_email', true ),
					get_post_meta( $id, '_convoca_dni', true ),
					get_post_meta( $id, '_convoca_plan', true ),
					get_post_meta( $id, '_convoca_estado_miembro', true ),
					get_post_meta( $id, '_convoca_vencimiento', true ),
				);
			}
		);

		// Inscriptions.
		$export_all(
			'inscripcion',
			array( 'ID', 'Nombre', 'Email', 'Estado', 'Actividad ID' ),
			function ( $id ) {
				return array(
					$id,
					get_post_meta( $id, '_convoca_nombre', true ),
					get_post_meta( $id, '_convoca_email', true ),
					get_post_meta( $id, '_convoca_estado', true ),
					get_post_meta( $id, '_convoca_actividad_id', true ),
				);
			}
		);

		// Projects.
		$export_all(
			'proyecto',
			array( 'ID', 'Título', 'Inicio', 'Fin', 'Activo' ),
			function ( $id ) {
				return array(
					$id,
					get_the_title( $id ),
					get_post_meta( $id, '_convoca_fecha_inicio', true ),
					get_post_meta( $id, '_convoca_fecha_fin', true ),
					get_post_meta( $id, '_convoca_activo', true ),
				);
			}
		);

		// Convoca Shifts.
		$export_all(
			'centro_turno',
			array( 'ID', 'Título', 'Responsable', 'Estado', 'Hora Fin' ),
			function ( $id ) {
				$resp_id = (int) get_post_meta( $id, '_id_responsable', true );
				return array(
					$id,
					get_the_title( $id ),
					$resp_id ?: '',
					get_post_meta( $id, '_estado_real', true ),
					get_post_meta( $id, '_hora_fin', true ),
				);
			}
		);

		// Settings (Whitelist based).
		$settings        = array();
		$allowed_options = array( 'convoca_gateway_settings', 'convoca_members_settings', 'convoca_members_plans', 'convoca_enroll_settings', 'convoca_shifts_hora_apertura', 'convoca_shifts_hora_cierre', 'convoca_shifts_calendar_page_url' );
		foreach ( $allowed_options as $k ) {
			$settings[ $k ] = get_option( $k );
		}
		$zip->addFromString( 'settings.json', json_encode( $settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );

		$zip->close();

		if ( ! file_exists( $tmp_file ) ) {
			wp_die( esc_html__( 'Error fatal: ZIP no generado.', 'convoca-core' ) );
		}

		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="convoca-backup-' . wp_date( 'Y-m-d-His' ) . '.zip"' );
		header( 'Content-Length: ' . filesize( $tmp_file ) );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- serving binary ZIP file, no escaping needed
		readfile( $tmp_file );
		$cleanup();
		exit;
	}

	/* ── Import Preview ── */

	public function handle_import_preview(): void {
		check_admin_referer( 'convoca_import_backup' );
		if ( ! current_user_can( 'common_manage_backup' ) ) {
			wp_die( esc_html__( 'No tienes permisos.', 'convoca-core' ) );
		}

		if ( empty( $_FILES['backup_zip']['tmp_name'] ) ) {
			wp_die( esc_html__( 'No se ha subido ningún archivo.', 'convoca-core' ) );
		}
		$file = $_FILES['backup_zip'];

		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			wp_die( esc_html__( 'Error en la subida: ', 'convoca-core' ) . esc_html( $file['error'] ) );
		}
		if ( strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) ) !== 'zip' ) {
			wp_die( esc_html__( 'Formato inválido. Solo ZIP.', 'convoca-core' ) );
		}
		if ( $file['size'] > self::MAX_ZIP_SIZE ) {
			wp_die( esc_html__( 'Archivo demasiado grande.', 'convoca-core' ) );
		}

		$zip = new \ZipArchive();
		if ( $zip->open( $file['tmp_name'] ) !== true ) {
			wp_die( esc_html__( 'No se pudo abrir el ZIP.', 'convoca-core' ) );
		}

		// Robust validation.
		$entities       = array();
		$valid_entities = array( 'miembros', 'inscripciones', 'proyectos', 'turnos' );
		foreach ( $valid_entities as $name ) {
			if ( $zip->locateName( $name . '.csv' ) !== false ) {
				$stream = $zip->getStream( $name . '.csv' );
				$count  = 0;
				while ( fgets( $stream ) ) {
					++$count;
				}
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- ZipArchive stream cleanup
				fclose( $stream );
				$entities[ $name ] = array(
					'label' => $this->entity_label( $name ),
					'count' => max( 0, $count - 1 ),
				);
			}
		}
		if ( $zip->locateName( 'settings.json' ) !== false ) {
			$entities['settings'] = array(
				'label' => __( 'Configuración del Sistema', 'convoca-core' ),
				'count' => 1,
			);
		}

		if ( empty( $entities ) ) {
			$zip->close();
			wp_die( esc_html__( 'El archivo ZIP no contiene datos compatibles de Convoca.', 'convoca-core' ) );
		}

		$zip->close();

		// Save to temp folder with security token.
		$upload_dir = wp_upload_dir();
		$import_dir = $upload_dir['basedir'] . '/' . self::IMPORT_DIR;
		wp_mkdir_p( $import_dir );
		if ( ! file_exists( $import_dir . '/.htaccess' ) ) {
			file_put_contents( $import_dir . '/.htaccess', "Deny from all\n" );
		}
		if ( ! file_exists( $import_dir . '/index.php' ) ) {
			file_put_contents( $import_dir . '/index.php', "<?php\n// Silence is golden.\n" );
		}

		$token  = wp_generate_password( 24, false );
		$target = $import_dir . '/import-' . $token . '.zip';
		// phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- WP_Filesystem does not handle uploaded files; move_uploaded_file is the standard approach
		if ( ! move_uploaded_file( $file['tmp_name'], $target ) ) {
			wp_die( esc_html__( 'Error al guardar el temporal.', 'convoca-core' ) );
		}

		set_transient(
			'convoca_import_preview_' . get_current_user_id(),
			array(
				'token'    => $token,
				'entities' => $entities,
			),
			self::PREVIEW_TTL
		);

		wp_safe_redirect( admin_url( 'admin.php?page=conv-backup' ) );
		exit;
	}

	/* ── Import Run ── */

	public function handle_import_run(): void {
		global $wpdb;
		check_admin_referer( 'convoca_import_backup_run' );
		if ( ! current_user_can( 'common_manage_backup' ) ) {
			wp_die( esc_html__( 'No tienes permisos.', 'convoca-core' ) );
		}

		$preview = get_transient( 'convoca_import_preview_' . get_current_user_id() );
		if ( ! $preview || ! isset( $_POST['session_token'] ) || ! hash_equals( $preview['token'], wp_unslash( $_POST['session_token'] ) ) ) {
			wp_die( esc_html__( 'Token de sesión inválido o expirado.', 'convoca-core' ) );
		}

		$selected = isset( $_POST['entities'] ) ? (array) wp_unslash( $_POST['entities'] ) : array();

		$cap_checks = array(
			'proyectos'     => 'manage_inscripciones',
			'miembros'      => 'gestionar_miembros',
			'inscripciones' => 'manage_inscripciones',
			'turnos'        => 'convoca_shifts_manage_turnos',
		);
		foreach ( $selected as $entity ) {
			if ( isset( $cap_checks[ $entity ] ) && ! current_user_can( $cap_checks[ $entity ] ) ) {
				/* translators: %s: entity type name. */
				wp_die( sprintf( esc_html__( 'No tienes permisos para importar %s.', 'convoca-core' ), esc_html( $entity ) ) );
			}
		}

		$upload_dir = wp_upload_dir();
		$filepath   = $upload_dir['basedir'] . '/' . self::IMPORT_DIR . '/import-' . $preview['token'] . '.zip';

		if ( ! file_exists( $filepath ) ) {
			wp_die( esc_html__( 'Archivo temporal no encontrado.', 'convoca-core' ) );
		}

		$zip = new \ZipArchive();
		if ( $zip->open( $filepath ) !== true ) {
			wp_die( esc_html__( 'No se pudo procesar el ZIP.', 'convoca-core' ) );
		}

		$results        = array( 'total' => 0 );
		$imported_posts = array();

		// Prevent concurrent imports.
		if ( ! \Convoca\Core\Utils::acquire_lock( 'convoca_backup_import', 300 ) ) {
			wp_die( esc_html__( 'Otra importación está en curso. Espera a que termine.', 'convoca-core' ) );
		}

		try {
			$order = array( 'settings', 'proyectos', 'miembros', 'inscripciones', 'turnos' );

			foreach ( $order as $entity ) {
				if ( ! in_array( $entity, $selected, true ) ) {
					continue;
				}

				if ( $entity === 'settings' ) {
					$this->import_settings( $zip );
					continue;
				}

				$csv_name = $entity . '.csv';
				if ( $zip->locateName( $csv_name ) === false ) {
					continue;
				}

				$stream = $zip->getStream( $csv_name );
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- reading BOM check from ZipArchive stream, no WP alternative
				if ( fread( $stream, 3 ) !== chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) ) {
					rewind( $stream );
				}

				$headers = fgetcsv( $stream, 0, ',', '"' );
				if ( ! $headers ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- ZipArchive stream cleanup
					fclose( $stream );
					continue; }

				// $entity proviene del literal $order (settings hace continue
				// antes); el mapa cubre todos los valores restantes. Si se
				// añade una entidad nueva, PHPStan marcará el offset como
				// inexistente — mejor que un guard silencioso.
				$post_type = self::ENTITY_CPT_MAP[ $entity ];

				while ( ( $data = fgetcsv( $stream, 0, ',', '"' ) ) !== false ) {
					if ( empty( array_filter( $data ) ) ) {
						continue;
					}

					$old_id = (int) ( $data[0] ?? 0 );
					$new_id = wp_insert_post(
						array(
							'post_type'   => $post_type,
							'post_title'  => sanitize_text_field( $data[1] ?? __( 'Importado', 'convoca-core' ) ),
							'post_status' => 'publish',
						)
					);

					if ( ! $new_id ) {
						// wp_insert_post devuelve 0 en error (no WP_Error en WP moderno).
						throw new \RuntimeException( __( 'Error al crear el post importado.', 'convoca-core' ) );
					}

					$imported_posts[] = $new_id;
					// Store old ID as meta instead of keeping a RAM dict.
					if ( $old_id ) {
						update_post_meta( $new_id, '_convoca_old_import_id', $old_id );
					}
					$this->apply_meta( $entity, $new_id, $data );
					++$results['total'];
				}
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- ZipArchive stream cleanup
					fclose( $stream );
			}
		} catch ( \Throwable $e ) {
			foreach ( $imported_posts as $pid ) {
				wp_delete_post( $pid, true );
			}
			\Convoca\Core\Utils::release_lock( 'convoca_backup_import' );
			$zip->close();
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- wp_delete_file is for uploads; this is an import temp file cleaned explicitly
			unlink( $filepath );
			delete_transient( 'convoca_import_preview_' . get_current_user_id() );
			\Convoca\Core\Logger::error( 'Importación masiva fallida: ' . $e->getMessage(), 'System' );
			wp_safe_redirect( admin_url( 'admin.php?page=conv-backup&import_result=' . urlencode( 'Error: ' . $e->getMessage() ) ) );
			exit;
		}

		\Convoca\Core\Utils::release_lock( 'convoca_backup_import' );

		$zip->close();
		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- wp_delete_file is for uploads; this is an import temp file cleaned explicitly
		unlink( $filepath );
		delete_transient( 'convoca_import_preview_' . get_current_user_id() );

		\Convoca\Core\Logger::info( sprintf( 'Importación masiva completada: %d registros.', $results['total'] ), 'System' );

		/* translators: %d: number of records processed */
		$msg = sprintf( __( 'Importación finalizada. %d registros procesados.', 'convoca-core' ), $results['total'] );
		wp_safe_redirect( admin_url( 'admin.php?page=conv-backup&import_result=' . urlencode( $msg ) ) );
		exit;
	}

	private function apply_meta( $entity, $id, $data ): void {
		switch ( $entity ) {
			case 'miembros':
				update_post_meta( $id, '_convoca_email', sanitize_email( $data[2] ?? '' ) );
				update_post_meta( $id, '_convoca_dni', sanitize_text_field( $data[3] ?? '' ) );
				update_post_meta( $id, '_convoca_plan', sanitize_text_field( $data[4] ?? '' ) );
				update_post_meta( $id, '_convoca_estado_miembro', sanitize_text_field( $data[5] ?? 'activo' ) );
				update_post_meta( $id, '_convoca_vencimiento', sanitize_text_field( $data[6] ?? '' ) );
				break;
			case 'proyectos':
				update_post_meta( $id, '_convoca_fecha_inicio', sanitize_text_field( $data[2] ?? '' ) );
				update_post_meta( $id, '_convoca_fecha_fin', sanitize_text_field( $data[3] ?? '' ) );
				update_post_meta( $id, '_convoca_activo', sanitize_text_field( $data[4] ?? '1' ) );
				break;
			case 'inscripciones':
				update_post_meta( $id, '_convoca_nombre', sanitize_text_field( $data[1] ?? '' ) );
				update_post_meta( $id, '_convoca_email', sanitize_email( $data[2] ?? '' ) );
				update_post_meta( $id, '_convoca_estado', sanitize_text_field( $data[3] ?? 'pendiente' ) );

				$old_pid = (int) ( $data[4] ?? 0 );
				$new_pid = $old_pid;
				if ( $old_pid ) {
					$mapped = get_posts(
						array(
							'post_type'      => 'proyecto',
							'meta_key'       => '_convoca_old_import_id',
							'meta_value'     => (string) $old_pid,
							'fields'         => 'ids',
							'posts_per_page' => 1,
							'post_status'    => 'any',
						)
					);
					if ( ! empty( $mapped ) ) {
						$new_pid = (int) $mapped[0];
					}
				}
				update_post_meta( $id, '_convoca_actividad_id', $new_pid );
				break;
			case 'turnos':
				update_post_meta( $id, '_estado_real', sanitize_text_field( $data[3] ?? 'pendiente' ) );
				update_post_meta( $id, '_hora_fin', sanitize_text_field( $data[4] ?? '' ) );
				$old_resp = (int) ( $data[2] ?? 0 );
				$new_resp = 0;
				if ( $old_resp && get_userdata( $old_resp ) ) {
					$new_resp = $old_resp;
				}
				update_post_meta( $id, '_id_responsable', $new_resp );
				break;
		}
	}

	private function import_settings( $zip ): void {
		$json = $zip->getFromName( 'settings.json' );
		if ( ! $json ) {
			return;
		}

		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return;
		}

		$allowed = array( 'convoca_gateway_settings', 'convoca_members_settings', 'convoca_members_plans', 'convoca_enroll_settings', 'convoca_shifts_hora_apertura', 'convoca_shifts_hora_cierre', 'convoca_shifts_calendar_page_url' );

		foreach ( $allowed as $key ) {
			if ( ! isset( $data[ $key ] ) ) {
				continue;
			}

			// Backup before overwrite.
			$current = get_option( $key );
			if ( $current ) {
				update_option( $key . '_backup_' . wp_date( 'Y-m-d-His' ), $current, false );
			}

			if ( is_array( $data[ $key ] ) ) {
				update_option( $key, $data[ $key ] );
			} else {
				update_option( $key, sanitize_text_field( $data[ $key ] ) );
			}
		}
	}

	private function entity_label( string $slug ): string {
		return match ( $slug ) {
			'miembros' => __( 'Miembros / Socios', 'convoca-core' ),
			'inscripciones' => __( 'Inscripciones a Actividades', 'convoca-core' ),
			'proyectos' => __( 'Proyectos y Actividades', 'convoca-core' ),
			'turnos' => __( 'Turnos', 'convoca-core' ),
			default => ucfirst( $slug ),
		};
	}
}
