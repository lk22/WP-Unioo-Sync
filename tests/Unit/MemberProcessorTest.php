<?php

use LeoKnudsen\WpUniooSync\Admin\Unioo\Sync\MemberProcessor;
use function Brain\Monkey\Functions\when;

use LeoKnudsen\WpUniooSync\Exceptions\UniooSyncUserNotCreatedException;

test('Makes sure MemberProcessor works', function() {
  $memberProcessor = new MemberProcessor();

  expect($memberProcessor)->toBeInstanceOf(MemberProcessor::class);
});

it('Gets defined username field as default', function() {
  $memberProcessor = new MemberProcessor();

  when('get_option')->justReturn('{{Nickname}}');

  expect($memberProcessor->getUsernameField())->toBe('nickname');
});

it('Uses email as default username field if no username field is defined', function() {
  $memberProcessor = new MemberProcessor();

  when('get_option')->justReturn('');

  expect($memberProcessor->getUsernameField())->toBe('email');
});

it('Uses random password if no password field is defined', function() {
  $memberProcessor = new MemberProcessor();

  when('get_option')->justReturn('');

  expect($memberProcessor->getPasswordField())->toBe('generate_password');
});

it('Gets defined password field as default', function() {
  $memberProcessor = new MemberProcessor();

  when('get_option')->justReturn('{{Password}}');

  expect($memberProcessor->getPasswordField())->toBe('password');
});

it('Checks if the member is marked as expired', function() {
  $memberProcessor = new MemberProcessor();

  $member = [
    'paymentMethod' => [
      'isExpired' => true
    ]
  ];

  expect($memberProcessor->isMemberExpired($member))->toBeTrue();
});

it('Checks if the member is not marked as expired', function() {
  $memberProcessor = new MemberProcessor();

  $member = [
    'paymentMethod' => [
      'isExpired' => false
    ]
  ];

  expect($memberProcessor->isMemberExpired($member))->toBeFalse();
});

it('Checks if the member has unpaid invoices and the member is active not having an existing user', function() {
  $memberProcessor = new MemberProcessor();

  $member = [
    'status' => 'ACTIVE',
    'email' => 'leo@example.com',
    'hasUnpaidInvoices' => true,
    'paymentMethod' => [
      'isExpired' => false
    ]
  ];

  when('get_user_by')->justReturn(null);

  $processedMember = $memberProcessor->process($member);

  expect($processedMember)->toBe('skipped');
});

it('Deletes the member if the member has unpaid invoices or expired payment method', function(){
  $memberProcessor = new MemberProcessor();

  $member = [
    'status' => 'INACTIVE',
    'email' => 'test@example.com',
    'hasUnpaidInvoices' => true,
    'paymentMethod' => [
      'isExpired' => false
    ]
  ];

  $existingUser = (object) ['ID' => 1, 'email' => $member['email']];
  when('get_user_by')->justReturn($existingUser);
  when('wp_delete_user')->justReturn(true);

  expect($memberProcessor->process($member))->toBe('deleted');
});

it('Tries to create a user if the member is marked active and user does not exist', function() {
  $memberProcessor = new MemberProcessor();

  $member = [
    "status" => "ACTIVE",
    "email" => "leo@example.com",
  ];

  when('get_user_by')->justReturn(null);
  when('get_option')->justReturn('');
  when('wp_create_user')->justReturn(1);
  when('wp_generate_password')->justReturn('randompassword');

  $created_user = [
    'ID' => 1,
    'user_email' => $member['email'],
    'user_pass' => 'randompassword',
  ];

  $result = $memberProcessor->createSyncedUser($member);
  expect($result)->toHaveKeys(['user', 'isNew']);
});

it('throws UniooSyncUserNotCreatedException when wp_create_user fails', function() {
    $memberProcessor = new MemberProcessor();

  $mocked_wp_error = \Mockery::mock('WP_Error');
    $mocked_wp_error->shouldReceive('get_error_message')->andReturn('User creation failed');

    $member = [
        'status' => 'ACTIVE',
        'email'  => 'leo@example.com',
    ];

    when('get_user_by')->justReturn(null);
    when('get_option')->justReturn('');
    when('wp_generate_password')->justReturn('randompassword');
    when('wp_create_user')->justReturn($mocked_wp_error);
    when('is_wp_error')->justReturn(true);

    expect(fn() => $memberProcessor->createSyncedUser($member))
      ->toThrow(UniooSyncUserNotCreatedException::class, 'User creation failed');
});

it('throws with dynamic wp error message when wp_create_user fails', function() {
  $memberProcessor = new MemberProcessor();

  $mocked_wp_error = \Mockery::mock('WP_Error');
  $mocked_wp_error->shouldReceive('get_error_message')
    ->andReturn('Username already exists');

  $member = [
    'status' => 'ACTIVE',
    'email' => 'leo@example.com',
  ];

  when('get_user_by')->justReturn(null);
  when('get_option')->justReturn('');
  when('wp_generate_password')->justReturn('randompassword');
  when('wp_create_user')->justReturn($mocked_wp_error);
  when('is_wp_error')->justReturn(true);

  expect(fn() => $memberProcessor->createSyncedUser($member))
    ->toThrow(UniooSyncUserNotCreatedException::class, 'Username already exists');
});

it('returns existing user and does not mark as new when email already exists', function() {
  $memberProcessor = new MemberProcessor();

  $existingUser = (object) [
    'ID' => 11,
    'user_email' => 'leo@example.com',
  ];

  $member = [
    'status' => 'ACTIVE',
    'email' => 'leo@example.com',
  ];

  when('get_user_by')->justReturn($existingUser);

  $result = $memberProcessor->createSyncedUser($member);

  expect($result['isNew'])->toBeFalse();
  expect($result['user'])->toBe($existingUser);
});

it('returns isNew true when user creation succeeds', function() {
  $memberProcessor = new MemberProcessor();

  $createdUser = (object) [
    'ID' => 42,
    'user_email' => 'leo@example.com',
  ];

  $member = [
    'status' => 'ACTIVE',
    'email' => 'leo@example.com',
  ];

  $getUserByCalls = 0;
  when('get_user_by')->alias(function($field, $value) use (&$getUserByCalls, $createdUser) {
    $getUserByCalls++;

    if ($field !== 'email' || $value !== 'leo@example.com') {
      return null;
    }

    return $getUserByCalls === 1 ? null : $createdUser;
  });
  when('get_option')->justReturn('');
  when('wp_generate_password')->justReturn('randompassword');
  when('wp_create_user')->justReturn(42);
  when('is_wp_error')->justReturn(false);

  $result = $memberProcessor->createSyncedUser($member);

  expect($result)->toHaveKeys(['user', 'isNew']);
  expect($result['isNew'])->toBeTrue();
  expect($result['user'])->toBe($createdUser);
});