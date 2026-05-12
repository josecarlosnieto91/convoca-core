<?php
/**
 * License Manager — validation, caching, and feature gating.
 *
 * Handles license key verification for Convoca PRO features.
 * Supports both local validation (cached) and remote (API) validation.
 *
 * @package Convoca\Core
 */

namespace Convoca\Core;

if (!defined('ABSPATH')) {
    exit;
}

class License_Manager
{
    const OPTION_KEY = 'convoca_license';
    const CACHE_TTL = DAY_IN_SECONDS;

    /**
     * Available PRO features.
     */
    const PRO_FEATURES = [
        'members'       => 'Convoca Members PRO',
        'enroll'        => 'Convoca Enroll PRO',
        'gateway'       => 'Convoca Gateway PRO',
        'shifts'        => 'Convoca Shifts PRO',
        'gamification'  => 'Gamificación Voluntariado',
        'pdf_memories'  => 'Memorias PDF automáticas',
        'pwa_checkin'   => 'PWA Check-in QR',
        'analytics'     => 'Analytics avanzados',
        'webhooks'      => 'Webhooks salientes',
    ];

    /**
     * API endpoint for license validation.
     * Override via filter 'convoca_license_api_url'.
     */
    const API_URL = 'https://convoca.app/api/license';

    /**
     * Initialize hooks.
     */
    public static function init(): void
    {
        add_action('admin_menu', [__CLASS__, 'add_admin_page'], 20);
        add_action('admin_post_convoca_activate_license', [__CLASS__, 'handle_activate']);
        add_action('admin_post_convoca_deactivate_license', [__CLASS__, 'handle_deactivate']);
        add_action('admin_notices', [__CLASS__, 'admin_notice']);
        
        // Weekly cron validation
        add_action('convoca_license_validate', [__CLASS__, 'validate_remote']);
    }

    /**
     * Check if a PRO feature is available.
     *
     * @param string $feature Feature key (e.g. 'members', 'enroll', 'gateway').
     * @return bool
     */
    public static function has_pro(string $feature): bool
    {
        $license = self::get_license();
        
        // No license key = FREE mode
        if (empty($license['key'])) {
            return false;
        }
        
        // Expired license
        if (!empty($license['expires']) && strtotime($license['expires']) < time()) {
            return false;
        }
        
        // Unlimited or specific feature
        if ($license['type'] === 'unlimited') {
            return true;
        }
        
        return in_array($feature, $license['features'] ?? [], true);
    }

    /**
     * Get stored license data.
     *
     * @return array
     */
    public static function get_license(): array
    {
        return get_option(self::OPTION_KEY, [
            'key'      => '',
            'status'   => 'inactive',
            'type'     => 'free',
            'features' => [],
            'expires'  => '',
            'email'    => '',
        ]);
    }

    /**
     * Get the status label.
     */
    public static function get_status_label(): string
    {
        $license = self::get_license();
        $status = $license['status'] ?? 'inactive';
        
        return match ($status) {
            'active'    => __('Activa', 'convoca-core'),
            'expired'   => __('Expirada', 'convoca-core'),
            'invalid'   => __('Invalida', 'convoca-core'),
            default     => __('Inactiva', 'convoca-core'),
        };
    }

    /**
     * Validate license key against remote API.
     *
     * @param string $key License key.
     * @return array{success: bool, message: string, data?: array}
     */
    public static function validate_key(string $key): array
    {
        $site_url = home_url();
        $api_url = apply_filters('convoca_license_api_url', self::API_URL);

        $response = wp_remote_post($api_url, [
            'timeout' => 15,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => json_encode([
                'license_key' => $key,
                'site_url'    => $site_url,
                'action'      => 'activate',
            ]),
        ]);

        if (is_wp_error($response)) {
            // Fallback: validate locally (cached)
            $cached = get_transient('convoca_license_check_' . md5($key));
            if ($cached) {
                return $cached;
            }
            // Store pending validation
            update_option(self::OPTION_KEY, array_merge(self::get_license(), [
                'key'    => $key,
                'status' => 'pending',
                'type'   => 'free',
            ]));
            return [
                'success' => true,
                'message' => 'Licencia guardada. La validación remota se realizará cuando el servidor esté disponible.',
            ];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!$body || empty($body['success'])) {
            return [
                'success' => false,
                'message' => $body['message'] ?? 'Error de validación con el servidor.',
            ];
        }

        // Cache locally
        set_transient('convoca_license_check_' . md5($key), $body, self::CACHE_TTL);

        // Save to options
        update_option(self::OPTION_KEY, [
            'key'      => $key,
            'status'   => $body['data']['status'] ?? 'active',
            'type'     => $body['data']['type'] ?? 'single',
            'features' => $body['data']['features'] ?? [],
            'expires'  => $body['data']['expires'] ?? '',
            'email'    => $body['data']['email'] ?? '',
        ]);

        return $body;
    }

    /**
     * Remote validation (cron).
     */
    public static function validate_remote(): void
    {
        $license = self::get_license();
        if (empty($license['key'])) {
            return;
        }
        self::validate_key($license['key']);
    }

    /**
     * Deactivate license remotely.
     */
    public static function deactivate(): array
    {
        $license = self::get_license();
        if (empty($license['key'])) {
            return ['success' => true, 'message' => 'Sin licencia activa.'];
        }

        $api_url = apply_filters('convoca_license_api_url', self::API_URL);
        wp_remote_post($api_url, [
            'timeout' => 10,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => json_encode([
                'license_key' => $license['key'],
                'site_url'    => home_url(),
                'action'      => 'deactivate',
            ]),
        ]);

        delete_option(self::OPTION_KEY);
        delete_transient('convoca_license_check_' . md5($license['key']));

        return ['success' => true, 'message' => 'Licencia desactivada.'];
    }

    /* ── Admin ──────────────────────────────────── */

    /**
     * Add license admin page.
     */
    public static function add_admin_page(): void
    {
        add_submenu_page(
            'convoca-core',
            __('Licencia Convoca', 'convoca-core'),
            __('Licencia', 'convoca-core'),
            'manage_options',
            'convoca-license',
            [__CLASS__, 'render_page']
        );
    }

    /**
     * Render license page.
     */
    public static function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos.');
        }

        $license = self::get_license();
        $features = self::PRO_FEATURES;
        $has_pro = $license['status'] === 'active';
        ?>
        <div class="wrap">
            <h1>🔑 <?php esc_html_e('Licencia Convoca', 'convoca-core'); ?></h1>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:20px;">
                <!-- License status card -->
                <div style="background:#fff;border-radius:12px;padding:25px;box-shadow:0 2px 8px rgba(0,0,0,0.06);border:1px solid #e2e8f0;">
                    <h2 style="margin-top:0;font-size:18px;">📋 <?php esc_html_e('Estado de la licencia', 'convoca-core'); ?></h2>
                    <table style="width:100%;border-collapse:collapse;">
                        <tr>
                            <td style="padding:8px 0;font-weight:600;"><?php esc_html_e('Estado', 'convoca-core'); ?></td>
                            <td style="padding:8px 0;">
                                <span style="display:inline-block;padding:3px 12px;border-radius:20px;font-weight:600;font-size:13px;
                                    <?php echo $has_pro ? 'background:#d1fae5;color:#065f46;' : 'background:#f1f5f9;color:#64748b;'; ?>">
                                    <?php echo esc_html(self::get_status_label()); ?>
                                </span>
                            </td>
                        </tr>
                        <?php if ($license['key']): ?>
                        <tr>
                            <td style="padding:8px 0;font-weight:600;"><?php esc_html_e('Clave', 'convoca-core'); ?></td>
                            <td style="padding:8px 0;font-family:monospace;"><?php echo esc_html(substr($license['key'], 0, 12) . '...'); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($license['expires']): ?>
                        <tr>
                            <td style="padding:8px 0;font-weight:600;"><?php esc_html_e('Válida hasta', 'convoca-core'); ?></td>
                            <td style="padding:8px 0;"><?php echo esc_html($license['expires']); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($license['type'] !== 'free'): ?>
                        <tr>
                            <td style="padding:8px 0;font-weight:600;"><?php esc_html_e('Tipo', 'convoca-core'); ?></td>
                            <td style="padding:8px 0;"><?php echo esc_html($license['type']); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>

                <!-- Activation card -->
                <div style="background:#fff;border-radius:12px;padding:25px;box-shadow:0 2px 8px rgba(0,0,0,0.06);border:1px solid #e2e8f0;">
                    <?php if ($has_pro): ?>
                        <h2 style="margin-top:0;font-size:18px;">🔓 <?php esc_html_e('Licencia activa', 'convoca-core'); ?></h2>
                        <p><?php esc_html_e('Tu licencia está activa. Puedes desactivarla para moverla a otro sitio.', 'convoca-core'); ?></p>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <input type="hidden" name="action" value="convoca_deactivate_license">
                            <?php wp_nonce_field('convoca_deactivate'); ?>
                            <button type="submit" class="button button-secondary" onclick="return confirm('¿Desactivar la licencia de este sitio?')">
                                <?php esc_html_e('Desactivar licencia', 'convoca-core'); ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <h2 style="margin-top:0;font-size:18px;">🔑 <?php esc_html_e('Activar licencia', 'convoca-core'); ?></h2>
                        <p><?php esc_html_e('Introduce tu clave de licencia para activar las funcionalidades PRO.', 'convoca-core'); ?></p>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <input type="hidden" name="action" value="convoca_activate_license">
                            <?php wp_nonce_field('convoca_activate'); ?>
                            <p>
                                <input type="text" name="license_key" value="" placeholder="XXXX-XXXX-XXXX-XXXX"
                                    style="width:100%;padding:10px;font-size:16px;font-family:monospace;text-align:center;"
                                    maxlength="30" required>
                            </p>
                            <button type="submit" class="button button-primary">
                                <?php esc_html_e('Activar licencia', 'convoca-core'); ?>
                            </button>
                        </form>
                        <p style="margin-top:20px;font-size:13px;color:#64748b;">
                            <?php esc_html_e('¿Sin licencia?', 'convoca-core'); ?>
                            <a href="https://convoca.app/pricing" target="_blank"><?php esc_html_e('Adquiere una aquí', 'convoca-core'); ?></a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Features grid -->
            <div style="margin-top:30px;">
                <h2>✨ <?php esc_html_e('Funcionalidades', 'convoca-core'); ?></h2>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:12px;margin-top:15px;">
                    <?php foreach ($features as $key => $label):
                        $available = $has_pro && self::has_pro($key);
                    ?>
                    <div style="background:#fff;border-radius:8px;padding:15px;border:1px solid <?php echo $available ? '#bbf7d0' : '#e2e8f0'; ?>;<?php echo $available ? 'background:#f0fdf4;' : 'opacity:0.6;'; ?>">
                        <span style="font-size:1.2rem;"><?php echo $available ? '✅' : '🔒'; ?></span>
                        <span style="font-weight:600;margin-left:8px;"><?php echo esc_html($label); ?></span>
                        <?php if (!$available): ?>
                            <span style="display:block;font-size:11px;color:#94a3b8;margin-top:4px;">PRO</span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Handle license activation.
     */
    public static function handle_activate(): void
    {
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'convoca_activate')) {
            wp_die('Nonce inválido.');
        }
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos.');
        }

        $key = sanitize_text_field($_POST['license_key'] ?? '');
        if (empty($key)) {
            wp_redirect(add_query_arg('message', 'empty_key', wp_get_referer()));
            exit;
        }

        $result = self::validate_key($key);
        set_transient('convoca_license_message', $result['message'], 30);

        wp_redirect(add_query_arg('message', $result['success'] ? 'activated' : 'error', wp_get_referer()));
        exit;
    }

    /**
     * Handle license deactivation.
     */
    public static function handle_deactivate(): void
    {
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'convoca_deactivate')) {
            wp_die('Nonce inválido.');
        }
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos.');
        }

        self::deactivate();
        set_transient('convoca_license_message', 'Licencia desactivada.', 30);

        wp_redirect(wp_get_referer());
        exit;
    }

    /**
     * Admin notices for license messages.
     */
    public static function admin_notice(): void
    {
        $message = get_transient('convoca_license_message');
        if (!$message) {
            return;
        }
        delete_transient('convoca_license_message');
        $type = strpos($message, 'Error') !== false ? 'error' : 'success';
        echo '<div class="notice notice-' . $type . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }
}
