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
 * Email Layout — Convoca premium HTML email template.
 *
 * Proporciona un layout HTML responsivo con la identidad visual de Convoca
 * (naranja #FF8700 + violeta #320028) para todos los emails del ecosistema.
 *
 * Uso:
 *   $html = \Convoca\Core\Email_Layout::render($body_html, $subject);
 *
 * @package Convoca\Core
 */

namespace Convoca\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Email_Layout {

	/**
	 * Valor con el que las plantillas representan «este dato no existe».
	 *
	 * Lo escriben los constructores de variables (`{fecha_renovacion}` → `—`).
	 * Es el único valor que se considera ausente: un `0` es un dato real.
	 */
	public const EMPTY_VALUE = '—';

	/**
	 * Render a complete HTML email with Convoca branding.
	 *
	 * @param string $body        Inner HTML content.
	 * @param string $subject     Email subject (used in <title>).
	 * @param array  $opts        {
	 *     Optional overrides.
	 *     @type string $theme          'light'|'dark'. Default: opción global convoca_document_theme (light).
	 *     @type string $preheader      Hidden preview text (max 150 chars).
	 *     @type string $footer_text    Custom footer text.
	 *     @type string $button_url     Primary CTA button URL.
	 *     @type string $button_text    Primary CTA button label.
	 *     @type string $header_color   Header background (default: #ffffff light / #320028 dark).
	 *     @type string $accent_color   Accent color for buttons/links (default: #FF8700).
	 * }
	 * @return string Complete <html> document.
	 */
	public static function render( string $body, string $subject = '', array $opts = array() ): string {
		$year        = wp_date( 'Y' );
		$site_name   = get_bloginfo( 'name' );
		$preheader   = $opts['preheader'] ?? '';
		$button_url  = $opts['button_url'] ?? '';
		$button_text = $opts['button_text'] ?? '';
		$footer_text = $opts['footer_text'] ?? 'Has recibido este email porque formas parte de ' . esc_html( $site_name ) . '.';

		// Theme del documento: explícito en $opts, o el global (convoca_document_theme).
		// light = cabecera clara con nombre en púrpura; dark = cabecera púrpura (clásica).
		$raw_theme = $opts['theme'] ?? Utils::get_document_theme( 'email' );
		$theme     = in_array( $raw_theme, array( 'light', 'dark' ), true ) ? $raw_theme : 'light';

		$header_color = $opts['header_color'] ?? ( 'light' === $theme ? '#ffffff' : '#320028' );
		$header_text  = 'light' === $theme ? '#320028' : '#ffffff';
		$accent_color = $opts['accent_color'] ?? '#FF8700';
		// Bajo la cabecera, una línea naranja mantiene la familia visual en ambos temas.
		$header_border = '4px solid ' . $accent_color;

		$logo_style = 'max-width:180px;height:auto;display:block;margin:0 auto;color:' . $header_text . ';';
		$logo_html  = Utils::get_branding_html( 'email', '', $logo_style );

		// Convert plain text line breaks to <p> if body has no HTML tags.
		if ( $body === wp_strip_all_tags( $body ) ) {
			$body = nl2br( esc_html( $body ) );
		}

		// El cuerpo llega con las variables ya sustituidas, así que lo que se
		// quedó sin dato se retira aquí: una fila «Nueva fecha renovación: —» o
		// un botón con `href=""` son visibles para el destinatario.
		$body = self::prune_empty_html( $body );

		// Ya con las variables sustituidas: es el punto donde se puede escapar el
		// enlace de un botón sin cargarse el placeholder que lo alimenta.
		$body = self::escape_button_urls( $body );

		if ( self::is_missing( $button_url ) ) {
			$button_url  = '';
			$button_text = '';
		}

		// Fade variation of accent for buttons (slightly darker on hover).
		$accent_dark = self::darken_hex( $accent_color, 15 );

		ob_start();
		?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?php echo esc_html( $subject ?: $site_name ); ?></title>
<!--[if mso]>
<xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml>
<![endif]-->
<style>
/* ── Reset ─────────────────────────────────────── */
body,table,td,p,a,li,blockquote{font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%}
body{margin:0;padding:0;background-color:#f4f0ed}
table{border-collapse:collapse;mso-table-lspace:0;mso-table-rspace:0}
img{display:block;border:0;height:auto;line-height:100%;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic}
/* ── Wrapper ────────────────────────────────────── */
.email-wrapper{max-width:600px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(50,0,40,0.08)}
/* ── Header ─────────────────────────────────────── */
.email-header{background:<?php echo esc_attr( $header_color ); ?>;border-bottom:<?php echo esc_attr( $header_border ); ?>;padding:32px 24px;text-align:center}
.email-header img{max-width:180px;height:auto;display:block;margin:0 auto}
/* ── Body ───────────────────────────────────────── */
.email-body{padding:36px 32px;color:#1e293b;font-size:16px;line-height:1.7}
.email-body h1{color:#320028;font-size:26px;margin:0 0 20px;font-weight:700}
.email-body h2{color:#320028;font-size:20px;margin:24px 0 12px;font-weight:600}
.email-body p{margin:0 0 18px}
.email-body a{color:<?php echo esc_attr( $accent_color ); ?>;font-weight:600;text-decoration:underline}
/* ── Meta box (detalles de actividad/socio) ────── */
.email-meta{background:#faf8f6;border-left:4px solid <?php echo esc_attr( $accent_color ); ?>;border-radius:0 8px 8px 0;padding:16px 20px;margin:20px 0;font-size:15px}
.email-meta table{width:100%}
.email-meta td{padding:6px 0;border:none;vertical-align:top;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif}
.email-meta .label{color:#64748b;font-weight:600;width:40%;padding-right:12px}
.email-meta .value{color:#1e293b;width:60%}
/* ── CTA Button ────────────────────────────────── */
.email-btn{display:inline-block;padding:14px 32px;background:<?php echo esc_attr( $accent_color ); ?>;color:#ffffff!important;font-size:16px;font-weight:700;text-decoration:none;border-radius:8px;margin:8px 0;text-align:center}
.email-btn:hover{background:<?php echo esc_attr( $accent_dark ); ?>}
/* ── Divider ────────────────────────────────────── */
.email-divider{border:none;border-top:2px solid #f0eae6;margin:28px 0}
/* ── Badge / highlight ────────────────────────── */
.email-badge{display:inline-block;background:<?php echo esc_attr( $accent_color ); ?>20;color:#320028;padding:4px 12px;border-radius:20px;font-size:14px;font-weight:600}
/* ── Footer ─────────────────────────────────────── */
.email-footer{background:#faf8f6;padding:24px 32px;text-align:center;font-size:12px;color:#94a3b8;line-height:1.6;border-top:1px solid #f0eae6}
.email-footer a{color:<?php echo esc_attr( $accent_color ); ?>;text-decoration:none}
/* ── Responsive ─────────────────────────────────── */
@media only screen and (max-width:480px){
.email-body{padding:24px 16px!important}
.email-meta td{display:block;width:100%!important;padding:2px 0}
.email-meta .label{width:100%!important;padding-right:0}
.email-header{padding:24px 16px!important}
.email-btn{display:block;width:100%;box-sizing:border-box}
}
</style>
</head>
<body>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%!important;background:#f4f0ed;padding:20px 10px">
<tr><td align="center">
		<?php if ( $preheader ) : ?>
<div style="display:none;font-size:1px;color:#f4f0ed;line-height:1px;max-height:0;max-width:0;overflow:hidden;mso-hide:all">
			<?php echo esc_html( $preheader ); ?>
</div>
		<?php endif; ?>

<!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0" align="center"><tr><td><![endif]-->
<table role="presentation" class="email-wrapper" width="100%" cellpadding="0" cellspacing="0">
<tr><td class="email-header">
		<?php echo wp_kses_post( $logo_html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already escaped inside get_branding_html ?>
</td></tr>
<tr><td class="email-body">
		<?php echo wp_kses_post( $body ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already sanitized by caller ?>
		<?php if ( $button_url && $button_text ) : ?>
<p style="text-align:center;margin:24px 0 0">
<a href="<?php echo esc_url( $button_url ); ?>" class="email-btn"><?php echo esc_html( $button_text ); ?></a>
</p>
		<?php endif; ?>
</td></tr>
<tr><td class="email-footer">
<p style="margin:0 0 8px">&copy; <?php echo esc_html( $year ); ?> <?php echo esc_html( $site_name ); ?></p>
<p style="margin:0 0 8px"><?php echo esc_html( $footer_text ); ?></p>
<p style="margin:0">
		<?php
		// Los enlaces del pie se resuelven contra las páginas reales del sitio:
		// escritos a mano son un 404 esperando (el panel de un sitio se llama
		// `/panel-socio/`, no `/mi-area/`). Lo que no resuelve, no se imprime.
		$convoca_pie = \Convoca\Core\Email_Links::footer();
		$convoca_pie = array_filter( $convoca_pie, static fn( $url ): bool => '' !== trim( (string) $url ) );
		$convoca_sep = false;
		foreach ( $convoca_pie as $convoca_etiqueta => $convoca_url ) :
			if ( $convoca_sep ) :
				?>
&nbsp;·&nbsp;
				<?php
			endif;
			$convoca_sep = true;
			?>
<a href="<?php echo esc_url( $convoca_url ); ?>"><?php echo esc_html( $convoca_etiqueta ); ?></a>
		<?php endforeach; ?>
</p>
</td></tr>
</table>
<!--[if mso]></td></tr></table><![endif]-->
</td></tr>
</table>
</body>
</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Generate a meta details table (2-column: label | value).
	 *
	 * @param array $rows Array of ['label' => string, 'value' => string].
	 * @return string HTML table inside a .email-meta div.
	 */
	public static function meta_table( array $rows ): string {
		$html = '<div class="email-meta"><table role="presentation" cellpadding="0" cellspacing="0">';
		foreach ( $rows as $row ) {
			$label = $row['label'] ?? '';
			$value = $row['value'] ?? '';
			// El ?? '' ya normaliza null a string vacío.
			if ( '' === $value ) {
				continue;
			}
			$html .= '<tr>'
				. '<td class="label">' . esc_html( $label ) . '</td>'
				. '<td class="value"><strong>' . esc_html( $value ) . '</strong></td>'
				. '</tr>';
		}
		$html .= '</table></div>';
		return $html;
	}

	/**
	 * ¿El valor representa un dato ausente?
	 *
	 * @param string $value Valor ya sustituido.
	 */
	public static function is_missing( string $value ): bool {
		$value = trim( html_entity_decode( $value, ENT_QUOTES, 'UTF-8' ) );
		return '' === $value || self::EMPTY_VALUE === $value || in_array( $value, array( '-', '--', '#' ), true );
	}

	/**
	 * Retira del cuerpo lo que quedó sin dato tras sustituir las variables.
	 *
	 * Las plantillas se montan ANTES de sustituir los placeholders, así que una
	 * fila cuyo valor venía vacío se imprimía igual y el destinatario leía
	 * «Nueva fecha renovación: —». Aquí se quitan, en este orden: el botón sin
	 * destino, el párrafo que se queda sin texto, la fila sin valor y la caja
	 * de datos entera si acaba sin filas.
	 *
	 * @param string $html Cuerpo con las variables ya sustituidas.
	 * @return string
	 */
	public static function prune_empty_html( string $html ): string {
		// 1. Botones cuyo enlace no lleva a ninguna parte (el placeholder se
		//    sustituyó por una raya, `esc_url()` dejó el href vacío, o el
		//    placeholder sigue SIN sustituir porque el dato no llegó: un botón
		//    con `href="{link_pago}"` acaba en «http://link_pago»).
		$html = preg_replace_callback(
			'#<a\b[^>]*class="email-btn"[^>]*>.*?</a>#is',
			static function ( array $m ): string {
				$url = preg_match( '#href="([^"]*)"#i', $m[0], $h ) ? $h[1] : '';
				return ( self::is_missing( $url ) || self::is_placeholder( $url ) ) ? '' : $m[0];
			},
			(string) $html
		);

		// 2. Párrafos que se quedan sin texto: los que eran solo el botón, los
		//    que llevaban un valor ausente y los que quedaron vacíos.
		$html = preg_replace_callback(
			'#<p\b[^>]*>(?:(?!</p>).)*</p>#is',
			static function ( array $m ): string {
				$texto = trim( html_entity_decode( wp_strip_all_tags( $m[0] ), ENT_QUOTES, 'UTF-8' ) );
				if ( self::is_missing( $texto ) ) {
					return '';
				}
				// «ID del certificado: —» → fuera el párrafo completo.
				if ( preg_match( '/^.{1,80}:\s*' . preg_quote( self::EMPTY_VALUE, '/' ) . '$/u', $texto ) ) {
					return '';
				}
				return $m[0];
			},
			(string) $html
		);

		// 3. Filas de la tabla de datos sin valor.
		$html = preg_replace_callback(
			'#<tr>(?:(?!</tr>).)*<td class="value">(?:(?!</td>).)*</td>\s*</tr>#is',
			static function ( array $m ): string {
				if ( ! preg_match( '#<td class="value">((?:(?!</td>).)*)</td>#is', $m[0], $c ) ) {
					return $m[0];
				}
				return self::is_missing( wp_strip_all_tags( $c[1] ) ) ? '' : $m[0];
			},
			(string) $html
		);

		// 4. La caja de datos si se quedó sin ninguna fila.
		return (string) preg_replace( '#<div class="email-meta"><table\b[^>]*>\s*</table></div>#is', '', (string) $html );
	}

	/**
	 * Darken a hex color by a percentage.
	 *
	 * @param string $hex  Color like #FF8700.
	 * @param int    $pct  Percentage to darken (0-100).
	 * @return string Darkened hex.
	 */
	private static function darken_hex( string $hex, int $pct ): string {
		$hex = ltrim( $hex, '#' );
		$len = strlen( $hex );
		if ( $len === 3 ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		$r = max( 0, min( 255, hexdec( substr( $hex, 0, 2 ) ) * ( 100 - $pct ) / 100 ) );
		$g = max( 0, min( 255, hexdec( substr( $hex, 2, 2 ) ) * ( 100 - $pct ) / 100 ) );
		$b = max( 0, min( 255, hexdec( substr( $hex, 4, 2 ) ) * ( 100 - $pct ) / 100 ) );
		return sprintf( '#%02x%02x%02x', (int) $r, (int) $g, (int) $b );
	}

	/**
	 * Build a simple button block (centered, full-width on mobile).
	 *
	 * El cuerpo de una plantilla se sustituye DESPUÉS de montarse, así que aquí
	 * NO se puede escapar un placeholder: `esc_url('{link_pago}')` devuelve
	 * `http://link_pago`, el motor ya no encuentra nada que sustituir y el botón
	 * viaja roto sin que nadie lo note. El escape de verdad lo hace `render()`,
	 * cuando el valor ya está sustituido.
	 */
	public static function button_html( string $url, string $text ): string {
		$href = self::is_placeholder( $url ) ? trim( $url ) : esc_url( $url );

		return '<p style="text-align:center;margin:24px 0 0">'
			. '<a href="' . $href . '" class="email-btn">' . esc_html( $text ) . '</a>'
			. '</p>';
	}

	/** ¿Es un placeholder sin sustituir, del tipo `{link_pago}`? */
	public static function is_placeholder( string $value ): bool {
		return 1 === preg_match( '/^\{[a-z0-9_]+\}$/i', trim( $value ) );
	}

	/**
	 * Escapa los enlaces de los botones del cuerpo.
	 *
	 * Es el ÚNICO punto donde se puede escapar un enlace sin cargarse el
	 * placeholder que lo alimenta: aquí el cuerpo ya está sustituido. Escapar
	 * dos veces una URL válida no la cambia, así que también vale para las
	 * plantillas que ya venían con el enlace literal.
	 */
	private static function escape_button_urls( string $html ): string {
		return (string) preg_replace_callback(
			'#<a\b[^>]*class="email-btn"[^>]*>#i',
			static function ( array $a ): string {
				return (string) preg_replace_callback(
					'#href="([^"]*)"#i',
					static fn( array $h ): string => 'href="' . esc_url( $h[1] ) . '"',
					$a[0]
				);
			},
			$html
		);
	}
}
