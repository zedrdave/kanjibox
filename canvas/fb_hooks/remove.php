<?php

include_once '../../api/facebook.php';

// this defines some of our basic setup
require_once '../../libs/lib.php';
require_once ABS_PATH . get_mode() .'.config.php';

$facebook = new Facebook($api_key, $secret);
$fb_id = $facebook->get_loggedin_user();

if(!$fb_id)
	log_error('remove.php: Unknown fb_id', true, true);
	
get_db_conn();

mysql_query('UPDATE users SET active = 0 WHERE fb_id = ' . (int) $fb_id);

?>