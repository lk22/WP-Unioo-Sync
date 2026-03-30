<?php

namespace LeoKnudsen\WpUniooSync\Exceptions;

use Exception;
if ( ! defined('ABSPATH') ) {
  exit();
}

if ( ! class_exists('UniooSyncUserNotCreatedException') ) {
  class UniooSyncUserNotCreatedException extends Exception {
    protected $message = 'Could not create user during sync process.';
    public function __construct($message = null) {
      if ($message) {
        $this->message = $message;
      }
    }
  }
}