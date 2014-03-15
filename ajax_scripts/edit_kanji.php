<div style="border: 1px solid black; background-color:yellow;padding:3px;margin-bottom:4px;">
<?php
if(!@$_SESSION['user'])
	die("logged-in only");



if(isset($params['id'])) {
	$kanji_id = $params['id'];
	
	if($_SESSION['user']->is_editor()) {
	
		if(isset($_REQUEST['njlpt']))
			echo post_db_correction('kanjis', 'id', $kanji_id, 'njlpt', (int) $_REQUEST['njlpt'], true);
	
		if(isset($_REQUEST['grade']))
			echo post_db_correction('kanjis', 'id', $kanji_id, 'grade', (int) $_REQUEST['grade'], true);

		// if(isset($_REQUEST['delete_english']))

	}
	
	
}
else {
	echo "Unknown kanji id";
}
?>
</div>