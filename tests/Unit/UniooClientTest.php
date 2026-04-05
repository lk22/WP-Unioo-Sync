<?php

use LeoKnudsen\WpUniooSync\Admin\Unioo\UniooClient;
use function Brain\Monkey\Functions\expect as expectFunction;
use function Brain\Monkey\Functions\when;

if ( ! defined('WP_UNIOO_SYNC_TEXTDOMAIN') ) {
  define('WP_UNIOO_SYNC_TEXTDOMAIN', 'wp-unioo-sync');
}

beforeEach(function() {
  when('__')->alias(function($message) {
    return $message;
  });
});

test('send_sync_request returns error for invalid action', function() {
  $client = new UniooClient('https://example.com/graphql', 'token-123');

  $result = $client->send_sync_request('invalid_action');

  expect($result['success'])->toBeFalse();
  expect($result['message'])->toBe('Invalid sync action specified.');
});

test('send_sync_request routes sync_members action', function() {
  $client = new UniooClient('https://example.com/graphql', 'token-123');

  when('wp_remote_post')->justReturn(['body' => json_encode([
    'data' => [
      'data' => [
        'totalCount' => 0,
        'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
        'nodes' => [],
      ],
    ],
  ])]);
  when('is_wp_error')->justReturn(false);
  when('wp_remote_retrieve_body')->alias(function($response) {
    return $response['body'];
  });

  $result = $client->send_sync_request('sync_members');

  expect($result['success'])->toBeTrue();
  expect($result['message'])->toBe('Members list synced successfully.');
  expect($result)->toHaveKey('data');
});

test('authenticate returns null when wp_remote_post fails', function() {
  $client = new UniooClient('https://example.com/graphql', 'token-123');

  $wpError = \Mockery::mock('WP_Error');
  $wpError->shouldReceive('get_error_message')->andReturn('request failed');

  when('get_option')->alias(function($key) {
    return $key === 'wp_unioo_sync_username' ? 'user@example.com' : 'secret';
  });
  when('wp_remote_post')->justReturn($wpError);
  when('is_wp_error')->justReturn(true);
  when('error_log')->justReturn(true);

  expectFunction('update_option')->never();

  $result = $client->authenticate();

  expect($result)->toBeNull();
});

test('authenticate returns null when token is missing from response', function() {
  $client = new UniooClient('https://example.com/graphql', 'token-123');

  when('get_option')->alias(function($key) {
    return $key === 'wp_unioo_sync_username' ? 'user@example.com' : 'secret';
  });
  when('wp_remote_post')->justReturn(['body' => '{"message":"invalid credentials"}']);
  when('is_wp_error')->justReturn(false);
  when('wp_remote_retrieve_body')->alias(function($response) {
    return $response['body'];
  });
  when('error_log')->justReturn(true);

  expectFunction('update_option')->never();

  $result = $client->authenticate();

  expect($result)->toBeNull();
});

test('authenticate returns null when sanitized token is empty', function() {
  $client = new UniooClient('https://example.com/graphql', 'token-123');

  when('get_option')->alias(function($key) {
    return $key === 'wp_unioo_sync_username' ? 'user@example.com' : 'secret';
  });
  when('wp_remote_post')->justReturn(['body' => '{"token":"   "}']);
  when('is_wp_error')->justReturn(false);
  when('wp_remote_retrieve_body')->alias(function($response) {
    return $response['body'];
  });
  when('sanitize_text_field')->justReturn('');
  when('error_log')->justReturn(true);

  expectFunction('update_option')->never();

  $result = $client->authenticate();

  expect($result)->toBeNull();
});

test('authenticate updates bearer token and sends expected request payload on success', function() {
  $client = new UniooClient('https://example.com/graphql', 'old-token');

  $capturedEndpoint = null;
  $capturedArgs = null;

  when('get_option')->alias(function($key) {
    if ($key === 'wp_unioo_sync_username') {
      return 'user@example.com';
    }

    if ($key === 'wp_unioo_sync_password') {
      return 'secret';
    }

    return '';
  });

  when('wp_remote_post')->alias(function($endpoint, $args) use (&$capturedEndpoint, &$capturedArgs) {
    $capturedEndpoint = $endpoint;
    $capturedArgs = $args;

    return ['body' => '{"token":" token-123 "}'];
  });
  when('is_wp_error')->justReturn(false);
  when('wp_remote_retrieve_body')->alias(function($response) {
    return $response['body'];
  });
  when('sanitize_text_field')->alias(function($value) {
    return trim($value);
  });

  expectFunction('update_option')
    ->once()
    ->with('wp_unioo_sync_bearer_token', 'token-123');

  $result = $client->authenticate();

  expect($result)->toBe('token-123');
  expect($capturedEndpoint)->toBe('https://api.unioo.io/api/authenticate/password');
  expect($capturedArgs['headers']['Content-Type'])->toBe('application/json');

  $decodedBody = json_decode($capturedArgs['body'], true);
  expect($decodedBody['username'])->toBe('user@example.com');
  expect($decodedBody['password'])->toBe('secret');
});

test('refresh_api_token returns early when wp_remote_post fails', function() {
  $client = new UniooClient('https://example.com/graphql', 'old-token');

  $wpError = \Mockery::mock('WP_Error');
  $wpError->shouldReceive('get_error_message')->andReturn('request failed');

  when('wp_remote_post')->justReturn($wpError);
  when('is_wp_error')->justReturn(true);
  when('error_log')->justReturn(true);

  expectFunction('update_option')->never();

  $client->refresh_api_token();

  expect(true)->toBeTrue();
});

test('refresh_api_token updates token on success and sends expected request payload', function() {
  $client = new UniooClient('https://example.com/graphql', 'old-token');

  $capturedEndpoint = null;
  $capturedArgs = null;

  when('wp_remote_post')->alias(function($endpoint, $args) use (&$capturedEndpoint, &$capturedArgs) {
    $capturedEndpoint = $endpoint;
    $capturedArgs = $args;

    return ['body' => '{"success":true,"token":" new-token "}'];
  });
  when('is_wp_error')->justReturn(false);
  when('wp_remote_retrieve_body')->alias(function($response) {
    return $response['body'];
  });
  when('sanitize_text_field')->alias(function($value) {
    return trim($value);
  });

  expectFunction('update_option')
    ->once()
    ->with('wp_unioo_sync_bearer_token', 'new-token');

  $client->refresh_api_token();

  expect($capturedEndpoint)->toBe('https://api.unioo.io/api/refresh-token');
  expect($capturedArgs['headers']['Content-Type'])->toBe('application/json');

  $decodedBody = json_decode($capturedArgs['body'], true);
  expect($decodedBody['token'])->toBe('old-token');
});

test('refresh_api_token does not update option on unsuccessful response', function() {
  $client = new UniooClient('https://example.com/graphql', 'old-token');

  when('wp_remote_post')->justReturn(['body' => '{"success":false,"message":"invalid"}']);
  when('is_wp_error')->justReturn(false);
  when('wp_remote_retrieve_body')->alias(function($response) {
    return $response['body'];
  });
  when('error_log')->justReturn(true);

  expectFunction('update_option')->never();

  $client->refresh_api_token();

  expect(true)->toBeTrue();
});

test('sync_members returns error when request returns wp error', function() {
  $client = new UniooClient('https://example.com/graphql', 'token-123');

  $wpError = \Mockery::mock('WP_Error');
  $wpError->shouldReceive('get_error_message')->andReturn('network down');

  when('wp_remote_post')->justReturn($wpError);
  when('is_wp_error')->justReturn(true);

  $result = $client->sync_members();

  expect($result['success'])->toBeFalse();
  expect($result['message'])->toBe('network down');
});

test('sync_members returns unauthorized message when graphql returns errors', function() {
  $client = new UniooClient('https://example.com/graphql', 'token-123');

  when('wp_remote_post')->justReturn(['body' => '{"errors":[{"message":"unauthorized"}]}']);
  when('is_wp_error')->justReturn(false);
  when('wp_remote_retrieve_body')->alias(function($response) {
    return $response['body'];
  });

  $result = $client->sync_members();

  expect($result['success'])->toBeFalse();
  expect($result['message'])->toBe('Unioo API error: User is Unauthorized. Please check your API credentials.');
});

test('sync_members returns mapped data and sends expected graphql payload', function() {
  $client = new UniooClient('https://example.com/graphql///', 'token-123');

  $capturedEndpoint = null;
  $capturedArgs = null;

  when('wp_remote_post')->alias(function($endpoint, $args) use (&$capturedEndpoint, &$capturedArgs) {
    $capturedEndpoint = $endpoint;
    $capturedArgs = $args;

    return ['body' => json_encode([
      'data' => [
        'data' => [
          'totalCount' => 1,
          'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
          'nodes' => [
            ['email' => 'leo@example.com', 'status' => 'ACTIVE'],
          ],
        ],
      ],
    ])];
  });
  when('is_wp_error')->justReturn(false);
  when('wp_remote_retrieve_body')->alias(function($response) {
    return $response['body'];
  });

  $result = $client->sync_members([
    'after' => 'cursor-123',
    'where' => ['status' => ['eq' => 'ACTIVE']],
    'subscriptionId' => 'sub-123',
  ]);

  expect($result['success'])->toBeTrue();
  expect($result['message'])->toBe('Members list synced successfully.');
  expect($result['data']['totalCount'])->toBe(1);
  expect($result['data']['nodes'][0]['email'])->toBe('leo@example.com');

  expect($capturedEndpoint)->toBe('https://example.com/graphql');
  expect($capturedArgs['headers']['Authorization'])->toBe('Bearer token-123');
  expect($capturedArgs['headers']['Content-Type'])->toBe('application/json');

  $decodedBody = json_decode($capturedArgs['body'], true);
  expect($decodedBody['variables']['first'])->toBe(10);
  expect($decodedBody['variables']['after'])->toBe('cursor-123');
  expect($decodedBody['variables']['where'])->toBe(['status' => ['eq' => 'ACTIVE']]);
  expect($decodedBody['variables']['subscriptionId'])->toBe('sub-123');
});

test('sync_members falls back to default endpoint when api_url is empty', function() {
  $client = new UniooClient('', 'token-123');

  $capturedEndpoint = null;

  when('wp_remote_post')->alias(function($endpoint) use (&$capturedEndpoint) {
    $capturedEndpoint = $endpoint;

    return ['body' => json_encode([
      'data' => [
        'data' => [
          'totalCount' => 0,
          'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
          'nodes' => [],
        ],
      ],
    ])];
  });
  when('is_wp_error')->justReturn(false);
  when('wp_remote_retrieve_body')->alias(function($response) {
    return $response['body'];
  });

  $client->sync_members();

  expect($capturedEndpoint)->toBe('https://api.unioo.io/graphql');
});
