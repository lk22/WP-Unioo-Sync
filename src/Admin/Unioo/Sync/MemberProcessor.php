<?php

namespace LeoKnudsen\WpUniooSync\Admin\Unioo\Sync;

if ( ! defined('ABSPATH') ) {
  exit();
}

use LeoKnudsen\WpUniooSync\Exceptions\UniooSyncUserNotCreatedException;

require_once ABSPATH . 'wp-admin/includes/user.php';
require_once ABSPATH . 'wp-includes/pluggable.php';

if ( ! class_exists('MemberProcessor') ) {
  class MemberProcessor
  {
    public function isActiveMember($member): bool
    {
      if ( ! isset($member['status']) ) {
        return false;
      }

      return $member['status'] === 'ACTIVE' ? true : false;
    }

    public function isMemberExpired($member): bool
    {
      return false;
    }

    public function process(array $member): string
    {
      $isActive = $this->isActiveMember($member);
      $existingUser = get_user_by('email', $member['email']);

      if ( ! $isActive ) {
        if ( $existingUser ) {
          wp_delete_user($existingUser->ID);
          return 'deleted';
        }

        return 'skipped';
      }

      if ($result = $this->createSyncedUser($member)) {
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
      } else {
        return 'skipped';
      }


      $result = $this->createSyncedUser($member);


      if ( null === $result ) {
        return 'skipped';
      }

      $this->insertUpdateIntoTable($member, $result['user']->ID);
      return $result['isNew'] ? 'created' : 'updated';
    }

    public function getUsernameField(): string
    {
      if ( get_option('wp_unioo_sync_user_default_username_field')) {
        return str_replace(
          ['{{','}}'],
          '',
          get_option('wp_unioo_sync_user_default_username_field')
        );
      }

      return 'email';
    }

    public function getPasswordField(): string
    {
      if ( get_option('wp_unioo_sync_user_default_password_field')) {
        return str_replace(
          ['{{','}}'],
          '',
          get_option('wp_unioo_sync_user_default_password_field')
        );
      }

      return wp_generate_password();
    }

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

    public function createSyncedUser(array $member): ?array
    {
      $user = get_user_by('email', $member['email']);
      $isNew = false;

      if ( ! $user ) {
        try {

          $username = $this->getUsernameField();
          $password = $this->getPasswordField();

          $user_id = wp_create_user(
            $username,
            $password,
            $member['email']
          );

          if ( is_wp_error($user_id) ) {
            throw new UniooSyncUserNotCreatedException($user_id->get_error_message());
          }
        } catch ( UniooSyncUserNotCreatedException $e ) {
          error_log('Error creating user for member ' . $member['email'] . ': ' . $e->getMessage());
          return null;
        }
      }

      return [
        'user' => get_user_by('email', $member['email']),
        'isNew' => $isNew,
      ];
    }

    public function insertUpdateIntoTable(array $member, int $user_id): void
    {
      global $wpdb;
      $table_name = $wpdb->prefix . 'unioo_members';

      $data = [
        'member_id' => $member['identification'],
        'user_id' => $user_id,
        'name' => $member['name'],
        'email' => $member['email'],
        'phone' => $member['phoneNumber'] ?? null,
        'sync_time' => current_time('mysql'),
      ];

      // only apply custom fields if the option is enabled and there are custom fields defined
      if ( get_option('wp_unioo_sync_custom_fields') ) {
        foreach (get_option('wp_unioo_sync_custom_fields', []) as $label => $column) {
            if (isset($member[$label])) {
                $data[$column] = $member[$label];
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