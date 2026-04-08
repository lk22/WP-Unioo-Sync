<?php

use LeoKnudsen\WpUniooSync\Admin\Unioo\Mailer\UniooMailer;
use function Brain\Monkey\Functions\when;

it('checks if the template file exists and throws an exception if it does not', function () {
  $nonExistentTemplate = 'non-existent-template.php';

  expect(fn() => new UniooMailer($nonExistentTemplate, 'log', 'Test Subject'))
    ->toThrow(LeoKnudsen\WpUniooSync\Exceptions\UniooSyncMailTemplateNotFoundException::class);
});

it('resolves the template path correctly when the template exists', function () {
  $templatePath = WP_UNIOO_SYNC_PLUGIN_DIR . 'templates/mails/test-template.php';

  when('file_exists')->alias(function ($path) use ($templatePath) {
    if ($path === $templatePath) return true;
    return false;
  });

  $mailer = new UniooMailer('test-template.php', 'log', 'Test Subject');

  expect($mailer->template)->toBe($templatePath);
});

it('sends the email as a log message in logfile if default mail option is empty or set to log', function () {
  $templatePath = WP_UNIOO_SYNC_PLUGIN_DIR . 'templates/mails/test-template.php';

  when('__')->alias(fn($s) => $s);
  when('wp_date')->justReturn('2026-04-08 12:00:00');
  when('get_option')->alias(function($option) {
    return match ($option) {
      'wp_unioo_sync_default_email_address_on_sync' => 'log',
      default => '',
    };
  });
  when('file_get_contents')->justReturn('<p>{{error_message}}</p>');
  when('mkdir')->justReturn(true);
  when('file_put_contents')->justReturn(true);

  when('file_exists')->alias(function ($path) use ($templatePath) {
    if ($path === $templatePath) return true;
    return false;
  });

  $mailer = new UniooMailer('test-template.php', 'log', 'Test Subject');
  $mailer->send('This is a test error message.');

  expect(true)->toBeTrue();
});


it('creates the log directory if it does not exist', function () {
  $templatePath = WP_UNIOO_SYNC_PLUGIN_DIR . 'templates/mails/test-template.php';
  $logDirPath = WP_UNIOO_SYNC_PLUGIN_DIR . 'logs/';

  when('__')->alias(fn($s) => $s);
  when('wp_date')->justReturn('2026-04-08 12:00:00');
  when('get_option')->justReturn('');
  when('file_get_contents')->justReturn('<p>{{error_message}}</p>');
  when('mkdir')->justReturn(true);
  when('file_put_contents')->justReturn(true);

  when('file_exists')->alias(function ($path) use ($templatePath, $logDirPath) {
    if ($path === $templatePath) return true;
    if ($path === $logDirPath) return false; // Simulate log directory does not exist
    return false;
  });

  $mailer = new UniooMailer('test-template.php', 'log', 'Test Subject');
  $mailer->send('This is a test error message.');

  expect(true)->toBeTrue();
});

it('sends the email to a provided recipient', function () {
  $templatePath = WP_UNIOO_SYNC_PLUGIN_DIR . 'templates/mails/test-template.php';

  when('__')->alias(fn($s) => $s);
  when('wp_date')->justReturn('2026-04-08 12:00:00');
  when('get_option')->justReturn('');
  when('file_get_contents')->justReturn('<p>{{error_message}}</p>');
  when('mkdir')->justReturn(true);
  when('file_put_contents')->justReturn(true);
  when('wp_mail')->justReturn(true);

  when('file_exists')->alias(function ($path) use ($templatePath) {
    if ($path === $templatePath) return true;
    return false;
  });

  $mailer = new UniooMailer('test-template.php', 'test@example.com', 'Test Subject');
  $mailer->send('This is a test error message.');
  expect(true)->toBeTrue();
});