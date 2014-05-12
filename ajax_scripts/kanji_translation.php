<?php

if(! @$_SESSION['user'])
	log_error('You need to be logged to access this function.', false, true);

if(@$params['type'] != 'general' && !@$_SESSION['cur_session'] && !$_SESSION['user']->isEditor())
	log_error('You need to be using Drill or Quiz mode to send this type of feedback.', false, true);

if(isset($_REQUEST['update'])) {
	if(! @$_REQUEST['kanji_id'])
		die('no kanji id');
		
	$kanji_id = (int) $_REQUEST['kanji_id'];
	$new_gloss = $_REQUEST['new_gloss'];
	
	if(!isset($_REQUEST['lang']) || !isset(Vocab::$langStrings[$_REQUEST['lang']]))
		return;
	
	$lang = $_REQUEST['lang'];
	
	
	$ret = post_db_correction('kanjis_ext', 'kanji_id', $kanji_id, 'meaning_' . Vocab::$langStrings[$lang], $new_gloss, true);
	
	if(@$_SESSION['cur_session'] && $q = $_SESSION['cur_session']->getQuestion(@$_REQUEST['sid']))
		$q->updateMeaningStr($new_gloss);
	
	echo '<div>';
	if($ret != 'Value unchanged')
		echo "Updating " . ucwords(Vocab::$langStrings[$lang]) . " translation to: <span id=\"newtranslation\">$new_gloss" . (@$_REQUEST['traditional'] ? ' (旧)' : '') . "</span><br/>";
	echo $ret;
	echo '</div>';
	
	return;
}

if(@$params['kanji_id']) {
	$kanji_id = (int) $params['kanji_id'];
			
	$query = 'SELECT * FROM `kanjis` k LEFT JOIN kanjis_ext kx ON k.id = kx.kanji_id WHERE id = ' . $kanji_id;
	$res = mysql_query($query) or die(mysql_error());
	$row = mysql_fetch_object($res);
	
	if($row) {
		$pref_lang = $_SESSION['user']->get_pref('lang', 'kanji_lang');
		
		echo  '<p style="font-style:italic;"><strong>Important:</strong> Please <a href="http://kanjibox.net/kb/page/international/" target="_new" style="font-weight:bold;color:#006;">read this brief message</a> if this is your first time submitting a translation.</p>';
		
		echo '<form id="translation_form">';
		
		if(!@$_SESSION['cur_session'] || $_SESSION['cur_session']->isDrill())	
			echo "<p style=\"font-size:120%;\"><strong>$row->kanji</strong> 【" . $row->prons . "】 [N$row->njlpt]</p>";
				
		// echo "<p><img src=\"" . SERVER_URL . "/img/flags/en.png\" alt=\"uk-flag\" style=\"vertical-align:bottom; margin:0 3px 0 0;\" /> <i>$row->gloss_english</i></p>";
		
		foreach(Vocab::$langStrings as $lang => $full_lang) {
			$meaning_col = "meaning_$full_lang";
			if($pref_lang == $lang) {
				if(substr($row->$meaning_col, 0, 3) == '(~)')
					$row->$meaning_col = substr($row->$meaning_col, 3);

				echo "<p><img src=\"" . SERVER_URL . "/img/flags/$lang.png\" alt=\"$lang-flag\" style=\"vertical-align:bottom; margin:0 3px 0 0;\" /> " . ucwords($full_lang) . ":</p>";
				echo "<p style=\"border: 1px solid black; padding: 2px;\">";
				echo "<input type=\"text\" name=\"new_gloss\" id=\"new_gloss\" value=\"" . htmlentities($row->$meaning_col, ENT_COMPAT, 'UTF-8') . "\" size=\"60\" /><br/>";
				
				echo "<input type=\"hidden\" name=\"traditional\" id=\"traditional\" value=\"$row->traditional\" /><input type=\"hidden\" name=\"lang\" id=\"lang\" value=\"$lang\" /><input type=\"hidden\" name=\"kanji_id\" id=\"kanji_id\" value=\"$kanji_id\" /></p>";
			}
			else if($row->$meaning_col) {
				echo "<p><img src=\"" . SERVER_URL . "/img/flags/$lang.png\" alt=\"$lang-flag\" style=\"vertical-align:bottom; margin:0 3px 0 0;\" /> " . $row->$meaning_col . "</p>";
			}
		}
		echo '<hr/><p style="font-size:12px;font-style:italic;">By submitting your translations you agree to license them to the public under the terms of the <a href="http://creativecommons.org/licenses/by-sa/3.0/">CC BY SA 3.0</a> license (Attribution-Share Alike).</p>';
		echo '</form>';
	}
}
?>
