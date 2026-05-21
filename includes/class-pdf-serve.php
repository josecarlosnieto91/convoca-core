<?php
/**
 * PDF serving with access control.
 * Bypasses nginx .htaccess limitation by serving files through PHP.
 *
 * @package Convoca\Core
 */

namespace Convoca\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PDF_Serve {

	/**
	 * Serve a PDF file securely through PHP.
	 * Only logged-in users can access the file.
	 *
	 * @param string $filename The PDF filename (basename only).
	 */
	public static function serve( string $filename ): void {
		$upload_dir = wp_upload_dir();
		$file_path  = $upload_dir['basedir'] . '/biodevas-documentos/' . basename( $filename );

		if ( ! file_exists( $file_path ) ) {
			wp_die( 'Archivo no encontrado.', '', array( 'response' => 404 ) );
		}

		if ( ! is_user_logged_in() ) {
			wp_die( 'No tienes permiso para acceder a este archivo.', '', array( 'response' => 403 ) );
		}

		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: inline; filename="' . basename( $file_path ) . '"' );
		header( 'Content-Length: ' . filesize( $file_path ) );
		header( 'Cache-Control: private, max-age=3600' );
		readfile( $file_path );
		exit;
	}
}
