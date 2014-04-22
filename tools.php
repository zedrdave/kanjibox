<?php
require_once('libs/lib.php');
require_once get_mode() .'.config.php';
if(! init_app())
	die("<div class=\"error_msg\">Non-logged in users cannot access this page directly. You need to be <a href=\"" .  get_page_url('home') . "\">logged in</a> first.</div>");

?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en"  xmlns:fb="http://www.facebook.com/2008/fbml">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>KB Editor Tools<?php if(@$_REQUEST['script']) echo ": " . $_REQUEST['script']; ?></title>
<script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/1.8.3/jquery.min.js"></script>
<script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jqueryui/1.10.3/jquery-ui.min.js"></script>
<link rel="stylesheet" href="//ajax.googleapis.com/ajax/libs/jqueryui/1.10.2/themes/flick/jquery-ui.css" type="text/css" />
<?php
include_jquery('corner');
include_jquery('form');

include_css('general.css');	
include_css('stats.css');	
include_css('messages.css');	

include_css('tools.css');	

include_js('general.js');
include_js('ajax.js');
?>
</head>
<body>
		<div style="background-color:white; text-align: left; width: 800px; margin: auto; padding: 20px;">
		<?php
		try
		{
		ini_set('display_errors', true);

		if(!@$_SESSION['user'] || !$_SESSION['user']->isEditor())
			die("editors only");


		define('EDITOR_MODE', true);

		if(empty($_REQUEST['script']))
		{
			echo "<ul>";
		    if($handle = opendir('tools'))
		    {
		    	while(($file = readdir($handle)) !== false)
			        if(substr($file, -4) == '.php' && filetype('tools/' .$file) !== 'dir')
		    		      echo '<li><a href="' . APP_URL . 'tools/' . substr($file, 0, -4) .'/">'. substr($file, 0, -4) .'</a></li>'."\n";
		      	closedir($handle);
		    }
			echo "</ul>";
		}
		else
		{
			echo 'loading: ' .  $_REQUEST['script'] . "\n\n<br/><br/>";
			require('tools/' . str_replace(array('.', '/'), '', $_REQUEST['script']) . '.php');
		}
		}
		catch(Exception $e)
		{
			log_error('Uncaught Exception in ' . __FILE__ . ': ' . $e->getMessage(), true, true);
		}
		?>
		</div>
</body>
</html>