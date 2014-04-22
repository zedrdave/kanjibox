<div>
<?php
if(!@$_SESSION['user'] || !$_SESSION['user']->isEditor())
	die("editors only");

if(@$params['action'] == 'save' && isset($_REQUEST['board_content'])) {
	$f = fopen(dirname(__FILE__) . '/../tools/notes.txt', 'w');
	fwrite($f, $_REQUEST['board_content']);
	fclose($f);
	echo 'Saved board edit...';
}
else
	echo 'No action taken...';
?>
</div>