<?php

// Get these from http://developers.facebook.com
$apiKey = '5132078849';
$secret = '7a566465283321ab2df0b2187090e1a1';

// The IP address of your database
$db_ip = 'localhost';

$db_user = 'kanjibox';
$db_pass = 'GA28zJcRpUxjTvHq';

$db_name = 'kanjibox';

define('APP_URL_HTTPS', 'https://kanjibox.net/kb/');
define('SERVER_URL_HTTPS', 'https://kanjibox.net/kb/');


if ($_SERVER['HTTPS']) {
    define('APP_URL', APP_URL_HTTPS);
    //$app_url = APP_URL; This variable is never used (nowhere in the app)
    define('SERVER_URL', SERVER_URL_HTTPS);
    $server_url = SERVER_URL;
} else {
    define('APP_URL', 'http://kanjibox.net/kb/');
    //$app_url = APP_URL; This variable is never used (nowhere in the app)
    define('SERVER_URL', 'http://kanjibox.net/kb/');
    $server_url = SERVER_URL;
}

error_reporting(E_ALL);
ini_set('display_errors', false);
ini_set('log_errors', true);
ini_set('error_log', '/srv/www/kanjibox.net/logs/php-kanjibox.log');

define('KB_LOG_PREFIX', '/srv/www/kanjibox.net/logs/kanjibox_');

global $no_file_log;
$no_file_log = false;

define('RANK_TEMPLATE_ID', '67078818849');

global $no_log;
$no_log = true;
define('DEBUG', true);

$hard_hat_zone = false;
$hard_hat_db_down = false;
