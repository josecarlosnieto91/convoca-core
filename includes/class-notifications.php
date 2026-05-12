<?php
/**
 * Biodevas Internal Notifications System.
 *
 * Stores notifications per user and displays a bell icon
 * in the admin bar with a dropdown of unread items.
 *
 * @package Convoca\Core
 */

namespace Convoca\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Notifications
{
    const META_KEY = '_bdv_notifications';

    /**
     * Initialize hooks.
     */
    public static function init(): void
    {
        // Admin bar bell icon
        add_action('admin_bar_menu', [__CLASS__, 'admin_bar_bell'], 100);
        add_action('wp_ajax_bdv_notifications_mark_read', [__CLASS__, 'ajax_mark_read']);
        add_action('wp_ajax_bdv_notifications_dismiss', [__CLASS__, 'ajax_dismiss']);

        // Event hooks
        add_action('bdv_member_created', [__CLASS__, 'on_member_created'], 10, 2);
        add_action('biodevas_payment_failed', [__CLASS__, 'on_payment_failed'], 10, 2);
        add_action('biodevas_hours_submitted', [__CLASS__, 'on_hours_submitted'], 10, 2);
        add_action('bdv_inscripcion_pendiente_pago', [__CLASS__, 'on_inscripcion_pendiente'], 10, 2);
        add_action('bdv_voluntario_pendiente', [__CLASS__, 'on_voluntario_pendiente'], 10);
        add_action('biodevas_members_unsubscribe_request', [__CLASS__, 'on_unsubscribe_request'], 10);

        // Enhanced hooks: push + member notifications
        add_action('bdv_voluntario_aprobado', [__CLASS__, 'on_voluntario_aprobado'], 10);
    }

    /* ── Storage ── */

    /**
     * Add a notification for all admin users.
     */
    public static function add(string $title, string $url, string $type = 'info'): void
    {
        $admins = get_users(['role__in' => ['administrator', 'monitor_actividad'], 'fields' => 'ID']);
        $notification = [
            'id'      => uniqid('bdv_', true),
            'title'   => $title,
            'url'     => $url,
            'type'    => $type,
            'time'    => current_time('mysql'),
            'read'    => false,
        ];

        foreach ($admins as $user_id) {
            $notifications = get_user_meta($user_id, self::META_KEY, true) ?: [];
            array_unshift($notifications, $notification);
            // Keep max 50 per user
            $notifications = array_slice($notifications, 0, 50);
            update_user_meta($user_id, self::META_KEY, $notifications);
        }
    }

    /* ── Member Notifications (post meta) ── */

    /**
     * Add a notification for a specific member (saves as post meta).
     */
    public static function add_member(int $member_id, string $title, string $url = '', string $type = 'info'): void
    {
        $notification = [
            'id'      => uniqid('bdv_m_', true),
            'title'   => $title,
            'url'     => $url,
            'type'    => $type,
            'time'    => current_time('mysql'),
            'read'    => false,
        ];

        $notifications = get_post_meta($member_id, self::META_KEY, true) ?: [];
        array_unshift($notifications, $notification);
        // Keep max 50 per member
        $notifications = array_slice($notifications, 0, 50);
        update_post_meta($member_id, self::META_KEY, $notifications);
    }

    /**
     * Get notifications for a specific member.
     */
    public static function get_member(int $member_id, int $limit = 10): array
    {
        $all = get_post_meta($member_id, self::META_KEY, true) ?: [];
        return array_slice($all, 0, $limit);
    }

    /**
     * Count unread notifications for a specific member.
     */
    public static function count_member_unread(int $member_id): int
    {
        $all = get_post_meta($member_id, self::META_KEY, true) ?: [];
        return count(array_filter($all, fn($n) => empty($n['read'])));
    }

    /**
     * Mark a member notification as read.
     */
    public static function mark_member_read(int $member_id, string $notification_id): void
    {
        $notifications = get_post_meta($member_id, self::META_KEY, true) ?: [];
        foreach ($notifications as &$n) {
            if ($n['id'] === $notification_id) {
                $n['read'] = true;
                break;
            }
        }
        update_post_meta($member_id, self::META_KEY, $notifications);
    }

    /**
     * Mark all member notifications as read.
     */
    public static function mark_member_all_read(int $member_id): void
    {
        $notifications = get_post_meta($member_id, self::META_KEY, true) ?: [];
        foreach ($notifications as &$n) {
            $n['read'] = true;
        }
        update_post_meta($member_id, self::META_KEY, $notifications);
    }

    /**
     * Get notifications for the current user.
     */
    public static function get(int $limit = 10, bool $unread_only = false): array
    {
        $all = get_user_meta(get_current_user_id(), self::META_KEY, true) ?: [];
        if ($unread_only) {
            $all = array_filter($all, fn($n) => empty($n['read']));
        }
        return array_slice($all, 0, $limit);
    }

    /**
     * Count unread notifications.
     */
    public static function count_unread(): int
    {
        $all = get_user_meta(get_current_user_id(), self::META_KEY, true) ?: [];
        return count(array_filter($all, fn($n) => empty($n['read'])));
    }

    /* ── Admin Bar Bell ── */

    public static function admin_bar_bell(\WP_Admin_Bar $wp_admin_bar): void
    {
        if (!is_admin() || !is_user_logged_in()) return;

        $count = self::count_unread();
        $unread = self::get(5, true);
        $badge = $count > 0 ? ' <span class="bdv-bell-count">' . $count . '</span>' : '';

        $wp_admin_bar->add_node([
            'id'     => 'bdv-notifications',
            'title'  => '<span class="bdv-bell-icon' . ($count > 0 ? ' bdv-bell-has-unread' : '') . '">🔔</span>' . $badge,
            'parent' => 'top-secondary',
        ]);

        // Dropdown
        $wp_admin_bar->add_node([
            'id'     => 'bdv-notifications-list',
            'parent' => 'bdv-notifications',
            'title'  => self::render_dropdown($unread, $count),
        ]);

        // Enqueue CSS
        add_action('wp_head', [__CLASS__, 'enqueue_styles']);
        add_action('admin_head', [__CLASS__, 'enqueue_styles']);
    }

    private static function render_dropdown(array $notifications, int $total): string
    {
        $html = '<div class="bdv-notif-dropdown">';
        $html .= '<div class="bdv-notif-header">';
        $html .= '<strong>' . sprintf(__('Notificaciones (%d)', 'convoca-core'), $total) . '</strong>';
        if ($total > 0) {
            $html .= ' <a href="#" class="bdv-notif-mark-all" style="float:right;font-size:12px;">' . __('Marcar todas leídas', 'convoca-core') . '</a>';
        }
        $html .= '</div>';
        $html .= '<div class="bdv-notif-list">';

        if (empty($notifications)) {
            $html .= '<div class="bdv-notif-empty">' . __('No hay notificaciones.', 'convoca-core') . '</div>';
        } else {
            foreach ($notifications as $n) {
                $class = empty($n['read']) ? 'bdv-notif-item bdv-notif-unread' : 'bdv-notif-item';
                $icon = match ($n['type'] ?? 'info') {
                    'success' => '✅',
                    'warning' => '⚠️',
                    'error'   => '❌',
                    default   => 'ℹ️',
                };
                $html .= '<div class="' . $class . '" data-id="' . esc_attr($n['id']) . '">';
                $html .= '<a href="' . esc_url($n['url']) . '" class="bdv-notif-link">';
                $html .= '<span class="bdv-notif-icon">' . $icon . '</span>';
                $html .= '<span class="bdv-notif-title">' . esc_html($n['title']) . '</span>';
                $html .= '<span class="bdv-notif-time">' . human_time_diff(strtotime($n['time']), current_time('timestamp')) . ' ' . __('atrás', 'convoca-core') . '</span>';
                $html .= '</a>';
                $html .= '<a href="#" class="bdv-notif-dismiss" title="' . __('Descartar', 'convoca-core') . '">✕</a>';
                $html .= '</div>';
            }
        }

        $html .= '</div>';
        $html .= '<div class="bdv-notif-footer">';
        $html .= '<a href="' . esc_url(admin_url('admin.php?page=bdv-notificaciones')) . '">' . __('Ver todas', 'convoca-core') . '</a>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    public static function enqueue_styles(): void
    {
        ?>
        <style>
        #wp-admin-bar-bdv-notifications .bdv-bell-icon { font-size: 18px; line-height: 26px; }
        #wp-admin-bar-bdv-notifications .bdv-bell-has-unread { animation: bdv-bell-ring 0.5s ease 3; }
        @keyframes bdv-bell-ring { 0%,100%{transform:rotate(0)} 25%{transform:rotate(15deg)} 75%{transform:rotate(-15deg)} }
        #wp-admin-bar-bdv-notifications .bdv-bell-count {
            display: inline-block; background: #dc3232; color: #fff; border-radius: 50%;
            padding: 1px 6px; font-size: 10px; font-weight: 700; line-height: 16px;
            min-width: 16px; text-align: center; vertical-align: top; margin-left: -2px;
        }
        .bdv-notif-dropdown { width: 320px; max-height: 400px; overflow-y: auto; font-size: 13px; }
        .bdv-notif-header { padding: 10px 12px; border-bottom: 1px solid #e0e0e0; background: #f8f9fa; }
        .bdv-notif-header a { text-decoration: none; }
        .bdv-notif-list { max-height: 300px; overflow-y: auto; }
        .bdv-notif-empty { padding: 20px; text-align: center; color: #999; }
        .bdv-notif-item { display: flex; align-items: center; border-bottom: 1px solid #f0f0f1; padding: 0; }
        .bdv-notif-unread { background: #f0f7ff; }
        .bdv-notif-link { display: flex; align-items: center; gap: 8px; padding: 10px 12px; text-decoration: none; flex: 1; color: #1d2327; }
        .bdv-notif-link:hover { background: #f0f0f1; }
        .bdv-notif-icon { flex-shrink: 0; font-size: 16px; }
        .bdv-notif-title { flex: 1; font-size: 12px; line-height: 1.3; }
        .bdv-notif-time { display: block; font-size: 10px; color: #999; margin-top: 2px; }
        .bdv-notif-dismiss { padding: 10px; color: #999; text-decoration: none; cursor: pointer; }
        .bdv-notif-dismiss:hover { color: #dc3232; }
        .bdv-notif-footer { padding: 8px 12px; text-align: center; border-top: 1px solid #e0e0e0; background: #f8f9fa; }
        .bdv-notif-footer a { text-decoration: none; font-weight: 600; }
        </style>
        <script>
        (function() {
            document.addEventListener('click', function(e) {
                // Mark as read on link click
                var link = e.target.closest('.bdv-notif-link');
                if (link) {
                    var item = link.closest('.bdv-notif-item');
                    if (item && item.classList.contains('bdv-notif-unread')) {
                        var id = item.dataset.id;
                        var fd = new FormData();
                        fd.append('action', 'bdv_notifications_mark_read');
                        fd.append('id', id);
                        fd.append('nonce', '<?php echo wp_create_nonce('bdv_notifications_ajax'); ?>');
                        fetch(ajaxurl, { method: 'POST', body: fd });
                        item.classList.remove('bdv-notif-unread');
                    }
                }
                // Dismiss single
                var dismiss = e.target.closest('.bdv-notif-dismiss');
                if (dismiss) {
                    e.preventDefault();
                    var item = dismiss.closest('.bdv-notif-item');
                    if (item) {
                        var id = item.dataset.id;
                        var fd = new FormData();
                        fd.append('action', 'bdv_notifications_dismiss');
                        fd.append('id', id);
                        fd.append('nonce', '<?php echo wp_create_nonce('bdv_notifications_ajax'); ?>');
                        fetch(ajaxurl, { method: 'POST', body: fd }).then(function() { item.remove(); });
                    }
                }
                // Mark all read
                var markAll = e.target.closest('.bdv-notif-mark-all');
                if (markAll) {
                    e.preventDefault();
                    document.querySelectorAll('.bdv-notif-item.bdv-notif-unread').forEach(function(item) {
                        var id = item.dataset.id;
                        var fd = new FormData();
                        fd.append('action', 'bdv_notifications_mark_read');
                        fd.append('id', id);
                        fd.append('nonce', '<?php echo wp_create_nonce('bdv_notifications_ajax'); ?>');
                        fetch(ajaxurl, { method: 'POST', body: fd });
                        item.classList.remove('bdv-notif-unread');
                    });
                }
            });
        })();
        </script>
        <?php
    }

    /* ── AJAX ── */

    public static function ajax_mark_read(): void
    {
        check_ajax_referer('bdv_notifications_ajax', 'nonce');
        if (!is_user_logged_in()) return;
        $id = sanitize_text_field($_POST['id'] ?? '');
        if (!$id) return;

        $notifications = get_user_meta(get_current_user_id(), self::META_KEY, true) ?: [];
        foreach ($notifications as &$n) {
            if ($n['id'] === $id) {
                $n['read'] = true;
                break;
            }
        }
        update_user_meta(get_current_user_id(), self::META_KEY, $notifications);
        wp_send_json_success();
    }

    public static function ajax_dismiss(): void
    {
        check_ajax_referer('bdv_notifications_ajax', 'nonce');
        $id = sanitize_text_field($_POST['id'] ?? '');
        if (!$id) return;

        $notifications = get_user_meta(get_current_user_id(), self::META_KEY, true) ?: [];
        $notifications = array_filter($notifications, fn($n) => $n['id'] !== $id);
        update_user_meta(get_current_user_id(), self::META_KEY, array_values($notifications));
        wp_send_json_success();
    }

    /* ── Event Hooks ── */

    public static function on_member_created(int $member_id, array $data): void
    {
        $title = sprintf(__('Nuevo socio pendiente: %s', 'convoca-core'), $data['nombre'] ?? '');
        $url = admin_url('admin.php?page=bdv-members&member_id=' . $member_id);

        self::add($title, $url, 'info');
        self::push(__('Nuevo socio', 'convoca-core'), $title);
    }

    public static function on_payment_failed(int $pago_id, string $order_id): void
    {
        $title = sprintf(__('Pago fallido: %s', 'convoca-core'), $order_id);
        $url = admin_url('admin.php?page=bdg-payments-detail&id=' . $pago_id);

        self::add($title, $url, 'error');
        self::push(__('Pago fallido', 'convoca-core'), $title, 'urgent', ['warning']);

        // Also notify the member if we can find the member ID from the pago
        $member_id = get_post_meta($pago_id, '_bdg_origin_id', true);
        if ($member_id) {
            self::add_member(
                (int) $member_id,
                sprintf(__('❌ Tu pago (%s) no pudo procesarse. Revisa tu método de pago.', 'convoca-core'), $order_id),
                '',
                'error'
            );
        }
    }

    public static function on_hours_submitted(int $record_id, string $member_name): void
    {
        $title = sprintf(__('Horas pendientes de aprobar: %s', 'convoca-core'), $member_name);
        $url = admin_url('admin.php?page=bdv-volunteer-hours');

        self::add($title, $url, 'warning');
        self::push(__('Horas voluntariado', 'convoca-core'), $title, 'default', ['clock']);
    }

    public static function on_inscripcion_pendiente(int $inscripcion_id, string $nombre): void
    {
        $title = sprintf(__('Inscripción pendiente de pago: %s', 'convoca-core'), $nombre);
        $url = admin_url('admin.php?page=convoca-core-enroll&inscripcion_id=' . $inscripcion_id);

        self::add($title, $url, 'warning');
        self::push(__('Pago pendiente', 'convoca-core'), $title, 'high', ['warning']);

        // Try to find the member related to this inscription
        $email = get_post_meta($inscripcion_id, '_bde_email', true);
        if ($email) {
            $members = get_posts([
                'post_type' => 'miembro',
                'posts_per_page' => 1,
                'meta_query' => [
                    ['key' => '_bdv_email', 'value' => $email],
                ],
                'fields' => 'ids',
            ]);
            if (!empty($members)) {
                self::add_member(
                    (int) $members[0],
                    sprintf(__('⚠️ Tienes una inscripción pendiente de pago. Revisa tu panel para completarla.', 'convoca-core'), $nombre),
                    '',
                    'warning'
                );
            }
        }
    }

    public static function on_voluntario_pendiente(int $user_id): void
    {
        $user = get_userdata($user_id);
        if (!$user) return;

        $title = sprintf(__('Nueva solicitud de voluntariado: %s', 'convoca-core'), $user->display_name);
        $url = admin_url('edit.php?post_type=centro_turno&page=cst_voluntarios_pendientes');

        self::add($title, $url, 'info');
        self::push(__('Voluntariado', 'convoca-core'), $title, 'high', ['raising_hand']);
    }

    public static function on_unsubscribe_request(int $member_id): void
    {
        $post = get_post($member_id);
        if (!$post) return;

        $title = sprintf(__('Solicitud de baja: %s', 'convoca-core'), $post->post_title);
        $url = admin_url('admin.php?page=bdv-members&id=' . $member_id);

        self::add($title, $url, 'warning');
        self::push(__('Solicitud de baja', 'convoca-core'), $title, 'high', ['warning']);
    }

    /**
     * Fired when a volunteer is approved.
     * Notifies the member via their member panel.
     */
    public static function on_voluntario_aprobado(int $user_id): void
    {
        $user = get_userdata($user_id);
        if (!$user) return;

        // Try to find the member by email
        $members = get_posts([
            'post_type' => 'miembro',
            'posts_per_page' => 1,
            'meta_query' => [
                ['key' => '_bdv_email', 'value' => $user->user_email],
            ],
            'fields' => 'ids',
        ]);

        if (!empty($members)) {
            self::add_member(
                (int) $members[0],
                __('🎉 ¡Tu solicitud de voluntariado ha sido aprobada! Bienvenido/a al equipo.', 'convoca-core'),
                '',
                'success'
            );
        }
    }

    /* ── Push helper ── */

    /**
     * Helper to send push notification.
     */
    private static function push(string $title, string $message, string $priority = 'default', array $tags = []): void
    {
        if (class_exists('\Convoca\Core\Push_Notifier')) {
            \Convoca\Core\Push_Notifier::notify_admins($title, $message, $priority, $tags);
        }
    }
}
