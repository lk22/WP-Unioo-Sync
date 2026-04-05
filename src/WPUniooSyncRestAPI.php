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

    public static $namespace = 'wp-unioo-sync/v1';

    public function __construct() {
      add_action('rest_api_init', function() {
        $this->register_routes();
      });
    }

    public static function get_namespace() {
      return self::$namespace;
    }

    public function register_routes() {
      register_rest_route(self::$namespace, '/sync-members', [
        'methods' => 'POST',
        'callback' => [$this, 'sync_csv_members'],
        'permission_callback' => function() {
          return current_user_can('manage_options');
        }
      ]);
    }

    public static function get_routes() {
      return rest_get_server()->get_routes();
    }

    public function sync_csv_members(WP_REST_Request $request) {
      global $wpdb;

      $members = $request->get_params(); // Ensure file parameters are available in the request

      $createdUsers = 0;
      $deletedUsers = 0;
      $updatedUsers = 0;

      // loop through the members and process the JSON data as needed
      foreach ($members["members"] as $key => $member) {
        // if there is a custom table for storing the members, we can check if the table exists and if not create it, and then insert the member data into the table
        // otherwise save the member data in the default WordPress user meta or a custom post type, depending on the use case
        // make sure if there is a user in the system with the same email as the member, if not create a new user and assign a role, for example: subscriber
        // then save the member data in the user meta, for example: email, gamertag, and other relevant information from the data
        // return rest_ensure_response($this->createSynceUnioodUser($member));
        if ( $user = $this->createSynceUnioodUser($member) ) {
          $memberData = [
            'name' => $member['Navn'],
            'email' => $member['Email'],
            'phone' => $member['Telefon'],
            'birth_date' => $member['Fødselsdato'],
            'address' => $member['Adresse'],
            'city' => $member['By'],
            'postal_code' => $member['Postnummer'],
            'identification' => $member['Identifikation'],
            'membership' => $member['Kontingenter (Navne)'],
            'unpaid_fee' => $member['Ubetalte regninger'],
            'member_since' => $member['Indmeldelsesdato'],
            'left_at' => $member['Udmeldelsesdato'],
            'active_payment_method' => $member['Aktiv betalingsmetode'],
            'latest_note' => $member['Nyeste note'],
          ];

          $custom_fields = get_option('wp_unioo_sync_custom_fields', []);

          if ( is_array($custom_fields) && count($custom_fields) > 0 ) {
            $custom_fields = get_option('wp_unioo_sync_custom_fields');
            foreach ( $custom_fields as $key => $field ) {
              if ( isset($member[$field]) ) {
                $lowercase_field = strtolower($field);
                $memberData[$lowercase_field] = $member[$field];
                $member[$lowercase_field] = $member[$field];
              }
            }
          }

          if ( ! get_option('wp_unioo_sync_members_table') ) {
            foreach ( $memberData as $key => $value ) {
              update_user_meta($user->ID, $key, $value);
            }
          } else {
            $this->insertUpdateIntoTable($member, $user->ID);
          }

          $createdUsers++;
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
     * Create user for synced unioo member
     *
     * @param mixed $member
     * @throws UniooSyncUserNotCreatedException
     * @return bool|WP_User|null
     */
    private function createSynceUnioodUser($member): array|WP_User|null {
      $user = get_user_by('email', $member['Email']);

      if ( ! $user ) {
        // if the option to require membership is enabled and the member has unpaid bills, skip creating the user and log the action
        if ( get_option('wp_unioo_sync_required_membership', false) && $member['Ubetalte regninger'] === "Ja" ) {
          $log_message = 'Member has unpaid bills, skipping user creation' . $member['Email'] . ' - ' . ($member['Navn'] ?? 'No name provided');
          $this->logSyncStatus('success', $log_message);
          return ["success" => false, "message" => 'Member has unpaid bills, skipping user creation.'];
        }

        // Create a new user with the member's email and a generated password
        if ( get_option('wp_unioo_sync_user_default_username_field') ) {
          $username = $member[str_replace(['{{', '}}'], '', get_option('wp_unioo_sync_user_default_username_field'))];
        } else {
          $username = $member['Email'];
        }

        $username = sanitize_user($username);
        $password = get_option('wp_unioo_sync_user_default_password_field', false);

        // make sure to sanitize the password field if it is set in the options, otherwise generate a random password
        if ( $password === 'generate_random' ) {
          $password = wp_generate_password();
        } else {
          $password = sanitize_text_field(
            $member[str_replace(['{{', '}}'], '', get_option('wp_unioo_sync_user_default_password_field', false))]
          );
        }

        $user_id = wp_create_user($username, $password, $member['Email']);
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
      } else if ( $user ) {
        $log_message = 'Existing user found for member: ' . $member['Email'] . ' - ' . ($member['Navn'] ?? 'No name provided') . ', user ID: ' . $user->ID;
        $this->logSyncStatus('success', $log_message);
        wp_update_user([
          'ID' => $user->ID,
          'user_email' => $member['Email'],
          'display_name' => $member['Navn'] ?? $user->display_name,
          'user_pass' => get_option('wp_unioo_sync_user_default_password_field', false) === 'generate_random' ? wp_generate_password() : sanitize_text_field(
            $member[str_replace(['{{', '}}'], '', get_option('wp_unioo_sync_user_default_password_field', false))]
          ),
          'user_login' => sanitize_user(
            get_option('wp_unioo_sync_user_default_username_field') ? $member[str_replace(['{{', '}}'], '', get_option('wp_unioo_sync_user_default_username_field'))] : $user->user_login
          ),
        ]);
      }

      return $user;
    }

    /**
     *
     * @param mixed $member
     * @param mixed $user_id
     * @return void
     */
    public function insertUpdateIntoTable($member, $user_id) {
      global $wpdb;
      $table_name = $wpdb->prefix . 'unioo_members';

      $data = [
        'member_id' => $member['Identifikation'],
        'user_id' => $user_id,
        'name' => $member['Navn'],
        'email' => $member['Email'],
        'phone' => $member['Telefon'],
        'birth_date' => $member['Fødselsdato'],
        'address' => $member['Adresse'],
        'city' => $member['By'],
        'postal_code' => $member['Postnummer'],
        'identification' => $member['Identifikation'],
        'membership' => $member['Kontingenter (Navne)'],
        'unpaid_fee' => $member['Ubetalte regninger'],
        'member_since' => $member['Indmeldelsesdato'],
        'left_at' => $member['Udmeldelsesdato'],
        'active_payment_method' => $member['Aktiv betalingsmetode'],
        'latest_note' => $member['Nyeste note'],
        'sync_time' => current_time('mysql'),
      ];

      foreach (get_option('wp_unioo_sync_custom_fields', []) as $label => $column) {
          if (isset($member[$label])) {
              $data[$column] = $member[$label];
          }
      }

      // Check if the member already exists in the table
      $existing_member = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table_name WHERE member_id = %s", [$data['member_id']])
      );

      if ( $existing_member ) {
        // Update existing member
        $wpdb->update(
          $table_name,
          $data,
          ['id' => $existing_member->id],
          [
            '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
            '%s', '%s', '%s', '%s', '%s', '%s', '%s',
          ],
          ['%d']
        );
      } else {
        // Insert new member
        $wpdb->insert(
          $table_name,
          $data,
          [
            '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
            '%s', '%s', '%s', '%s', '%s', '%s', '%s',
          ]
        );
      }
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
          WP_UNIOO_SYNC_TABLE_NAME,
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