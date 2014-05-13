<?php
	if(! @$_SESSION['user'])
		log_error('You need to be logged to access this function.', false, true);
	
		
	if(mysql_query('INSERT INTO user_feedbacks SET user_id = ' . $_SESSION['user']->getID() . ', param_1 = ' . ((int) @$_REQUEST['param_1']) . ', param_2 = ' . ((int) @$_REQUEST['param_2']) . ', param_3 = \'' . DB::getConnection()->quote(@$_REQUEST['param_3']) . '\', comment = \'' . DB::getConnection()->quote(@$_REQUEST['comment']) . '\', type = \'' . DB::getConnection()->quote($_REQUEST['type']) . '\''))
		echo 'Feedback recorded... Thanks!';
	else
		echo 'Database Error: could not record feedback.';
?>
