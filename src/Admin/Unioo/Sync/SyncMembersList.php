<?php

namespace LeoKnudsen\WpUniooSync\Admin\Unioo\Sync;

if ( ! defined('ABSPATH') ) {
  exit();
}

use LeoKnudsen\WpUniooSync\Admin\Unioo\UniooClient;

if ( ! class_exists('SyncMembersList') ) {
  class SyncMembersList {
    public function __construct(
      private UniooClient $unioo_client
    ) {
      $this->unioo_client = $unioo_client;
    }

    public function execute(): array {
      global $wpdb;
      $response = $this->unioo_client->send_sync_request('sync_members');

      if ( ! $response["success"] === true && isset($response['message']) && str_contains($response['message'], 'Unauthorized') ) {
        $this->unioo_client->refresh_api_token();
        $response = $this->unioo_client->send_sync_request('sync_members');
      }

      $wpdb->insert(
        "wp_unioo_sync",
        [
          'sync_status' => 'success',
          'sync_time' => current_time('mysql'),
          'sync_message' => "Unioo API sync: " . $response['message'],
        ],
        [
          '%s',
          '%s',
          '%s',
        ]
      );
      return $response;
    }
  }
}