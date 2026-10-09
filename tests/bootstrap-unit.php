<?php
/**
 * Bootstrap for unit tests — standalone, no WordPress needed.
 * Mocks WordPress functions for Convoca Core testing.
 * IMPORTANT: stubs must be defined BEFORE the Composer autoloader
 * to prevent the autoloader from loading real source classes
 * that depend on WP functions before stubs exist.
 */

// Global stores for mocks
$GLOBALS['_wp_cron'] = ['programados' => [], 'agendados' => [], 'limpiados' => [], 'consultados' => []];
$GLOBALS['_wp_stores'] = [
    'options'    => [],
    'post_meta'  => [],
    'transients' => [],
    'user_meta'  => [],
    'emails'     => [],
];

// --- WP constants ---
if (!defined('ABSPATH')) { define('ABSPATH', dirname(__DIR__) . '/'); }
if (!defined('WP_DEBUG')) { define('WP_DEBUG', true); }
if (!defined('OBJECT')) { define('OBJECT', 'OBJECT'); }
// Constantes de tiempo de WP (las usan crons, ventanas y retenciones).
if (!defined('MINUTE_IN_SECONDS')) { define('MINUTE_IN_SECONDS', 60); }
if (!defined('HOUR_IN_SECONDS')) { define('HOUR_IN_SECONDS', 3600); }
if (!defined('DAY_IN_SECONDS')) { define('DAY_IN_SECONDS', 86400); }
if (!defined('WEEK_IN_SECONDS')) { define('WEEK_IN_SECONDS', 604800); }
if (!defined('MONTH_IN_SECONDS')) { define('MONTH_IN_SECONDS', 2592000); }
if (!defined('YEAR_IN_SECONDS')) { define('YEAR_IN_SECONDS', 31536000); }

// --- WordPress option functions ---
if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        $s = &$GLOBALS['_wp_stores']['options'];
        return array_key_exists($key, $s) ? $s[$key] : $default;
    }
    function update_option($key, $value, $autoload = null) {
        $GLOBALS['_wp_stores']['options'][$key] = $value; return true;
    }
    function delete_option($key) {
        unset($GLOBALS['_wp_stores']['options'][$key]); return true;
    }
}

// --- Transient functions ---
if (!function_exists('get_transient')) {
    function get_transient($key) {
        $s = &$GLOBALS['_wp_stores']['transients'];
        return $s[$key] ?? false;
    }
    function set_transient($key, $value, $exp = 0) {
        $GLOBALS['_wp_stores']['transients'][$key] = $value; return true;
    }
    function delete_transient($key) {
        unset($GLOBALS['_wp_stores']['transients'][$key]); return true;
    }
}

// --- Post meta functions ---
if (!function_exists('get_post_meta')) {
    function get_post_meta($id, $key, $single = false) {
        $s = &$GLOBALS['_wp_stores']['post_meta'];
        $v = $s[$id][$key] ?? null;
        if ($v === null) return $single ? '' : [];
        if ($single) return $v;
        return is_array($v) ? $v : [$v];
    }
    function update_post_meta($id, $key, $value) {
        $GLOBALS['_wp_stores']['post_meta'][$id][$key] = $value; return true;
    }
    function delete_post_meta($id, $key) {
        unset($GLOBALS['_wp_stores']['post_meta'][$id][$key]); return true;
    }
}

// --- User meta (voluntariado, no-show, turnos) ---
if (!function_exists('get_user_meta')) {
    function get_user_meta($user_id, $key = '', $single = false) {
        $s = &$GLOBALS['_wp_stores']['user_meta'];
        $meta = $s[$user_id] ?? [];
        if ('' === $key) return $meta;
        $v = $meta[$key] ?? null;
        if ($v === null) return $single ? '' : [];
        if ($single) return $v;
        return is_array($v) ? $v : [$v];
    }
    function update_user_meta($user_id, $key, $value) {
        $GLOBALS['_wp_stores']['user_meta'][$user_id][$key] = $value; return true;
    }
    function delete_user_meta($user_id, $key) {
        unset($GLOBALS['_wp_stores']['user_meta'][$user_id][$key]); return true;
    }
}
if (!function_exists('add_user_meta')) {
    function add_user_meta($user_id, $key, $value, $unique = false) {
        return update_user_meta($user_id, $key, $value);
    }
}
if (!function_exists('get_users')) { function get_users($args = []) { return []; } }
if (!function_exists('is_user_logged_in')) { function is_user_logged_in() { return false; } }
if (!function_exists('wp_get_current_user')) {
    function wp_get_current_user() {
        $u = new WP_User();
        $u->ID = get_current_user_id();
        $u->user_email = 'test@example.com';
        return $u;
    }
}

// --- Common WP functions ---
if (!function_exists('__')) { function __($t, $d = 'default') { return $t; } }
if (!function_exists('_e')) { function _e($t, $d = 'default') { echo $t; } }
if (!function_exists('_x')) { function _x($t, $c, $d = 'default') { return $t; } }
if (!function_exists('esc_html')) { function esc_html($t) { return htmlspecialchars($t, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('esc_attr')) { function esc_attr($t) { return htmlspecialchars($t, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('esc_url')) { function esc_url($u) { return filter_var($u, FILTER_SANITIZE_URL); } }
if (!function_exists('_n')) { function _n($s, $p, $n, $d = 'default') { return 1 === (int) $n ? $s : $p; } }
if (!function_exists('esc_html__')) { function esc_html__($t, $d = 'default') { return $t; } }
if (!function_exists('esc_attr__')) { function esc_attr__($t, $d = 'default') { return $t; } }
if (!function_exists('esc_html_e')) { function esc_html_e($t, $d = 'default') { echo $t; } }
if (!function_exists('esc_attr_e')) { function esc_attr_e($t, $d = 'default') { echo $t; } }
if (!function_exists('is_ssl')) { function is_ssl() { return false; } }
if (!function_exists('wp_parse_url')) { function wp_parse_url($url, $component = -1) { return parse_url($url, $component); } }
if (!function_exists('esc_url_raw')) { function esc_url_raw($u) { return (string) $u; } }
if (!function_exists('add_query_arg')) {
    function add_query_arg($args, $url = '') {
        if (!is_array($args)) { return $url; }
        $sep = (false === strpos($url, '?')) ? '?' : '&';
        return $url . $sep . http_build_query($args);
    }
}
if (!function_exists('set_url_scheme')) { function set_url_scheme($url, $scheme = null) { return $url; } }
if (!function_exists('wp_kses_post')) { function wp_kses_post($s) { return $s; } }
if (!function_exists('wp_strip_all_tags')) { function wp_strip_all_tags($s, $rb = true) { return strip_tags((string) $s); } }
if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = []) {
        if (is_object($args)) { $args = get_object_vars($args); }
        return array_merge((array) $defaults, (array) $args);
    }
}
if (!function_exists('shortcode_atts')) { function shortcode_atts($pairs, $atts, $sc = '') { return wp_parse_args($atts, $pairs); } }
if (!function_exists('wp_mail')) {
    function wp_mail($to, $subject, $message, $headers = '', $attachments = []) {
        // Los tests afirman sobre $GLOBALS['_wp_stores']['emails'] (lo limpian en
        // su setUp); guardamos ahí para que 'to'/'subject' sean comprobables.
        $GLOBALS['_wp_stores']['emails'][] = compact('to', 'subject', 'message', 'headers', 'attachments');
        return true;
    }
}
if (!function_exists('sanitize_key')) { function sanitize_key($k) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $k)); } }
if (!function_exists('number_format_i18n')) { function number_format_i18n($n, $dec = 0) { return number_format((float) $n, (int) $dec); } }
if (!function_exists('date_i18n')) { function date_i18n($f, $ts = null) { return gmdate($f, $ts ?? time()); } }
if (!function_exists('wp_list_pluck')) {
    function wp_list_pluck($list, $field, $index_key = null) {
        $out = [];
        foreach ((array) $list as $k => $v) {
            $val = is_object($v) ? ($v->$field ?? null) : ($v[$field] ?? null);
            $key = $index_key ? (is_object($v) ? ($v->$index_key ?? $k) : ($v[$index_key] ?? $k)) : $k;
            $out[$key] = $val;
        }
        return $out;
    }
}
// Los emails de Convoca llevan el nombre del sitio en asuntos y textos.
if (!function_exists('get_bloginfo')) { function get_bloginfo($show = 'name') { return 'Sitio de Prueba'; } }
// El branding de emails y PDFs sale del logo del sitio (custom_logo). Sin logo
// configurado cae al nombre del sitio, que es lo que se prueba por defecto.
if (!function_exists('get_theme_mod')) { function get_theme_mod($name = '', $default = false) { return $default; } }
if (!function_exists('wp_get_attachment_image_src')) { function wp_get_attachment_image_src($id, $size = 'thumbnail', $icon = false) { return false; } }
// Resolución de páginas por shortcode (Email_Links). `get_page_by_path` lee del
// mismo almacén que get_posts, para poder montar un sitio de prueba coherente.
if (!function_exists('has_shortcode')) {
    function has_shortcode($content, $tag) {
        if (!is_string($content) || '' === $content) { return false; }
        return (bool) preg_match('/\[' . preg_quote($tag, '/') . '[\s\]]/', $content);
    }
}
if (!function_exists('get_permalink')) { function get_permalink($id = 0) { $pid = is_object($id) ? (int) ($id->ID ?? 0) : (int) $id; return 'https://example.org/?p=' . $pid; } }
if (!function_exists('get_page_by_path')) {
    function get_page_by_path($slug) {
        // Convencion de pruebas: $GLOBALS['convoca_test_pages'][slug] permite declarar
        // una pagina sin tener que montar el store completo de entradas.
        $directa = $GLOBALS['convoca_test_pages'][$slug] ?? null;
        if (is_object($directa)) { return $directa; }
        foreach (($GLOBALS['_wp_stores']['posts'] ?? []) as $p) {
            if (($p->post_type ?? '') === 'page' && ($p->post_name ?? '') === $slug) { return $p; }
        }
        return null;
    }
}
if (!function_exists('sanitize_text_field')) { function sanitize_text_field($s) { return trim(strip_tags($s)); } }
if (!function_exists('sanitize_title')) { function sanitize_title($t) { return strtolower(str_replace(' ', '-', trim($t))); } }
if (!function_exists('sanitize_email')) { function sanitize_email($e) { return filter_var($e, FILTER_SANITIZE_EMAIL); } }
if (!function_exists('sanitize_url')) { function sanitize_url($u) { return filter_var($u, FILTER_SANITIZE_URL); } }
if (!function_exists('absint')) { function absint($v) { return abs((int)$v); } }
if (!function_exists('wp_unslash')) { function wp_unslash($s) { return is_string($s) ? stripslashes($s) : $s; } }

// --- Hooks ---
// Registro real de filtros: con un `apply_filters` que devolvía el valor tal cual,
// cualquier contrato basado en filtros pasaba sin comprobar nada (y `add_filter`
// era un no-op). Un check que no puede fallar no es un check.
if (!isset($GLOBALS['_wp_filters'])) { $GLOBALS['_wp_filters'] = array(); }
if (!function_exists('add_filter')) {
    function add_filter($t, $c, $p = 10, $a = 1) {
        $GLOBALS['_wp_filters'][$t][(int) $p][] = $c;
        return true;
    }
}
if (!function_exists('apply_filters')) {
    function apply_filters($t, $v, ...$args) {
        $niveles = $GLOBALS['_wp_filters'][$t] ?? array();
        ksort($niveles);
        foreach ($niveles as $cbs) {
            foreach ($cbs as $cb) {
                if (is_callable($cb)) { $v = $cb($v, ...$args); }
            }
        }
        return $v;
    }
}
if (!function_exists('remove_all_filters')) {
    function remove_all_filters($t) { unset($GLOBALS['_wp_filters'][$t]); return true; }
}
if (!function_exists('do_action')) { function do_action($t, ...$a) {} }
if (!function_exists('add_action')) { function add_action($t, $c, $p = 10, $a = 1) { return true; } }
if (!function_exists('remove_action')) { function remove_action($t, $c, $p = 10) { return true; } }
if (!function_exists('has_action')) { function has_action($t, $c = false) { return false; } }
if (!function_exists('has_filter')) {
    function has_filter($t, $c = false) {
        if (empty($GLOBALS['_wp_filters'][$t])) { return false; }
        if (false === $c) { return true; }
        foreach ($GLOBALS['_wp_filters'][$t] as $cbs) {
            if (in_array($c, $cbs, true)) { return true; }
        }
        return false;
    }
}
if (!function_exists('do_action_deprecated')) { function do_action_deprecated($t, $a = [], $v = '', $alt = '') {} }
if (!function_exists('apply_filters_deprecated')) { function apply_filters_deprecated($t, $a = [], $v = '', $alt = '') { return $a[0] ?? null; } }
if (!function_exists('did_action')) { function did_action($t) { return 0; } }

// --- Auth ---
if (!function_exists('current_user_can')) { function current_user_can($c, ...$a) { return true; } }
if (!function_exists('get_current_user_id')) { function get_current_user_id() { return 1; } }
if (!function_exists('wp_create_nonce')) { function wp_create_nonce($a = -1) { return md5($a . time()); } }
if (!function_exists('wp_verify_nonce')) { function wp_verify_nonce($n, $a = -1) { return true; } }
if (!function_exists('get_userdata')) {
    function get_userdata($id) {
        // Override por test: _test_users[id].
        if (!empty($GLOBALS['_test_users'][(int) $id])) {
            return $GLOBALS['_test_users'][(int) $id];
        }
        $u = new WP_User();
        $u->ID = (int) $id;
        $u->display_name = 'Test User';
        $u->first_name = 'First' . (int) $id;
        // Derivado del ID: los tests afirman sobre el email del usuario.
        $u->user_email = 'user' . (int) $id . '@example.com';
        return $u;
    }
}
if (!function_exists('get_user_by')) { function get_user_by($field, $value) { $u = new WP_User(); $u->ID = 1; $u->user_email = 'test@example.com'; return $u; } }
if (!function_exists('wp_set_current_user')) { function wp_set_current_user($id, $name = '') { return new WP_User(); } }

// --- HTTP ---
if (!function_exists('wp_remote_get')) { function wp_remote_get($u, $a = []) { return ['response' => ['code' => 200], 'body' => '{}']; } }
if (!function_exists('wp_remote_post')) { function wp_remote_post($u, $a = []) { return ['response' => ['code' => 200], 'body' => '{}']; } }
if (!function_exists('is_wp_error')) { function is_wp_error($t) { return $t instanceof WP_Error; } }
if (!function_exists('wp_json_encode')) { function wp_json_encode($d, $o = 0, $d2 = 512) { return json_encode($d, $o, $d2); } }

// --- Time ---
if (!function_exists('current_time')) {
    function current_time($type = 'mysql') {
        if ($type === 'mysql') return date('Y-m-d H:i:s');
        if ($type === 'timestamp') return time();
        return date($type);
    }
}
if (!function_exists('wp_timezone_string')) {
    // Sin esto, Utils::format_date() no puede crear la fecha, captura el error y devuelve la
    // cadena sin tocar: cualquier regla que sume dias o años se rompe en silencio.
    function wp_timezone_string() { return (string) (get_option('timezone_string') ?: 'UTC'); }
}
if (!function_exists('wp_timezone')) { function wp_timezone() { return new \DateTimeZone(wp_timezone_string()); } }
if (!function_exists('date_i18n')) { function date_i18n($f, $ts = null) { return date($f, $ts ?? time()); } }
if (!function_exists('wp_date')) { function wp_date($f, $ts = null) { return date($f, $ts ?? time()); } }

// --- Posts ---
if (!function_exists('get_the_title')) {
    function get_the_title($id) {
        // Convencion de pruebas: permite fijar el titulo sin montar la entrada entera.
        if (isset($GLOBALS['_test_post_title'])) { return (string) $GLOBALS['_test_post_title']; }
        return "Post $id";
    }
}
if (!function_exists('get_post_status')) { function get_post_status($id) { return 'publish'; } }
if (!function_exists('get_post_type')) {
    function get_post_type($post = null) {
        $id = is_object($post) ? (int) ($post->ID ?? 0) : (int) $post;
        if (!empty($GLOBALS['_test_posts'][$id])) { return $GLOBALS['_test_posts'][$id]->post_type ?? false; }
        return $GLOBALS['_wp_stores']['post_types'][$id] ?? 'miembro';
    }
}
if (!function_exists('is_email')) { function is_email($email) { return (bool) filter_var((string) $email, FILTER_VALIDATE_EMAIL); } }
if (!function_exists('post_type_exists')) { function post_type_exists($t) { return in_array($t, ['post', 'page', 'miembro', 'registro_hora', 'actividad', 'inscripcion', 'centro_turno', 'centro_actividad'], true); } }
if (!function_exists('get_post')) {
    function get_post($id = null, $output = OBJECT, $filter = 'raw') {
        // Convención de tests: _test_posts[id] permite mockear posts completos.
        if (!empty($GLOBALS['_test_posts'][(int) $id])) {
            return $GLOBALS['_test_posts'][(int) $id];
        }
        $p = new WP_Post();
        $p->ID = (int) $id;
        // Permitir que los tests configuren el tipo por ID (convención: _wp_post_type_{id}).
        $type = $GLOBALS['_wp_stores']['post_types'][(int) $id] ?? 'miembro';
        $p->post_type = $type;
        $p->post_status = 'publish';
        return $p;
    }
}
if (!function_exists('wp_insert_post')) {
    function wp_insert_post($data, $error = false) {
        // Store de posts para los tests que necesitan recorrer un ciclo real
        // (crear → consultar → actualizar). El primer ID sigue siendo 99, así que los
        // tests que solo comprobaban "se creó algo" no cambian.
        $GLOBALS['_wp_stub_next_id'] = $GLOBALS['_wp_stub_next_id'] ?? 99;
        $id = $GLOBALS['_wp_stub_next_id']++;

        $p = new WP_Post();
        $p->ID          = $id;
        $p->post_type   = $data['post_type'] ?? 'post';
        $p->post_status = $data['post_status'] ?? 'publish';
        $p->post_title  = $data['post_title'] ?? '';
        $p->post_author = (int) ($data['post_author'] ?? 0);

        $GLOBALS['_wp_stores']['posts'][$id]       = $p;
        $GLOBALS['_wp_stores']['post_types'][$id]  = $p->post_type;
        $GLOBALS['_wp_stores']['post_status'][$id] = $p->post_status;

        foreach ((array) ($data['meta_input'] ?? []) as $k => $v) {
            $GLOBALS['_wp_stores']['post_meta'][$id][$k] = $v;
        }

        return $id;
    }
}
if (!function_exists('wp_update_post')) {
    function wp_update_post($data) {
        // Spy: registrar los datos pasados (convención usada por los tests).
        $id = $data['ID'] ?? 0;
        $GLOBALS['_wp_stores']['post_meta'][$id]['_wp_update_data'] = $data;
        if (!empty($data['post_title'])) {
            $GLOBALS['_wp_stores']['post_meta'][$id]['_wp_updated_title'] = $data['post_title'];
        }
        return $id;
    }
}
if (!function_exists('wp_delete_post')) { function wp_delete_post($id, $force = false) { return true; } }
if (!function_exists('get_posts')) {
    /** ¿Cumple un post las cláusulas de meta_query? (soporta =, EXISTS, NOT EXISTS) */
    function _wp_stub_meta_matches($id, $mq) {
        foreach ((array) $mq as $clause) {
            if (!is_array($clause) || empty($clause['key'])) { continue; }
            $key     = $clause['key'];
            $compare = strtoupper((string) ($clause['compare'] ?? '='));
            $meta    = $GLOBALS['_wp_stores']['post_meta'][$id] ?? [];
            $has     = array_key_exists($key, $meta);

            if ($compare === 'NOT EXISTS') { if ($has) { return false; } continue; }
            if ($compare === 'EXISTS')     { if (!$has) { return false; } continue; }
            if (!$has) { return false; }
            if ($compare === '=' && (string) $meta[$key] !== (string) ($clause['value'] ?? '')) { return false; }
        }
        return true;
    }

    function get_posts($args = []) {
        // Compatibilidad: sin posts en el store no hay nada que devolver (comportamiento previo).
        $posts = $GLOBALS['_wp_stores']['posts'] ?? [];
        if (empty($posts)) { return []; }

        $types  = (array) ($args['post_type'] ?? 'post');
        $status = (array) ($args['post_status'] ?? ['publish']);
        $mq     = $args['meta_query'] ?? [];
        $limit  = (int) ($args['posts_per_page'] ?? 5);
        $fields = $args['fields'] ?? '';

        // Consulta simple por meta_key/meta_value (la que usa la búsqueda del socio).
        if (empty($mq) && !empty($args['meta_key'])) {
            $mq = array(
                array(
                    'key'   => $args['meta_key'],
                    'value' => (string) ($args['meta_value'] ?? ''),
                ),
            );
        }

        $out = [];
        foreach ($posts as $id => $p) {
            if (!in_array($p->post_type ?? '', $types, true)) { continue; }
            if (!in_array($p->post_status ?? 'publish', $status, true)) { continue; }
            if (!_wp_stub_meta_matches((int) $id, $mq)) { continue; }
            $out[] = ($fields === 'ids') ? (int) $id : $p;
            if ($limit > 0 && count($out) >= $limit) { break; }
        }

        if (strtoupper((string) ($args['order'] ?? '')) === 'DESC') { $out = array_reverse($out); }

        return $out;
    }
}
if (!function_exists('wp_get_post_terms')) {
    function wp_get_post_terms($id, $tax, $args = []) {
        // Override por test: _wp_stores['post_terms'][id].
        if (isset($GLOBALS['_wp_stores']['post_terms'][(int) $id])) {
            return $GLOBALS['_wp_stores']['post_terms'][(int) $id];
        }
        // Default: sin términos.
        return [];
    }
}
if (!function_exists('get_post_meta')) {
    function get_post_meta($id, $key = '', $single = false) {
        $s = &$GLOBALS['_wp_stores']['post_meta'];
        $v = $s[$id][$key] ?? null;
        if ($v === null) return $single ? '' : [];
        if ($single) return $v;
        return is_array($v) ? $v : [$v];
    }
}
if (!function_exists('update_post_meta')) { function update_post_meta($id, $key, $value) { $GLOBALS['_wp_stores']['post_meta'][$id][$key] = $value; return true; } }
if (!function_exists('delete_post_meta')) { function delete_post_meta($id, $key) { unset($GLOBALS['_wp_stores']['post_meta'][$id][$key]); return true; } }

// --- URL helpers ---
if (!function_exists('home_url')) { function home_url($p = '') { return "https://example.com$p"; } }
if (!function_exists('admin_url')) { function admin_url($p = '') { return "/wp-admin/$p"; } }
if (!function_exists('plugin_basename')) { function plugin_basename($f) { return basename($f); } }
if (!function_exists('plugin_dir_path')) { function plugin_dir_path($f) { return dirname($f) . '/'; } }

// --- Misc ---
if (!function_exists('wp_next_scheduled')) {
    function wp_next_scheduled($h) {
        $GLOBALS['_wp_cron']['consultados'][] = $h;
        return $GLOBALS['_wp_cron']['programados'][$h] ?? false;
    }
}
if (!function_exists('wp_schedule_event')) {
    function wp_schedule_event($ts, $r, $h, $a = []) {
        $GLOBALS['_wp_cron']['programados'][$h] = $ts;
        $GLOBALS['_wp_cron']['agendados'][] = ['hook' => $h, 'ts' => $ts, 'recurrencia' => $r];
        return true;
    }
}
if (!function_exists('wp_clear_scheduled_hook')) {
    function wp_clear_scheduled_hook($h, $a = []) {
        $GLOBALS['_wp_cron']['limpiados'][] = $h;
        unset($GLOBALS['_wp_cron']['programados'][$h]);
        return 0;
    }
}
if (!function_exists('register_post_type')) { function register_post_type($s, $a) { return null; } }
if (!function_exists('register_taxonomy')) { function register_taxonomy($s, $t, $a) { return null; } }
if (!function_exists('register_rest_route')) { function register_rest_route($n, $r, $a) { return true; } }
if (!function_exists('wp_redirect')) { function wp_redirect($u) {} }
if (!function_exists('wp_die')) { function wp_die($m = '', $t = '', $a = []) {} }
if (!function_exists('wp_cache_delete')) { function wp_cache_delete($k, $g = '') { return true; } }
if (!function_exists('flush_rewrite_rules')) { function flush_rewrite_rules() {} }

// --- WP_Error ---
if (!class_exists('WP_Error')) {
    class WP_Error {
        private $errors = []; private $error_data = [];
        public function __construct($code = '', $message = '', $data = '') {
            if ($code) { $this->errors[$code] = [$message]; $this->error_data[$code] = $data; }
        }
        public function get_error_code() { return key($this->errors); }
        public function get_error_message($code = '') {
            if (!$code) $code = $this->get_error_code();
            return isset($this->errors[$code][0]) ? $this->errors[$code][0] : '';
        }
    }
}

// --- WP_Post ---
if (!class_exists('WP_Post')) {
    class WP_Post {
        public $ID = 0; public $post_title = ''; public $post_type = 'post';
        public $post_status = 'publish'; public $post_content = '';
        public $post_author = 0; public $post_date = ''; public $post_name = '';
    }
}

// --- WP_User ---
if (!class_exists('WP_User')) {
    class WP_User {
        public $ID = 0; public $roles = ['administrator'];
        public $display_name = 'Test User';
        public $first_name = 'First';
        public $user_email = 'test@example.com';
        public function exists() { return $this->ID > 0; }
        public function has_cap($cap) { return true; }
    }
}


// --- Funciones de WordPress que faltaban en el entorno de pruebas unitarias ---
if (!function_exists('add_shortcode')) {
    function add_shortcode($tag, $cb) { $GLOBALS['_wp_stores']['shortcodes'][$tag] = $cb; return true; }
}
if (!function_exists('get_the_date')) {
    function get_the_date($format = '', $post = null) {
        $id = is_object($post) ? (int) ($post->ID ?? 0) : (int) $post;
        return (string) ($GLOBALS['_wp_stores']['post_dates'][$id] ?? gmdate($format ?: 'Y-m-d'));
    }
}
if (!function_exists('get_the_time')) { function get_the_time($format = '', $post = null) { return get_the_date($format, $post); } }

if (!function_exists('wp_generate_password')) {
    // Usada por Utils::get_persistent_salt() entre otros. Secuencia determinista para que
    // las pruebas no dependan del azar.
    function wp_generate_password($length = 12, $special = true, $extra = false) {
        static $n = 0;
        ++$n;
        return substr(str_repeat(md5((string) $n), 4), 0, max(1, (int) $length));
    }
}
if (!function_exists('wp_rand')) { function wp_rand($min = 0, $max = 0) { return random_int((int) $min, max((int) $min, (int) $max)); } }
if (!function_exists('get_the_author_meta')) { function get_the_author_meta($f = '', $id = 0) { return ''; } }

// Constantes de WordPress que el codigo usa al leer de base de datos.
if (!defined('ARRAY_A')) { define('ARRAY_A', 'ARRAY_A'); }
if (!defined('ARRAY_N')) { define('ARRAY_N', 'ARRAY_N'); }
if (!defined('OBJECT')) { define('OBJECT', 'OBJECT'); }
if (!defined('OBJECT_K')) { define('OBJECT_K', 'OBJECT_K'); }

// --- $wpdb global ---
if (!isset($GLOBALS['wpdb'])) {
    $GLOBALS['wpdb'] = new class {
        public $prefix = 'wp_'; public $posts = 'wp_posts';
        public $postmeta = 'wp_postmeta'; public $options = 'wp_options';
        public $insert_id = 42;
        public function get_var($q = null, $x = 0, $y = 0) {
            // Como WordPress: SHOW TABLES LIKE 'x' devuelve el nombre si existe. Sin esto el
            // Logger cree que no hay tabla y no escribe, y el bloqueo cae al camino de opciones.
            if (is_string($q) && preg_match("/SHOW TABLES LIKE '?([A-Za-z0-9_]+)'?/i", $q, $m)) {
                return $m[1];
            }
            // Bloqueos: Utils::acquire_lock guarda un vencimiento y despues comprueba que
            // el valor leido es el suyo. Sin devolverlo, todo bloqueo parece contencion.
            if (is_string($q) && preg_match('/(convoca_lock_[A-Za-z0-9_]+)/', $q, $k)) {
                return $GLOBALS['_wp_stores']['locks'][$k[1]] ?? null;
            }
            return '0';
        }
        public function get_results($q = null, $o = 'OBJECT') { return []; }
        public function get_row($q = null) { return null; }
        public function query($q) {
            // Los flujos atómicos (p. ej. Hours_Manager::process_approval) cambian
            // el estado con un UPDATE condicional sobre wp_postmeta y solo caen al
            // update_post_meta() si ese UPDATE no afectó a ninguna fila. Sin
            // simularlo, el store de metadatos no cambiaba y los tests veían el
            // estado antiguo. Se aplica el UPDATE al store y se devuelve el número
            // de filas realmente afectadas.
            if (preg_match('/UPDATE\s+\S*postmeta\s+SET\s+meta_value\s*=\s*(\S+)\s+WHERE\s+post_id\s*=\s*(\d+)\s+AND\s+meta_key\s*=\s*\'([^\']+)\'/i', (string) $q, $m)) {
                $new   = $m[1];
                $post  = (int) $m[2];
                $key   = $m[3];
                $store = &$GLOBALS['_wp_stores']['post_meta'];
                $cur   = $store[$post][$key] ?? null;
                if ($cur === null || (string) $cur === $new) {
                    return 0;
                }
                $store[$post][$key] = $new;
                return 1;
            }
            // Bloqueos: el vencimiento se guarda por clave; si sigue vivo, no se concede.
            if (preg_match('/INSERT INTO \S*(?:locks|options)\s*\(/i', (string) $q)
                && preg_match('/(convoca_lock_[A-Za-z0-9_]+)/', (string) $q, $k)) {
                $numeros = array();
                preg_match_all('/\b(\d{6,})\b/', (string) $q, $numeros);
                $nuevo  = (int) ($numeros[1][0] ?? 0);
                $actual = $GLOBALS['_wp_stores']['locks'][$k[1]] ?? null;
                if (null !== $actual && (int) $actual >= time()) {
                    return 0;
                }
                $GLOBALS['_wp_stores']['locks'][$k[1]] = $nuevo;
                return 1;
            }
            if (preg_match('/DELETE FROM \S*(?:locks|options)/i', (string) $q)
                && preg_match('/(convoca_lock_[A-Za-z0-9_]+)/', (string) $q, $k)) {
                unset($GLOBALS['_wp_stores']['locks'][$k[1]]);
                return 1;
            }
            return 1;
        }
        public function insert($t, $d, $f = []) {
            // Se guarda lo insertado: es lo unico que permite afirmar, en una prueba
            // unitaria, que algo quedo registrado en base de datos (por ejemplo un
            // aviso del Logger, que no tiene buffer en memoria).
            $GLOBALS['_wp_stores']['db_inserts'][] = ['table' => $t, 'data' => $d];
            $this->insert_id = 42;
            return 1;
        }
        public function update($t, $d, $w) { return 1; }
        public function delete($t, $w) {
            if (is_array($w) && isset($w['option_name']) && isset($GLOBALS['_wp_stores']['locks'][$w['option_name']])) {
                unset($GLOBALS['_wp_stores']['locks'][$w['option_name']]);
            }
            return 1;
        }
        public function prepare($q, ...$a) {
            $sql = $q;
            foreach ($a as $arg) {
                $p = strpos($sql, '%');
                if ($p !== false) { $sql = substr_replace($sql, (string)$arg, $p, 2); }
            }
            return $sql;
        }
        public function escape($d) { return addslashes($d); }
        public function get_charset_collate() { return 'DEFAULT CHARSET=utf8mb4'; }
    };
}

// Load Composer autoloader LAST (after all stubs)
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

date_default_timezone_set('Europe/Madrid');
