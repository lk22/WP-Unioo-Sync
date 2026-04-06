<?php

use LeoKnudsen\WpUniooSync\Admin\Unioo\Mailer\UniooMailer;
use LeoKnudsen\WpUniooSync\Exceptions\UniooSyncMailTemplateNotFoundException;
use function Brain\Monkey\Functions\when;


it('throws UniooSyncMailTemplateNotFoundException when template parameter is empty', function () {
  // TODO: This test is currently failing because the UniooMailer class is trying to access the file system to check for the existence of the template file, which is not possible in the testing environment. We need to mock the file_exists function to return false when checking for the template file, and true for any other file checks.
});