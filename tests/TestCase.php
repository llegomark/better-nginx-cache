<?php
/**
 * Base test case for Better Nginx Cache tests.
 *
 * @package Better_Nginx_Cache
 */

namespace BetterNginxCache\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

/**
 * Abstract base class for all tests.
 *
 * Sets up Brain Monkey for WordPress function mocking.
 */
abstract class TestCase extends PHPUnitTestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * Set up Brain Monkey before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        // Define common WordPress function stubs.
        $this->setupWordPressStubs();
    }

    /**
     * Set up WordPress function stubs.
     */
    protected function setupWordPressStubs(): void
    {
        Monkey\Functions\stubs([
            '__' => static function ($text, $domain = 'default') {
                return $text;
            },
            'esc_html__' => static function ($text, $domain = 'default') {
                return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
            },
            'esc_html' => static function ($text) {
                return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
            },
            'sanitize_text_field' => static function ($str) {
                if (!is_string($str)) {
                    return '';
                }
                $str = preg_replace('/[\r\n\t ]+/', ' ', $str);
                return trim($str);
            },
            'absint' => static function ($maybeint) {
                return abs((int) $maybeint);
            },
            'add_settings_error' => static function () {
                // No-op in tests.
            },
        ]);
    }

    /**
     * Tear down Brain Monkey after each test.
     */
    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }
}
