<?php

$testAppKey = 'base64:'.base64_encode(hash('sha256', 'cremona-test-application-key', true));

putenv("APP_KEY={$testAppKey}");
$_ENV['APP_KEY'] = $testAppKey;
$_SERVER['APP_KEY'] = $testAppKey;

require __DIR__.'/../vendor/autoload.php';
