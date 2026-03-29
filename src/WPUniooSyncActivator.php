<?php
namespace LeoKnudsen\WpUniooSync;

if( ! defined('ABSPATH') ) {
  exit();
}

if (!class_exists('WPUniooSyncActivator')) {
  class WPUniooSyncActivator {
    public static function activate() {
      global $wpdb;
      $table_name = WP_UNIOO_SYNC_TABLE_NAME;
      $charset_collate = $wpdb->get_charset_collate();

      $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        sync_status varchar(50) NOT NULL,
        sync_time datetime NOT NULL,
        sync_message text,
        PRIMARY KEY  (id)
      ) $charset_collate;";

      require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
      dbDelta($sql);
    }
  }
}