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
		$file_path  = $upload_dir['basedir'] . '/convoca-documentos/' . basename( $filename );

		if ( ! file_exists( $file_path ) ) {
			wp_die( __( 'Archivo no encontrado.', 'convoca-core' ), '', array( 'response' => 404 ) );
		}

		if ( ! is_user_logged_in() ) {
			wp_die( __( 'No tienes permiso para acceder a este archivo.', 'convoca-core' ), '', array( 'response' => 403 ) );
		}

		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: inline; filename="' . basename( $file_path ) . '"' );
		header( 'Content-Length: ' . filesize( $file_path ) );
		header( 'Cache-Control: private, max-age=3600' );
		readfile( $file_path );
		exit;
	}
}
