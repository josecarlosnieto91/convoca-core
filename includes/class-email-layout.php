<?php
/**
 * Email Layout — Biodevas premium HTML email template.
 *
 * Proporciona un layout HTML responsivo con la identidad visual de Biodevas
 * (naranja #FF8700 + violeta #320028) para todos los emails del ecosistema.
 *
 * Uso:
 *   $html = \Convoca\Core\Email_Layout::render($body_html, $subject);
 *
 * @package Convoca\Core
 */

namespace Convoca\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Email_Layout
{
    /**
     * Render a complete HTML email with Biodevas branding.
     *
     * @param string $body        Inner HTML content.
     * @param string $subject     Email subject (used in <title>).
     * @param array  $opts        {
     *     Optional overrides.
     *     @type string $preheader      Hidden preview text (max 150 chars).
     *     @type string $footer_text    Custom footer text.
     *     @type string $button_url     Primary CTA button URL.
     *     @type string $button_text    Primary CTA button label.
     *     @type string $header_color   Header background (default: #320028).
     *     @type string $accent_color   Accent color for buttons/links (default: #FF8700).
     * }
     * @return string Complete <html> document.
     */
    public static function render(string $body, string $subject = '', array $opts = []): string
    {
        $year          = wp_date('Y');
        $site_name     = get_bloginfo('name');
        $preheader     = $opts['preheader'] ?? '';
        $button_url    = $opts['button_url'] ?? '';
        $button_text   = $opts['button_text'] ?? '';
        $header_color  = $opts['header_color'] ?? '#320028';
        $accent_color  = $opts['accent_color'] ?? '#FF8700';
        $footer_text   = $opts['footer_text'] ?? 'Has recibido este email porque formas parte de ' . esc_html($site_name) . '.';

        $logo_html = Utils::get_branding_html('email', '', 'max-width:180px;height:auto;display:block;margin:0 auto;');

        // Convert plain text line breaks to <p> if body has no HTML tags
        if ($body === strip_tags($body)) {
            $body = nl2br(esc_html($body));
        }

        // Fade variation of accent for buttons (slightly darker on hover)
        $accent_dark = self::darken_hex($accent_color, 15);

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?php echo esc_html($subject ?: $site_name); ?></title>
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
.email-header{background:<?php echo esc_attr($header_color); ?>;padding:32px 24px;text-align:center}
.email-header img{max-width:180px;height:auto;display:block;margin:0 auto}
/* ── Body ───────────────────────────────────────── */
.email-body{padding:36px 32px;color:#1e293b;font-size:16px;line-height:1.7}
.email-body h1{color:<?php echo esc_attr($header_color); ?>;font-size:26px;margin:0 0 20px;font-weight:700}
.email-body h2{color:<?php echo esc_attr($header_color); ?>;font-size:20px;margin:24px 0 12px;font-weight:600}
.email-body p{margin:0 0 18px}
.email-body a{color:<?php echo esc_attr($accent_color); ?>;font-weight:600;text-decoration:underline}
/* ── Meta box (detalles de actividad/socio) ────── */
.email-meta{background:#faf8f6;border-left:4px solid <?php echo esc_attr($accent_color); ?>;border-radius:0 8px 8px 0;padding:16px 20px;margin:20px 0;font-size:15px}
.email-meta table{width:100%}
.email-meta td{padding:6px 0;border:none;vertical-align:top;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif}
.email-meta .label{color:#64748b;font-weight:600;width:40%;padding-right:12px}
.email-meta .value{color:#1e293b;width:60%}
/* ── CTA Button ────────────────────────────────── */
.email-btn{display:inline-block;padding:14px 32px;background:<?php echo esc_attr($accent_color); ?>;color:#ffffff!important;font-size:16px;font-weight:700;text-decoration:none;border-radius:8px;margin:8px 0;text-align:center}
.email-btn:hover{background:<?php echo esc_attr($accent_dark); ?>}
/* ── Divider ────────────────────────────────────── */
.email-divider{border:none;border-top:2px solid #f0eae6;margin:28px 0}
/* ── Badge / highlight ────────────────────────── */
.email-badge{display:inline-block;background:<?php echo esc_attr($accent_color); ?>20;color:<?php echo esc_attr($header_color); ?>;padding:4px 12px;border-radius:20px;font-size:14px;font-weight:600}
/* ── Footer ─────────────────────────────────────── */
.email-footer{background:#faf8f6;padding:24px 32px;text-align:center;font-size:12px;color:#94a3b8;line-height:1.6;border-top:1px solid #f0eae6}
.email-footer a{color:<?php echo esc_attr($accent_color); ?>;text-decoration:none}
/* ── Responsive ─────────────────────────────────── */
@media only screen and (max-width:480px){
.email-body{padding:24px 16px!important}
.email-meta td{display:block;width:100%!important;padding:2px 0}
.email-meta .label{width:100%!important;padding-right:0}
.email-header{padding:24px 16px!important}
.email-btn{display:block;width:100%;box-sizing:border-box}
}
/* ── Dark mode (prefers-color-scheme) ──────────── */
@media(prefers-color-scheme:dark){
.email-wrapper{background:#1a1a1a!important}
.email-body{color:#e2e8f0!important}
.email-body h1,.email-body h2{color:#ffab00!important}
.email-meta{background:#262626!important}
.email-meta .value{color:#e2e8f0!important}
.email-footer{background:#1a1a1a!important;border-color:#333!important;color:#64748b!important}
.email-divider{border-color:#333!important}
}
</style>
</head>
<body>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%!important;background:#f4f0ed;padding:20px 10px">
<tr><td align="center">
<?php if ($preheader): ?>
<div style="display:none;font-size:1px;color:#f4f0ed;line-height:1px;max-height:0;max-width:0;overflow:hidden;mso-hide:all">
<?php echo esc_html($preheader); ?>
</div>
<?php endif; ?>

<!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0" align="center"><tr><td><![endif]-->
<table role="presentation" class="email-wrapper" width="100%" cellpadding="0" cellspacing="0">
<tr><td class="email-header">
<?php echo $logo_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — already escaped inside get_branding_html ?>
</td></tr>
<tr><td class="email-body">
<?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — already sanitized by caller ?>
<?php if ($button_url && $button_text): ?>
<p style="text-align:center;margin:24px 0 0">
<a href="<?php echo esc_url($button_url); ?>" class="email-btn"><?php echo esc_html($button_text); ?></a>
</p>
<?php endif; ?>
</td></tr>
<tr><td class="email-footer">
<p style="margin:0 0 8px">&copy; <?php echo esc_html($year); ?> <?php echo esc_html($site_name); ?></p>
<p style="margin:0 0 8px"><?php echo esc_html($footer_text); ?></p>
<p style="margin:0">
<a href="<?php echo esc_url(home_url('/mi-area/')); ?>">Mi Área</a>
&nbsp;·&nbsp;
<a href="<?php echo esc_url(home_url('/contacto/')); ?>">Contacto</a>
&nbsp;·&nbsp;
<a href="<?php echo esc_url(home_url('/aviso-legal/')); ?>">Aviso Legal</a>
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
    public static function meta_table(array $rows): string
    {
        $html = '<div class="email-meta"><table role="presentation" cellpadding="0" cellspacing="0">';
        foreach ($rows as $row) {
            $label = $row['label'] ?? '';
            $value = $row['value'] ?? '';
            if ($value === '' || $value === null) {
                continue;
            }
            $html .= '<tr>'
                . '<td class="label">' . esc_html($label) . '</td>'
                . '<td class="value"><strong>' . esc_html($value) . '</strong></td>'
                . '</tr>';
        }
        $html .= '</table></div>';
        return $html;
    }

    /**
     * Darken a hex color by a percentage.
     *
     * @param string $hex  Color like #FF8700.
     * @param int    $pct  Percentage to darken (0-100).
     * @return string Darkened hex.
     */
    private static function darken_hex(string $hex, int $pct): string
    {
        $hex = ltrim($hex, '#');
        $len = strlen($hex);
        if ($len === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $r = max(0, min(255, hexdec(substr($hex, 0, 2)) * (100 - $pct) / 100));
        $g = max(0, min(255, hexdec(substr($hex, 2, 2)) * (100 - $pct) / 100));
        $b = max(0, min(255, hexdec(substr($hex, 4, 2)) * (100 - $pct) / 100));
        return sprintf('#%02x%02x%02x', (int) $r, (int) $g, (int) $b);
    }

    /**
     * Build a simple button block (centered, full-width on mobile).
     */
    public static function button_html(string $url, string $text): string
    {
        return '<p style="text-align:center;margin:24px 0 0">'
            . '<a href="' . esc_url($url) . '" class="email-btn">' . esc_html($text) . '</a>'
            . '</p>';
    }
}
