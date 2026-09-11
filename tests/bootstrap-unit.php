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
if (!function_exists('sanitize_text_field')) { function sanitize_text_field($s) { return trim(strip_tags($s)); } }
if (!function_exists('sanitize_title')) { function sanitize_title($t) { return strtolower(str_replace(' ', '-', trim($t))); } }
if (!function_exists('sanitize_email')) { function sanitize_email($e) { return filter_var($e, FILTER_SANITIZE_EMAIL); } }
if (!function_exists('sanitize_url')) { function sanitize_url($u) { return filter_var($u, FILTER_SANITIZE_URL); } }
if (!function_exists('absint')) { function absint($v) { return abs((int)$v); } }
if (!function_exists('wp_unslash')) { function wp_unslash($s) { return is_string($s) ? stripslashes($s) : $s; } }

// --- Hooks ---
if (!function_exists('apply_filters')) { function apply_filters($t, $v, ...$a) { return $v; } }
if (!function_exists('do_action')) { function do_action($t, ...$a) {} }
if (!function_exists('add_action')) { function add_action($t, $c, $p = 10, $a = 1) { return true; } }
if (!function_exists('add_filter')) { function add_filter($t, $c, $p = 10, $a = 1) { return true; } }
if (!function_exists('remove_action')) { function remove_action($t, $c, $p = 10) { return true; } }
if (!function_exists('has_action')) { function has_action($t, $c = false) { return false; } }
if (!function_exists('has_filter')) { function has_filter($t, $c = false) { return false; } }
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
if (!function_exists('wp_date')) { function wp_date($f, $ts = null) { return date($f, $ts ?? time()); } }

// --- Posts ---
if (!function_exists('get_the_title')) { function get_the_title($id) { return "Post $id"; } }
if (!function_exists('get_post_status')) { function get_post_status($id) { return 'publish'; } }
if (!function_exists('post_type_exists')) { function post_type_exists($t) { return in_array($t, ['post', 'page', 'miembro'], true); } }
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
if (!function_exists('wp_insert_post')) { function wp_insert_post($data, $error = false) { return 99; } }
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
if (!function_exists('get_posts')) { function get_posts($args = []) { return []; } }
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

// --- $wpdb global ---
if (!isset($GLOBALS['wpdb'])) {
    $GLOBALS['wpdb'] = new class {
        public $prefix = 'wp_'; public $posts = 'wp_posts';
        public $postmeta = 'wp_postmeta'; public $options = 'wp_options';
        public $insert_id = 42;
        public function get_var($q = null, $x = 0, $y = 0) { return '0'; }
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
            return 1;
        }
        public function insert($t, $d, $f = []) { $this->insert_id = 42; return 1; }
        public function update($t, $d, $w) { return 1; }
        public function delete($t, $w) { return 1; }
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
