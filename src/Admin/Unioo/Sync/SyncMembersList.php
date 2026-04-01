<?php

namespace LeoKnudsen\WpUniooSync\Admin\Unioo\Sync;

if ( ! defined('ABSPATH') ) {
  exit();
}

use LeoKnudsen\WpUniooSync\Admin\Unioo\UniooClient;
use LeoKnudsen\WpUniooSync\Admin\Unioo\Sync\MemberProcessor;

if ( ! class_exists('SyncMembersList') ) {
  class SyncMembersList {
    public function __construct(
      private UniooClient $unioo_client
    ) {}

    public function execute(): array {
      global $wpdb;
      $table_name = WP_UNIOO_SYNC_TABLE_NAME;

      if (
        ! get_option('wp_unioo_sync_username') ||
        ! get_option('wp_unioo_sync_password')
      ) {
        $wpdb->insert($table_name, [
          'sync_status' => 'failure',
          'sync_time' => current_time('mysql'),
          'sync_message' => "Unioo API sync failed: Missing API credentials. Please provide both username and password in the plugin settings.",
        ], [
          '%s',
          '%s',
          '%s',
        ]);

        return [
          'success' => false,
          'message' => __('Unioo API credentials are not set. Please provide both username and password in the plugin settings.', WP_UNIOO_SYNC_TEXTDOMAIN),
        ];
      }

      $response = $this->unioo_client->send_sync_request('sync_members');
      $processor = new MemberProcessor();

      if ( false !== $response['success'] ) {
        $results = $processor->processBatch($response["data"]["nodes"]);
      }

      /**
       * if the sync request failed due to unauthorized error, attempt to authenticate and retry the synchronization request.
       * This handles the case where the bearer token has expired or is invalid
       */
      if (
        false === $response['success'] &&
        isset($response["message"]) &&
        str_contains($response["message"], 'Unauthorized')
        && get_option('wp_unioo_sync_auto_generate_token_on_unauthorization')
      ) {
        $wpdb->insert($table_name, [
          'sync_status' => 'failure',
          'sync_time' => current_time('mysql'),
          'sync_message' => "Unioo API sync failed: " . $response['message'] . " Attempting to re-authenticate and retry.",
        ], [
          '%s',
          '%s',
          '%s',
        ]);

        $this->unioo_client->authenticate();
        $response = $this->unioo_client->sync_members();

        if ( false !== $response['success'] ) {
          $results = $processor->processBatch($response["data"]["nodes"]);
        }

         if (false === $response['success']) {
           $wpdb->insert($table_name, [
             'sync_status' => 'failure',
             'sync_time' => current_time('mysql'),
             'sync_message' => "Unioo API sync failed after re-authentication: " . $response['message'],
           ], [
             '%s',
             '%s',
             '%s',
           ]);
           return $response;
         }
      }

      $wpdb->insert(
        $table_name,
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

    public function fetchAllMembers(): \Generator {
      $cursor = null;

      do {
        $result = $this->fetchPage($cursor);
        if ($result === null) return;
        foreach ($result['data']['nodes'] as $member) {
          yield $member;
        }
        $cursor = $result['data']['pageInfo']['endCursor'] ?? null;
        $hasNextPage = $result['data']['pageInfo']['hasNextPage'] ?? false;
      } while ($hasNextPage && $cursor !== null);
    }

    public function fetchPage(?string $cursor): ?array {
      $variables = [
        'first' => 10,
        'after' => $cursor,
      ];

      $response = $this->unioo_client->send_sync_request('sync_members', $variables);

      if ( false === $response['success'] ) {
        return null;
      }

      return $response;
    }
  }
}