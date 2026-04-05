<?php

uses()->beforeEach(function() {
  Brain\Monkey\setUp();
})->afterEach(function() {
  Brain\Monkey\tearDown();
})->in('Unit');