<?php
if(! @$_SESSION['cur_session'])
	force_reload('Game session has timed out.');

$_SESSION['cur_session']->stopQuiz();
$_SESSION['cur_session']->cleanupBeforeDestroy();
$_SESSION['cur_session'] = NULL;

?>