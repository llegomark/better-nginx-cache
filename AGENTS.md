# AGENTS.md - Better Nginx Cache

Guidelines for AI coding agents working on this WordPress plugin.

## Project Overview

- **Type**: WordPress plugin (PHP 7.4+, WP 5.0+)
- **Purpose**: Manages Nginx FastCGI cache with automatic purging on content changes (excluding comments)

## Project Structure

```
better-nginx-cache/
├── better-nginx-cache.php        # Main plugin file (singleton pattern)
├── includes/
│   ├── class-cache-stats.php     # Cache statistics handling
│   └── class-settings-page.php   # Admin settings page
├── assets/css/admin.css          # Admin styles
├── languages/                    # Translation files
├── uninstall.php                 # Cleanup on deletion
└── composer.json                 # Dev dependencies
```

## Build/Lint/Test Commands

```bash
# Install dependencies
composer install

# Linting (if PHPCS installed)
./vendor/bin/phpcs --standard=WordPress better-nginx-cache.php includes/
./vendor/bin/phpcs --standard=WordPress includes/class-cache-stats.php  # Single file

# Testing (if PHPUnit installed)
./vendor/bin/phpunit                                    # All tests
./vendor/bin/phpunit tests/test-cache-stats.php         # Single file
./vendor/bin/phpunit --filter test_format_size          # Single method
```

## Code Style Guidelines

### Formatting
- **Indentation**: Tabs (not spaces)
- **PHP tags**: Always `<?php`, omit closing `?>`
- **Braces**: Opening brace on same line (K&R style)

### Naming Conventions

| Element | Convention | Example |
|---------|------------|---------|
| Classes | `Upper_Snake_Case` + prefix | `BNC_Cache_Stats` |
| Methods | `snake_case` | `get_cache_status()` |
| Variables | `$snake_case` | `$cache_path` |
| Constants | `UPPER_SNAKE_CASE` + prefix | `BNC_VERSION` |
| Hooks | `prefix_name` | `bnc_purge_actions` |
| Options | `prefix_name` | `bnc_cache_path` |

### Plugin Prefix
Use `bnc_` or `BNC_` for all: classes, functions, constants, options, hooks, transients.

### Required File Headers

```php
<?php
/**
 * Description of file purpose.
 *
 * @package Better_Nginx_Cache
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
```

### PHPDoc Standards

```php
/**
 * Short description.
 *
 * @since 1.0.0
 * @param string $path The cache path.
 * @return array|WP_Error Statistics or error.
 */
```

### Error Handling
- Return `WP_Error` for failures
- Check with `is_wp_error($result)`
- Use translation functions: `__()`, `esc_html__()`

### Security

```php
// Sanitize input
$path = sanitize_text_field($_POST['path']);
$id = absint($_GET['id']);

// Escape output
echo esc_html($text);
echo esc_url($url);

// Verify nonces and capabilities
if (!wp_verify_nonce($_GET['_wpnonce'], 'action-name')) {
    wp_die('Security check failed.');
}
if (!current_user_can('manage_options')) {
    wp_die('Unauthorized.');
}
```

### WordPress Filesystem API
Use `WP_Filesystem` instead of direct PHP file functions:

```php
global $wp_filesystem;
require_once ABSPATH . 'wp-admin/includes/file.php';
WP_Filesystem();
$wp_filesystem->exists($path);
$wp_filesystem->rmdir($path, true);
```

## Important Plugin Concepts

### Cache Purge Triggers
Purges on: post status transitions, theme changes, customizer saves, menu updates.
Does NOT purge on: comments, autosaves, revisions, drafts.

### Key Filters
- `bnc_purge_actions` - Add custom purge triggers
- `bnc_excluded_post_types` - Exclude post types
- `bnc_should_purge_on_status_change` - Fine-grained status control
- `bnc_should_purge` - Global purge control

### Key Actions
- `bnc_cache_purged` - Fires after cache purge

## Common Patterns

### Adding New Settings
1. Register in `register_settings()` method
2. Add UI in `class-settings-page.php`
3. Set default in activation hook
4. Clean up in `uninstall.php`

### Singleton Pattern (main class)

```php
final class Better_Nginx_Cache {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() { /* init */ }
}
```

## Multisite

- Plugin supports multisite
- `uninstall.php` iterates all sites
- Options are per-site (not network-wide)
