<?php

namespace LeoKnudsen\WpUniooSync\Admin\Unioo\Mailer;
use LeoKnudsen\WpUniooSync\Exceptions\UniooSyncMailTemplateNotFoundException;
use function plugin_dir_path;

if ( ! defined('ABSPATH') ) {
  exit();
}

if ( ! class_exists('UniooMailer') ) {
  class UniooMailer {

    public function __construct(
      public string $template = '',
      private string $recipient = '',
      private string $subject = ''
    ) {
      $template = trim($template);

      $plugin_root_path = defined('WP_UNIOO_SYNC_PLUGIN_DIR')
        ? WP_UNIOO_SYNC_PLUGIN_DIR
        : plugin_dir_path(dirname(__DIR__, 4) . '/wp-unioo-sync.php');
      $resolved_template = $plugin_root_path . 'templates/mails/' . ltrim($template, '/\\');

      if ( ! $this->pathExists($resolved_template) ) {
        throw new UniooSyncMailTemplateNotFoundException("Email template not found: {$resolved_template}");
      }

      $this->template = $resolved_template;
    }

    private function checkTemplateExists(): bool {
      if ( ! $this->pathExists($this->template) ) {
        $this->checkLogFile();
        return false;
      }

      return true;
    }

    private function checkLogFile(): void {
      $log_dir = $this->getLogDirPath();
      if ( ! $this->pathExists($log_dir) ) {
        $this->makeDirectory($log_dir, 0755, true);
      }
    }

    protected function pathExists(string $path): bool {
      return file_exists($path);
    }

    protected function makeDirectory(string $path, int $permissions, bool $recursive): bool {
      return mkdir($path, $permissions, $recursive);
    }

    protected function readTemplateFile(string $path): string|false {
      return file_get_contents($path);
    }

    protected function writeLogFile(string $path, string $content, int $flags): int|false {
      return file_put_contents($path, $content, $flags);
    }

    protected function getLogDirPath(): string {
      return dirname(__DIR__, 4) . '/logs/';
    }

    protected function getLogFilePath(): string {
      return ABSPATH . 'wp-content/plugins/wp-unioo-sync/logs/unioo_mailer.log';
    }

    public function send(string $msg): void {
      if ( ! $this->checkTemplateExists() ) {
        error_log("UniooMailer: Email template not found: {$this->template}");
        return;
      }

      if ( empty(get_option('wp_unioo_sync_default_email_address_on_sync', '')) && $this->recipient === '' ) {
        error_log('No recipient specified for UniooMailer. Please set a default email address in the plugin settings or specify a recipient when instantiating the UniooMailer class.');
        return;
      }

      $subject = __($this->subject, WP_UNIOO_SYNC_TEXTDOMAIN);

      $template_content = $this->readTemplateFile($this->template);
      if ( $template_content === false ) {
        error_log("UniooMailer: Failed to read template file: {$this->template}");
        return;
      }

      $message = str_replace('{{message}}', $msg, $template_content);

      // if the default email address is set to 'log' or is empty, log the message instead of sending an email
      if (
        get_option('wp_unioo_sync_default_email_address_on_sync') === '' ||
        get_option('wp_unioo_sync_default_email_address_on_sync') === 'log'
      ) {
        $this->checkLogFile();

        $log_file = $this->getLogFilePath();
        $log_message = sprintf("[%s] Subject: %s | Message: %s\n", wp_date('Y-m-d H:i:s'), $subject, $message);
        $this->writeLogFile($log_file, $log_message, FILE_APPEND);

        return;
      } else if ( ! empty($this->recipient) ) {
        if ( ! str_contains($this->recipient, '@') ) {
          error_log("UniooMailer: Invalid recipient email address: {$this->recipient}");
          return;
        }

        $sent = wp_mail($this->recipient, $subject, $message);

        if ( ! $sent ) {
          error_log("UniooMailer: Failed to send email to {$this->recipient}");
        }
      }
    }
  }
}