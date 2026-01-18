<?php
/**
 * Cache statistics class.
 *
 * @package Better_Nginx_Cache
 * @since 1.0.0
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class for tracking and displaying cache statistics.
 *
 * @since 1.0.0
 */
class BNC_Cache_Stats
{

    /**
     * Cache path.
     *
     * @var string
     */
    private $cache_path;

    /**
     * Constructor.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->cache_path = get_option('bnc_cache_path', '');
    }

    /**
     * Get cache statistics.
     *
     * @since 1.0.0
     * @return array Cache statistics.
     */
    public function get_stats()
    {
        // Always calculate fresh stats for accuracy.
        return $this->calculate_stats();
    }

    /**
     * Calculate cache statistics.
     *
     * @since 1.0.0
     * @return array Calculated statistics.
     */
    private function calculate_stats()
    {
        $stats = array(
            'file_count' => 0,
            'total_size' => 0,
            'cache_path' => $this->cache_path,
            'last_update' => current_time('mysql'),
        );

        if (empty($this->cache_path) || !is_dir($this->cache_path)) {
            return $stats;
        }

        $this->scan_directory($this->cache_path, $stats);

        return $stats;
    }

    /**
     * Recursively scan directory for cache files.
     *
     * @since 1.0.0
     * @param string $dir   Directory to scan.
     * @param array  $stats Statistics array (passed by reference).
     */
    private function scan_directory($dir, &$stats)
    {
        $files = @scandir($dir); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

        if (false === $files) {
            return;
        }

        foreach ($files as $file) {
            if ('.' === $file || '..' === $file) {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $file;

            if (is_dir($path)) {
                $this->scan_directory($path, $stats);
            } elseif (is_file($path)) {
                $stats['file_count']++;
                $stats['total_size'] += filesize($path);
            }
        }
    }

    /**
     * Format bytes to human-readable size.
     *
     * @since 1.0.0
     * @param int $bytes Number of bytes.
     * @return string Formatted size.
     */
    public function format_size($bytes)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $bytes = max($bytes, 0);
        $pow = (int) floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Get cache status from server headers.
     *
     * @since 1.0.0
     * @return string Cache status (HIT, MISS, BYPASS, or UNKNOWN).
     */
    public function get_cache_status()
    {
        $headers = headers_list();

        foreach ($headers as $header) {
            // Check for Fastcgi-Cache header.
            if (stripos($header, 'Fastcgi-Cache:') === 0) {
                $parts = explode(':', $header, 2);
                if (isset($parts[1])) {
                    return strtoupper(trim($parts[1]));
                }
            }
        }

        return 'UNKNOWN';
    }

    /**
     * Get HTML comment footer for cache statistics.
     *
     * @since 1.0.0
     * @return string HTML comment with cache statistics.
     */
    public function get_footer_comment()
    {
        $comment = "\n<!--\n";
        $comment .= "Performance optimized by Better Nginx Cache\n";
        $comment .= "By Mark Anthony Llego - https://llego.dev/\n";
        $comment .= "-->\n";

        return $comment;
    }

    /**
     * Clear cached statistics.
     *
     * @since 1.0.0
     */
    public function clear_stats_cache()
    {
        delete_transient('bnc_cache_stats');
    }
}
