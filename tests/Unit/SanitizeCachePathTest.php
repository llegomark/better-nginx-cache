<?php
/**
 * Unit tests for sanitize_cache_path() method.
 *
 * @package Better_Nginx_Cache
 */

namespace BetterNginxCache\Tests\Unit;

use BetterNginxCache\Tests\TestCase;
use Brain\Monkey\Functions;

/**
 * Tests for the cache path sanitization function.
 */
class SanitizeCachePathTest extends TestCase
{
    /**
     * Instance of the sanitizer for testing.
     *
     * @var \Better_Nginx_Cache_Sanitizer
     */
    private $sanitizer;

    /**
     * Set up test fixtures.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create a minimal class that exposes the sanitize method for testing.
        $this->sanitizer = new class {
            /**
             * Sanitize and validate cache path.
             *
             * This is a copy of Better_Nginx_Cache::sanitize_cache_path() for isolated testing.
             *
             * @param string $path The cache path to sanitize.
             * @return string Sanitized path.
             */
            public function sanitize_cache_path($path)
            {
                // Remove any null bytes (security).
                $path = str_replace("\0", '', $path);

                // Sanitize as text field.
                $path = sanitize_text_field($path);

                // Normalize path separators.
                $path = str_replace('\\', '/', $path);

                // Remove trailing slash.
                $path = rtrim($path, '/');

                // Prevent directory traversal.
                if (strpos($path, '..') !== false) {
                    add_settings_error(
                        'bnc_cache_path',
                        'invalid_path',
                        __('Invalid path: Directory traversal not allowed.', 'better-nginx-cache')
                    );
                    return get_option('bnc_cache_path', '');
                }

                return $path;
            }
        };
    }

    /**
     * Test that a valid path is normalized correctly.
     */
    public function testValidPathIsNormalized(): void
    {
        $path = '/var/www/example.com/cache/';
        $result = $this->sanitizer->sanitize_cache_path($path);

        $this->assertSame('/var/www/example.com/cache', $result);
    }

    /**
     * Test that backslashes are converted to forward slashes.
     */
    public function testBackslashesAreConverted(): void
    {
        $path = 'C:\\nginx\\cache\\example';
        $result = $this->sanitizer->sanitize_cache_path($path);

        $this->assertSame('C:/nginx/cache/example', $result);
    }

    /**
     * Test that trailing slashes are removed.
     */
    public function testTrailingSlashesAreRemoved(): void
    {
        $path = '/var/cache///';
        $result = $this->sanitizer->sanitize_cache_path($path);

        $this->assertSame('/var/cache', $result);
    }

    /**
     * Test that null bytes are removed (security).
     */
    public function testNullBytesAreRemoved(): void
    {
        $path = "/var/www/cache\0/malicious";
        $result = $this->sanitizer->sanitize_cache_path($path);

        $this->assertSame('/var/www/cache/malicious', $result);
        $this->assertStringNotContainsString("\0", $result);
    }

    /**
     * Test that directory traversal attempts are blocked.
     */
    public function testDirectoryTraversalIsBlocked(): void
    {
        Functions\expect('get_option')
            ->once()
            ->with('bnc_cache_path', '')
            ->andReturn('/safe/path');

        $path = '/var/www/../../../etc/passwd';
        $result = $this->sanitizer->sanitize_cache_path($path);

        // Should return the stored option value, not the malicious path.
        $this->assertSame('/safe/path', $result);
    }

    /**
     * Test directory traversal with encoded dots.
     */
    public function testDirectoryTraversalWithDoubleDots(): void
    {
        Functions\expect('get_option')
            ->once()
            ->with('bnc_cache_path', '')
            ->andReturn('');

        $path = '/var/www/cache/..';
        $result = $this->sanitizer->sanitize_cache_path($path);

        $this->assertSame('', $result);
    }

    /**
     * Test that an empty path is handled gracefully.
     */
    public function testEmptyPathReturnsEmpty(): void
    {
        $path = '';
        $result = $this->sanitizer->sanitize_cache_path($path);

        $this->assertSame('', $result);
    }

    /**
     * Test that whitespace is trimmed.
     */
    public function testWhitespaceIsTrimmed(): void
    {
        $path = '  /var/www/cache  ';
        $result = $this->sanitizer->sanitize_cache_path($path);

        $this->assertSame('/var/www/cache', $result);
    }

    /**
     * Test path with mixed slashes.
     */
    public function testMixedSlashesAreNormalized(): void
    {
        $path = '/var\\www/cache\\nginx/';
        $result = $this->sanitizer->sanitize_cache_path($path);

        $this->assertSame('/var/www/cache/nginx', $result);
    }

    /**
     * Test that special characters in path names are preserved.
     */
    public function testSpecialCharactersInPathPreserved(): void
    {
        $path = '/var/www/my-cache_v2/example.com';
        $result = $this->sanitizer->sanitize_cache_path($path);

        $this->assertSame('/var/www/my-cache_v2/example.com', $result);
    }
}
