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

// --- Roles de WordPress (el vocabulario de personas lo crea core) ---
if ( ! class_exists( 'WP_Role' ) ) {
    class WP_Role {
        public $name;
        public $capabilities;
        public function __construct( $name = '', $caps = array() ) {
            $this->name         = $name;
            $this->capabilities = is_array( $caps ) ? $caps : array();
        }
        public function has_cap( $cap ) { return ! empty( $this->capabilities[ $cap ] ); }
        public function add_cap( $cap, $grant = true ) { $this->capabilities[ $cap ] = $grant; }
        public function remove_cap( $cap ) { unset( $this->capabilities[ $cap ] ); }
    }
}
if ( ! isset( $GLOBALS['_wp_roles'] ) ) {
    $GLOBALS['_wp_roles']      = array();
    $GLOBALS['_roles_creados'] = 0;
}
if ( ! function_exists( 'add_role' ) ) {
    function add_role( $slug, $nombre, $caps = array() ) {
        if ( isset( $GLOBALS['_wp_roles'][ $slug ] ) ) {
            return null; // WordPress real tampoco pisa un rol existente.
        }
        $GLOBALS['_wp_roles'][ $slug ] = new WP_Role( $nombre, $caps );
        ++$GLOBALS['_roles_creados'];
        return $GLOBALS['_wp_roles'][ $slug ];
    }
    function get_role( $slug ) {
        return $GLOBALS['_wp_roles'][ $slug ] ?? null;
    }
}

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
        // Se apunta **qué** se lee: hay guardas que valen precisamente porque no llegan a consultar la base.
        $GLOBALS['_wp_stores']['meta_reads'][] = array( (int) $id, (string) $key );
        // Lectura de un solo uso (`post_meta_once`): permite que el valor cambie ENTRE dos lecturas, que es
        // justo lo que hay que probar en una carrera (el socio paga mientras el cron está corriendo).
        if (isset($GLOBALS['_wp_stores']['post_meta_once'][(int) $id][$key])) {
            $v = $GLOBALS['_wp_stores']['post_meta_once'][(int) $id][$key];
            unset($GLOBALS['_wp_stores']['post_meta_once'][(int) $id][$key]);
            return $single ? $v : (is_array($v) ? $v : [$v]);
        }
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
if (!function_exists('get_users')) {
    /**
     * Usuarios por rol, desde el almacén real (`_wp_stores['usuarios']`). Devolvía `[]` siempre, así que
     * cualquier código que reparta algo entre los administradores (un aviso, por ejemplo) no se podía medir: la
     * prueba habría dado por bueno que no se avisa a nadie.
     */
    function get_users($args = []) {
        $roles  = (array) ($args['role__in'] ?? ($args['role'] ?? []));
        $campos = $args['fields'] ?? '';
        $salida = [];
        foreach (($GLOBALS['_wp_stores']['usuarios'] ?? []) as $id => $u) {
            if ($roles) {
                $suyos = (array) ($u->roles ?? []);
                if (!array_intersect($roles, $suyos)) { continue; }
            }
            if ('ID' === $campos) { $salida[] = (int) $id; continue; }
            $salida[] = $u;
        }
        return $salida;
    }
}
// Por defecto, visitante anónimo (el valor de siempre). La prueba puede abrir sesión con
// `$GLOBALS['_wp_logged_in'] = true`: sin eso no hay forma de probar los flujos que cambian de
// comportamiento cuando ya hay sesión de WordPress (p. ej. el login del panel de socios).
// `remove_accents()`: la usan los planes de socio para que «socia etica» encuentre «Socia Ética». Sin ella
// (no está en el arnés) la búsqueda se vuelve sensible a los acentos y nadie lo nota.
if (!function_exists('remove_accents')) {
    function remove_accents($texto = '') {
        $mapa = array(
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', 'ç' => 'c',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N', 'Ç' => 'C',
        );
        return \strtr((string) $texto, $mapa);
    }
}

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in() { return (bool) ($GLOBALS['_wp_logged_in'] ?? false); }
}
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
if (!function_exists('esc_html')) { function esc_html($t) { return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('esc_attr')) { function esc_attr($t) { return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('esc_url')) { function esc_url($u) { return filter_var($u, FILTER_SANITIZE_URL); } }
if (!function_exists('_n')) { function _n($s, $p, $n, $d = 'default') { return 1 === (int) $n ? $s : $p; } }
if (!function_exists('esc_html__')) { function esc_html__($t, $d = 'default') { return $t; } }
if (!function_exists('esc_attr__')) { function esc_attr__($t, $d = 'default') { return $t; } }
if (!function_exists('esc_html_e')) { function esc_html_e($t, $d = 'default') { echo $t; } }
if (!function_exists('esc_attr_e')) { function esc_attr_e($t, $d = 'default') { echo $t; } }
if (!function_exists('is_ssl')) { function is_ssl() { return false; } }
if (!function_exists('wp_parse_url')) { function wp_parse_url($url, $component = -1) { return parse_url($url, $component); } }
if (!function_exists('esc_url_raw')) {
    /** Saneado de URL para guardar: `esc_url()` codifica los espacios (`%20`) y descarta esquemas raros. */
    function esc_url_raw($u) {
        $u = trim((string) $u);
        if ('' === $u) { return ''; }
        if (!preg_match('~^(https?:|mailto:|/#|/)~i', $u)) { return ''; }
        return str_replace(' ', '%20', $u);
    }
}
if (!function_exists('add_query_arg')) {
    function add_query_arg($args, $url = '') {
        // Dos formas, como WordPress: `add_query_arg( array( ... ), $url )` y `add_query_arg( $clave, $valor, $url )`.
        // Sólo la primera estaba: con la segunda, `add_query_arg( 'members_success', '1', home_url() )` devolvía
        // `'1'` y el redirect de un alta quedaba en una cadena sin URL, que ninguna prueba miraba.
        if (!is_array($args)) {
            $clave = (string) $args;
            $valor = (string) $url;
            $destino = (string) (func_get_args()[2] ?? '');
            $sep = (false === strpos($destino, '?')) ? '?' : '&';
            return $destino . $sep . rawurlencode($clave) . '=' . rawurlencode($valor);
        }
        $sep = (false === strpos($url, '?')) ? '?' : '&';
        return $url . $sep . http_build_query($args);
    }
}
if (!function_exists('set_url_scheme')) { function set_url_scheme($url, $scheme = null) { return $url; } }
if (!function_exists('wp_kses_post')) {
    /**
     * Subconjunto de `wp_kses_post()`: no es el filtro completo de WordPress, pero sí lo que hace falta para poder
     * afirmar que un contenido de la base **no** se pinta crudo (el arnés anterior era passthrough, así que una
     * prueba de seguridad con `<script>` habría pasado sin medir nada).
     */
    function wp_kses_post($s) {
        $s = (string) $s;
        $s = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $s);
        $s = preg_replace('#<iframe\b[^>]*>.*?</iframe>#is', '', $s);
        $s = preg_replace('#\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $s);
        return $s;
    }
}
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
// `date_i18n()` formatea en la **zona del sitio**, igual que `wp_date()`. Con `gmdate()` se leía en UTC: una
// fecha de evento a las 18:30 en Madrid salía a las 17:30, y la prueba lo habría tomado por un fallo del producto.
if (!function_exists('date_i18n')) { function date_i18n($f, $ts = null) { return wp_date($f, $ts ?? time()); } }
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
// El nombre del sitio sale en los correos, los certificados y los pies: la prueba puede cambiarlo con
// `$GLOBALS['_wp_site']['name']`. Sin fijarlo, el de siempre.
if (!function_exists('get_bloginfo')) {
    function get_bloginfo($show = 'name') {
        $sitio = $GLOBALS['_wp_site'] ?? array();
        if (isset($sitio[$show])) { return $sitio[$show]; }
        return 'name' === $show ? ($sitio['name'] ?? 'Sitio de Prueba') : ($sitio[$show] ?? '');
    }
}
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
// `sanitize_textarea_field()`: la usan el consentimiento y las notas. Faltaba y reventaba el registro.
if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($t = '') { return trim(strip_tags((string) $t)); }
}
// `sanitize_text_field()` acepta `null` como WordPress (los parámetros REST que no vienen llegan así) y no
// dispara el deprecado de `strip_tags(null)`.
if (!function_exists('sanitize_text_field')) { function sanitize_text_field($s = '') { return trim(strip_tags((string) $s)); } }
if (!function_exists('sanitize_title')) { function sanitize_title($t) { return strtolower(str_replace(' ', '-', trim($t))); } }
if (!function_exists('sanitize_email')) { function sanitize_email($e) { return filter_var(trim((string) $e), FILTER_SANITIZE_EMAIL); } }
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
if (!function_exists('remove_filter')) {
    /**
     * Quita un filtro. Faltaba en el arnés: una prueba que añade un filtro para medir un caso y no lo quita
     * lo deja puesto para las siguientes, y el fallo aparece lejos de su causa.
     */
    function remove_filter($t, $c, $p = 10) {
        if (!isset($GLOBALS['_wp_filters'][$t][(int) $p])) {
            return false;
        }
        $i = array_search($c, $GLOBALS['_wp_filters'][$t][(int) $p], true);
        if (false === $i) {
            return false;
        }
        unset($GLOBALS['_wp_filters'][$t][(int) $p][$i]);
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
// `do_action()` apunta lo disparado: los webhooks (caducidad, alta, baja) son el efecto observable de medio
// plugin y con un no-op no se puede afirmar que se disparen.
if (!function_exists('do_action')) {
    function do_action($hook, ...$args) {
        $GLOBALS['_wp_actions'][] = array('hook' => $hook, 'args' => $args);
    }
}
if (!function_exists('add_action')) {
    /**
     * `add_action()` **registra**, y `has_action()` lo consulta: con `has_action()` devolviendo siempre `false`,
     * la rama de compatibilidad de `Utils::do_action()` (disparar también el gancho viejo) era inmedible y
     * cualquier prueba habría dado por hecho que no existe.
     */
    function add_action($t, $c, $p = 10, $a = 1) { $GLOBALS['_wp_hooks'][$t][] = $c; return true; }
}
if (!function_exists('remove_action')) { function remove_action($t, $c, $p = 10) { return true; } }
if (!function_exists('has_action')) { function has_action($t, $c = false) { return isset($GLOBALS['_wp_hooks'][$t]) ? 10 : false; } }
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
if (!function_exists('do_action_deprecated')) {
    /**
     * En WordPress `do_action_deprecated()` **dispara** el gancho antiguo (avisando del cambio). Aquí era un
     * no-op: la rama de compatibilidad de `Utils::do_action()` se la tragaba y parecía que el gancho viejo no
     * existía. Se apunta igual que `do_action()`.
     */
    function do_action_deprecated($t, $a = [], $v = '', $alt = '') {
        $GLOBALS['_wp_actions'][] = array('hook' => $t, 'args' => (array) $a, 'deprecated_por' => $alt);
        return null;
    }
}
if (!function_exists('apply_filters_deprecated')) { function apply_filters_deprecated($t, $a = [], $v = '', $alt = '') { return $a[0] ?? null; } }
if (!function_exists('did_action')) { function did_action($t) { return 0; } }

// --- Auth ---
if (!function_exists('current_user_can')) {
    // Override por prueba: $GLOBALS['_test_caps'][capacidad] = false. Sin entrada, se
    // concede, que es como se comportaba antes (no cambia ninguna prueba existente).
    function current_user_can($c, ...$a) { return $GLOBALS['_test_caps'][$c] ?? true; }
}
if (!function_exists('get_current_user_id')) { function get_current_user_id() { return 1; } }

/**
 * ¿Puede ESTE usuario hacer algo? Importa cuando la decisión no es sobre quien tiene la sesión: el aterrizaje
 * tras el acceso o los permisos de una pantalla concreta se deciden sobre el usuario que entra.
 */
if (!function_exists('user_can')) {
    function user_can($user, $cap, ...$a) {
        if (!($user instanceof WP_User)) { return false; }
        return $GLOBALS['_test_user_caps'][$user->ID][$cap] ?? ($GLOBALS['_test_caps'][$cap] ?? true);
    }
}
if (!function_exists('wp_create_nonce')) { function wp_create_nonce($a = -1) { return md5($a . time()); } }
if (!function_exists('wp_verify_nonce')) {
    /**
     * Si la prueba declara `_wp_stores['nonces_validos']`, sólo esos valores valen; si no, se acepta cualquiera (lo
     * que esperan las pruebas que no van de nonces). Con «siempre verdadero» una guarda de nonce no se podía medir
     * y, peor, una prueba que debía morir seguía adelante y llegaba a un `exit` que mataba PHPUnit en silencio.
     */
    function wp_verify_nonce($n, $a = -1) {
        if (array_key_exists('nonces_validos', $GLOBALS['_wp_stores'] ?? array())) {
            return in_array((string) $n, array_map('strval', (array) $GLOBALS['_wp_stores']['nonces_validos']), true);
        }
        return true;
    }
}
if (!function_exists('get_userdata')) {
    function get_userdata($id) {
        // Override por test: _test_users[id].
        if (!empty($GLOBALS['_test_users'][(int) $id])) {
            return $GLOBALS['_test_users'][(int) $id];
        }
        // Usuario del almacén real (`_wp_stores['usuarios']`): sin esto, `get_userdata()` devolvía un usuario
        // inventado sin `user_login`, así que quien leía el nombre de cuenta recibía una cadena vacía.
        if (isset($GLOBALS['_wp_stores']['usuarios'][(int) $id])) {
            // Se devuelve un `WP_User` hidratado, no el objeto plano del almacén: WordPress entrega un WP_User y
            // el producto comprueba `instanceof`. Con el objeto plano, la rama que **retira los roles** al borrar
            // los datos personales (members#40) no se ejecutaba nunca y no se podía medir.
            $datos = $GLOBALS['_wp_stores']['usuarios'][(int) $id];
            $u = new WP_User();
            foreach ((array) $datos as $campo => $valor) {
                if ('roles' === $campo) { $u->roles = (array) $valor; continue; }
                if (property_exists($u, $campo)) { $u->$campo = $valor; }
            }
            $u->ID = (int) $id;
            return $u;
        }
        // Un usuario que no está **no existe**: WordPress devuelve `false`. Inventarlo hacía que una fila con un
        // id de usuario ya borrado se pintara como «Test User» en vez de como su número.
        if (empty($GLOBALS['_wp_stores']['users_forzados'][(int) $id])) {
            return false;
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
// Usuarios del sitio con registro REAL (`_wp_stores['usuarios']`, id => datos). Antes `get_user_by()`
// devolvía siempre el usuario 1, así que cualquier código que decida «¿ya existe una cuenta con este correo?»
// tomaba siempre la misma rama y no había manera de probar las demás.
if (!function_exists('get_user_by')) {
    function get_user_by($field, $value) {
        $campo = 'email' === $field ? 'user_email' : ('login' === $field ? 'user_login' : ('id' === $field ? 'ID' : $field));
        foreach (($GLOBALS['_wp_stores']['usuarios'] ?? []) as $id => $u) {
            $actual = is_object($u) ? ($u->$campo ?? null) : ($u[$campo] ?? null);
            if (null !== $actual && (string) $actual === (string) $value) {
                return is_object($u) ? $u : (object) $u;
            }
        }
        return false;
    }
}
if (!function_exists('username_exists')) {
    function username_exists($login) { return (bool) get_user_by('login', $login); }
}
if (!function_exists('wp_create_user')) {
    function wp_create_user($login, $pass = '', $email = '') {
        // Fallo controlable: la cola `_wp_create_fail` devuelve errores en orden.
        if (!empty($GLOBALS['_wp_create_fail'])) {
            $fallo = array_shift($GLOBALS['_wp_create_fail']);
            if ($fallo instanceof WP_Error) { return $fallo; }
        }
        $id = 1 + max(array_merge([0], array_map('intval', array_keys($GLOBALS['_wp_stores']['usuarios'] ?? []))));
        $GLOBALS['_wp_stores']['usuarios'][$id] = (object) [
            'ID' => $id, 'user_login' => (string) $login, 'user_email' => (string) $email,
            'display_name' => (string) $login, 'first_name' => '',
        ];
        $GLOBALS['_wp_stores']['users_created'][] = compact('id', 'login', 'pass', 'email');
        return $id;
    }
}
if (!function_exists('wp_update_user')) {
    function wp_update_user($datos = []) {
        $id = (int) ($datos['ID'] ?? 0);
        if ($id && isset($GLOBALS['_wp_stores']['usuarios'][$id])) {
            foreach ($datos as $k => $v) {
                if ('ID' !== $k) { $GLOBALS['_wp_stores']['usuarios'][$id]->$k = $v; }
            }
        }
        $GLOBALS['_wp_stores']['users_updated'][] = $datos;
        return $id;
    }
}
if (!function_exists('wp_set_password')) {
    function wp_set_password($pass, $id) { $GLOBALS['_wp_stores']['passwords'][(int) $id] = (string) $pass; }
}
if (!function_exists('wp_set_current_user')) { function wp_set_current_user($id, $name = '') { return new WP_User(); } }

// --- HTTP ---
if (!function_exists('wp_remote_get')) { function wp_remote_get($u, $a = []) { return ['response' => ['code' => 200], 'body' => '{}']; } }
if (!function_exists('wp_remote_post')) {
    /**
     * POST HTTP: **se apunta lo que sale** (URL, cuerpo y cabeceras) y se puede programar la respuesta.
     *
     * Devolvía siempre 200 y no dejaba rastro, así que lo que viaja en un webhook —el cuerpo y, sobre todo, la
     * firma HMAC— no se podía afirmar. `_wp_stores['http_response']` permite simular un fallo para probar los
     * reintentos: `array('response' => array('code' => 500), 'body' => '')`.
     */
    function wp_remote_post($u, $a = []) {
        $GLOBALS['_wp_http_posts'][] = ['url' => $u, 'args' => $a];
        // Se devuelve lo programado sea lo que sea: un `WP_Error` también es una respuesta legítima de
        // `wp_remote_post()`, y con `is_array()` se descartaba —así no había forma de simular un fallo de red,
        // y la rama que evita degradar una licencia activa quedaba inmedible.
        $respuesta = $GLOBALS['_wp_stores']['http_response'] ?? null;
        if (null !== $respuesta) { return $respuesta; }
        return ['response' => ['code' => 200], 'body' => '{}'];
    }
}
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
    function wp_timezone_string() {
        // Si no hay zona configurada se usa la del proceso: `strtotime()` interpreta en esa y, devolviendo 'UTC'
        // fijo, una fecha guardada como local se leía desplazada (20:15 salía 18:15).
        return (string) (get_option('timezone_string') ?: date_default_timezone_get());
    }
}
if (!function_exists('wp_timezone')) { function wp_timezone() { return new \DateTimeZone(wp_timezone_string()); } }
if (!function_exists('date_i18n')) { function date_i18n($f, $ts = null) { return date($f, $ts ?? time()); } }
// `get_site_option()` (multisite y respaldo del correo de administración): lee del mismo almacén que las
// opciones, con el prefijo de red que usa WordPress.
if (!function_exists('get_site_option')) {
    function get_site_option($name, $default = false) {
        return $GLOBALS['_wp_stores']['site_options'][$name] ?? $default;
    }
}
if (!function_exists('wp_date')) {
    /**
     * `wp_date()` formatea **en la zona del sitio**, no en la del proceso: con `date()` a secas, una fecha
     * guardada como local se desplazaba una hora (18:00 se leía 19:00) y la prueba lo tomaba por un fallo del
     * producto.
     */
    function wp_date($f, $ts = null, $zona = null) {
        $tz = $zona instanceof \DateTimeZone ? $zona : new \DateTimeZone(wp_timezone_string());
        // Reloj controlable (`_test_now`): hay lógica que depende del día del mes (la memoria sólo se genera la
        // primera semana) y sin poder fijar «hoy» esa rama no se puede probar.
        $ahora = $ts ?? $GLOBALS['_test_now'] ?? time();
        $dt = new \DateTime('@' . (int) $ahora);
        $dt->setTimezone($tz);
        return $dt->format($f);
    }
}

// --- Posts ---
if (!function_exists('get_the_title')) {
    function get_the_title($id) {
        // Convencion de pruebas: permite fijar el titulo sin montar la entrada entera.
        if (isset($GLOBALS['_test_post_title'])) { return (string) $GLOBALS['_test_post_title']; }
        // El título del post sembrado: devolver «Post N» siempre hacía que un listado con títulos pareciera
        // correcto sin serlo.
        foreach (['posts', 'test_posts'] as $almacen) {
            if (isset($GLOBALS['_wp_stores'][$almacen][(int) $id]->post_title)) {
                return (string) $GLOBALS['_wp_stores'][$almacen][(int) $id]->post_title;
            }
            if (isset($GLOBALS['_test_posts'][(int) $id]->post_title) && 'test_posts' === $almacen) {
                return (string) $GLOBALS['_test_posts'][(int) $id]->post_title;
            }
        }
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
        // `get_post(0)` es `null` en WordPress: un id 0 no es un post. Inventarlo hacía que un handler «sin
        // sesión» creyera tener ficha y contestara 200 con datos vacíos en vez de 404.
        if ((int) $id <= 0) { return null; }
        // Convención de tests: _test_posts[id] permite mockear posts completos.
        if (!empty($GLOBALS['_test_posts'][(int) $id])) {
            return $GLOBALS['_test_posts'][(int) $id];
        }
        // Y un post **creado** con `wp_insert_post()` se puede volver a leer: se escribe en
        // `_wp_stores['posts']` y `get_post()` lo ignoraba, así que lo recién creado se leía como un post
        // inventado sin cuerpo (la descripción de un registro de horas se perdía por el camino).
        if (!empty($GLOBALS['_wp_stores']['posts'][(int) $id])) {
            return $GLOBALS['_wp_stores']['posts'][(int) $id];
        }
        // Un id que no está en el almacén **no es un post**: WordPress devuelve `null`. Inventarlo hacía que una
        // ficha borrada a mano siguiera «existiendo» para el código, que entonces la reactivaba en el aire en vez
        // de avisar. La convención `_wp_stores['post_types'][$id]` sigue valiendo para declarar el tipo de un id
        // que se da por existente a propósito.
        if (isset($GLOBALS['_wp_stores']['post_types'][(int) $id])) {
            $p              = new WP_Post();
            $p->ID          = (int) $id;
            $p->post_type   = $GLOBALS['_wp_stores']['post_types'][(int) $id];
            $p->post_status = 'publish';
            return $p;
        }
        return null;
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
        // El cuerpo y el extracto también se guardan: sin `post_content`, lo que el socio escribe en la
        // descripción de sus horas se perdía y ninguna prueba podía notarlo.
        $p->post_content = $data['post_content'] ?? '';
        $p->post_excerpt = $data['post_excerpt'] ?? '';
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
        // Y **actualiza** el post: se quedaba en el espía y la ficha seguía con el título viejo, así que un
        // renombrado (el re-alta lo hace) no se podía afirmar. WordPress cambia el post, no un metadato.
        foreach (array('post_title', 'post_status', 'post_content', 'post_excerpt', 'post_name') as $campo) {
            if (array_key_exists($campo, $data)) {
                if (!empty($GLOBALS['_wp_stores']['posts'][$id])) {
                    $GLOBALS['_wp_stores']['posts'][$id]->$campo = $data[$campo];
                }
                if (!empty($GLOBALS['_test_posts'][$id])) {
                    $GLOBALS['_test_posts'][$id]->$campo = $data[$campo];
                }
            }
        }
        return $id;
    }
}
if (!function_exists('wp_delete_post')) {
    /**
     * Borra un post: lo apunta y lo quita del almacén. Era un no-op que devolvía `true`, así que un borrado de
     * datos personales (inscripciones, horas) no dejaba rastro y no se podía afirmar.
     */
    function wp_delete_post($id, $force = false) {
        // Se apunta el borrado, que es lo observable: antes era un no-op sin rastro y no se podía afirmar que
        // un borrado de datos personales (inscripciones, horas) había ocurrido. NO se quita del almacén: hay
        // pruebas que siembran un post y luego lo releen, y el doble no debe cambiarles el escenario.
        $GLOBALS['_wp_stores']['deleted_posts'][] = (int) $id;
        return true;
    }
}
if (!function_exists('get_posts')) {
    /** ¿Cumple un post las cláusulas de meta_query? (soporta =, EXISTS, NOT EXISTS) */
    function _wp_stub_meta_matches($id, $mq) {
        // `relation => OR`: cualquier cláusula basta. Sin esto, las consultas «por correo **o** DNI» exigían
        // las dos y devolvían vacío (una inscripción se busca así).
        $relacion = strtoupper((string) ($mq['relation'] ?? 'AND'));
        if ('OR' === $relacion) {
            foreach ((array) $mq as $clave => $clause) {
                if (!is_array($clause) || empty($clause['key'])) { continue; }
                if (_wp_stub_meta_matches($id, array($clause))) { return true; }
            }
            return false;
        }
        foreach ((array) $mq as $clause) {
            if (!is_array($clause) || empty($clause['key'])) { continue; }
            $key     = $clause['key'];
            $compare = strtoupper((string) ($clause['compare'] ?? '='));
            $meta    = $GLOBALS['_wp_stores']['post_meta'][$id] ?? [];
            $has     = array_key_exists($key, $meta);

            if ($compare === 'NOT EXISTS') { if ($has) { return false; } continue; }
            if ($compare === 'EXISTS')     { if (!$has) { return false; } continue; }
            if (!$has) { return false; }
            // MySQL compara con la colación por defecto, que es **insensible a mayúsculas** (`_ci`): con `!==`
            // estricto, un correo escrito en mayúsculas no encontraba a su socio y la exportación de datos
            // personales respondía «no hay nada», que es un falso negativo con consecuencias legales.
            if ($compare === '=' && 0 !== strcasecmp((string) $meta[$key], (string) ($clause['value'] ?? ''))) { return false; }
            // `compare => 'LIKE'`: subcadena, también sin distinguir mayúsculas. Es como busca la búsqueda
            // global por correo, DNI, teléfono o número de socio; sin soportarlo, la cláusula no filtraba y
            // aparecían fichas que no casaban.
            if ($compare === 'LIKE' && false === mb_stripos((string) $meta[$key], (string) ($clause['value'] ?? ''))) { return false; }
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
            $estado = $p->post_status ?? 'publish';
            // `post_status => 'any'` en WordPress es «todo menos papelera, borrador automático y herencia».
            // Tratarlo como un estado literal dejaba las consultas de «todo» devolviendo nada.
            if (in_array('any', $status, true)) {
                if (in_array($estado, ['trash', 'auto-draft', 'inherit'], true)) { continue; }
            } elseif (!in_array($estado, $status, true)) { continue; }
            if (!_wp_stub_meta_matches((int) $id, $mq)) { continue; }
            $out[] = ($fields === 'ids') ? (int) $id : $p;
            if ($limit > 0 && count($out) >= $limit) { break; }
        }

        if (strtoupper((string) ($args['order'] ?? '')) === 'DESC') { $out = array_reverse($out); }

        return $out;
    }
}
// Términos de un objeto (proyectos del socio en los correos y certificados). Se sirven del almacén; por
// defecto, ninguno.
/**
 * `WP_Query` mínimo sobre `_wp_stores['posts']`: filtra por tipo, estado y `meta_query` (con sus EXISTS y
 * NOT EXISTS). Lo usan las lecturas del área de socio.
 */
if (!class_exists('WP_Query')) {
    class WP_Query {
        public $posts = array();
        public $found_posts = 0;
        public $post_count = 0;
        private $i = 0;

        public function __construct($args = array()) {
            $tipos  = (array) ($args['post_type'] ?? 'post');
            $estado = (array) ($args['post_status'] ?? ['publish']);
            $mq     = $args['meta_query'] ?? array();
            $limit  = (int) ($args['posts_per_page'] ?? 10);

            $busca = isset($args['s']) ? trim((string) $args['s']) : '';
            // `date_query` por año: sin esto, «las entradas de este año» contaba todas y la cifra mentía.
            $anio = null;
            foreach ((array) ($args['date_query'] ?? array()) as $dq) {
                if (isset($dq['year'])) { $anio = (int) $dq['year']; }
            }
            foreach (($GLOBALS['_wp_stores']['posts'] ?? []) as $post) {
                if (!in_array($post->post_type ?? '', $tipos, true) && !in_array('any', $tipos, true)) { continue; }
                $st = $post->post_status ?? 'publish';
                if (!in_array('any', $estado, true) && !in_array($st, $estado, true)) { continue; }
                // `s`: WordPress busca en título, extracto y contenido, sin distinguir mayúsculas (colación `_ci`).
                // Sin esto, una búsqueda devolvía **todo** y la prueba de «un anónimo no ve socios» no probaba nada.
                if ('' !== $busca) {
                    $pajar = (string) ($post->post_title ?? '') . ' ' . (string) ($post->post_excerpt ?? '') . ' ' . (string) ($post->post_content ?? '');
                    if (false === mb_stripos($pajar, $busca)) { continue; }
                }
                if ($mq && !_wp_stub_meta_matches((int) ($post->ID ?? 0), $mq)) { continue; }
                if (null !== $anio) {
                    $fecha = (string) ($post->post_date ?? '');
                    if ((int) substr($fecha, 0, 4) !== $anio) { continue; }
                }
                $this->posts[] = $post;
            }

            // `orderby => 'date'`: por fecha. Con `order => DESC` WordPress pone lo más reciente primero; se
            // ordena **antes** de recortar, que es lo que decide qué entra en el límite.
            $orden = strtoupper((string) ($args['order'] ?? ''));
            if (strtolower((string) ($args['orderby'] ?? '')) === 'date') {
                usort($this->posts, static function ($a, $b) {
                    return strcmp((string) ($a->post_date ?? ''), (string) ($b->post_date ?? ''));
                });
            }
            if ('DESC' === $orden) { $this->posts = array_reverse($this->posts); }
            // `found_posts` es el total que casa, no lo que cabe en la página: WordPress devuelve ahí el total
            // de la consulta, y con `posts_per_page => 1` hay quien cuenta con ese número (cifras del sitio).
            $this->found_posts = count($this->posts);
            if ($limit > 0) { $this->posts = array_slice($this->posts, 0, $limit); }
            $this->post_count = count($this->posts);
        }

        public function have_posts() { return $this->i < count($this->posts); }
        public function the_post() { return $this->posts[$this->i++] ?? null; }
        public function get_posts() { return $this->posts; }
    }
}
if (!function_exists('wp_get_object_terms')) {
    function wp_get_object_terms($id, $tax, $args = []) {
        return $GLOBALS['_wp_stores']['object_terms'][(int) $id] ?? [];
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
// Los registros se GUARDAN, como ya hace _wp_cron, para que las pruebas puedan comprobar
// los ARGUMENTOS con los que se declara cada cosa y no solo que se declare. Hay defectos
// que solo se ven ahi: una taxonomia sin `show_ui` deja su pantalla de terminos en HTTP
// 500 en WordPress 7.
if (!function_exists('register_post_type')) { function register_post_type($s, $a = []) { $GLOBALS['_wp_post_types_registrados'][$s] = $a; return null; } }
if (!function_exists('register_taxonomy')) { function register_taxonomy($s, $t, $a = []) { $GLOBALS['_wp_taxonomias_registradas'][$s] = ['object_type' => $t, 'args' => $a]; return null; } }
// Las PRUEBAS DE INTEGRACION llamaban a esta funcion y no habia doble: el codigo la usa
// desde el namespace Convoca\Core, asi que sin una version global la suite fallaba con 6
// errores («Call to undefined function ...wp_generate_uuid4»). No se veia porque el CI nunca
// ejecutaba esa suite.
if (!function_exists('wp_generate_uuid4')) {
    function wp_generate_uuid4() {
        $h = bin2hex(random_bytes(16));
        return sprintf('%s-%s-%s-%s-%s', substr($h, 0, 8), substr($h, 8, 4), substr($h, 12, 4), substr($h, 16, 4), substr($h, 20, 12));
    }
}
// Las rutas REST se APUNTAN: un no-op deja sin poder afirmar qué se expone y con qué puerta, que es
// justo donde se cuelan las fugas de datos (una ruta de socio contestando a cualquiera).
// `__return_true` / `__return_false` son de WordPress y se usan como puerta de las rutas públicas: sin ellas
// `is_callable()` dice que la puerta no existe y no se puede distinguir «pública a propósito» de «sin puerta».
if (!function_exists('__return_true'))  { function __return_true()  { return true; } }
if (!function_exists('__return_false')) { function __return_false() { return false; } }

if (!function_exists('register_rest_route')) {
    function register_rest_route($namespace, $route, $args = array()) {
        $GLOBALS['_wp_routes'][] = array('namespace' => $namespace, 'route' => $route, 'args' => $args);
        return true;
    }
}
// `nocache_headers()`: las puertas REST la llaman antes de decidir. Se cuenta, para poder afirmar que se
// llama también cuando la petición se va a rechazar.
if (!function_exists('nocache_headers')) {
    function nocache_headers() {
        $GLOBALS['_wp_stores']['nocache'] = ($GLOBALS['_wp_stores']['nocache'] ?? 0) + 1;
    }
}
if (!function_exists('wp_redirect')) { function wp_redirect($u) {} }
if (!function_exists('wp_die')) {
    /**
     * `wp_die()` **mata** cuando se le pide (`_wp_die_lanza`), que es lo que hace en WordPress: sin eso, una
     * guarda de permiso seguía ejecutando lo que venía detrás y parecía que no protegía nada.
     */
    function wp_die($m = '', $t = '', $a = []) {
        $GLOBALS['_wp_die_llamado'] = $m;
        if (!empty($GLOBALS['_wp_die_lanza'])) { throw new \RuntimeException('wp_die: ' . (string) $m); }
        return null;
    }
}
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
        /** Todos los mensajes, como en WordPress. */
        public function get_error_messages($code = '') {
            if ($code) { return isset($this->errors[$code]) ? $this->errors[$code] : []; }
            $todos = [];
            foreach ($this->errors as $mensajes) { $todos = array_merge($todos, $mensajes); }
            return $todos;
        }

        /**
         * Datos del error (`['status' => 403]`), que es lo que mira el cliente para saber el código HTTP.
         *
         * @param string $code Código del error.
         * @return mixed
         */
        public function get_error_data($code = '') {
            $code = $code ?: $this->get_error_code();
            return $this->error_data[$code] ?? null;
        }
    }
}

// --- WP_Post ---
if (!class_exists('WP_Post')) {
    class WP_Post {
        public $ID = 0; public $post_title = ''; public $post_type = 'post';
        public $post_status = 'publish'; public $post_content = '';
        public $post_excerpt = ''; public $post_modified = ''; public $post_parent = 0;
        public $post_author = 0; public $post_date = ''; public $post_name = '';
    }
}

// --- WP_User ---
if (!class_exists('WP_User')) {
    class WP_User {
        public $ID = 0; public $roles = ['administrator'];
        public $display_name = 'Test User';
        public $first_name = 'First';
        public $last_name = '';
        public $user_login = '';
        public $user_email = 'test@example.com';
        public function exists() { return $this->ID > 0; }
        public function has_cap($cap) { return true; }

        // add_role/remove_role/set_role existen en la clase REAL de WordPress y faltaban en el doble:
        // sin ellos, cualquier código que retire un rol no se podía probar (se estrenó con el borrado de
        // datos del usuario en members#40). Escriben en el almacén para que la prueba pueda comprobarlo,
        // como hace el resto del arnés.
        public function add_role($rol) {
            if (!in_array((string) $rol, $this->roles, true)) { $this->roles[] = (string) $rol; }
            $GLOBALS['_wp_stores']['users'][$this->ID]['roles'] = $this->roles;
            if (isset($GLOBALS['_wp_stores']['usuarios'][$this->ID])) {
                $GLOBALS['_wp_stores']['usuarios'][$this->ID]->roles = $this->roles;
            }
        }
        public function remove_role($rol) {
            $this->roles = array_values(array_diff($this->roles, array((string) $rol)));
            $GLOBALS['_wp_stores']['users'][$this->ID]['roles'] = $this->roles;
            // El almacén de usuarios (`usuarios`) es el que lee `get_userdata()`: si el rol solo se quitara de
            // `users`, la siguiente lectura seguiría viendo el rol viejo.
            if (isset($GLOBALS['_wp_stores']['usuarios'][$this->ID])) {
                $GLOBALS['_wp_stores']['usuarios'][$this->ID]->roles = $this->roles;
            }
        }
        public function set_role($rol) {
            $this->roles = array((string) $rol);
            $GLOBALS['_wp_stores']['users'][$this->ID]['roles'] = $this->roles;
            if (isset($GLOBALS['_wp_stores']['usuarios'][$this->ID])) {
                $GLOBALS['_wp_stores']['usuarios'][$this->ID]->roles = $this->roles;
            }
        }
    }
}

// --- get_edit_post_link() / get_permalink() ---
// Faltaban y hacen falta para probar cualquier aviso que enlace a la ficha del socio.
if (!function_exists('get_edit_post_link')) {
    function get_edit_post_link($post = 0, $context = 'display') {
        $id = is_object($post) ? (int) ($post->ID ?? 0) : (int) $post;
        return 'https://example.test/wp-admin/post.php?post=' . $id . '&action=edit';
    }
}
if (!function_exists('get_permalink')) {
    function get_permalink($post = 0) { $id = is_object($post) ? (int) ($post->ID ?? 0) : (int) $post; return 'https://example.test/?p=' . $id; }
}

// --- wp_delete_attachment() ---
// Faltaba y hace falta para probar cualquier borrado de datos: el borrado de members#40 borra la foto del
// documento de identidad y sin este doble no se puede ejercitar.
if (!function_exists('wp_delete_attachment')) {
    function wp_delete_attachment($id, $force_delete = false) {
        $GLOBALS['_wp_stores']['deleted_attachments'][] = (int) $id;
        return true;
    }
}


// --- Funciones de WordPress que faltaban en el entorno de pruebas unitarias ---
if (!function_exists('add_shortcode')) {
    /**
     * Registro de shortcodes en un almacén **propio** (`$GLOBALS['_wp_shortcodes']`), no dentro de `_wp_stores`:
     * los plugins registran al cargarse, y una prueba que reemplaza el almacén entero borraba el registro y la
     * comprobación de que el shortcode existe dejaba de medir nada.
     */
    function add_shortcode($tag, $cb) { $GLOBALS['_wp_shortcodes'][(string) $tag] = $cb; return true; }

    /**
     * ¿Está registrado un shortcode? WordPress lo tiene siempre; el arnés no lo tenía y un plugin que lo
     * usa legítimamente (para no pintar nada si el otro plugin no está) reventaba al probarse.
     */
    function shortcode_exists($tag) { return isset($GLOBALS['_wp_shortcodes'][$tag]); }

    /**
     * Renderizado mínimo: si el shortcode está registrado, se llama a su devolución con los atributos; si no,
     * se deja el texto tal cual. No pretende imitar a WordPress (no hay `do_shortcode_tag` ni anidados): sirve
     * para comprobar que un plugin delega en otro, que es lo que se prueba aquí.
     */
    function do_shortcode($content) {
        if (!preg_match('/^\[(\S+?)(\s[^\]]*)?\]$/', trim((string) $content), $m)) {
            return $content;
        }
        $tag = $m[1];
        if (!isset($GLOBALS['_wp_stores']['shortcodes'][$tag])) {
            return $content;
        }
        $atts = array();
        if (!empty($m[2]) && preg_match_all('/(\w+)="([^"]*)"/', $m[2], $pares, PREG_SET_ORDER)) {
            foreach ($pares as $par) { $atts[$par[1]] = $par[2]; }
        }
        return (string) call_user_func($GLOBALS['_wp_stores']['shortcodes'][$tag], $atts, '', $tag);
    }
}
if (!function_exists('get_the_date')) {
    function get_the_date($format = '', $post = null) {
        $id = is_object($post) ? (int) ($post->ID ?? 0) : (int) $post;
        $fecha = (string) ($GLOBALS['_wp_stores']['post_dates'][$id] ?? '');
        if ('' === $fecha) {
            $fecha = (string) ($GLOBALS['_wp_stores']['posts'][$id]->post_date ?? '');
        }
        if ('' === $fecha) { return ''; } // Sin fecha conocida no se inventa la de hoy.
        $ts = strtotime($fecha);
        return $ts ? wp_date($format ?: 'Y-m-d', $ts) : $fecha;
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
        /**
         * Escapa `%` y `_` para un LIKE, como WordPress (`esc_like`). Sin esto, buscar la página de un shortcode
         * revienta con «Call to undefined method» y —peor— el producto acaba llevando ramas «de test» para
         * esquivarlo, que es lo que pasaba en `Member_Auth::logout_member_sessions`.
         */
        public function esc_like($text) { return addcslashes((string) $text, '_%\\'); }

        public function get_var($q = null, $x = 0, $y = 0) {
            $GLOBALS['_wp_stores']['db_reads'][] = (string) (is_string($q) ? $q : '');
            // Mapa por consulta (`get_var_mapa`): un método que busca la página de un shortcode y también, más
            // abajo, un pago pendiente recibe respuestas distintas según lo que pregunte. Sin esto las dos
            // páginas (`[convoca_renovar]` y `[convoca_pago]`) daban el mismo id y sus URL coincidían.
            // Se compara sin las barras de escape: `esc_like()` escapa los `_` y `%`.
            $q_mapa = is_string($q) ? str_replace('\\', '', $q) : '';
            foreach (($GLOBALS['_wp_stores']['get_var_mapa'] ?? []) as $aguja => $valor) {
                if ('' !== $q_mapa && false !== strpos($q_mapa, $aguja)) { return $valor; }
            }
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
            // MAX(id) de una tabla: la secuencia de socios se apoya en él para dejar la opción heredada al
            // día. Devolver siempre 0 dejaba esa parte muerta y la prueba no podía notarlo.
            if (is_string($q) && preg_match('/MAX\(\s*id\s*\)\s+FROM\s+([A-Za-z0-9_]+)/i', $q, $m)) {
                return (string) ($GLOBALS['_wp_stores']['max_id'][$m[1]] ?? 0);
            }
            // Respuesta genérica para las consultas que la prueba fija: los flujos con SQL propio (el
            // deduplicador del alta, las transiciones de estado) necesitan devolver un id concreto, no siempre 0.
            return (string) ($GLOBALS['_wp_stores']['get_var'] ?? '0');
        }
        /**
         * Lista de valores (ids, casi siempre). Se sirve de `_wp_stores['col']`, que la prueba fija; sin él,
         * cualquier método que consulte ids (caducidades, renovaciones) reventaba en vez de medirse.
         *
         * @param string|null $q Consulta.
         * @param int         $x Columna.
         * @return array
         */
        public function get_col($q = null, $x = 0) {
            // Cola por consulta (`col_cola`): un método que consulta varias veces con filtros distintos —los
            // avisos de renovación a 30, 15 y 7 días— necesita respuestas distintas, no la misma para todas.
            if (!empty($GLOBALS['_wp_stores']['col_cola'])) {
                $siguiente = array_shift($GLOBALS['_wp_stores']['col_cola']);
                return is_array($siguiente) ? $siguiente : [];
            }
            return $GLOBALS['_wp_stores']['col'] ?? [];
        }
        public function get_results($q = null, $o = 'OBJECT') {
            if (!empty($GLOBALS['_wp_stores']['results'])) { return $GLOBALS['_wp_stores']['results']; }
            // `SELECT option_name, option_value FROM wp_options WHERE option_name LIKE %s`: el barrido que usa
            // Member_Auth para cerrar las sesiones de un socio. Devolver siempre `[]` obligaba al producto a
            // llevar una rama «de test» que leía `$GLOBALS['_wp_stores']` — código de prueba dentro del plugin.
            if (is_string($q) && preg_match("/option_name\s+LIKE\s+'?([^'\s]+)'?/i", $q, $m)) {
                $patron  = str_replace(array('\\_', '\\%', '\\\\'), array('_', '%', '\\'), stripcslashes($m[1]));
                $prefijo = rtrim($patron, '%');
                $filas   = array();
                foreach (($GLOBALS['_wp_stores']['options'] ?? array()) as $nombre => $valor) {
                    if (0 === strpos((string) $nombre, $prefijo)) {
                        $filas[] = (object) array('option_name' => $nombre, 'option_value' => $valor);
                    }
                }
                // El almacén de transients del arnés es esa misma tabla con el prefijo `_transient_`.
                foreach (($GLOBALS['_wp_stores']['transients'] ?? array()) as $clave => $valor) {
                    $nombre = '_transient_' . $clave;
                    if (0 === strpos($nombre, $prefijo)) {
                        $filas[] = (object) array('option_name' => $nombre, 'option_value' => $valor);
                    }
                }
                return $filas;
            }
            return array();
        }
        public function get_row($q = null) {
            $GLOBALS['_wp_stores']['db_reads'][] = (string) (is_string($q) ? $q : '');
            // Respuesta programada por aguja (`get_row_mapa`): un agregado SQL (`SUM(IF(...))`) no se puede
            // simular con el almacén, pero sí se puede dar la fila que devolvería y comprobar cómo se lee.
            foreach (($GLOBALS['_wp_stores']['get_row_mapa'] ?? []) as $aguja => $valor) {
                if (false !== strpos((string) $q, (string) $aguja)) { return $valor; }
            }
            return null;
        }
        public function query($q) {
            $GLOBALS['_wp_stores']['queries'][] = (string) $q;
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
            // Un INSERT directo en `*postmeta` (el que usan las transiciones atómicas de estado) **escribe de
            // verdad**: apuntarlo sólo en `db_inserts` dejaba la meta invisible para `get_post_meta()` y una
            // aprobación recién insertada se leía como «sin estado».
            if (is_string($t) && preg_match('/postmeta$/', $t) && isset($d['post_id'], $d['meta_key'])) {
                $GLOBALS['_wp_stores']['post_meta'][(int) $d['post_id']][$d['meta_key']] = $d['meta_value'] ?? '';
            }
            // AUTO_INCREMENT de mentira: la prueba puede fijar el siguiente id (`$_wp_insert_id`). Sin fijarlo,
            // el de siempre, para no mover lo que ya cuenta con 42.
            $this->insert_id = (int) ($GLOBALS['_wp_insert_id'] ?? 42);
            return 1;
        }
        public function update($t, $d, $w) { return 1; }
        public function delete($t, $w) {
            if (is_array($w) && isset($w['option_name']) && isset($GLOBALS['_wp_stores']['locks'][$w['option_name']])) {
                unset($GLOBALS['_wp_stores']['locks'][$w['option_name']]);
            }
            // Se apunta el borrado: es lo único que permite afirmar, en una prueba unitaria, que se limpió
            // una fila (p. ej. la secuencia de un socio).
            $GLOBALS['_wp_stores']['db_deletes'][] = ['table' => $t, 'where' => $w];
            return 1;
        }
        public function prepare($q, ...$a) {
            // Un solo argumento que sea array se **expande**: es lo que hace WordPress con `IN (%d,%d,...)`.
            // Sin esto, un `DELETE ... IN ($placeholders)` salía literalmente como `IN (Array,%d,%d)` y no se
            // podía afirmar qué filas se borran.
            if (1 === count($a) && is_array($a[0])) { $a = array_values($a[0]); }
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

// Autoloader del propio nucleo. Los plugins cargan este bootstrap y NO instalan las
// dependencias del nucleo, asi que su vendor/autoload.php puede no estar: sin esto, las
// clases reales (Logger, Utils...) no se encuentran y cada plugin acaba tapandolo con
// dobles propios que miden un comportamiento que no existe.
if ( ! function_exists( 'convoca_core_unit_autoload' ) ) {
    function convoca_core_unit_autoload( $class ) {
        $prefijo = 'Convoca\\Core\\';
        if ( 0 !== strpos( $class, $prefijo ) ) {
            return;
        }
        $relativo = str_replace( '\\', '/', substr( $class, strlen( $prefijo ) ) );
        foreach ( array( 'class-', '' ) as $prefijo_archivo ) {
            $ruta = __DIR__ . '/../includes/' . $prefijo_archivo . strtolower( str_replace( '_', '-', $relativo ) ) . '.php';
            if ( file_exists( $ruta ) ) {
                require_once $ruta;
                return;
            }
        }
        // Convencion del proyecto: Clase_Larga.php -> Clase_Larga.php o class-clase-larga.php
        $ruta = __DIR__ . '/../includes/' . $relativo . '.php';
        if ( file_exists( $ruta ) ) {
            require_once $ruta;
        }
    }
    spl_autoload_register( 'convoca_core_unit_autoload' );
}

// Load Composer autoloader LAST (after all stubs)
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

/**
 * Doble mínimo de la respuesta REST de WordPress: los manejadores de los plugins devuelven una, y sin ella el
 * arnés no puede comprobar ni el contenido ni el código de estado de un endpoint.
 */
if (!class_exists('WP_REST_Response')) {
    /**
     * Petición REST mínima: lo que un handler lee de verdad es `get_param()`.
     */
    class WP_REST_Request {
        private $params = array();
        public function __construct($params = array()) { $this->params = (array) $params; }
        public function get_param($key, $default = null) {
            return array_key_exists($key, $this->params) ? $this->params[$key] : $default;
        }
        public function set_param($key, $value) { $this->params[$key] = $value; }
        public function get_params() { return $this->params; }
        public function get_header($name) { return ''; }
        // Ruta de la petición: la usa el monitor de seguridad para decidir si la respuesta es de las nuestras.
        // Faltaba, y sin ella no se podía probar qué se vigila y qué no.
        public function set_route($ruta) { $this->params['__route'] = (string) $ruta; }
        public function get_route() { return (string) ($this->params['__route'] ?? ''); }
    }

    class WP_REST_Response {
        private $data;
        private $status;
        public function __construct($data = null, $status = 200) { $this->data = $data; $this->status = (int) $status; }
        public function get_data() { return $this->data; }
        public function set_data($data) { $this->data = $data; }
        public function get_status() { return $this->status; }
        public function set_status($status) { $this->status = (int) $status; }
        public function get_headers() { return array(); }
        public function header($key, $value, $replace = false) { return $this; }
    }
}

date_default_timezone_set('Europe/Madrid');

// --- WP_User_Query, wp_nonce_url y esc_js: el panel de voluntariado no se podía medir sin ellos ---
if (!class_exists('WP_User_Query')) {
    /**
     * Consulta de usuarios, con los dos filtros que usa el panel: por meta (`_convoca_aprobado = 0`) y por rol.
     *
     * Lee el almacén real (`_wp_stores['usuarios']`, `_wp_stores['user_meta']`), no una lista inventada: si el
     * almacén cambia, la consulta lo ve. Devuelve `[]` si no hay nadie, como WordPress.
     */
    class WP_User_Query {
        private $args = array();
        public function __construct($args = array()) { $this->args = (array) $args; }
        public function get_results() {
            $usuarios = $GLOBALS['_wp_stores']['usuarios'] ?? array();
            $meta     = $GLOBALS['_wp_stores']['user_meta'] ?? array();
            $salida   = array();
            foreach ($usuarios as $id => $u) {
                if (isset($this->args['role'])) {
                    $roles = (array) ($u->roles ?? array());
                    if (!in_array($this->args['role'], $roles, true)) { continue; }
                }
                if (isset($this->args['meta_key'])) {
                    $clave = $this->args['meta_key'];
                    $valor = $meta[(int) $id][$clave] ?? null;
                    if (!array_key_exists('meta_value', $this->args)) {
                        if (null === $valor) { continue; }
                    } elseif ((string) $valor !== (string) $this->args['meta_value']) {
                        continue;
                    }
                }
                $salida[] = $u;
            }
            return $salida;
        }
    }
}

if (!function_exists('wp_nonce_url')) {
    /**
     * URL con nonce. El nonce se compone de la acción para que la prueba pueda afirmar **cuál** se pidió: un
     * botón de aprobar con el nonce de revocar es un fallo que no da error, sólo abre lo que no toca.
     */
    function wp_nonce_url($url, $action = -1) {
        $nonce = 'nonce-' . (is_string($action) ? $action : (string) $action);
        $GLOBALS['_wp_nonces'][] = $nonce;
        return $url . (false === strpos($url, '?') ? '?' : '&') . '_wpnonce=' . $nonce;
    }
}

if (!function_exists('esc_js')) { function esc_js($t) { return addslashes((string) $t); } }

if (!function_exists('wp_nonce_field')) {
    /**
     * Campo de nonce. Se registra la acción para poder afirmar **cuál** se firma: un formulario que cambia el
     * estado de alguien con el nonce de otra acción es un fallo que no da error.
     */
    function wp_nonce_field($action = -1, $name = '_wpnonce', $referer = true, $echo = true) {
        $GLOBALS['_wp_nonces'][] = (string) $action;
        $campo = '<input type="hidden" name="' . $name . '" value="nonce-' . $action . '">';
        if ($echo) { echo $campo; }
        return $campo;
    }
}

if (!function_exists('rest_url')) {
    /** URL base de la API REST. La búsqueda devuelve el enlace a `/me` en vez de datos personales. */
    function rest_url($ruta = '') { return 'https://example.test/wp-json/' . ltrim((string) $ruta, '/'); }
}
if (!function_exists('wp_json_encode')) {
    /** JSON como WordPress (con los flags de siempre). */
    function wp_json_encode($d, $opciones = 0) { return json_encode($d, $opciones); }
}
if (!function_exists('wp_timezone_string')) { function wp_timezone_string() { return 'UTC'; } }

if (!function_exists('wp_remote_retrieve_response_code')) {
    /** Código de respuesta de una petición HTTP, como WordPress. Faltaba y un webhook reventaba al leerlo. */
    function wp_remote_retrieve_response_code($r) { return (int) ($r['response']['code'] ?? 0); }
}
if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($r) { return (string) ($r['body'] ?? ''); }
}
if (!function_exists('is_wp_error')) {
    // Ya suele existir; si no, la mínima: un array de respuesta no es un error.
    function is_wp_error($t) { return $t instanceof \WP_Error; }
}

if (!function_exists('wp_cache_set')) {
    /** Caché de objetos: el limitador de intentos la usa como atajo (sólo un `0` corta). */
    function wp_cache_set($clave, $valor, $grupo = '') { $GLOBALS['_wp_cache'][$grupo][$clave] = $valor; return true; }
}

if (!function_exists('wp_cache_get')) {
    /**
     * Caché de objetos (lectura). Faltaba, y `Utils::check_rate_limit()` la llama: cualquier prueba que llegara
     * al limitador de intentos reventaba con «Call to undefined function».
     */
    function wp_cache_get($clave, $grupo = '', $forzar = false) { return $GLOBALS['_wp_cache'][$grupo][$clave] ?? false; }
}

if (!function_exists('trailingslashit')) {
    /** Barra final, como WordPress. Faltaba y la protección del directorio de PDF la usa. */
    function trailingslashit($ruta) { return rtrim((string) $ruta, '/\\') . '/'; }
}

// El `Logger` limita los avisos por proceso (50 en 60 s) y la suite entera comparte proceso: cuando un fichero
// registra mucho, las pruebas siguientes dejan de ver **sus** avisos y parecen rotas. Se sube el tope para medir;
// el valor de producción sigue en 50 y el filtro es la vía prevista para cambiarlo.
add_filter(
	'convoca_logger_rate_limit_max',
	static function () {
		return PHP_INT_MAX;
	}
);

if (!class_exists('WP_List_Table')) {
    /**
     * Doble mínimo de `WP_List_Table`. Las pantallas de listado del panel (registros, etc.) heredan de ella; sin
     * el doble no se pueden ni construir, así que sus columnas y sus acciones en bloque quedaban sin medir.
     */
    class WP_List_Table {
        public $items = array();
        public $_column_headers = array();
        public $screen = null;
        protected $args = array();
        public function __construct($args = array()) { $this->args = (array) $args; }
        public function get_pagenum() { return (int) ($_GET['paged'] ?? 1); }
        public function current_action() {
            $accion = $_REQUEST['action'] ?? '';
            if (in_array($accion, array('', '-1'), true)) { $accion = $_REQUEST['action2'] ?? ''; }
            return (-1 === (int) $accion || '' === (string) $accion) ? '' : (string) $accion;
        }
        public function display() { echo 'tabla-renderizada'; }
    }
}

if (!function_exists('check_admin_referer')) {
    /** Comprobación de nonce de una acción en bloque: en unitario no hay formulario, así que no mata. */
    function check_admin_referer($accion = -1, $campo = '_wpnonce') { return true; }
}

if (!function_exists('shortcode_atts')) {
    /** Atributos de un shortcode: los del autor sobre los de por defecto. */
    function shortcode_atts($pairs, $atts, $shortcode = '') {
        $atts = (array) $atts;
        $out  = array();
        foreach ((array) $pairs as $name => $default) {
            $out[$name] = array_key_exists($name, $atts) ? $atts[$name] : $default;
        }
        return $out;
    }
}
if (!function_exists('add_shortcode')) {
    function add_shortcode($tag, $cb) { $GLOBALS['_wp_shortcodes'][(string) $tag] = $cb; }
}
if (!function_exists('has_nav_menu')) {
    function has_nav_menu($location) { return !empty($GLOBALS['_wp_stores']['nav_menus'][(string) $location]); }
}
if (!function_exists('wp_nav_menu')) {
    /**
     * Devuelve el HTML del menú de una ubicación. `echo => false` es lo que usa el shortcode; con `echo => true`
     * se imprime, como en WordPress.
     */
    function wp_nav_menu($args = array()) {
        $html = (string) ($GLOBALS['_wp_stores']['nav_html'][(string) ($args['theme_location'] ?? '')] ?? '');
        if (!empty($args['echo'])) { echo $html; return true; }
        return $html;
    }
}
if (!function_exists('do_blocks')) {
    /**
     * Renderiza bloques. Por defecto devuelve el marcado tal cual; con `_wp_stores['blocks_callback']` se delega,
     * que es como se prueba que un bloque pueda volver a entrar en el shortcode (guardas anti-recursión).
     */
    function do_blocks($content) {
        $cb = $GLOBALS['_wp_stores']['blocks_callback'] ?? null;
        return is_callable($cb) ? $cb((string) $content) : (string) $content;
    }
}
if (!function_exists('get_the_ID')) {
    function get_the_ID() { return isset($GLOBALS['post']->ID) ? (int) $GLOBALS['post']->ID : 0; }
}
if (!function_exists('wp_count_posts')) {
    function wp_count_posts($type = 'post') {
        $cuenta = (object) array('publish' => 0, 'draft' => 0, 'pending' => 0, 'private' => 0);
        // El almacén guarda las entradas como `posts[ID]` (que es lo que leen `get_post()`, `WP_Query` y los
        // listados). Contar sólo `posts[tipo]` dejaba las cifras del sitio siempre a cero.
        $todos = $GLOBALS['_wp_stores']['posts'] ?? array();
        if (isset($todos[$type]) && is_array($todos[$type])) { $todos = $todos[$type]; }
        foreach ((array) $todos as $p) {
            $tipo_p = is_object($p) ? (string) ($p->post_type ?? 'post') : (string) ($p['post_type'] ?? 'post');
            if ($tipo_p !== $type) { continue; }
            $estado = is_object($p) ? (string) ($p->post_status ?? 'publish') : (string) ($p['post_status'] ?? 'publish');
            if (isset($cuenta->$estado)) { $cuenta->$estado++; }
        }
        return $cuenta;
    }
}
if (!function_exists('date_i18n')) {
    /** Formato con la zona del sitio, como WordPress. */
    function date_i18n($format, $timestamp_with_offset = false, $gmt = false) {
        $ts = (false === $timestamp_with_offset) ? time() : (int) $timestamp_with_offset;
        return wp_date($format, $ts);
    }
}

if (!function_exists('register_block_type')) {
    /** Registro de bloques: se apunta el nombre y sus argumentos. */
    function register_block_type($name, $args = array()) {
        $GLOBALS['_wp_stores']['blocks'][(string) $name] = $args;
        return true;
    }
}
if (!function_exists('make_clickable')) {
    /** Convierte URLs sueltas en enlaces, como WordPress. */
    function make_clickable($text) {
        return preg_replace('~(?<!["\'=>])(https?://[^\s<]+)~i', '<a href="$1" rel="nofollow">$1</a>', (string) $text);
    }
}

if (!function_exists('wp_script_is')) {
    /** Estado de un script registrado/encolado. */
    function wp_script_is($handle, $list = 'enqueued') {
        $s = $GLOBALS['_wp_stores']['scripts'][(string) $handle] ?? null;
        if (null === $s) { return false; }
        if ('registered' === $list) { return true; }
        return !empty($s['enqueued']);
    }
}
if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script($handle, $src = '', $deps = array(), $ver = false, $footer = false) {
        $GLOBALS['_wp_stores']['scripts'][(string) $handle] = array(
            'src' => (string) $src, 'deps' => (array) $deps, 'ver' => $ver, 'footer' => (bool) $footer,
            'enqueued' => true,
        );
        $GLOBALS['_wp_stores']['enqueues'][] = (string) $handle;
        return true;
    }
}

if (!function_exists('wp_upload_dir')) {
    /** Directorio de subidas: por defecto uno temporal, sin tocar `wp-content`. */
    function wp_upload_dir($time = null, $create_dir = true, $refresh_cache = false) {
        $base = (string) ($GLOBALS['_wp_stores']['upload_dir'] ?? ((getenv('TMPDIR') ?: sys_get_temp_dir()) . '/convoca-uploads'));
        return array('path' => $base, 'url' => 'https://example.test/uploads', 'subdir' => '',
            'basedir' => $base, 'baseurl' => 'https://example.test/uploads', 'error' => false);
    }
}
if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p($dir) { return is_dir($dir) || @mkdir($dir, 0777, true); }
}
if (!function_exists('sanitize_file_name')) {
    function sanitize_file_name($name) { return trim(preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $name), '-'); }
}

if (!function_exists('wp_kses_allowed_html')) {
    /**
     * Lista permitida de `wp_kses()`, en su subconjunto de `post`: lo que hace falta para comprobar que una
     * plantilla **conserva** su estructura (html/head/style/table) y **pierde** lo que no debe estar.
     */
    function wp_kses_allowed_html($context = '') {
        $comunes = array( 'class' => true, 'id' => true, 'style' => true, 'title' => true );
        return array(
            'a' => array_merge($comunes, array('href' => true)),
            'p' => $comunes, 'div' => $comunes, 'span' => $comunes, 'strong' => $comunes,
            'b' => $comunes, 'em' => $comunes, 'i' => $comunes, 'u' => $comunes,
            'h1' => $comunes, 'h2' => $comunes, 'h3' => $comunes, 'h4' => $comunes,
            'ul' => $comunes, 'ol' => $comunes, 'li' => $comunes,
            'br' => array(), 'hr' => array(), 'img' => array('src' => true, 'alt' => true, 'width' => true, 'height' => true),
            'table' => array_merge($comunes, array('border' => true, 'width' => true)),
            'thead' => array(), 'tbody' => array(), 'tr' => $comunes,
            'th' => array_merge($comunes, array('colspan' => true)), 'td' => array_merge($comunes, array('colspan' => true)),
        );
    }
}
if (!function_exists('wp_kses')) {
    /**
     * Subconjunto de `wp_kses()`: elimina lo que no está en la lista permitida (incluidos `script`, `iframe`,
     * `object` y los atributos `on*`). No replica todos los casos de WordPress, pero sí el comportamiento que se
     * mide aquí: una plantilla no puede guardar código ejecutable.
     */
    function wp_kses($content, $allowed) {
        $content = (string) $content;
        // Fuera los elementos peligrosos por completo (con su contenido cuando procede).
        $content = preg_replace('#<(script|iframe|object|embed|form)\b[^>]*>.*?</\1>#is', '', $content);
        $content = preg_replace('#<(script|iframe|object|embed|form)\b[^>]*/?>#is', '', $content);
        // **Lista blanca de atributos**, como WordPress: un atributo que no esté permitido se va. Sin esto, un
        // HTML mal formado podía colar `javascript:…` como atributo suelto y el doble no lo veía.
        $content = preg_replace_callback(
            '#<([a-zA-Z][a-zA-Z0-9]*)([^>]*)>#',
            function ($m) use ($allowed) {
                $etiqueta = strtolower($m[1]);
                if (!isset($allowed[$etiqueta])) { return $m[0]; }
                $permitidos = (array) $allowed[$etiqueta];
                $atributos = preg_replace_callback(
                    '#\s([a-zA-Z_:][a-zA-Z0-9_:.-]*)(\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+))?#',
                    function ($a) use ($permitidos) {
                        return isset($permitidos[strtolower($a[1])]) ? $a[0] : '';
                    },
                    $m[2]
                );
                return '<' . $etiqueta . $atributos . '>';
            },
            $content
        );
        return $content;
    }
}
if (!function_exists('add_options_page')) {
    function add_options_page($page_title, $menu_title, $capability, $menu_slug, $callback = '') {
        $GLOBALS['_wp_stores']['options_pages'][] = compact('page_title', 'menu_title', 'capability', 'menu_slug', 'callback');
        return $menu_slug;
    }
}

if (!function_exists('add_submenu_page')) {
    function add_submenu_page($parent, $page_title, $menu_title, $capability, $menu_slug, $callback = '') {
        $GLOBALS['_wp_stores']['submenu_pages'][] = compact('parent', 'page_title', 'menu_title', 'capability', 'menu_slug', 'callback');
        return $menu_slug;
    }
}
if (!function_exists('admin_url')) {
    function admin_url($path = '') { return 'https://example.test/wp-admin/' . ltrim((string) $path, '/'); }
}
if (!function_exists('get_current_user_id')) {
    function get_current_user_id() { return (int) ($GLOBALS['_test_user_id'] ?? 1); }
}
