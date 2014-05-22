<?php
// this defines some of our basic setup
require_once '../../libs/lib.php';
require_once ABS_PATH . get_mode() . '.config.php';
require_once ABS_PATH . '/vendor/autoload.php';

$facebook = new Facebook($api_key, $secret);
$fb_id = $facebook->get_loggedin_user();

if (!$fb_id) {
    log_error('remove.php: Unknown fb_id', true, true);
}

try {
    $stmt = DB::getConnection()->prepare('UPDATE users SET active = 0 WHERE fb_id = :fb_id');
    $stmt->bindValue(':fb_id', $fb_id, PDO::PARAM_INT);
    $stmt->execute();
    $stmt = null;
} catch (PDOException $e) {
    log_db_error($query, $e->getMessage());
}