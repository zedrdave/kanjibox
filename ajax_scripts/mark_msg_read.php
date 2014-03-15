<?php
if(! @$_SESSION['user'])
{
	log_error('You need to be logged-in to access this function.', false, true);
}

$query = 'UPDATE `messages` SET msg_read = 1 WHERE user_id_to = ' . (int) $_SESSION['user']->get_id() . " AND message_id = " . (int) @$params['id'];
$res = mysql_query_debug($query) or log_db_error($query, '', false, true);

echo 'Marked as read!'
?>