<?php

namespace LeoKnudsen\WpUniooSync\Admin\Unioo;

if ( ! defined('ABSPATH') ) {
  exit();
}

if ( ! class_exists('UniooClient') ) {
  class UniooClient {
    public function __construct(
      private string $api_url,
      private string $bearer_token
    ) {
      $this->api_url = rtrim($api_url, '/');
      $this->bearer_token = $bearer_token;
    }

    /**
     * sending syncronization request
     * @param string $action default is 'sync_members', can be extended in the future for other sync actions
     * @return array
     */
    public function send_sync_request(string $action = 'sync_members'): array {
      return match($action) {
        'sync_members' => $this->sync_members(),
        'default' => [
          'success' => false,
          'message' => __('Invalid sync action specified.', WP_UNIOO_SYNC_TEXTDOMAIN),
        ],
      };
    }
  }
}
