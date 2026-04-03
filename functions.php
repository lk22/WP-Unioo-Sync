<?php

if ( ! function_exists('insert_custom_field_table_columns') )
{
  function insert_custom_field_table_columns($columns)
  {
    global $wpdb;
    foreach ($columns as $key => $field) {
      $lowercase_field = preg_replace('/[^a-z0-9_]/', '', strtolower($field));
      if ( empty($lowercase_field) ) {
        continue;
      }

      // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- column name is validated to [a-z0-9_] only
      $wpdb->query("ALTER TABLE " . $wpdb->prefix . "unioo_members ADD COLUMN IF NOT EXISTS `" . esc_sql($lowercase_field) . "` varchar(255) DEFAULT NULL AFTER postal_code");
    }
  }
}

if ( ! function_exists('fetch_sync_logs') ) {
  function fetch_sync_logs()
  {
    global $wpdb;
    $table_name = WP_UNIOO_SYNC_TABLE_NAME;
    return $wpdb->get_results("SELECT * FROM $table_name ORDER BY sync_time DESC");
  }
}

if ( ! function_exists('send_to_sync_log') ) {
  function send_to_sync_log(string $message, string $status = 'success')
  {
    global $wpdb;
    $table_name = WP_UNIOO_SYNC_TABLE_NAME;
    $wpdb->insert(
      $table_name,
      [
        'sync_status' => $status,
        'sync_time' => current_time('mysql'),
        'sync_message' => $message,
      ],
      [
        '%s',
        '%s',
        '%s',
      ]
    );
  }
}