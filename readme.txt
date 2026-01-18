=== Better Nginx Cache ===
Contributors: markllego
Donate link: https://llego.dev/
Tags: nginx, cache, fastcgi, performance, purge
Requires at least: 5.0
Tested up to: 6.9
Stable tag: 1.0.2
Requires PHP: 7.4
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Purge Nginx FastCGI cache automatically when content changes. Comment-safe - comments do NOT trigger cache purges.

== Description ==

Better Nginx Cache intelligently manages your Nginx FastCGI cache by automatically purging it when content actually changes, while **excluding comment-related actions** that would otherwise cause unnecessary cache invalidation.

= Key Features =

* **Selective Cache Purging** - Only purges cache on actual content changes (posts, pages, menus, themes, widgets)
* **Comment-Safe** - Comments do NOT trigger cache purges, preventing unnecessary cache invalidation on high-traffic sites
* **Cache Statistics** - View cached file count and total cache size in the admin dashboard
* **HTML Footer Comment** - Optional attribution displayed as HTML comment in page source
* **Admin Bar Integration** - Quick "Purge Cache" button in the WordPress admin bar
* **Manual Purge** - One-click cache purge from the settings page
* **Multisite Compatible** - Proper cleanup on plugin deletion

= Why This Plugin? =

Most Nginx cache plugins purge the entire cache whenever a comment is submitted, approved, or moderated. On high-traffic sites, this causes:

* Unnecessary server load regenerating cached pages
* Slower page loads for visitors
* Wasted resources

Better Nginx Cache solves this by only purging when actual content changes - not when users leave comments.

= What Triggers Cache Purge =

* Posts/pages created, updated, or deleted
* Theme changes
* Navigation menu updates
* Widget modifications
* Site title, tagline, or URL changes

= What Does NOT Trigger Cache Purge =

* Comment submissions
* Comment approvals
* Comment edits
* Comment deletions
* Trackbacks and pingbacks

= Requirements =

* Nginx with FastCGI cache configured
* Write access to the cache directory
* PHP 7.4 or higher
* WordPress 5.0 or higher

== Installation ==

1. Upload the `better-nginx-cache` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Tools → Nginx Cache to configure

= Configuration =

1. **Cache Path**: Enter the absolute path to your Nginx FastCGI cache directory (e.g., `/var/www/example.com/cache`)
2. **Auto Purge**: Enable/disable automatic cache purging on content changes
3. **Cache Footer**: Enable/disable the HTML comment footer

= Nginx Configuration Example =

Your Nginx configuration should include FastCGI caching:

`
fastcgi_cache_path /var/www/example.com/cache levels=1:2 keys_zone=example:100m inactive=24h;

# In your server block:
fastcgi_cache example;
fastcgi_cache_valid 200 301 24h;
fastcgi_cache_key "$scheme$request_method$host$request_uri";
`

== Frequently Asked Questions ==

= Why don't comments trigger cache purges? =

This is by design. On high-traffic sites, comments can be frequent. Purging the entire cache for every comment causes unnecessary server load. The cached page will naturally refresh based on your Nginx `inactive` setting.

= Can I add custom actions that trigger purges? =

Yes! Use the `bnc_purge_actions` filter:

`
add_filter( 'bnc_purge_actions', function( $actions ) {
    $actions[] = 'my_custom_action';
    return $actions;
} );
`

= Can I exclude certain post types from triggering purges? =

Yes! Use the `bnc_excluded_post_types` filter:

`
add_filter( 'bnc_excluded_post_types', function( $types ) {
    $types[] = 'revision';
    return $types;
} );
`

= Does this work with Redis Object Cache? =

Yes! Better Nginx Cache works alongside Redis Object Cache. They handle different caching layers:
* Nginx FastCGI Cache = Full HTML page caching
* Redis Object Cache = Database query caching

= Does this work with Cloudflare? =

Yes! Cloudflare sits in front of Nginx. This plugin manages your origin server's Nginx cache.

= My cache path shows an error =

Ensure:
1. The path exists on your server
2. The web server user (www-data, nginx, etc.) has write permissions
3. The path points to a valid Nginx cache directory with `levels=1:2` structure

== Screenshots ==

1. Settings page with cache statistics
2. Admin bar with quick purge option
3. Cache statistics sidebar

== Changelog ==

= 1.0.2 =
* **SECURITY**: Fixed potential XSS vulnerability in settings page author link (now uses wp_kses)
* **FIXED**: Corrected "Purge Triggers" list to accurately reflect implemented triggers
* **IMPROVED**: Removed unused clear_stats_cache() method from BNC_Cache_Stats class
* Code cleanup and WordPress Plugin Review Team compliance improvements

= 1.0.1 =
* **FIXED**: Cache now reliably persists - no more unexpected purges
* **IMPROVED**: Replaced multiple aggressive hooks (save_post, edit_post, delete_post) with transition_post_status for precise control
* **IMPROVED**: Autosaves, drafts, and revisions no longer trigger cache purges
* **IMPROVED**: Cache only purges when posts are actually published, updated, unpublished, or trashed
* **IMPROVED**: Removed option update hooks that caused excessive purging
* **IMPROVED**: Added did_action() check to prevent duplicate hook registrations
* **FIXED**: Changed purge flag from instance variable to static variable for reliable single-purge per request
* **NEW**: Added bnc_should_purge_on_status_change filter for fine-grained control
* **NEW**: Added bnc_should_purge filter for non-post purge triggers

= 1.0.0 =
* Initial release
* Selective cache purging (excludes comments)
* Cache statistics tracking
* HTML footer comment with attribution
* Admin bar integration
* Settings page under Tools menu
* Full multisite support
* Internationalization ready

== Upgrade Notice ==

= 1.0.2 =
Security fix: Addresses XSS vulnerability in settings page. Recommended update for all users.

= 1.0.1 =
Important reliability fix: Cache no longer gets deleted unexpectedly. Autosaves, drafts, and revisions no longer trigger purges.

= 1.0.0 =
Initial release of Better Nginx Cache.

== Developer Hooks ==

= Filters =

**bnc_purge_actions** - Modify the list of non-post WordPress actions that trigger cache purges.

**bnc_excluded_post_types** - Exclude specific post types from triggering cache purges.

**bnc_should_purge_on_status_change** - Fine-grained control over whether to purge for specific post status transitions. Receives $should_purge, $new_status, $old_status, $post.

**bnc_should_purge** - Control whether non-post triggers should purge the cache.

= Actions =

**bnc_cache_purged** - Fires after the cache has been purged. Receives the cache path as parameter.
