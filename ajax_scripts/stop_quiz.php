<?php
if(! @$_SESSION['cur_session'])
	force_reload('Game session has timed out.');

$_SESSION['cur_session']->stop_quiz();
$_SESSION['cur_session']->cleanup_before_destroy();
$_SESSION['cur_session'] = NULL;

?>