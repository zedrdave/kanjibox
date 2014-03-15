<?php

if(!@$_SESSION['user'] || !$_SESSION['user']->is_editor())
	die("editors only");

$pretty_numbers = array(1 => '①', 2 => '②', 3 => '③', 4 => '④', 5 => '⑤', 6 => '⑥', 7 => '⑦', 8 => '⑧', 9 => '⑨', 10 => '⑩', 11 => '⑪', 12 => '⑫', 13 => '⑬', 14 => '⑭', 15 => '⑮', 16 => '⑯', 17 => '⑰', 18 => '⑱', 19 => '⑲', 20 => '⑳', 21 => '○', 22 => '○', 23 => '○', 24 => '○', 25 => '○', 26 => '○', 27 => '○', 28 => '○',  29 => '○', 30 => '○', 31 => '○',  32 => '○', 33 => '○', 34 => '○',  35 => '○', 36 => '###');

$pretty_num_regex = implode('|', array_slice($pretty_numbers, 0, 21));

$lang_vocab = Vocab::$lang_strings[$_SESSION['user']->get_pref('lang', 'vocab_lang')];

if(isset($_REQUEST['submit'])) {
	$query = "SELECT * FROM jmdict j LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id WHERE 1 ";
	if(!empty($_REQUEST['id'])) {
		$query .= 'AND id IN (0';
		$ids = explode(',', $_REQUEST['id']);
		foreach($ids as $id)
			$query .= ',' . (int) $id;
		
		$query .= ') ';
	}
	foreach(array('word', 'reading', 'njlpt', 'njlpt_r') as $field) {
		if(!empty($_REQUEST[$field])) {
			if(@$_REQUEST['exact_match'])
				$query .= "AND $field = '" . mysql_real_escape_string($_REQUEST[$field]) . "' ";
			else
				$query .= "AND $field LIKE '%" . mysql_real_escape_string($_REQUEST[$field]) . "%' ";
		}
	}
	
	if(@$_REQUEST['translator'])
		$query .= " AND (jx.gloss_" . $lang_vocab . ' IS NULL OR jx.gloss_' . $lang_vocab . " = '' OR jx.gloss_" . $lang_vocab . " LIKE '(~)%')";
	
	$query .= " ORDER BY j.njlpt DESC, j.njlpt_r DESC, j.reading LIMIT 100";
}
else {
	$_REQUEST['exact_match'] = true;
}
?>
<div id="ajax-result" class="message" style="position:fixed; top:10px; display:none;"></div>
<form>
	Id(s): <input name="id" size="30" value="<?php echo @$_REQUEST['id'] ?>" /> <br/>
	Word: <input name="word" size="10" value="<?php echo @$_REQUEST['word'] ?>" /> -	Reading: <input name="reading" size="20" value="<?php echo @$_REQUEST['reading'] ?>" /> (Exact match: <input type="checkbox" name="exact_match" <?php echo (@$_REQUEST['exact_match'] ? 'checked' : '') ?> />)<br/>
	JLPT: N<input name="njlpt" size="1" value="<?php echo @$_REQUEST['njlpt'] ?>" /> - R: N<input name="njlpt_r" size="1" value="<?php echo @$_REQUEST['njlpt_r'] ?>" /><br/>
	Show disabled entries: <input type="checkbox" name="show_disabled" <?php echo (@$_REQUEST['show_disabled'] ? 'checked' : '') ?> /><br/>
	Translator mode: <input type="checkbox" name="translator" <?php echo (@$_REQUEST['translator'] ? 'checked' : '') ?> /><br/>
	<input type="submit" name="submit" value="Find" />
</form>
<?php
	if(@$query) {

		$res = mysql_query($query) or die(mysql_error());
		
		if(mysql_num_rows($res) == 0)
			echo '<p><em>No entries matching these criteria</em></p>';
		else
			echo '<p><strong>' . mysql_num_rows($res) . ' entries matching these criteria</strong></p>';
		
		while($word = mysql_fetch_object($res)) {
			echo "<div class=\"word-block\">$word->id";
			
			if($_SESSION['user']->is_admin()) {
				echo " <a href=\"#\" onclick=\"\$(this).hide(); \$('#copy_jmdict_$word->id').show(); return false;\" style=\"font-size:80%;\">[copy entry &raquo;]</a><form id=\"copy_jmdict_$word->id\" class=\"copy_ajaxform\" action=\"" . SERVER_URL . "/ajax/edit_jmdict/\" method=\"post\" style=\"display:none;\"><input type=\"hidden\" name=\"copy_jmdict_id\" value=\"$word->id\"></input><p>Create a new word record using these information:</p><p>ID: <input name=\"new_jmdict_id\" type=\"text\"></input></p><p>Word: <input name=\"word\" type=\"text\" value=\"$word->word\"></input></p><p>Reading: <input name=\"reading\" type=\"text\" value=\"$word->reading\"></input></p><p><input type=\"submit\" name=\"create\" value=\"Create\"></input></p></form>";
			
				echo " <a href=\"#\" onclick=\"\$(this).hide();\$('#archive_jmdict_$word->id').show(); return false;\" style=\"font-size:80%;\">[archive entry &raquo;]</a><div id=\"archive_jmdict_$word->id\" class=\"archive_form\" style=\"display:none;\">Replace by ID (optional): <input type=\"text\" name=\"replace_id\" id=\"replace_id_$word->id\"></input><br/> <button onclick=\"archive_entry($word->id, \$('#replace_id_$word->id').val(), '?submit=Find&show_disabled=on&id=$word->id'); return false;\">Archive</button></div>";
			
			}
			
			echo "<br/><strong onclick=\"\$(this).hide(); \$('#word_$word->id').show();\">$word->word</strong><input type=\"edit\" id=\"word_$word->id\" style=\"display:none;\" onchange=\"update_word($word->id, this.value);\" value=\"$word->word\"></input>";
			
			echo "<div id=\"reading_$word->id\"" . ($word->katakana ? ' style="display:none;"' : '') . " style=\"display:inline;\"> 【<span onclick=\"\$(this).hide(); \$('#reading_field_$word->id').show();\">" . $word->reading . "</span><input type=\"edit\" id=\"reading_field_$word->id\" style=\"display:none;\" onchange=\"update_reading($word->id, this.value);\" value=\"$word->reading\"></input>】</div>";
			echo " <a href=\"#\" onclick=\"$(this).hide();$('#sels_$word->id').show();return false;\" id=\"jltp_$word->id\">(N$word->njlpt, R-N$word->njlpt_r)</a><span id=\"sels_$word->id\" style=\"display:none;\"><select id=\"njltp_sel_$word->id\" onchange=\"update_word_jlpt($word->id,this.value)\">";
			for($i = 5; $i >= 0; $i--)
				echo "<option value=\"$i\"" . ($word->njlpt == $i ? ' selected' : '') . ">N$i</option>";
			echo "</select><select id=\"njltp_r_sel_$word->id\" onchange=\"update_word_jlpt_r($word->id,this.value); return false;\">";
			for($i = 5; $i >= 0; $i--)
				echo "<option value=\"$i\""  . ($word->njlpt_r == $i ? ' selected' : '') . ">N$i</option>";
			echo "</select></span><br/>";
			echo "Katakana word: <input type=\"checkbox\" id=\"katakana_$word->id\" name=\"\" value=\"1\" " . (@$word->katakana ? 'checked' : '') . " onclick=\"update_word_katakana($word->id, this.checked); $('#reading_$word->id').toggle();\" />";
			echo " - Rare kanji: <input type=\"checkbox\" id=\"usually_kana_$word->id\" name=\"\" value=\"1\" " . (@$word->usually_kana ? 'checked' : '') . " onclick=\"update_word_usually_kana($word->id, this.checked);\" /><br/>";
			
			$english_sense_count = count(preg_split("/$pretty_num_regex/", $word->gloss_english));
			
			foreach(Vocab::$lang_strings as $lang => $full_lang) {
				if(@$_REQUEST['translator'] && $full_lang != 'english' && $full_lang != $lang_vocab)
					continue;
				
				$gloss = "gloss_$full_lang";
				echo "<p><a href=\"#\" id=\"static_lang_$lang" . "_$word->id\" onclick=\"$('#edit_lang_$lang" . "_$word->id').show(); return false;\"><img src=\"" . SERVER_URL . "/img/flags/$lang.png\" alt=\"$lang-flag\" style=\"vertical-align:bottom; margin:0 3px 0 0;\" /><span class=\"gloss\" style=\"font-style:italic;\">" . $word->$gloss . "</span></a></p>";
				echo "<form class=\"lang_edit\" action=\"" . SERVER_URL . "/ajax/vocab_translation/type/general/\" id=\"edit_lang_$lang" . "_$word->id\" style=\"border: 1px solid black; padding: 2px; ". (@$_REQUEST['translator'] && $full_lang != 'english'  ? '' : 'display:none;') . "\">";
				echo '<input type="checkbox" name="set_null" /> Erase<br/>';
				$senses = preg_split("/$pretty_num_regex/", $word->$gloss);
				$i = 1;
				foreach($senses as $sense) {
					$sense = trim($sense);

					if(empty($sense))
						continue;
					echo " $pretty_numbers[$i] <input type=\"text\" name=\"senses[$i]\" id=\"sense[$i]\" value=\"" . htmlentities($sense, ENT_COMPAT, 'UTF-8') . "\" size=\"100\" /><br/>";
					$i++;
				}

				$j = max($i+2, 1+$english_sense_count);

				while($i < $j) {
					echo "+ $pretty_numbers[$i] <input type=\"text\" name=\"senses[$i]\" id=\"sense[$i]\" value=\"\" size=\"100\" /><br/>";
					$i++;
				}

//onclick=\"$(this).parent().parent().hide();\"
				echo "<div style=\"text-align:center;\"><input type=\"submit\" name=\"submit\" value=\"Save\" /></div>";
				echo "<input type=\"hidden\" name=\"lang\" id=\"lang\" value=\"$lang\" /><input type=\"hidden\" name=\"jmdict_id\" id=\"jmdict_id\" value=\"$word->id\" /><input type=\"hidden\" name=\"update\" id=\"update\" value=\"1\" /></form>";
			}
		
			
			echo "</div>";
		
		}
	}


	if(@$query && isset($_REQUEST['show_disabled'])) {
		$query = str_replace('jmdict_ext ', 'jmdict_ext_deleted ', $query);
		$query = str_replace('jmdict ', 'jmdict_deleted ', $query);
		
		$res = mysql_query($query) or die(mysql_error());
		if(mysql_num_rows($res))
			echo "<h2 style=\"color: #A00\">Disabled:</h2>";
			
		while($word = mysql_fetch_object($res)) {
			echo "<div class=\"word-block\">$word->id - <a href=\"#\" onclick=\"reimport_entry($word->id, '?submit=Find&show_disabled=on&id=$word->id'); return false;\">[Re-import]</a> <a href=\"#\" onclick=\"delete_entry($word->id, '?submit=Find&show_disabled=on&id=$word->id'); return false;\">[Delete &raquo;]</a><br/><strong>$word->word</strong> 【" . $word->reading . "】 (N$word->njlpt, R-N$word->njlpt_r)<br/>";

			$english_sense_count = count(preg_split("/$pretty_num_regex/", $word->gloss_english));
						
			foreach(Vocab::$lang_strings as $lang => $full_lang) {
				$gloss = "gloss_$full_lang";
				echo "<p><img src=\"" . SERVER_URL . "/img/flags/$lang.png\" alt=\"$lang-flag\" style=\"vertical-align:bottom; margin:0 3px 0 0;\" /><span class=\"gloss\" style=\"font-style:italic;\">" . $word->$gloss . "</span></p>";
			}

			echo "</div>";

		}
	}

?>
<script type="text/javascript">
	$(document).ready(function() {
		$('.lang_edit').ajaxForm({
			beforeSubmit: function(msg, jqForm) {
				jqForm.hide();
				$('#ajax-result').html('Submitting changes...');
				$('#ajax-result').show();
				setTimeout(function() { $('#ajax-result').hide() }, 2000);
			 },
			success: function(msg, statusText, jqForm) {
				new_translation = $('#newtranslation', msg);
			    if(new_translation && new_translation.length > 0)
					jqForm.prev().find('.gloss').html(new_translation);
			    //     jqForm.$('a').html(new_translation);
			},
			target: '#ajax-result',
		});	

		$('.copy_ajaxform').ajaxForm({
			beforeSubmit: function(msg, jqForm) {
				$(jqForm, 'input').attr('disabled' , true);
				$('#ajax-result').html('Submitting changes...');
				$('#ajax-result').show();
			 },
			success: function(msg, statusText, jqForm) {
				$(jqForm).css('border', '1px solid green');
				$(jqForm, 'input').attr('disabled' , false);
				$('#ajax-result').html(msg);
				$('#ajax-result').show();
				setTimeout(function() { $('#ajax-result').hide() }, 5000);
			},
			target: '#ajax-result',
		});	
		
	})

	function reimport_entry(id, url)  
	{
		$.get('<?php echo SERVER_URL ?>ajax/edit_jmdict/?reimport_id=' + id, function (data) {
			$('#ajax-result').html(data);
			$('#ajax-result').show();
			setTimeout(function() { $('#ajax-result').hide(); window.location.href = url;}, 2000);
		});
	}
	
	function delete_entry(id, url)  
	{
		if(! confirm("Are you certain you want to permanently delete this entry?"))
			return;
		$.get('<?php echo SERVER_URL ?>ajax/edit_jmdict/?delete_id=' + id, function (data) {
			$('#ajax-result').html(data);
			$('#ajax-result').show();
			setTimeout(function() { $('#ajax-result').hide(); window.location.href = url;}, 3000);
		});
	}

	function archive_entry(id, replace_id, url)  
	{
		$.get('<?php echo SERVER_URL ?>ajax/edit_jmdict/?archive_id=' + id + '&replace_id=' + replace_id, function (data) {
			$('#ajax-result').html(data);
			$('#ajax-result').show();
			setTimeout(function() { $('#ajax-result').hide(); window.location.href = url;}, 2000);
		});
	}

	function update_word_katakana(id, is_katakana)  
	{
		$.get('<?php echo SERVER_URL ?>ajax/edit_jmdict/?jmdict_id=' + id + '&katakana=' + is_katakana, function (data) {
			$('#is_katakana_' + id).css('border', '2px solid green')
			$('#ajax-result').html(data);
			$('#ajax-result').show();
			setTimeout(function() { $('#ajax-result').hide() }, 2000);
		});
	}

	function update_word_usually_kana(id, is_usually_kana)  
	{
		$.get('<?php echo SERVER_URL ?>ajax/edit_jmdict/?jmdict_id=' + id + '&usually_kana=' + is_usually_kana, function (data) {
			$('#is_usually_kana_' + id).css('border', '2px solid green')
			$('#ajax-result').html(data);
			$('#ajax-result').show();
			setTimeout(function() { $('#ajax-result').hide() }, 2000);
		});
	}

	function update_word(id, word)  
	{
		$.get('<?php echo SERVER_URL ?>ajax/edit_jmdict/?jmdict_id=' + id + '&word=' + word, function (data) {
			$('#word_' + id).css('border', '2px solid green')
			$('#ajax-result').html(data);
			$('#ajax-result').show();
			setTimeout(function() { $('#ajax-result').hide() }, 2000);
		});
	}

	function update_reading(id, reading)  
	{
		$.get('<?php echo SERVER_URL ?>ajax/edit_jmdict/?jmdict_id=' + id + '&reading=' + reading, function (data) {
			$('#reading_field_' + id).css('border', '2px solid green')
			$('#ajax-result').html(data);
			$('#ajax-result').show();
			setTimeout(function() { $('#ajax-result').hide() }, 2000);
		});
	}
	
	function update_word_jlpt(id, jlpt)  
	{
		$.get('<?php echo SERVER_URL ?>ajax/edit_jmdict/?jmdict_id=' + id + '&njlpt=' + jlpt, function (data) {
			$('#njltp_sel_' + id).css('border', '2px solid green')
			$('#ajax-result').html(data);
			$('#ajax-result').show();
			setTimeout(function() { $('#ajax-result').hide() }, 2000);
		});
	}

	function update_word_jlpt_r(id, jlpt)  
	{
		$.get('<?php echo SERVER_URL ?>ajax/edit_jmdict/?jmdict_id=' + id + '&njlpt_r=' + jlpt, function (data) {
			$('#njltp_r_sel_' + id).css('border', '2px solid green')
			$('#ajax-result').html(data);
			$('#ajax-result').show();
			setTimeout(function() { $('#ajax-result').hide() }, 2000);
		});
	}

</script>