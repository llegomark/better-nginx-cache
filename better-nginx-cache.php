<?php
/**
 * Plugin Name: Better Nginx Cache
 * Plugin URI: https://llego.dev/
 * Description: Purge the Nginx FastCGI cache automatically when content changes (excludes comments) with cache statistics display.
 * Version: 1.0.0
 * Author: Mark Anthony Llego
 * Author URI: https://llego.dev/
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: better-nginx-cache
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 *
 * @package Better_Nginx_Cache
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
	exit;
}

// Plugin constants.
define('BNC_VERSION', '1.0.0');
define('BNC_PLUGIN_FILE', __FILE__);
define('BNC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BNC_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main plugin class.
 *
 * @since 1.0.0
 */
final class Better_Nginx_Cache
{

	/**
	 * Single instance of the class.
	 *
	 * @var Better_Nginx_Cache|null
	 */
	private static $instance = null;

	/**
	 * Admin page screen ID.
	 *
	 * @var string
	 */
	private $screen = 'tools_page_better-nginx-cache';

	/**
	 * Required capability for settings.
	 *
	 * @var string
	 */
	private $capability = 'manage_options';

	/**
	 * Admin page URL path.
	 *
	 * @var string
	 */
	private $admin_page = 'tools.php?page=better-nginx-cache';

	/**
	 * Flag to prevent multiple purges per request.
	 *
	 * @var bool
	 */
	private $purged = false;

	/**
	 * Get single instance.
	 *
	 * @since 1.0.0
	 * @return Better_Nginx_Cache
	 */
	public static function get_instance()
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct()
	{
		$this->load_dependencies();
		$this->init_hooks();
	}

	/**
	 * Load required files.
	 *
	 * @since 1.0.0
	 */
	private function load_dependencies()
	{
		require_once BNC_PLUGIN_DIR . 'includes/class-cache-stats.php';
		require_once BNC_PLUGIN_DIR . 'includes/class-settings-page.php';
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @since 1.0.0
	 */
	private function init_hooks()
	{
		// Load text domain.
		add_action('init', array($this, 'load_textdomain'));

		// Sanitize options on retrieval.
		add_filter('option_bnc_cache_path', 'sanitize_text_field');
		add_filter('option_bnc_auto_purge', 'absint');

		// Plugin action links.
		add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_plugin_action_links'));

		// Register auto-purge actions if enabled.
		if (get_option('bnc_auto_purge', 1)) {
			add_action('init', array($this, 'register_purge_actions'), 20);
		}

		// Admin hooks.
		add_action('admin_init', array($this, 'register_settings'));
		add_action('admin_menu', array($this, 'add_admin_menu_page'));
		add_action('admin_bar_menu', array($this, 'add_admin_bar_node'), 100);
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
		add_action('load-' . $this->screen, array($this, 'handle_admin_actions'));
		add_action('load-' . $this->screen, array($this, 'add_settings_notices'));

		// Frontend cache stats footer.
		if (!is_admin() && get_option('bnc_show_footer', 1)) {
			add_action('shutdown', array($this, 'output_cache_stats_footer'), 0);
		}
	}

	/**
	 * Load plugin text domain.
	 *
	 * @since 1.0.0
	 */
	public function load_textdomain()
	{
		load_plugin_textdomain(
			'better-nginx-cache',
			false,
			dirname(plugin_basename(__FILE__)) . '/languages'
		);
	}

	/**
	 * Register purge actions for content changes only (excludes comments).
	 *
	 * @since 1.0.0
	 */
	public function register_purge_actions()
	{
		/**
		 * Filter the actions that trigger cache purge.
		 *
		 * Note: Comment-related actions are intentionally excluded to prevent
		 * unnecessary cache invalidation when comments are submitted/moderated.
		 *
		 * @since 1.0.0
		 * @param array $actions The actions that trigger cache purge.
		 */
		$actions = (array) apply_filters(
			'bnc_purge_actions',
			array(
				// Post changes.
				'save_post',
				'edit_post',
				'delete_post',
				'wp_trash_post',
				// Theme and appearance.
				'switch_theme',
				'customize_save_after',
				// Navigation.
				'wp_update_nav_menu',
				// Core options that affect frontend.
				'update_option_blogname',
				'update_option_blogdescription',
				'update_option_siteurl',
				'update_option_home',
				// Widgets and sidebars.
				'update_option_sidebars_widgets',
			)
		);

		foreach ($actions as $action) {
			add_action($action, array($this, 'purge_cache_once'));
		}
	}

	/**
	 * Register plugin settings.
	 *
	 * @since 1.0.0
	 */
	public function register_settings()
	{
		register_setting(
			'better-nginx-cache',
			'bnc_cache_path',
			array(
				'type' => 'string',
				'sanitize_callback' => array($this, 'sanitize_cache_path'),
				'default' => '',
			)
		);

		register_setting(
			'better-nginx-cache',
			'bnc_auto_purge',
			array(
				'type' => 'integer',
				'sanitize_callback' => 'absint',
				'default' => 1,
			)
		);

		register_setting(
			'better-nginx-cache',
			'bnc_show_footer',
			array(
				'type' => 'integer',
				'sanitize_callback' => 'absint',
				'default' => 1,
			)
		);
	}

	/**
	 * Sanitize and validate cache path.
	 *
	 * @since 1.0.0
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

	/**
	 * Add settings notices on the settings page.
	 *
	 * @since 1.0.0
	 */
	public function add_settings_notices()
	{
		$validation = $this->validate_cache_path();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if (isset($_GET['message']) && !isset($_GET['settings-updated'])) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$message = sanitize_key($_GET['message']);

			if ('cache-purged' === $message) {
				add_settings_error(
					'bnc_cache_path',
					'cache_purged',
					__('Cache purged successfully.', 'better-nginx-cache'),
					'updated'
				);
			}

			if ('purge-failed' === $message) {
				$error_msg = is_wp_error($validation) ? $validation->get_error_message() : __('Unknown error.', 'better-nginx-cache');
				add_settings_error(
					'bnc_cache_path',
					'purge_failed',
					sprintf(
						/* translators: %s: Error message */
						__('Cache could not be purged. %s', 'better-nginx-cache'),
						esc_html($error_msg)
					)
				);
			}
		} elseif (is_wp_error($validation) && 'fs' === $validation->get_error_code()) {
			add_settings_error(
				'bnc_cache_path',
				'path_error',
				esc_html($validation->get_error_message('fs'))
			);
		}
	}

	/**
	 * Handle admin page actions.
	 *
	 * @since 1.0.0
	 */
	public function handle_admin_actions()
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if (!isset($_GET['action']) || 'purge-cache' !== $_GET['action']) {
			return;
		}

		// Verify nonce.
		if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_key($_GET['_wpnonce']), 'bnc-purge-cache')) {
			wp_die(esc_html__('Security check failed.', 'better-nginx-cache'));
		}

		// Verify capability.
		if (!current_user_can($this->capability)) {
			wp_die(esc_html__('You do not have permission to perform this action.', 'better-nginx-cache'));
		}

		$result = $this->purge_cache();
		$message = is_wp_error($result) ? 'purge-failed' : 'cache-purged';

		wp_safe_redirect(admin_url(add_query_arg('message', $message, $this->admin_page)));
		exit;
	}

	/**
	 * Add admin bar node.
	 *
	 * @since 1.0.0
	 * @param WP_Admin_Bar $wp_admin_bar The admin bar object.
	 */
	public function add_admin_bar_node($wp_admin_bar)
	{
		if (!current_user_can($this->capability)) {
			return;
		}

		$wp_admin_bar->add_node(
			array(
				'id' => 'better-nginx-cache',
				'title' => __('Nginx Cache', 'better-nginx-cache'),
				'href' => admin_url($this->admin_page),
			)
		);

		$wp_admin_bar->add_node(
			array(
				'parent' => 'better-nginx-cache',
				'id' => 'bnc-purge-cache',
				'title' => __('Purge Cache', 'better-nginx-cache'),
				'href' => wp_nonce_url(
					admin_url(add_query_arg('action', 'purge-cache', $this->admin_page)),
					'bnc-purge-cache'
				),
			)
		);

		$wp_admin_bar->add_node(
			array(
				'parent' => 'better-nginx-cache',
				'id' => 'bnc-settings',
				'title' => __('Settings', 'better-nginx-cache'),
				'href' => admin_url($this->admin_page),
			)
		);
	}

	/**
	 * Add admin menu page.
	 *
	 * @since 1.0.0
	 */
	public function add_admin_menu_page()
	{
		add_management_page(
			__('Better Nginx Cache', 'better-nginx-cache'),
			__('Nginx Cache', 'better-nginx-cache'),
			$this->capability,
			'better-nginx-cache',
			array($this, 'render_settings_page')
		);
	}

	/**
	 * Render settings page.
	 *
	 * @since 1.0.0
	 */
	public function render_settings_page()
	{
		$settings_page = new BNC_Settings_Page($this);
		$settings_page->render();
	}

	/**
	 * Add plugin action links.
	 *
	 * @since 1.0.0
	 * @param array $links Existing plugin action links.
	 * @return array Modified plugin action links.
	 */
	public function add_plugin_action_links($links)
	{
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url(admin_url($this->admin_page)),
			esc_html__('Settings', 'better-nginx-cache')
		);

		array_unshift($links, $settings_link);
		return $links;
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @since 1.0.0
	 * @param string $hook_suffix The current admin page.
	 */
	public function enqueue_admin_assets($hook_suffix)
	{
		if ($hook_suffix !== $this->screen) {
			return;
		}

		wp_enqueue_style(
			'better-nginx-cache-admin',
			BNC_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			BNC_VERSION
		);
	}

	/**
	 * Validate cache path.
	 *
	 * @since 1.0.0
	 * @return true|WP_Error True if valid, WP_Error otherwise.
	 */
	public function validate_cache_path()
	{
		global $wp_filesystem;

		$path = get_option('bnc_cache_path');

		if (empty($path)) {
			return new WP_Error('empty', __('Cache path is not configured.', 'better-nginx-cache'));
		}

		if (!$this->init_filesystem()) {
			return new WP_Error('fs', __('Could not initialize WordPress filesystem.', 'better-nginx-cache'));
		}

		if (!$wp_filesystem->exists($path)) {
			return new WP_Error('fs', __('Cache path does not exist.', 'better-nginx-cache'));
		}

		if (!$wp_filesystem->is_dir($path)) {
			return new WP_Error('fs', __('Cache path is not a directory.', 'better-nginx-cache'));
		}

		if (!$wp_filesystem->is_writable($path)) {
			return new WP_Error('fs', __('Cache path is not writable.', 'better-nginx-cache'));
		}

		// Validate that it looks like a Nginx cache directory.
		$dirlist = $wp_filesystem->dirlist($path, true, true);

		if (is_array($dirlist) && !empty($dirlist) && !$this->validate_nginx_cache_dir($dirlist)) {
			return new WP_Error('fs', __('Path does not appear to be a valid Nginx cache directory.', 'better-nginx-cache'));
		}

		return true;
	}

	/**
	 * Validate directory structure as Nginx cache.
	 *
	 * @since 1.0.0
	 * @param array $dirlist Directory listing from WP_Filesystem.
	 * @return bool True if valid Nginx cache structure.
	 */
	private function validate_nginx_cache_dir($dirlist)
	{
		foreach ($dirlist as $item) {
			// Allow files with extensions (like .tmp files).
			if ('f' === $item['type'] && strpos($item['name'], '.') !== false) {
				continue;
			}

			// Nginx cache files are MD5 hashes (32 hex characters).
			if ('f' === $item['type']) {
				$name = $item['name'];
				if (strlen($name) !== 32 || !ctype_xdigit($name)) {
					return false;
				}
			}

			// Recursively validate subdirectories.
			if ('d' === $item['type'] && isset($item['files']) && is_array($item['files']) && !$this->validate_nginx_cache_dir($item['files'])) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Purge cache (only once per request).
	 *
	 * @since 1.0.0
	 */
	public function purge_cache_once()
	{
		if ($this->purged) {
			return;
		}

		$this->purge_cache();
		$this->purged = true;
	}

	/**
	 * Purge the cache.
	 *
	 * @since 1.0.0
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public function purge_cache()
	{
		global $wp_filesystem;

		// Check if we should purge based on post type.
		if (!$this->should_purge()) {
			return false;
		}

		$path = get_option('bnc_cache_path');

		$validation = $this->validate_cache_path();
		if (is_wp_error($validation)) {
			return $validation;
		}

		// Delete cache directory contents recursively.
		$wp_filesystem->rmdir($path, true);

		// Recreate empty cache directory.
		$wp_filesystem->mkdir($path);

		// Clear cached statistics so admin page shows accurate data.
		delete_transient('bnc_cache_stats');

		/**
		 * Fires after the cache has been purged.
		 *
		 * @since 1.0.0
		 * @param string $path The cache path that was purged.
		 */
		do_action('bnc_cache_purged', $path);

		return true;
	}

	/**
	 * Check if cache should be purged based on post type.
	 *
	 * @since 1.0.0
	 * @return bool True if cache should be purged.
	 */
	private function should_purge()
	{
		$post_type = get_post_type();

		if (!$post_type) {
			return true;
		}

		/**
		 * Filter post types excluded from cache purging.
		 *
		 * @since 1.0.0
		 * @param array $excluded_types Array of post type slugs to exclude.
		 */
		$excluded_types = (array) apply_filters('bnc_excluded_post_types', array());

		return !in_array($post_type, $excluded_types, true);
	}

	/**
	 * Initialize WordPress filesystem.
	 *
	 * @since 1.0.0
	 * @return bool True on success.
	 */
	private function init_filesystem()
	{
		global $wp_filesystem;

		if ($wp_filesystem instanceof WP_Filesystem_Base) {
			return true;
		}

		$path = get_option('bnc_cache_path');

		// Load WordPress file API.
		if (!function_exists('request_filesystem_credentials')) {
			require_once ABSPATH . 'wp-admin/includes/file.php'; // phpcs:ignore -- ABSPATH is defined by WordPress core.
		}

		// Suppress output from credential requests.
		ob_start();
		$credentials = request_filesystem_credentials('', '', false, $path, null, true);
		ob_end_clean();

		if (false === $credentials) {
			return false;
		}

		if (!WP_Filesystem($credentials, $path, true)) {
			return false;
		}

		return true;
	}

	/**
	 * Output cache statistics as HTML comment in footer.
	 *
	 * @since 1.0.0
	 */
	public function output_cache_stats_footer()
	{
		// Only output on frontend HTML pages.
		if (is_admin() || wp_doing_ajax() || wp_doing_cron() || defined('REST_REQUEST') || defined('XMLRPC_REQUEST')) {
			return;
		}

		// Check if we're outputting HTML.
		$headers = headers_list();
		$is_html = true;

		foreach ($headers as $header) {
			if (stripos($header, 'content-type:') === 0 && stripos($header, 'text/html') === false) {
				$is_html = false;
				break;
			}
		}

		if (!$is_html) {
			return;
		}

		$stats = new BNC_Cache_Stats();
		echo $stats->get_footer_comment(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Get admin page URL.
	 *
	 * @since 1.0.0
	 * @return string Admin page URL.
	 */
	public function get_admin_page()
	{
		return $this->admin_page;
	}

	/**
	 * Get capability.
	 *
	 * @since 1.0.0
	 * @return string Required capability.
	 */
	public function get_capability()
	{
		return $this->capability;
	}
}

/**
 * Initialize the plugin.
 *
 * @since 1.0.0
 * @return Better_Nginx_Cache Plugin instance.
 */
function better_nginx_cache()
{
	return Better_Nginx_Cache::get_instance();
}

// Initialize plugin.
add_action('plugins_loaded', 'better_nginx_cache');

/**
 * Activation hook.
 *
 * @since 1.0.0
 */
function bnc_activate()
{
	// Set default options.
	add_option('bnc_auto_purge', 1);
	add_option('bnc_show_footer', 1);
	add_option('bnc_cache_path', '');
}
register_activation_hook(__FILE__, 'bnc_activate');

/**
 * Deactivation hook.
 *
 * @since 1.0.0
 */
function bnc_deactivate()
{
	// Clean up transients if any.
	delete_transient('bnc_cache_stats');
}
register_deactivation_hook(__FILE__, 'bnc_deactivate');
