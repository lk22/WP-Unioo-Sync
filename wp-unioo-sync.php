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
global $wpdb;
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
use LeoKnudsen\WpUniooSync\WPUniooSyncRestAPI;

use LeoKnudsen\WpUniooSync\Admin\Unioo\UniooClient;
use LeoKnudsen\WpUniooSync\Admin\Unioo\Sync\SyncMembersList;

// register activation hook
register_activation_hook(__FILE__, [new WPUniooSyncActivator(), 'activate']);
// register deactivation hook
register_deactivation_hook(__FILE__, [new WPUniooSyncDeactivator(), 'deactivate']);

add_action('plugins_loaded', function() {
  new WPUniooSyncAdminMenu();
  new WPUniooSyncRestAPI();
});

add_action('admin_init', function() {
  global $wpdb;
  if ( get_option('wp_unioo_sync_custom_fields', false)) {
    $custom_fields = get_option('wp_unioo_sync_custom_fields', []);
    foreach ($custom_fields as $key => $field) {
      $lowercase_field = preg_replace('/[^a-z0-9_]/', '', strtolower($field));
      if ( empty($lowercase_field) ) {
        continue;
      }
      // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- column name is validated to [a-z0-9_] only
      $wpdb->query("ALTER TABLE " . $wpdb->prefix . "unioo_members ADD COLUMN IF NOT EXISTS `" . esc_sql($lowercase_field) . "` varchar(255) DEFAULT NULL AFTER postal_code");
    }
  }
});

add_action('wp_ajax_sync_members_list', function() {
  if ( ! current_user_can('manage_options') ) {
    wp_send_json_error(['message' => __('You do not have permission to perform this action.', WP_UNIOO_SYNC_TEXTDOMAIN)], 403);
    return;
  }

  check_ajax_referer('wp_unioo_sync_nonce', 'nonce');

  $client = new UniooClient(get_option('wp_unioo_sync_graphql_url'), get_option('wp_unioo_sync_bearer_token'));
  $sync = new SyncMembersList($client);
  $response = $sync->execute();
  wp_send_json_success(['message' => 'Unioo sync completed', 'response' => $response]);
});