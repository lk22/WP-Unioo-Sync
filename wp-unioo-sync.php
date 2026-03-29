<?php
/**
 * Plugin Name: WP Unioo Sync
 * Description: Sync data between WP and Unioo
 * Author: Leo Knudsen
 * Version: 1.0.0
 * Text Domain: wp-unioo-sync
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 5.8
 * Requires PHP: 7.0
 */
if (! defined('ABSPATH')) {
  exit();
}

define('WP_UNIOO_SYNC_VERSION', '1.0.0');
define('WP_UNIOO_SYNC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_UNIOO_SYNC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WP_UNIOO_SYNC_PLUGIN_FILE', __FILE__);
define('WP_UNIOO_SYNC_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('WP_UNIOO_SYNC_TEXTDOMAIN', 'wp-unioo-sync');
define('WP_UNIOO_SYNC_TABLE_NAME', $wpdb->prefix . 'wp_unioo_sync');

require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

use LeoKnudsen\WpUniooSync\WPUniooSyncActivator;
use LeoKnudsen\WpUniooSync\WPUniooSyncDeactivator;
use LeoKnudsen\WpUniooSync\WPUniooSyncAdminMenu;

// register activation hook
register_activation_hook(__FILE__, [new WPUniooSyncActivator(), 'activate']);
// register deactivation hook
register_deactivation_hook(__FILE__, [new WPUniooSyncDeactivator(), 'deactivate']);

add_action('plugins_loaded', function() {
  new WPUniooSyncAdminMenu();
});