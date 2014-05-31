<?php

// This defines some of our basic setup
require_once '../../libs/lib.php';
require_once ABS_PATH . get_mode() . '.config.php';
require_once ABS_PATH . '/vendor/autoload.php';

$facebook = new Facebook($apiKey, $secret);
$fbID = $facebook->get_loggedin_user();

if (!$fbID) {
    log_error('remove.php: Unknown fb_id', true, true);
}

DB::update('UPDATE users SET active = 0 WHERE fb_id = :fb_id', [':fb_id' => $fbID]);
