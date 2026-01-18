<?php
/**
 * Settings page class.
 *
 * @package Better_Nginx_Cache
 * @since 1.0.0
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class for rendering the admin settings page.
 *
 * @since 1.0.0
 */
class BNC_Settings_Page
{

    /**
     * Plugin instance.
     *
     * @var Better_Nginx_Cache
     */
    private $plugin;

    /**
     * Constructor.
     *
     * @since 1.0.0
     * @param Better_Nginx_Cache $plugin Plugin instance.
     */
    public function __construct($plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Render the settings page.
     *
     * @since 1.0.0
     */
    public function render()
    {
        $cache_path = get_option('bnc_cache_path', '');
        $auto_purge = get_option('bnc_auto_purge', 1);
        $show_footer = get_option('bnc_show_footer', 1);
        $validation = $this->plugin->validate_cache_path();
        $stats = new BNC_Cache_Stats();
        $cache_stats = $stats->get_stats();
        ?>
        <div class="wrap bnc-wrap">
            <h1>
                <?php esc_html_e('Better Nginx Cache', 'better-nginx-cache'); ?>
            </h1>

            <?php settings_errors(); ?>

            <div class="bnc-container">
                <div class="bnc-main">
                    <form method="post" action="options.php">
                        <?php settings_fields('better-nginx-cache'); ?>

                        <table class="form-table" role="presentation">
                            <tbody>
                                <tr>
                                    <th scope="row">
                                        <label for="bnc_cache_path">
                                            <?php esc_html_e('Cache Path', 'better-nginx-cache'); ?>
                                        </label>
                                    </th>
                                    <td>
                                        <input type="text" id="bnc_cache_path" name="bnc_cache_path" class="regular-text code"
                                            value="<?php echo esc_attr($cache_path); ?>"
                                            placeholder="/var/www/example.com/cache" />
                                        <p class="description">
                                            <?php
                                            echo wp_kses(
                                                __('The absolute path to your Nginx cache directory, specified in the <code>fastcgi_cache_path</code> or <code>proxy_cache_path</code> directive.', 'better-nginx-cache'),
                                                array('code' => array())
                                            );
                                            ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <?php esc_html_e('Auto Purge', 'better-nginx-cache'); ?>
                                    </th>
                                    <td>
                                        <label for="bnc_auto_purge">
                                            <input type="checkbox" id="bnc_auto_purge" name="bnc_auto_purge" value="1" <?php checked($auto_purge, 1); ?>
                                            />
                                            <?php esc_html_e('Automatically purge cache when content changes', 'better-nginx-cache'); ?>
                                        </label>
                                        <p class="description">
                                            <?php esc_html_e('Note: Comment submissions and moderation will NOT trigger cache purges.', 'better-nginx-cache'); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <?php esc_html_e('Cache Footer', 'better-nginx-cache'); ?>
                                    </th>
                                    <td>
                                        <label for="bnc_show_footer">
                                            <input type="checkbox" id="bnc_show_footer" name="bnc_show_footer" value="1" <?php checked($show_footer, 1); ?>
                                            />
                                            <?php esc_html_e('Show cache statistics in HTML comment footer', 'better-nginx-cache'); ?>
                                        </label>
                                        <p class="description">
                                            <?php esc_html_e('Adds an HTML comment at the end of each page with cache information (only visible in page source).', 'better-nginx-cache'); ?>
                                        </p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <p class="submit">
                            <?php submit_button(null, 'primary', 'submit', false); ?>
                            &nbsp;
                            <a href="<?php echo esc_url(wp_nonce_url(admin_url(add_query_arg('action', 'purge-cache', $this->plugin->get_admin_page())), 'bnc-purge-cache')); ?>"
                                class="button button-secondary bnc-purge-button<?php echo is_wp_error($validation) ? ' disabled' : ''; ?>"
                                <?php echo is_wp_error($validation) ? 'aria-disabled="true" onclick="return false;"' : ''; ?>
                                >
                                <?php esc_html_e('Purge Cache Now', 'better-nginx-cache'); ?>
                            </a>
                        </p>
                    </form>
                </div>

                <div class="bnc-sidebar">
                    <div class="bnc-card">
                        <h2>
                            <?php esc_html_e('Cache Statistics', 'better-nginx-cache'); ?>
                        </h2>

                        <?php if (is_wp_error($validation)): ?>
                            <p class="bnc-error">
                                <?php esc_html_e('Configure cache path to view statistics.', 'better-nginx-cache'); ?>
                            </p>
                        <?php else: ?>
                            <table class="bnc-stats-table">
                                <tr>
                                    <th>
                                        <?php esc_html_e('Cached Files', 'better-nginx-cache'); ?>
                                    </th>
                                    <td>
                                        <?php echo esc_html(number_format_i18n($cache_stats['file_count'])); ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>
                                        <?php esc_html_e('Cache Size', 'better-nginx-cache'); ?>
                                    </th>
                                    <td>
                                        <?php echo esc_html($stats->format_size($cache_stats['total_size'])); ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>
                                        <?php esc_html_e('Last Updated', 'better-nginx-cache'); ?>
                                    </th>
                                    <td>
                                        <?php echo esc_html($cache_stats['last_update']); ?>
                                    </td>
                                </tr>
                            </table>
                        <?php endif; ?>
                    </div>

                    <div class="bnc-card">
                        <h2>
                            <?php esc_html_e('Purge Triggers', 'better-nginx-cache'); ?>
                        </h2>
                        <p class="description">
                            <?php esc_html_e('Cache is automatically purged when:', 'better-nginx-cache'); ?>
                        </p>
                        <ul class="bnc-triggers-list">
                            <li>
                                <?php esc_html_e('Posts/Pages are created, updated, or deleted', 'better-nginx-cache'); ?>
                            </li>
                            <li>
                                <?php esc_html_e('Theme is changed or customizer saved', 'better-nginx-cache'); ?>
                            </li>
                            <li>
                                <?php esc_html_e('Navigation menus are updated', 'better-nginx-cache'); ?>
                            </li>
                        </ul>
                        <p class="description bnc-note">
                            <strong>
                                <?php esc_html_e('Note:', 'better-nginx-cache'); ?>
                            </strong>
                            <?php esc_html_e('Comments do NOT trigger cache purges.', 'better-nginx-cache'); ?>
                        </p>
                    </div>

                    <div class="bnc-card bnc-about">
                        <h2>
                            <?php esc_html_e('About', 'better-nginx-cache'); ?>
                        </h2>
                        <p>
                            <?php
                            printf(
                                /* translators: %s: Plugin version */
                                esc_html__('Better Nginx Cache v%s', 'better-nginx-cache'),
                                esc_html(BNC_VERSION)
                            );
                            ?>
                        </p>
                        <p>
                            <?php
                            echo wp_kses(
                                sprintf(
                                    /* translators: %s: Author website link */
                                    __('By %s', 'better-nginx-cache'),
                                    '<a href="https://llego.dev/" target="_blank" rel="noopener noreferrer">Mark Anthony Llego</a>'
                                ),
                                array(
                                    'a' => array(
                                        'href'   => array(),
                                        'target' => array(),
                                        'rel'    => array(),
                                    ),
                                )
                            );
                            ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
