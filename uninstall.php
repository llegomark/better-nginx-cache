<?php
/**
 * Uninstall script for Better Nginx Cache.
 *
 * This file is executed when the plugin is deleted via the WordPress admin.
 * It cleans up all plugin data from the database.
 *
 * @package Better_Nginx_Cache
 * @since 1.0.0
 */

// Exit if accessed directly or not through WordPress uninstall.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options.
delete_option('bnc_cache_path');
delete_option('bnc_auto_purge');
delete_option('bnc_show_footer');

// Delete transients.
delete_transient('bnc_cache_stats');

// For multisite, delete options from all sites.
if (is_multisite()) {
    $sites = get_sites(array('fields' => 'ids'));

    if (is_array($sites)) {
        foreach ($sites as $site_id) {
            switch_to_blog((int) $site_id);

            delete_option('bnc_cache_path');
            delete_option('bnc_auto_purge');
            delete_option('bnc_show_footer');
            delete_transient('bnc_cache_stats');

            restore_current_blog();
        }
    }
}
