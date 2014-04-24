<?php

require_once('libs/lib.php');
require_once get_mode() .'.config.php';

if(! init_app() || !@$_SESSION['user'])
 	die();

echo "UniqueID=" .  $_SESSION['user']->getID() . "\nName=" . ($_SESSION['user']->get_first_name() ? $_SESSION['user']->get_first_name() : ($_SESSION['user']->get_email() ? strstr($_SESSION['user']->get_email(), '@', true) : 'user_' . $_SESSION['user']->getID())) . "\nEmail=" . $_SESSION['user']->get_email();

?>