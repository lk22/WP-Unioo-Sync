<?php
namespace LeoKnudsen\WpUniooSync\Exceptions;

if ( ! defined('ABSPATH') ) {
  exit();
}

use Exception;

if ( ! class_exists('UniooSyncMailTemplateNotFoundException') ) {
  class UniooSyncMailTemplateNotFoundException extends Exception
  {
    protected $message = 'The specified email template was not found.';
  }
}