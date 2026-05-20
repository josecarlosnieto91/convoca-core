<?php
/**
 * PHPUnit bootstrap for Convoca Core.
 *
 * Loads WordPress test framework from devstack or CI environment.
 * Falls back to mock-based testing when WP is not available.
 *
 * @package Convoca\Core\Tests
 */

// First, try to find WordPress test library in CI / devstack environment.
$wp_tests_dir = getenv('WP_TESTS_DIR');

if (!$wp_tests_dir) {
    // Attempt common locations
    $candidates = [
        '/var/www/html/wp-content/plugins/../..', // Devstack
        getenv('WP_DEVELOP_DIR') . '/tests/phpunit',
        '/tmp/wordpress-tests-lib',
        '../../../../tests/phpunit', // Relative from plugin
    ];
    foreach ($candidates as $candidate) {
        if ($candidate && file_exists($candidate . '/includes/functions.php')) {
            $wp_tests_dir = $candidate;
            break;
        }
    }
}

if ($wp_tests_dir && file_exists($wp_tests_dir . '/includes/functions.php')) {
    // WordPress test environment is available - bootstrap properly.
    require_once $wp_tests_dir . '/includes/functions.php';

    /**
     * Manually load the plugin after WordPress is bootstrapped.
     */
    function _manually_load_plugin(): void {
        $plugin_file = dirname(__DIR__) . '/convoca-core.php';
        if (file_exists($plugin_file)) {
            require_once $plugin_file;
        }
    }
    tests_add_filter('muplugins_loaded', '_manually_load_plugin');

    // Start the WordPress test suite bootstrap.
    require_once $wp_tests_dir . '/includes/bootstrap.php';
} else {
    // Standalone test mode: define ABSPATH and declare function stubs.
    // This environment is sufficient for pure unit tests that don't touch the DB.
    if (!defined('ABSPATH')) {
        define('ABSPATH', dirname(__DIR__) . '/');
    }
    if (!defined('WP_DEBUG')) {
        define('WP_DEBUG', true);
    }
    if (!defined('BDV_COMMON_VERSION')) {
        define('BDV_COMMON_VERSION', '2.1.2');
    }
    if (!defined('BDV_COMMON_DIR')) {
        define('BDV_COMMON_DIR', dirname(__DIR__) . '/');
    }
    if (!defined('BDV_COMMON_DB_VERSION')) {
        define('BDV_COMMON_DB_VERSION', '1.1.0');
    }

    // Load plugin's own autoloader.
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
    }

    // Load the plugin's spl_autoload_register.
    $plugin_main = dirname(__DIR__) . '/convoca-core.php';
    if (file_exists($plugin_main)) {
        // Extract just the autoloader portion (skip WP-specific hooks)
        require_once dirname(__DIR__) . '/includes/class-utils.php';
        require_once dirname(__DIR__) . '/includes/class-logger.php';
        require_once dirname(__DIR__) . '/includes/class-installer.php';
        require_once dirname(__DIR__) . '/includes/class-capabilities.php';
        require_once dirname(__DIR__) . '/includes/class-webhook-manager.php';
    }
}

// Define test utilities and constants used by test classes.
if (!defined('CONVOCA_CORE_TEST_MODE')) {
    define('CONVOCA_CORE_TEST_MODE', true);
}

// Include TestUtils base if not already loaded.
require_once __DIR__ . '/Unit/TestUtils.php';
