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

    public function refresh_api_token(): void {
      $url = $this->api_url . '/api/refresh-token';
      $response = wp_remote_post($url, [
        'headers' => [
          'Authorization' => 'Bearer ' . $this->bearer_token,
          'Content-Type' => 'application/json',
        ],
        'body' => json_encode([
          'token' => $this->bearer_token,
        ]),
      ]);

      if ( is_wp_error($response) ) {
        error_log('Failed to refresh API token: ' . $response->get_error_message());
        return;
      }

      $body = wp_remote_retrieve_body($response);
      $data = json_decode($body, true);

      if ( isset($data['success']) && $data['success'] && isset($data['token']) ) {
        update_option('wp_unioo_sync_bearer_token', sanitize_text_field($data['token']));
        $this->bearer_token = sanitize_text_field($data['token']);
      } else {
        error_log('Failed to refresh API token: ' . ($data['message'] ?? 'Unknown error'));
      }
    }

    /**
     * fetching member from unioo and return the response
     * @return array{data: mixed, success: bool|array{message: mixed, success: bool}|array{message: string, success: bool}}
     */
    public function sync_members(): array {
      $endpoint = $this->api_url . '/graphql';
      $response = wp_remote_post($endpoint, [
        'headers' => [
          'Authorization' => 'Bearer ' . $this->bearer_token,
          'Content-Type' => 'application/json',
        ],
        'body' => json_encode([
          'query' => '
            query listOverviewMembers($first: Int, $after: String, $order: [ListOverviewMemberSortInput!], $where: ListOverviewMemberFilterInput, $subscriptionId: UUID) {
              data: listOverviewMembers(
                first: $first
                after: $after
                order: $order
                where: $where
                subscriptionId: $subscriptionId
              ) {
                totalCount
                pageInfo {
                  hasNextPage
                  endCursor
                }
                nodes {
                  id
                  identification
                  userId
                  type
                  name
                  birthDate
                  address
                  postalCode
                  city
                  callingCode
                  email
                  phoneNumber
                  memberSince
                  status
                  invitationDate
                }
              }
            }
          ',
        ]),
      ]);

      if (is_wp_error($response)) {
        return [
          'success' => false,
          'message' => $response->get_error_message(),
        ];
      }

      $body = wp_remote_retrieve_body($response);
      $data = json_decode($body, true);

      if (isset($data['errors'])) {
        return [
          'success' => false,
          'message' => $data['errors'][0]['message'] ?? __('An error occurred while syncing members.', WP_UNIOO_SYNC_TEXTDOMAIN),
        ];
      }

      return [
        'success' => true,
        'data' => $data['data']['listOverviewMembers'] ?? [],
      ];
    }
  }
}