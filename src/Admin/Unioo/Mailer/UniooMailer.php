<?php

namespace LeoKnudsen\WpUniooSync\Admin\Unioo\Mailer;

if ( ! defined('ABSPATH') ) {
  exit();
}

use LeoKnudsen\WpUniooSync\Exceptions\UniooSyncMailTemplateNotFoundException;

if ( ! class_exists('UniooMailer') ) {
  class UniooMailer {

    public function __construct(
      public string $template = '',
      private string $receipient = '',
      private string $subject = ''
    ) {
      $this->template = ABSPATH . "/wp-content/plugins/wp-unioo-sync/templates/mails/{$template}";

      if ( empty($this->template) ) {
        throw new UniooSyncMailTemplateNotFoundException("Email template not specified. Please specify an email template parameter when instantiating the UniooMailer class.");
      }
    }

    private function checkTemplateExists(): void {
      if ( ! file_exists($this->template) ) {
        $this->checkLogFile();
        $this->send("Email template not found: {$this->template}");
      }
    }

    private function checkLogFile(): void {
      $log_dir = plugin_dir_path(__FILE__) . 'logs/';
      if ( ! file_exists($log_dir) ) {
        mkdir($log_dir, 0755, true);
      }
    }

    public function send(string $msg): void {

      if ( ! $this->checkTemplateExists() ) {
        throw new UniooSyncMailTemplateNotFoundException("Email template not found: {$this->template}");
      }

      if ( empty(get_option('wp_unioo_sync_default_email_address_on_sync', '')) && $this->receipient === '' ) {
        error_log('No receipient specified for UniooMailer. Please set a default email address in the plugin settings or specify a receipient when instantiating the UniooMailer class.');
        return;
      }

      $subject = __($this->subject, WP_UNIOO_SYNC_TEXTDOMAIN);
      $message = sprintf(
        __('The Unioo sync process encountered an error: %s. Please check the sync logs for more details.', WP_UNIOO_SYNC_TEXTDOMAIN),
        $msg
      );

      $message = file_get_contents($this->template);
      $message = str_replace('{{error_message}}', $msg, $message);

      // if the receipient is set to "log", we will log the mail message instead of sending it. This can be useful for debugging purposes and to prevent spamming the inbox with test emails during development.
      if ( $this->receipient === "log" ) {
        $this->checkLogFile();

        $log_file = plugin_dir_path(__FILE__) . 'logs/unioo_mailer.log';
        $log_message = sprintf("[%s] Subject: %s | Message: %s\n", date('Y-m-d H:i:s'), $subject, $message);
        file_put_contents($log_file, $log_message, FILE_APPEND);
        return;
      } else if ( ! empty($this->receipient) && str_contains($this->receipient, '@') ) {
        wp_mail($this->receipient, $subject, $message);
      }
    }
  }
}