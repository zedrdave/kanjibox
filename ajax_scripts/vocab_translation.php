<?php

if(! @$_SESSION['user'])
	log_error('You need to be logged to access this function.', false, true);

if(@$params['type'] != 'general' && !@$_SESSION['cur_session'])
	log_error('You need to be using Drill or Quiz mode to send this type of feedback.', false, true);

$pretty_numbers = [1 => '①', 2 => '②', 3 => '③', 4 => '④', 5 => '⑤', 6 => '⑥', 7 => '⑦', 8 => '⑧', 9 => '⑨', 10 => '⑩', 11 => '⑪', 12 => '⑫', 13 => '⑬', 14 => '⑭', 15 => '⑮', 16 => '⑯', 17 => '⑰', 18 => '⑱', 19 => '⑲', 20 => '⑳', 21 => '○', 22 => '○', 23 => '○', 24 => '○', 25 => '○', 26 => '○', 27 => '○', 28 => '○',  29 => '○', 30 => '○', 31 => '○',  32 => '○', 33 => '○', 34 => '○',  35 => '○', 36 => '###'];
if(isset($_REQUEST['update'])) {
	if(! @$_REQUEST['jmdict_id'])
		die('no jmdict id');
		
	$jmdict_id = (int) $_REQUEST['jmdict_id'];
	
	if(!isset($_REQUEST['lang']) || !isset(Vocab::$lang_strings[$_REQUEST['lang']]))
		return;
	
	$lang = $_REQUEST['lang'];
	$need_work = (int) @$_REQUEST['need_work'];
	
	$new_gloss = '';
	$i = 1;
	foreach($_REQUEST['senses'] as $sense) {
		if(trim($sense) == '')
			continue;
			
		if($i > 1)
			$new_gloss .= ' ' . $pretty_numbers[$i] . ' ';
		$new_gloss .= $sense;
		
		$i++;
	}
	if($i > 2)
		$new_gloss = $pretty_numbers[1] . ' ' . $new_gloss;
	
	if(empty($new_gloss)) {
		if(!@$_REQUEST['set_null']) {
			echo 'Empty translation.';
		}
		else {
			mysql_query("UPDATE jmdict_ext SET gloss_" . Vocab::$lang_strings[$lang] . " = NULL WHERE jmdict_id = " . $jmdict_id) or die(mysql_error());
			echo "Set " . ucwords(Vocab::$lang_strings[$lang]) . " translation to: Null<br/>";
			
		}
		
		return;
	}
	
	$ret = post_db_correction('jmdict_ext', 'jmdict_id', $jmdict_id, 'gloss_' . Vocab::$lang_strings[$lang], $new_gloss, true, '', '', false, '', $need_work);
	echo '<div>';
	if($ret != 'Value unchanged')
		echo "Updating " . ucwords(Vocab::$lang_strings[$lang]) . " translation to: <span id=\"newtranslation\">$new_gloss</span><br/>";
	echo $ret;
	echo '</div>';
	
	return;
}

if(@$params['jmdict_id']) {
	$jmdict_id = (int) $params['jmdict_id'];
		
	$pretty_num_regex = implode('|', array_slice($pretty_numbers, 0, 21));
	
	// foreach(Vocab::$lang_strings as $lang) {
		// if($lang == 'en')
		// 			$gloss_query = "jx.gloss_english AS `fullgloss`, 0 AS missing_lang";
		// 		elseif(isset(Vocab::$lang_strings[$lang]))
		// 			$gloss_query = "IFNULL(jx.gloss_" . Vocab::$lang_strings[$lang] . ", jx.gloss_english) AS `fullgloss`, (jx.gloss_" . Vocab::$lang_strings[$lang] . " IS NULL) AS missing_lang";
	$query = 'SELECT * FROM `jmdict` j LEFT JOIN jmdict_ext jx ON j.id = jx.jmdict_id WHERE id = ' . $jmdict_id;
	$res = mysql_query($query) or die(mysql_error());
	$row = mysql_fetch_object($res);
	
	if($row) {
		$pref_lang = $_SESSION['user']->get_pref('lang', 'vocab_lang');
		
		echo  '<p style="font-style:italic;"><strong>Important:</strong> Please <a href="http://kanjibox.net/kb/page/international/" target="_new" style="font-weight:bold;color:#006;">read this brief message</a> if this is your first time submitting a translation.</p>';
		
		echo '<form id="translation_form">';
		
		if(!@$_SESSION['cur_session'] || $_SESSION['cur_session']->isDrill())	
			echo "<p style=\"font-size:120%;\"><strong>$row->word</strong> 【" . $row->reading . "】 [N$row->njlpt , Reading-N$row->njlpt_r]</p>";
		
		$english_sense_count = count(preg_split("/$pretty_num_regex/", $row->gloss_english));
		
		// echo "<p><img src=\"" . SERVER_URL . "/img/flags/en.png\" alt=\"uk-flag\" style=\"vertical-align:bottom; margin:0 3px 0 0;\" /> <i>$row->gloss_english</i></p>";
		
		foreach(Vocab::$lang_strings as $lang => $full_lang) {
			$gloss = "gloss_$full_lang";
			if($pref_lang == $lang) {
				echo "<p><img src=\"" . SERVER_URL . "/img/flags/$lang.png\" alt=\"$lang-flag\" style=\"vertical-align:bottom; margin:0 3px 0 0;\" /> " . ucwords($full_lang) . ":</p>";
				echo "<p style=\"border: 1px solid black; padding: 2px;\">";
				
				if(substr($row->$gloss, 0, 3) == '(~)')
					$row->$gloss = substr($row->$gloss, 3);
				$senses = preg_split("/$pretty_num_regex/", $row->$gloss);
				$i = 1;
				foreach($senses as $sense) {
					$sense = trim($sense);
					
					if(empty($sense))
						continue;
					echo " $pretty_numbers[$i] <input type=\"text\" name=\"senses[$i]\" id=\"sense[$i]\" value=\"" . htmlentities($sense, ENT_COMPAT, 'UTF-8') . "\" size=\"56\" /><br/>";
					$i++;
				}
				
				$j = max($i+2, 1+$english_sense_count);
				while($i < $j) {
					echo "+ $pretty_numbers[$i] <input type=\"text\" name=\"senses[$i]\" id=\"sense[$i]\" value=\"\" size=\"56\" /><br/>";
					$i++;
				}
				
				echo "<input type=\"hidden\" name=\"lang\" id=\"lang\" value=\"$lang\" /><input type=\"hidden\" name=\"jmdict_id\" id=\"jmdict_id\" value=\"$jmdict_id\" /></p>";
			}
			else {
				if($row->$gloss)
					echo "<p><img src=\"" . SERVER_URL . "/img/flags/$lang.png\" alt=\"$lang-flag\" style=\"vertical-align:bottom; margin:0 3px 0 0;\" /> " . $row->$gloss . "</p>";
			}
		}
		echo '<hr/><p style="font-size:12px;font-style:italic;">By submitting your translations you agree to license them to the public under the terms of the <a href="http://creativecommons.org/licenses/by-sa/3.0/">CC BY SA 3.0</a> license (Attribution-Share Alike).</p>';
		echo '</form>';
	}
}
?>
