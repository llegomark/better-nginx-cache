<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package Better_Nginx_Cache
 */

// Composer autoloader.
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Define WordPress constants needed by the plugin.
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}
if (!defined('BNC_VERSION')) {
    define('BNC_VERSION', '1.0.2');
}
if (!defined('BNC_PLUGIN_FILE')) {
    define('BNC_PLUGIN_FILE', dirname(__DIR__) . '/better-nginx-cache.php');
}
if (!defined('BNC_PLUGIN_DIR')) {
    define('BNC_PLUGIN_DIR', dirname(__DIR__) . '/');
}
if (!defined('BNC_PLUGIN_URL')) {
    define('BNC_PLUGIN_URL', 'https://example.com/wp-content/plugins/better-nginx-cache/');
}

// Note: Brain Monkey setUp/tearDown is handled in the TestCase class, not here.
