<?php
namespace LeoKnudsen\WpUniooSync;

if ( ! defined('ABSPATH') ) {
  exit();
}

if ( ! class_exists( 'WPUniooSyncAdminMenu' ) ) {
  class WPUniooSyncAdminMenu {
    public function __construct() {
      add_action('admin_menu', [$this, 'add_admin_menu']);
    }

    public function add_admin_menu() {
      add_menu_page(
        __('WP Unioo Sync', WP_UNIOO_SYNC_TEXTDOMAIN),
        __('WP Unioo Sync', WP_UNIOO_SYNC_TEXTDOMAIN),
        'manage_options',
        'wp-unioo-sync',
        [$this, 'admin_page'],
        'dashicons-update',
        6
      );
    }

    public function admin_page() {
      echo '<div class="wrap">';
      echo '<h1>' . __('WP Unioo Sync', WP_UNIOO_SYNC_TEXTDOMAIN) . '</h1>';
      echo '<p>' . __('Welcome to the WP Unioo Sync plugin. Use this page to manage your sync settings and view sync logs.', WP_UNIOO_SYNC_TEXTDOMAIN) . '</p>';
      echo '</div>';
    }
  }
}