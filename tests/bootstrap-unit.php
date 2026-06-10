<?php
/**
 * Unit test bootstrap for Convoca Core (standalone, no WordPress).
 * Loads only the composer autoloader, avoiding WP function dependencies.
 */
// Define constants needed by Convoca classes.
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
