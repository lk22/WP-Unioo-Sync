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

      $processor = new MemberProcessor();
      $results = [
        "created" => 0,
        "updated" => 0,
        "failed" => 0,
        "skipped" => 0,
        "deleted" => 0,
      ];

      foreach ( $this->fetchAllMembers() as $member ) {
        $status = $processor->process($member);
        $results[$status]++;
      }

      $wpdb->insert(
        $table_name,
        [
          'sync_status' => 'success',
          'sync_time' => current_time('mysql'),
          'sync_message' => sprintf(
            __('Unioo API sync completed: %d created, %d updated, %d failed, %d skipped.', WP_UNIOO_SYNC_TEXTDOMAIN),
            $results['created'],
            $results['updated'],
            $results['failed'],
            $results['skipped']
          ),
        ],
        [
          '%s',
          '%s',
          '%s',
        ]
      );

      return [
        'success' => true,
        "message" => sprintf(
          __('Unioo API sync completed: %d created, %d updated, %d failed, %d skipped.', WP_UNIOO_SYNC_TEXTDOMAIN),
          $results['created'],
          $results['updated'],
          $results['failed'],
          $results['skipped']
        ),
      ];
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
      global $wpdb;
      $variables = [
        'first' => 10,
        'after' => $cursor,
      ];

      $response = $this->unioo_client->send_sync_request('sync_members', $variables);

      if (
        false === $response['success'] &&
        isset($response['message']) &&
        str_contains($response['message'], 'Unauthorized') &&
        get_option('wp_unioo_sync_auto_generate_token_on_unauthorization')
      ) {
        // Re-authenticate and retry with fresh token
        $this->unioo_client->authenticate();

        // Retry the request after re-authentication with the same variables
        $response = $this->unioo_client->send_sync_request('sync_members', $variables);
      }

      if ( false === $response['success'] ) {
        return null;
      }

      return $response;
    }
  }
}