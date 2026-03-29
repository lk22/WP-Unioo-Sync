<?php
namespace LeoKnudsen\WpUniooSync;

if ( ! defined('ABSPATH') ) {
  exit();
}

if (!class_exists('WPUniooSyncDeactivator')) {
  class WPUniooSyncDeactivator {
    public static function deactivate() {
      global $wpdb;
      $table_name = WP_UNIOO_SYNC_TABLE_NAME;
      $sql = "DROP TABLE IF EXISTS $table_name;";
      $wpdb->query($sql);
    }
  }
}