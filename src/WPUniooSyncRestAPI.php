<?php

namespace LeoKnudsen\WpUniooSync;
use WP_REST_Request;
use WP_REST_Server;
use WP_User;

use LeoKnudsen\WpUniooSync\Exceptions\UniooSyncUserNotCreatedException;
use LeoKnudsen\WpUniooSync\Exceptions\UniooSyncLogNotCreatedException;

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

      $createdUsers = 0;
      $deletedUsers = 0;
      $updatedUsers = 0;

      // loop through the members and process the JSON data as needed
      foreach ($members["members"] as $member) {
        // if there is a custom table for storing the members, we can check if the table exists and if not create it, and then insert the member data into the table
        // otherwise save the member data in the default WordPress user meta or a custom post type, depending on the use case
        if ( get_option('wp_unioo_sync_members_table', false) ) {
          $table_name = get_option('wp_unioo_sync_members_table');
        } else {
          // make sure if there is a user in the system with the same email as the member, if not create a new user and assign a role, for example: subscriber
          // then save the member data in the user meta, for example: email, gamertag, and other relevant information from the data

          if ( $user = $this->createSynceUnioodUser($member) ) {
            $memberData = [
              'Navn' => $member['Navn'],
              'email' => $member['Email'],
              'Telefon' => $member['Telefon'],
              'Fødselsdato' => $member['Fødselsdato'],
              'Adresse' => $member['Adresse'],
              'By' => $member['By'],
              'Postnummer' => $member['Postnummer'],
              'Identifikation' => $member['Identifikation'],
              'Kontingent' => $member['Kontingenter (Navne)'],
              'Ikke betalt kontingent' => $member['Ubetalte regninger'],
              'Indmeldelsesdato' => $member['Indmeldelsesdato'],
              'Udmeldelsesdato' => $member['Udmeldelsesdato'],
              'Aktiv betalingsmetode' => $member['Aktiv betalingsmetode'],
              'Nyeste note' => $member['Nyeste note'],
            ];

            $custom_fields = get_option('wp_unioo_sync_custom_fields', []);

            if ( is_array(json_encode($custom_fields)) && count($custom_fields) > 0 ) {
              $custom_fields = get_option('wp_unioo_sync_custom_fields');
              foreach ( $custom_fields as $field ) {
                if ( isset($member[$field]) ) {
                  $memberData[$field] = $member[$field];
                }
              }
            }

            foreach ( $memberData as $key => $value ) {
              update_user_meta($user->ID, $key, $value);
            }
            $createdUsers++;

          } else {
            continue; // Skip to the next member if user creation failed
          }
        }
      }

      // Process the CSV file and sync members with Unioo
      // This is a placeholder for your actual sync logic
      $sync_result = [
        'success' => true,
        'message' => __('CSV import completed successfully.', WP_UNIOO_SYNC_TEXTDOMAIN),
      ];

      // Log the sync status in the database
      $this->logSyncStatus(
        $sync_result['success'] ? 'success' : 'failure',
        $sync_result['message']
      );

      return rest_ensure_response($sync_result);
    }

    /**
     *
     * Create user for synced unioo member
     * @param mixed $member
     * @throws UniooSyncUserNotCreatedException
     * @return bool|WP_User|null
     */
    private function createSynceUnioodUser($member): array|WP_User|null {
      $user = get_user_by('email', $member['email']);

      if ( ! $user ) {
        // if the option to require membership is enabled and the member has unpaid bills, skip creating the user and log the action
        if ( get_option('wp_unioo_sync_required_membership', false) && $member['Ubetalte regninger'] === "Ja" ) {
          $log_message = 'Member has unpaid bills, skipping user creation' . $member['Email'] . ' - ' . ($member['Navn'] ?? 'No name provided');
          $this->logSyncStatus('success', $log_message);
          return ["success" => false, "message" => 'Member has unpaid bills, skipping user creation.'];
        }

        // Create a new user with the member's email and a generated password
        $password = get_option('wp_unioo_sync_default_password', wp_generate_password());

        // make sure to sanitize the password field if it is set in the options, otherwise generate a random password
        if ( empty($password) ) {
          $password = wp_generate_password();
        } else {
          $password = sanitize_text_field($password);
        }

        $user_id = wp_create_user($member['Email'], $password, $member['Email']);
        if (is_wp_error($user_id)) {
          $log_message = 'Failed to create user: ' . $user_id->get_error_message() . ' for member: ' . $member['Email'] . ' - ' . ($member['Navn'] ?? 'No name provided');
          $this->logSyncStatus('failure', $log_message);
          return ["success" => false, "message" => 'Failed to create user: ' . $user_id->get_error_message()];
        }

        $user = get_user_by('id', $user_id);
        $user->set_role('subscriber');
        $this->logSyncStatus('success', 'User created successfully for member: ' . $member['Email'] . ' - ' . ($member['Navn'] ?? 'No name provided'));
      }

      // if current user has unpaid bills and the option to require membership is enabled, delete the user and log the action
      if ( $user && get_option('wp_unioo_sync_required_membership', false) && $member['Ubetalte regninger'] === "Ja" ) {
        wp_delete_user($user->ID);
        $log_message = 'Existing user member with unpaid bills, user deleted: ' . $member['Email'] . ' - ' . ($member['Navn'] ?? 'No name provided');
        $this->logSyncStatus('success', $log_message);
        return ["success" => false, "message" => 'Existing member with existing user, has unpaid bills, user deleted.'];
      }

      return $user;
    }

    /**
     * Create new log for sync status
     *
     * @param mixed $status
     * @param mixed $message
     * @throws UniooSyncLogNotCreatedException
     * @return void
     */
    private function logSyncStatus($status, $message) {
      global $wpdb;
      try {
        $sync_log_creatd = $wpdb->insert(
          "wp_unioo_sync",
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
          if ( $sync_log_creatd === false ) {
            throw new UniooSyncLogNotCreatedException('Failed to log sync status in the database.');
          }
      } catch (UniooSyncLogNotCreatedException $e) {
        error_log('Failed to log sync status: ' . $e->getMessage());
      }
    }
  }
}