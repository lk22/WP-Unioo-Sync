<?php

use LeoKnudsen\WpUniooSync\WPUniooSyncRestAPI;

use function Brain\Monkey\Functions\when;

if ( ! defined('WP_UNIOO_SYNC_TABLE_NAME') ) {
  define('WP_UNIOO_SYNC_TABLE_NAME', 'wp_unioo_sync');
}

if ( ! defined('WP_UNIOO_SYNC_TEXTDOMAIN') ) {
  define('WP_UNIOO_SYNC_TEXTDOMAIN', 'wp-unioo-sync');
}

it('Checks if the REST API class can be instantiated', function() {
  $restAPI = new WPUniooSyncRestAPI();

  expect($restAPI)->toBeInstanceOf(WPUniooSyncRestAPI::class);
});

it('checks the REST API namespace', function() {
  $restAPI = new WPUniooSyncRestAPI();

  expect($restAPI::get_namespace())->toBe('wp-unioo-sync/v1');
});

it('Checks if the REST API route is registered', function() {
  $restAPI = new WPUniooSyncRestAPI();

  when('do_action')->alias(function($key) use ($restAPI) {
    // We only want to check for the 'rest_api_init' action, so we can ignore other actions
    if ($key === 'rest_api_init') {
      // Call the callback function that registers the REST API routes
      $restAPI->register_routes();
    }
  });

  when('register_rest_route')->alias(function($namespace, $route, $args) {
    // We only want to check for the '/wp-unioo-sync/v1/sync-members' route, so we can ignore other routes
    if ($namespace === 'wp-unioo-sync/v1' && $route === '/members/sync-members') {
      // We can return true to indicate that the route was registered successfully
      return true;
    }
  });

  when('rest_get_server')->alias(function() {
    // We can return a mock of the WP_REST_Server class that has the get_routes method
    $mock = \Mockery::mock('WP_REST_Server');
    $mock->shouldReceive('get_routes')->andReturn([
      '/wp-unioo-sync/v1/members/sync-members' => []
    ]);
    return $mock;
  });

  // Trigger the registration of REST API routes
  do_action('rest_api_init');

  $routes = $restAPI::get_routes();

  expect($routes)->toHaveKey('/wp-unioo-sync/v1/members/sync-members');
});

it('Checks if the REST API callback function is working', function() {
  $restAPI = new WPUniooSyncRestAPI();
  global $wpdb;

  $wpdb = \Mockery::mock();
  $wpdb->shouldReceive('insert')->atLeast()->once()->andReturn(true);

  $mockedRequest = \Mockery::mock('WP_REST_Request');
  $mockedRequest->shouldReceive('get_params')->andReturn([
    'members' => [
      [
        'Navn' => 'John Doe',
        'Email' => 'john@doe',
        'Telefon' => '12345678',
        'Fødselsdato' => '01-01-2000',
        'Adresse' => '123 Main St',
        'By' => 'Copenhagen',
        'Postnummer' => '1234',
        'Identifikation' => '1234567890',
        'Kontingenter (Navne)' => 'Membership 1, Membership 2',
        'Ubetalte regninger' => 'Invoice 1, Invoice 2',
        'Indmeldelsesdato' => '01-01-2020',
        'Udmeldelsesdato' => null,
        'Aktiv betalingsmetode' => 'Credit Card',
        'Nyeste note' => 'This is a note',
      ]
    ]
  ]);

  $mockedUser = \Mockery::mock('WP_User');
  $mockedUser->ID = 1;
  $mockedUser->display_name = 'John Doe';
  $mockedUser->user_login = 'john@doe';
  $mockedUser->shouldReceive('set_role')->once()->with('subscriber');

  // We can mock the sync_csv_members method to return a specific response
  when('get_user_by')->alias(function($field, $value) use ($mockedUser) {
    if ($field === 'email') {
      return null;
    }

    if ($field === 'id' && $value === 1) {
      return $mockedUser;
    }

    return null;
  });

  when('get_option')->alias(function($option, $default = false) {
    return match ($option) {
      'wp_unioo_sync_required_membership' => false,
      'wp_unioo_sync_user_default_username_field' => '',
      'wp_unioo_sync_user_default_password_field' => 'generate_random',
      'wp_unioo_sync_custom_fields' => [],
      'wp_unioo_sync_members_table' => false,
      default => $default,
    };
  });

  when('is_wp_error')->justReturn(false);

  when('sanitize_user')->alias(function($username) {
    return $username; // Simulate that the username is sanitized successfully
  });

  when('wp_generate_password')->alias(function() {
    return 'generated_password'; // Simulate that a password is generated successfully
  });

  when('sanitize_text_field')->alias(function($text) {
    return $text; // Simulate that the text is sanitized successfully
  });

  when('wp_create_user')->alias(function($username, $password, $email) {
    return 1;
  });

  when('__')->alias(function($text, $domain = null) {
    return $text;
  });

  when('current_time')->justReturn('2026-04-06 12:00:00');

  when('wp_update_user')->justReturn(true);

  when('update_user_meta')->justReturn(true);

  when('rest_ensure_response')->alias(function($response) {
    return $response;
  });

  // We can call the callback function directly to test it
  $response = $restAPI->sync_csv_members($mockedRequest);

  expect($response)->toBeArray();
  assert(is_array($response));
  expect($response)->toHaveKey('success');
  expect($response['success'])->toBeTrue();
  expect($response)->toHaveKey('message');
  expect($response['message'])->toBe('CSV import completed successfully.');
});