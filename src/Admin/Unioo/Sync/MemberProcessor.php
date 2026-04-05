<?php

namespace LeoKnudsen\WpUniooSync\Admin\Unioo\Sync;

if ( ! defined('ABSPATH') ) {
  exit();
}

use LeoKnudsen\WpUniooSync\Exceptions\UniooSyncUserNotCreatedException;

if ( ! class_exists('MemberProcessor') ) {
  class MemberProcessor
  {
    /**
     * Check if a member is active based on the status field
     * if the status field is missing, assume the member is not active to prevent creating users for members that are not active
     *
     * @param mixed $member
     * @return bool
     */
    public function isActiveMember($member): bool
    {
      if ( ! isset($member['status']) ) {
        return false;
      }

      return $member['status'] === 'ACTIVE';
    }

    /**
     * Check if the member is expired
     * @todo make sure to check the paymentMethod isExpired field
     *
     * @param mixed $member
     * @return bool
     */
    public function isMemberExpired($member): bool
    {
      return $member['paymentMethod']['isExpired'] ?? false;
    }

    /**
     * fetching username field for member user creation
     *
     * @return array|string
     */
    public function getUsernameField(): string
    {
      if ( get_option('wp_unioo_sync_user_default_username_field')) {
        return str_replace(
          ['{{','}}'],
          '',
          strtolower(get_option('wp_unioo_sync_user_default_username_field'))
        );
      }

      return 'email';
    }

    /**
     * Getting password field for use as default password field value
     * default is randome generated password by WordPress
     *
     * @return array|string
     */
    public function getPasswordField(): string
    {
      if ( get_option('wp_unioo_sync_user_default_password_field')) {
        return str_replace(
          ['{{','}}'],
          '',
          strtolower(get_option('wp_unioo_sync_user_default_password_field'))
        );
      }

      return 'generate_password';
    }

    /**
     * the process of a member from af batch of fetched members
     * @param array $member
     * @return string
     */
    public function process(array $member)
    {
      $isActive = $this->isActiveMember($member);
      $existingUser = get_user_by('email', $member['email']);

      /**
       * Delete user if the member is not active but exists as a user, this prevents non payed users have access
       */
      if ( ! $isActive ) {
        if ( $existingUser ) {
          wp_delete_user($existingUser->ID);
          return 'deleted';
        }

        return 'skipped';
      }

      /**
       * Save the member data if the user is created or updated successfully, if the user creation or update is skipped, do not save the member data to prevent having member data without a corresponding user, which could lead to orphaned member records and potential confusion when managing members and users in the future.
        * The check for null result from createSyncedUser is to ensure that we only attempt to save member data when a user was actually created or updated, if the result is null, it means the user creation or update was skipped due to an error or because the member was not active, in which case we should also skip saving the member data.
       */
      $result = $this->createSyncedUser($member);

      $memberData = [
        'name' => $member['name'],
        'email' => $member['email'],
        'phone' => $member['phoneNumber'] ?? null,
        'birth_date' => $member['birthDate'] ?? null,
        'address' => $member['address'] ?? null,
        'city' => $member['city'] ?? null,
        'postal_code' => $member['postalCode'] ?? null,
        'identification' => $member['identification'] ?? null,
        'membership' => $member['membership'] ?? null,
        'unpaid_fee' => $member['unpaidFee'] ?? null,
      ];

      if ( get_option('wp_unioo_sync_custom_fields') ) {
        foreach (get_option('wp_unioo_sync_custom_fields', []) as $label => $column) {
          foreach ( $member['customFieldValues'] as $customFieldValue) {
            if (strtolower($customFieldValue['customField']['name']) === strtolower($label)) {
              $memberData[$column] = $customFieldValue['text'] ?? null;
            }
          }
        }
      }

      /**
       * If the custom table option is enabled, insert or update the member data into the custom table, otherwise save the member data as user meta for the created or updated user.
       * This is to provide flexibility for different use cases, some may prefer to have the member data in a custom table for easier querying and management, while others may prefer to have it as user meta for simplicity and compatibility with existing WordPress user management features.
       * The custom fields option allows users to map additional fields from the Unioo member data to either the custom table or user meta, providing further customization options for different use cases and requirements.
       */
      if ( get_option('wp_unioo_sync_members_table') ) {
          $this->insertUpdateIntoTable($memberData, $result['user']->ID);
      } else {
        foreach( $memberData as $key => $value ) {
          update_user_meta(
            $result['user']->ID,
            $key,
            $value
          );
        }
      }

      return $result['isNew'] ? 'created' : 'updated';
    }

    /**
     * the process of a batch of members
     * @param array $members
     * @return array
     */
    public function processBatch(array $members): array
    {
      $results = [
        'created' => 0,
        'updated' => 0,
        'deleted' => 0,
        'skipped' => 0
      ];

      foreach ( $members as $member ) {
        $status = $this->process($member);
        $results[$status]++;
      }

      return $results;
    }

    /**
     * Creating the synced user
     *
     * @param array $member
     * @throws UniooSyncUserNotCreatedException
     * @return array{isNew: bool, user: bool|\WP_User|null}
     */
    public function createSyncedUser(array $member): array
    {
      $user = get_user_by('email', $member['email']);
      $isNew = false;

      if ( ! $user ) {
        $username_field = $this->getUsernameField();
        $passwordField = $this->getPasswordField();

        // loop through custom fields to find the username field value if the username field is a custom field
        // otherwise assume the username field is a member field and try to get the value from the member data refer default field mapping to email
        $username = $member["email"];
        $password = wp_generate_password();

        if ( ! empty($username_field) && isset($member["customFieldValues"]) ) {
          foreach ( $member["customFieldValues"] as $cfkey => $customFieldValue) {
            if (strtolower($member["customFieldValues"][$cfkey]['customField']['name']) === strtolower($username_field)) {
              $username = sanitize_user($member["customFieldValues"][$cfkey]['text'] ?? $member["email"]);
            }
          }
        }

        if ( ! empty($passwordField) && isset($member["customFieldValues"]) ) {
          foreach ( $member["customFieldValues"] as $cfkey => $customFieldValue) {
            if (strtolower($member["customFieldValues"][$cfkey]['customField']['name']) === strtolower($passwordField)) {
              $password = $member["customFieldValues"][$cfkey]['text'] ?? wp_generate_password();
            }
          }
        }

        $user_id = wp_create_user(
          $username,
          $password,
          $member['email']
        );

        if ( is_wp_error($user_id) ) {
          throw new UniooSyncUserNotCreatedException($user_id->get_error_message());
        }

        $isNew = true;
      }

      return [
        'user' => get_user_by('email', $member['email']),
        'isNew' => $isNew,
      ];
    }

    /**
     * Inserting or updating member data into the custom table
     *
     * @param array $member
     * @param int $user_id
     * @return void
     */
    public function insertUpdateIntoTable(array $member, int $user_id): void
    {
      global $wpdb;
      $table_name = $wpdb->prefix . 'unioo_members';

      $data = [
        'member_id' => $member['identification'] ?? null,
        'user_id' => $user_id,
        'name' => $member['name'] ?? null,
        'email' => $member['email'] ?? null,
        'phone' => $member['phoneNumber'] ?? null,
        'birth_date' => $member['birthDate'] ?? null,
        'address' => $member['address'] ?? null,
        'city' => $member['city'] ?? null,
        'postal_code' => $member['postalCode'] ?? null,
        'member_since' => $member['memberSince'] ?? null,
        'unpaid_fee' => $member['hasUnpaidInvoices'] ?? null,
      ];

      // only apply custom fields if the option is enabled and there are custom fields defined
      if ( get_option('wp_unioo_sync_custom_fields') && !empty($member["customFieldValues"]) ) {
        foreach (get_option('wp_unioo_sync_custom_fields', []) as $label => $column) {
          foreach ( $member['customFieldValues'] as $customFieldValue) {
            if (strtolower($customFieldValue['customField']['name']) === strtolower($label)) {
              $data[$column] = $customFieldValue['text'] ?? null;
            }
          }
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
          null,
          ['%d']
        );
      } else {
        // Insert new member
        $wpdb->insert(
          $table_name,
          array_merge($data, [
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
          ]),
          null
        );
       }
    }
  }
}