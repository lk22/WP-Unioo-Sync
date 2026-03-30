<?php

namespace LeoKnudsen\WpUniooSync;
use WP_REST_Request;
use WP_REST_Server;
use WP_User;

use LeoKnudsen\WpUniooSync\Exceptions\UniooSyncUserNotCreatedException;

if ( ! defined('ABSPATH') ) {
  exit();
}

if ( ! class_exists('WPUniooSyncRestAPI') ) {
  class WPUniooSyncRestAPI {
    public function __construct() {
      add_action('rest_api_init', function() {
        register_rest_route('wp-unioo-sync/v1', '/members/import-csv', [
          'methods' => WP_REST_Server::CREATABLE,
          'callback' => [$this, 'sync_csv_members'],
          'permission_callback' => function() {
            return current_user_can('manage_options');
          }
        ]);
      });
    }

    public function sync_csv_members(WP_REST_Request $request) {
      global $wpdb;

      $members = $request->get_params(); // Ensure file parameters are available in the request

      // loop through the members and process the JSON data as needed, for example:
      foreach ($members as $member) {
        // make sure if there is a user in the system with the same email as the member, if not create a new user and assign a role, for example: subscriber

        // if there is a custom table for storing the members, we can check if the table exists and if not create it, and then insert the member data into the table
        // otherwise save the member data in the default WordPress user meta or a custom post type, depending on the use case
        if ( get_option('wp_unioo_sync_members_table', false) ) {
          $table_name = get_option('wp_unioo_sync_members_table');
        } else {
          if ( $this->createSyncedUser($member) ) {
            $memberData = [
              'name' => $member['name'],
              'email' => $member['email'],
              'telefon' => $member['telefon'] ?? '',
              'member_id' => $member['member_id'] ?? '',
            ];
          }
        }
      }
      // Process the CSV file and sync members with Unioo
      // This is a placeholder for your actual sync logic
      $sync_result = [
        'success' => true,
        'message' => __('Members synced successfully.', WP_UNIOO_SYNC_TEXTDOMAIN),
      ];

      // Log the sync status in the database
      $wpdb->insert(
        "wp_unioo_sync",
        [
          'sync_status' => $sync_result['success'] ? 'success' : 'failure',
          'sync_time' => current_time('mysql'),
          'sync_message' => $sync_result['message'],
        ],
        [
          '%s',
          '%s',
          '%s',
        ]
      );

      return rest_ensure_response($sync_result);
    }

    private function createSyncedUser($member): ?WP_User {
      $user = get_user_by('email', $member['email']);
      if ( ! $user ) {
        try {
          $user_id = wp_create_user($member['email'], wp_generate_password(), $member['email']);
          if (is_wp_error($user_id)) {
            throw new UniooSyncUserNotCreatedException('Failed to create user: ' . $user_id->get_error_message());
          }
          $user = get_user_by('id', $user_id);
          $user->set_role('subscriber');
          return $user;
        } catch (UniooSyncUserNotCreatedException $e) {
          error_log('Failed to create user from Unioo sync API: ' . $e->getMessage());
          return null;
        }
      }
      return $user;
    }
  }
}