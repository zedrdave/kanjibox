<?php
if(! @$_SESSION['user']->is_logged_in())
{
	log_error('is_logged_in() == false, in stats.php', true);

	log_error('You need to be logged to access this function.', false, true);
}

require_once(ABS_PATH . 'pages/stats.php');

?>