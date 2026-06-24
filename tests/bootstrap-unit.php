<?php
/**
 * Unit test bootstrap for Convoca Core (standalone, no WordPress).
 * Loads only the composer autoloader, avoiding WP function dependencies.
 */

// ── WordPress constants used by Convoca classes ──────────────────────────
if (!defined('ABSPATH')) {
    define('ABSPATH', '/var/www/html/');
}

if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

if (!defined('CONV_COMMON_VERSION')) {
    define('CONV_COMMON_VERSION', '2.1.2');
}
if (!defined('CONV_COMMON_DIR')) {
    define('CONV_COMMON_DIR', dirname(__DIR__) . '/');
}
if (!defined('CONV_COMMON_DB_VERSION')) {
    define('CONV_COMMON_DB_VERSION', '1.1.0');
}
if (!defined('CONVOCA_CORE_TEST_MODE')) {
    define('CONVOCA_CORE_TEST_MODE', true);
}

// ── WordPress functions used by Convoca classes ─────────────────────────
if (!function_exists('__')) {
    /**
     * Passthrough translation function (WordPress __() replacement).
     */
    function __(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}

if (!function_exists('esc_html')) {
    function esc_html(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__(string $text, string $domain = 'default'): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    function esc_url(string $url): string
    {
        return $url;
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

// Load composer autoloader.
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

// Include the test utilities.
$testUtils = __DIR__ . '/Unit/TestUtils.php';
if (file_exists($testUtils)) {
    require_once $testUtils;
}
