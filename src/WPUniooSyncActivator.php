<?php
namespace LeoKnudsen\WpUniooSync;

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

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
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id)
      ) $charset_collate;";

      dbDelta($sql);

      $table_name = $wpdb->prefix . 'unioo_members';
      $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        member_id varchar(50) NOT NULL,
        user_id bigint(20) unsigned,
        name varchar(255) NOT NULL,
        email varchar(255) NOT NULL,
        phone varchar(50),
        birth_date date,
        address varchar(255),
        city varchar(100),
        postal_code varchar(20),
        identification varchar(50),
        membership varchar(50),
        unpaid_fee varchar(50),
        member_since date,
        left_at date,
        active_payment_method varchar(50),
        latest_note text,
        sync_time datetime NOT NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY member_id (member_id)
      ) $charset_collate;";

      dbDelta($sql);
    }
  }
}