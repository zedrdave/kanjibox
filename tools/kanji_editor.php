<?php

if(!@$_SESSION['user'] || !$_SESSION['user']->isEditor())
	die("editors only");


if(isset($_REQUEST['submit']) || @$_REQUEST['id']) {
	$query = "SELECT k.*, kx.* FROM kanjis k LEFT JOIN kanjis_ext kx ON kx.kanji_id = k.id WHERE 1 ";
	if(!empty($_REQUEST['id'])) {
		$query .= 'AND k.id IN (0';
		$ids = explode(',', $_REQUEST['id']);
		foreach($ids as $id)
			$query .= ',' . (int) $id;
		
		$query .= ') ';
	}
	if(!empty($_REQUEST['prons']))
		$query .= "AND prons LIKE '%" . mysql_real_escape_string($_REQUEST['prons']) . "%' ";
	
	foreach(array('kanji', 'njlpt', 'grade', 'strokes') as $field) {
		if(!empty($_REQUEST[$field]))
			$query .= "AND $field = '" . mysql_real_escape_string($_REQUEST[$field]) . "' ";
	}
	
	$pref_lang_kanji = Kanji::$langStrings[$_SESSION['user']->get_pref('lang', 'kanji_lang')];
	
	if(@$_REQUEST['translator'])
		$query .= ' AND (kx.meaning_' . $pref_lang_kanji . ' IS NULL OR kx.meaning_' . $pref_lang_kanji . " = '' OR kx.meaning_" . $pref_lang_kanji . " LIKE '(~)%')";
	
	$query .= " GROUP BY k.id ORDER BY k.id, k.njlpt, kx.prons";
}
?>
<div id="ajax-result" class="message" style="display:none;"></div>
<form>
	Id(s): <input name="id" size="30" value="<?php echo @$_REQUEST['id'] ?>" /> <br/>
	Kanji: <input name="kanji" size="10" value="<?php echo @$_REQUEST['kanji'] ?>" /> -	Reading: <input name="prons" size="10" value="<?php echo @$_REQUEST['prons'] ?>" /><br/>
	JLPT: N<input name="njlpt" size="1" value="<?php echo @$_REQUEST['njlpt'] ?>" /> - Grade: <input name="grade" size="1" value="<?php echo @$_REQUEST['grade'] ?>" /> - Strokes: <input name="strokes" size="2" value="<?php echo @$_REQUEST['strokes'] ?>" /><br/>
	<input type="checkbox" name="translator" value="1" <?php echo (@$_REQUEST['translator'] ? 'checked' : '') ?> /> Translator mode<br/>
	<input type="submit" name="submit" value="Find" />
</form>
<?php
	if(@$query) {
		$res = mysql_query($query) or die(mysql_error());
		if(mysql_num_rows($res) == 0)
			echo '<p><em>No entries matching these criteria</em></p>';
		else
			echo '<p><strong>' . mysql_num_rows($res) . ' entries matching these criteria</strong></p>';
		
		while($kanji = mysql_fetch_object($res)) {
			echo "<div class=\"word-block\">$kanji->id<br/><span class=\"kanji\" style=\"font-size:150%;font-weight:bold;\">$kanji->kanji</span> 【" . $kanji->prons . "】 <a href=\"#\" onclick=\"$(this).hide();$('#sels_$kanji->id').show();return false;\" id=\"jltp_$kanji->id\">(N$kanji->njlpt, Grade: $kanji->grade)</a><span id=\"sels_$kanji->id\" style=\"display:none;\">";
			echo get_jlpt_menu('njltp_sel_' . $kanji->id, $kanji->njlpt, "update_kanji_jlpt($kanji->id,this.value);");
			echo "<select id=\"grade_sel_$kanji->id\" onchange=\"update_kanji_grade($kanji->id,this.value); return false;\">";
			for($i = 9; $i >= 0; $i--)
				echo "<option value=\"$i\""  . ($kanji->grade == $i ? ' selected' : '') . ">$i</option>";
			echo "</select></span><br/>";
			
			foreach(Kanji::$langStrings as $lang => $full_lang) {
				if(@$_REQUEST['translator'] && $full_lang != 'english' && $full_lang != $pref_lang_kanji)
					continue;
				
				
				$meaning_col = "meaning_$full_lang";
				echo "<p style=\"padding:0; margin:2px;\"><a href=\"#\" onclick=\"$('#meaning_$kanji->id" . "_$lang" . "_edit').toggle(); $('#meaning_$kanji->id" . "_$lang').toggle(); return false;\"><img src=\"" . SERVER_URL . "/img/flags/$lang.png\" alt=\"$lang-flag\" style=\"vertical-align:bottom; margin:0 3px 0 0;\" /> " . ucwords($full_lang) . ": <span id=\"meaning_$kanji->id" . "_$lang\" style=\"font-style:italic;" . ($full_lang == $pref_lang_kanji ? 'display:none;' : '') . "\">" . $kanji->$meaning_col . "</span></a>";
				echo "<input type=\"text\" name=\"new_gloss\" id=\"meaning_$kanji->id" . "_$lang" . "_edit\" value=\"" . str_replace('"', '\"', $kanji->$meaning_col) . "\" style=\"width:600px;" . 				($full_lang == $pref_lang_kanji ? '' : 'display:none;') . "\" onchange=\"update_kanji_lang($kanji->id, '$lang', this.value);\" /><br/>";

				echo "<input type=\"hidden\" name=\"traditional\" id=\"traditional\" value=\"$kanji->traditional\" /><input type=\"hidden\" name=\"lang\" id=\"lang\" value=\"$lang\" /><input type=\"hidden\" name=\"kanji_id\" id=\"kanji_id\" value=\"$kanji->id\" /></p>";
					// echo "<p><img src=\"" . SERVER_URL . "/img/flags/$lang.png\" alt=\"$lang-flag\" style=\"vertical-align:bottom; margin:0 3px 0 0;\" /> " . ($kanji->$meaning_col ? $kanji->$meaning_col : '<i>&lt;none></i>') . "</p>";
			}
			echo '</div>';
		}
	}

?>
<script type="text/javascript">

	function update_kanji_jlpt(id, jlpt)  
	{
		$.get('<?php echo SERVER_URL ?>ajax/edit_kanji/id/' + id + '/?njlpt=' + jlpt, function (data) {
			$('#njltp_sel_' + id).css('border', '2px solid green')
			$('#ajax-result').show().html(data);
			setTimeout(function() { $('#ajax-result').hide().html('') }, 2000);
		});
	}

	function update_kanji_grade(id, grade)  
	{		
		$.get('<?php echo SERVER_URL ?>ajax/edit_kanji/id/' + id + '/?grade=' + grade, function (data) {
			$('#grade_sel_' + id).css('border', '2px solid green')
			$('#ajax-result').show().html(data);
			setTimeout(function() { $('#ajax-result').hide().html('') }, 2000);
		});
	}

	function update_kanji_lang(id, lang, meaning)  
	{		
		$.get('<?php echo SERVER_URL ?>ajax/kanji_translation/?update=1&kanji_id=' + id + '&lang=' + lang + '&new_gloss=' + encodeURIComponent(meaning), function (data) {
			$('#meaning_' + id + '_' + lang + '_edit').css('border', '2px solid green')
			$('#ajax-result').show().html(data);
			setTimeout(function() { 
				$('#ajax-result').hide().html('');
				$('#meaning_' + id + '_' + lang + '_edit').hide();
				$('#meaning_' + id + '_' + lang).html($('#meaning_' + id + '_' + lang + '_edit').val()).show();
			}, 2000);
		});
	}

</script>