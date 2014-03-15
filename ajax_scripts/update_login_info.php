<div><?php
if(!@$_SESSION['user'])
	die('need to be logged in');

if(@$_REQUEST['set_login']) {
	echo ($_SESSION['user']->update_login($_REQUEST['set_login']));
}
if(@$_REQUEST['set_password']) {
	echo ($_SESSION['user']->update_password($_REQUEST['set_password']));
}

if(@$_REQUEST['set_first_name'] || @$_REQUEST['set_last_name']) {
	echo ($_SESSION['user']->update_name(@$_REQUEST['set_first_name'], @$_REQUEST['set_last_name']));
}

?></div>