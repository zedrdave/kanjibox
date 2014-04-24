<?php

if(!@$_SESSION['user'] || !$_SESSION['user']->is_elite())
	die('need to be at least elite');
mb_internal_encoding('UTF-8');
	
$apply = $_SESSION['user']->isEditor();

if(isset($_REQUEST['delete_id']) ) {
	if(!$_SESSION['user']->isAdministrator())
		die("Not allowed to delete entries...");
	if(@$_REQUEST['delete_jmdict_id']) {
		$sent_id = (int) $_REQUEST['delete_id'];
		$jmdict_id = (int) $_REQUEST['jmdict_id'];
		$query = "DELETE FROM example_answers WHERE example_id = $sent_id AND jmdict_id = $jmdict_id LIMIT 1";
	
		mysql_query("INSERT INTO data_update_queries SET user_id = " . $_SESSION['user']->getID() . ", query_str = '" . mysql_real_escape_string($query) . "', applied = 1");
	
		mysql_query($query) or die(mysql_error());
		echo "<div class=\"message\">Deleted answer: ($sent_id, $jmdict_id)</div>";
	}
	else {
		$sent_id = (int) $_REQUEST['delete_id'];
		$query = "DELETE FROM examples WHERE example_id = $sent_id LIMIT 1";
		mysql_query("INSERT INTO data_update_queries SET user_id = " . $_SESSION['user']->getID() . ", query_str = '" . mysql_real_escape_string($query) . "', applied = 1");
	
		mysql_query($query) or die(mysql_error());
		echo "<div class=\"message\">Deleted sentence ID: $sent_id</div>";
		$query = "DELETE FROM example_parts WHERE example_id = $sent_id";	
		mysql_query("INSERT INTO data_update_queries SET user_id = " . $_SESSION['user']->getID() . ", query_str = '" . mysql_real_escape_string($query) . "', applied = 1");
		mysql_query($query) or die(mysql_error());		
		echo "<div class=\"message\">Deleted " . mysql_affected_rows() . " sentence parts.</div>";
		return;
	}
}
if(isset($_REQUEST['create'])) {
	if(empty($_REQUEST['example_str'])) {
		echo '<div class="message">Error: please provide at least Japanese text.</div>';
		return;
	}
	elseif(mysql_query("INSERT INTO examples SET example_str = '" . mysql_real_escape_string($_REQUEST['example_str']) . "', english = '" . mysql_real_escape_string($_REQUEST['english']) . '\', status = \'kanjibox\'')) {
		$example_id = mysql_insert_id();
		
		echo '<div class="message">Created new sentence ID: ' . $example_id . '</div>';
	
		$sentence = $_REQUEST['example_str'];
		
		require_once(ABS_PATH . 'libs/mecab_lib.php');
		
		$queries = parse_jp_sentence($sentence, true, true, $example_id);
		
		array_walk($queries, function(&$n) { $n = '(' . implode(',', $n) . ')'; });
		
		$query = "INSERT INTO example_parts (example_id, jmdict_id, part_num, pos_start, pos_end, need_furi, proper_noun) VALUES " . implode(',', $queries);
		// echo $query;
		mysql_query($query) or die(mysql_error());
		echo '<div class="message">Inserted : ' . count($queries) . ' sentence parts <a href="#" onclick="$(\'.debug\').show(); return false;">[show debug]</a></div>';
		
		$query = "UPDATE examples e SET e.njlpt = (SELECT MIN(j.njlpt) FROM example_parts ep LEFT JOIN jmdict j ON j.id = ep.jmdict_id WHERE ep.example_id = $example_id), e.njlpt_r = (SELECT IFNULL(MIN(j.njlpt_r), 5) FROM example_parts ep LEFT JOIN jmdict j ON j.id = ep.jmdict_id  WHERE ep.example_id = $example_id AND need_furi = 1) WHERE e.example_id = $example_id LIMIT 1";
		// echo $query;
		mysql_query($query) or die(mysql_error());
		
		$_REQUEST['id'] = $example_id;
		// $_REQUEST['display_done_editing'] = true;
		$params['edit'] = true;
		$params['show_vocab'] = true;
		require_once('get_sentence.php');
		return;
	}
	else {
		echo '<div class="message">DB Error: ' . mysql_error() . '</div>';
	}
}

if(isset($_REQUEST['id'])) {
	$changes = 0;
	echo '<div class="message">';
	foreach(array('example_str', 'example_var', 'english', 'annotation', 'njlpt', 'njlpt_r', 'status') as $field) {
		if(isset($_REQUEST[$field])) {
			if($field == 'status')
				$ret = post_db_correction('examples', 'example_id', (int) $_REQUEST['id'], $field, $_REQUEST[$field], true, '', '', true);
			else
				$ret = post_db_correction('examples', 'example_id', (int) $_REQUEST['id'], $field, $_REQUEST[$field], $apply);
			if($ret != 'Value unchanged') {
			 	echo $ret . '<br/>';
				$changes++;
			}
		}
	}
	
	if(isset($_REQUEST['pos_end']) && isset($_REQUEST['merge_until_pos_end'])) {
		
		mysql_query("DELETE FROM example_parts WHERE example_id = " . (int) $_REQUEST['id'] . " AND pos_start >= " . (int) $_REQUEST['pos_end'] . " AND pos_start < " . (int) $_REQUEST['merge_until_pos_end'] . " LIMIT 1") or die(mysql_error());
		
		mysql_query("UPDATE example_parts SET pos_end = " . (int) $_REQUEST['merge_until_pos_end'] . " WHERE example_id = " . (int) $_REQUEST['id'] . " AND pos_end = " . (int) $_REQUEST['pos_end']);
	
		echo "Fragments merged";
	}
	if(isset($_REQUEST['pos_start']) && isset($_REQUEST['new_pos_start']) && isset($_REQUEST['new_pos_end'])) {
				
		mysql_query("UPDATE example_parts SET pos_start = " . (int) $_REQUEST['new_pos_start'] . ", pos_end = " . (int) $_REQUEST['new_pos_end'] . " WHERE example_id = " . (int) $_REQUEST['id'] . " AND pos_start = " . (int) $_REQUEST['pos_start']) or die(mysql_error());
		echo "Changed position to: (" . (int) $_REQUEST['new_pos_start'] . ", " . (int) $_REQUEST['new_pos_end'] . ")";
	}
	if(isset($_REQUEST['pos_start']) && isset($_REQUEST['delete_jmdict_id'])) {
				
		mysql_query("DELETE FROM example_parts WHERE example_id = " . (int) $_REQUEST['id'] . " AND pos_start = " . (int) $_REQUEST['pos_start'] . " LIMIT 1") or die(mysql_error());
		echo "Deleted fragment at position: " . (int) $_REQUEST['pos_start'];
	}
	elseif(isset($_REQUEST['pos_start']) && isset($_REQUEST['split_at_pos'])) {
		
		$res = mysql_query("SELECT ep.*, SUBSTRING(e.example_str, ep.pos_start+1, ep.pos_end-ep.pos_start) AS fragment FROM example_parts ep LEFT JOIN examples e ON e.example_id = ep.example_id WHERE ep.example_id = " . (int) $_REQUEST['id'] . " AND ep.pos_start = " . (int) $_REQUEST['pos_start']) or die(mysql_error());
		
		$row = mysql_fetch_object($res);
		if($row) { 
			$word = $row->fragment;
			$need_furi_1 = (int) !(preg_match('/[^\p{Hiragana}\p{Katakana}ー・〜？！。０-９0-9a-zA-Z＝「」]/u', mb_substr($word, 0, $_REQUEST['split_at_pos']), $matches) == 0);
			$need_furi_2 =  (int) !(preg_match('/[^\p{Hiragana}\p{Katakana}ー・〜？！。０-９0-9a-zA-Z＝「」]/u', mb_substr($word, $_REQUEST['split_at_pos']), $matches) == 0);
		
			$query = "UPDATE example_parts SET part_num = part_num+1 WHERE example_id = $row->example_id AND part_num > $row->part_num";
			mysql_query($query) or die(mysql_error());
		
			$query = "UPDATE example_parts SET pos_end = " . (int) ($row->pos_start + $_REQUEST['split_at_pos']) . ", need_furi = $need_furi_1 WHERE example_id = $row->example_id AND pos_start = $row->pos_start";
			mysql_query($query) or die(mysql_error());
		
			$query = "INSERT INTO example_parts SET  example_id = $row->example_id, jmdict_id = $row->jmdict_id, part_num = " . ($row->part_num+1) . ", pos_start = " . (int) ($row->pos_start + $_REQUEST['split_at_pos']) . ", pos_end = $row->pos_end, need_furi = $need_furi_2";
			mysql_query($query) or die(mysql_error());
		
			echo "Fragment split.";
			// mysql_query($query) or die(mysql_error());
		}
		else {
			echo "Can't find this fragment.";
		}		
	}
	elseif(isset($_REQUEST['pos_start']) && isset($_REQUEST['new_jmdict_id'])) {

		$ret = post_db_correction('example_parts', 'example_id', (int) $_REQUEST['id'], 'jmdict_id', (int) $_REQUEST['new_jmdict_id'], $apply, 'pos_start', (int) $_REQUEST['pos_start']);
		// echo $ret . ' - ';
		if($ret != 'Value unchanged') {
		 	echo $ret . '<br/>';
			$changes++;
		}
		/*
	   if($changes) {
	         $res = mysql_query('SELECT * FROM examples WHERE example_id = ' . (int) $_REQUEST['id']) or die(mysql_error());
	        	$sentence = mysql_fetch_object($res);

	         $res = mysql_query('SELECT j.* FROM jmdict j WHERE j.id = ' . (int) $_REQUEST['new_jmdict_id']) or die(mysql_error());
	        	$word = mysql_fetch_object($res);
			
				$part_reading = mb_substr($sentence->example_str, (int) $_REQUEST['pos_start'], mb_strlen($word->reading));
				$part_word = mb_substr($sentence->example_str, (int) $_REQUEST['pos_start'], mb_strlen($word->word));

	 			if($word->reading != $part_reading && $word->word != $part_word) {
		    		$res = mysql_query("SELECT ep.*, j.* FROM example_parts ep LEFT JOIN jmdict j ON j.id = ep.jmdict_id LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id WHERE example_id = " . (int) $_REQUEST['id'] . " AND ep.pos_start = " . (int) $_REQUEST['pos_start']) or die(mysql_error());
		         $pos = mysql_fetch_object($res);
            
		         $new_str = mb_substr($sentence->example_str, 0, $pos->pos_start) . ($word->usually_kana ? $word->reading : $word->word) . mb_substr($sentence->example_str, $pos->pos_end);
					$ret .= post_db_correction('examples', 'example_id', (int) $_REQUEST['id'], 'example_str', $new_str, $apply);
				
					$diff = mb_strlen($word->usually_kana ? $word->reading : $word->word) - ($pos->pos_end - $pos->pos_start);

					if($diff != 0) {
						$ret .= post_db_correction('example_parts', 'example_id', (int) $_REQUEST['id'], 'pos_end', $pos->pos_end + $diff, $apply, 'pos_start', $pos->pos_start);
						
			    		$res = mysql_query("SELECT ep.* FROM example_parts ep WHERE example_id = " . (int) $_REQUEST['id'] . " AND ep.pos_start > " . (int) $_REQUEST['pos_start'] . " ORDER BY ep.pos_start DESC") or die(mysql_error());
						while($next_pos = mysql_fetch_object($res)) {
							// if($next_pos->) {
							// 	
							// }
							// else {
								$ret .= post_db_correction('example_parts', 'example_id', (int) $_REQUEST['id'], 'pos_end', $next_pos->pos_end + $diff, $apply, 'pos_start', $next_pos->pos_start);
								$ret .= post_db_correction('example_parts', 'example_id', (int) $_REQUEST['id'], 'pos_start', $next_pos->pos_start + $diff, $apply, 'pos_start', $next_pos->pos_start);
							// }
						}
					}
				}
	   }
		*/
		if($changes == 0)
			echo 'No changes';
	
	}
	
	echo '</div>';
	
	if(!isset($_REQUEST['short_reply'])) {
		// $_REQUEST['display_done_editing'] = true;
		$params['edit'] = true;
		$params['show_vocab'] = true;
	
		require_once('get_sentence.php');
	}
}
else
	echo "No id provided";


?>