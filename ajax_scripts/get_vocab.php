<?php
if(! @$_SESSION['user'])
	log_error('You need to be logged to access this function.', false, true);

if(!isset($params['jmdict_id']) && !@$_SESSION['cur_session'])
	log_error('You need to be using Drill or Quiz mode to access this feature.', false, true);

$jmdict_id = (int) $params['jmdict_id'];

$query = 'SELECT j.word, j.reading, j.usually_kana, j.katakana, j.njlpt, j.njlpt_r, jx.gloss_english AS fullgloss FROM jmdict j LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id WHERE j.id = ' . $jmdict_id;

$res = mysql_query_debug($query) or log_db_error($query);
$row = mysql_fetch_object($res);
if(! $row)
	echo 'Word not found: ' . $jmdict_id;
else {
	$userID = (int) $_SESSION['user']->getID();
	if(@$_REQUEST['learn_reading']) {
		mysql_query("INSERT IGNORE INTO reading_learning (user_id, jmdict_id, date_first) VALUES ($userID, $jmdict_id, NOW())") or die(mysql_error());
		mysql_query("UPDATE reading_learning SET total = total+1, curve = LEAST(2000, tan(atan(curve/1000-1)+0.15)*1000+1000) where `user_id` = $userID AND jmdict_id = $jmdict_id") or die(mysql_error());
	}
	if(@$_REQUEST['learn_vocab']) {
		mysql_query("INSERT IGNORE INTO jmdict_learning (user_id, jmdict_id, date_first) VALUES ($userID, $jmdict_id, NOW())") or die(mysql_error());
		mysql_query("UPDATE jmdict_learning SET total = total+1, curve = LEAST(2000, tan(atan(curve/1000-1)+0.15)*1000+1000) where `user_id` = $userID AND jmdict_id = $jmdict_id") or die(mysql_error());
	}
	
	echo (@$_REQUEST['word'] ? $_REQUEST['word'] : ($row->usually_kana ? $row->reading : $row->word)) . '<span class="definition">' .($row->usually_kana ? $row->reading : $row->word . ($row->katakana || $row->word == $row->reading ? '' : ' 「' . $row->reading . '」')) . '<br/>' . $row->fullgloss . '<br/><span class="level">JLPT: N' . $row->njlpt . ' (Reading: N' .  $row->njlpt_r . ')</span></span>';
}
?>