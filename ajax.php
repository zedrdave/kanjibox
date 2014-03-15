<?php

require_once('libs/lib.php');
require_once get_mode() .'.config.php';

try
{
	global $ajax;
	$ajax = true;
	if(! init_app(true))
		die("<div class=\"error_msg\">Non-logged in users cannot access this function directly. You need to be <a href=\"" .  get_page_url('home') . "\">logged in</a> first.</div>");
	
	if (extension_loaded('newrelic')) {
	   newrelic_name_transaction('/kb/ajax/'. $_REQUEST['script']);
	}
	
	
	require('ajax_scripts/' . str_replace(array('.', '/'), '', $_REQUEST['script']) . '.php');

}
catch(Exception $e)
{
	log_error('Uncaught Exception in ' . __FILE__ . ': ' . $e->getMessage(), true, true);
}
?>