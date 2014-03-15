<?php

require_once('../libs/lib.php');
require_once ABS_PATH . get_mode() .'.config.php';

global $logged_in;
$logged_in = init_app();

if (! isset($_SESSION) || @count($_SESSION) == 0)
	die('please do not call this page directly: use the main interface');

if(! @$_REQUEST['set_id'])
	die("No set id");
	
$set = new LearningSet($_REQUEST['set_id']);
if(! $set->is_valid())
	die('invalid set');
if(!$set->is_owner() && !$set->is_public())
    die('This set is not public');
?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<title>KanjiBox Set Export</title>
<meta http-equiv="Content-type" content="text/html; charset=utf-8" />
</head>
<body><pre>
<?php
echo $set->get_export();
?>
</pre>
</body>
</html>