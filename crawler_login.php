<?php
if(@$_REQUEST['crawler'] != 'adsense')
	die();
	
session_start();

$_SESSION['crawler'] = true;

?>Logged-in as crawler