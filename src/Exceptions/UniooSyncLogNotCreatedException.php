<?php

namespace LeoKnudsen\WpUniooSync\Exceptions;

use Exception;

if ( ! defined('ABSPATH') ) {
  exit();
}

if ( ! class_exists('UniooSyncLogNotCreatedException') ) {
  class UniooSyncLogNotCreatedException extends Exception {}
}