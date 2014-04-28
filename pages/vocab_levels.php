<?php


if(!@$_SESSION['user']) //  && !$_SESSION['user']->is_admin()
	die("Need to be logged-in");
	
$can_edit = $_SESSION['user']->is_elite();
	
$level = (int) (isset($params['level']) ? $params['level'] : @$_SESSION['user']->getLevel());
	
$level_array = array(5 => 'N5', 4 => 'N4', 3 => 'N3', 2 => 'N2', 1 => 'N1', 0 => '先生');
?>
<div id="ajax-result" class="message" style="position:fixed; top:10px; display:none;"></div>
<h2>Vocab List for level <?php echo get_select_menu($level_array, 'level_select', $level, 'location.href=\'' . get_this_page_url(NULL, '', true) . "level/' + this.value + '/'"); ?></h2>
<form><input type="submit" name="search" value="Search" /> <input type="text" size="20" name="contains" value="<?php echo htmlentities(@$_REQUEST['contains'], ENT_COMPAT, 'UTF-8') ?>"></input> (<input type="checkbox" name="exact_match" <?php if(@$_REQUEST['exact_match']) echo 'checked="yes"'; ?>/> exact match)</form>
	<?php
	$skip = (int) max(@$_REQUEST['skip'], 0);
	$how_many = (int) (@$_REQUEST['how_many'] ? $_REQUEST['how_many'] : 100);

	$query_body = " FROM jmdict j LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id";
	
	$query_where = ' WHERE ';
	if(!empty($_REQUEST['contains'])) {
		$str = mysql_real_escape_string($_REQUEST['contains']);
		
		if(@$_REQUEST['exact_match'])
			$query_where .= " word = '$str' OR reading = '$str'";
		else
			$query_where .= " word LIKE '%$str%' OR reading LIKE '%$str%'";
	}
	else
		$query_where .= "j.njlpt = " . $level;
		
	$res = mysql_query('SELECT COUNT(*) AS c '. $query_body . $query_where) or die(mysql_error());
	$row = mysql_fetch_object($res);
    $navig = "<h3>";
	if($skip > 0)
		$navig .= "<a href=\"?skip=" . max(0, $skip-$how_many) . "&how_many=$how_many\">&laquo;</a> ";
	$navig .= "[" . ($skip+1) . "~" . min($skip+$how_many, $row->c) . "]/" . $row->c;
	if($skip + $how_many < $row->c)
		$navig .= " <a href=\"?skip=" . max(0, $skip+$how_many) . "&how_many=$how_many\">&raquo;</a> ";
	$navig .= "</h3>";
	echo $navig;
	
	$query_body .= " LEFT JOIN data_updates du ON du.table_name = 'jmdict' AND du.id_value = j.id AND du.col_name = 'word' AND du.applied = 0 LEFT JOIN data_updates du2 ON du2.table_name = 'jmdict' AND du2.id_value = j.id AND du2.col_name = 'reading' AND du2.applied = 0 LEFT JOIN data_updates du3 ON du3.table_name = 'jmdict' AND du3.id_value = j.id AND du3.col_name = 'njlpt' AND du3.applied = 0 LEFT JOIN data_updates du4 ON du4.table_name = 'jmdict' AND du4.id_value = j.id AND du4.col_name = 'njlpt_r' AND du4.applied = 0";
	
	$res = mysql_query('SELECT j.*, jx.*, du.new_value AS update_word, du2.new_value AS update_reading, du3.new_value AS update_njlpt, du4.new_value AS update_njlpt_r  '. $query_body . $query_where . " GROUP BY j.id ORDER BY j.reading, du.ts DESC, du2.ts DESC, du3.ts DESC, du4.ts DESC LIMIT $skip, $how_many") or die(mysql_error());

	echo "<table class=\"db-list\">";
	if($can_edit)
		echo "<th class=\"edit\">Edit</th>";
		
	echo "<th class=\"word\">Word</th><th class=\"reading\">Reading</th><th class=\"level\"><small>JLPT</small></th><th class=\"level\"><small>Read. JLPT</small></th><th>English</th>";
	
	while($row = mysql_fetch_object($res)) {
		echo '<tr id="jmdict_' . $row->id . '" class="' .  ($row->usually_kana ? 'usually-kana' : '') . '">';
		if($can_edit)
			echo "<td class=\"edit\"><a class=\"editing\" href=\"#\" onclick=\"$(this).parent().parent().removeClass('editing'); return false;\">✔</a><a class=\"edited\" href=\"#\" onclick=\"$(this).parent().parent().addClass('editing'); return false;\">✍</a></td>";
		
		echo "<td class=\"word" . ($row->update_word ? ' update-pending' : '') ."\">$row->word";
		if($can_edit)
			echo "<div class=\"editing\"> <input type=\"checkbox\" class=\"set-usually-kana\" onchange=\"update_word_usually_kana($row->id, (this.checked ? 'true' : 'false'));\" " . ($row->usually_kana ? ' checked' : '') . " >Usually kana</input></div>";
                // echo "<div class=\"editing\"> <a class=\"set-usually-kana\" href=\"#\" onclick=\"update_word_usually_kana($row->id, 'true'); return false;\">always kana&nbsp;&raquo;</a><a class=\"unset-usually-kana\" href=\"#\"  onclick=\"update_word_usually_kana($row->id, 'false'); return false;\">NOT always kana&nbsp;&raquo;</a></div>";
		echo "</td>";
		
		echo "<td class=\"reading" . ($row->update_reading ? ' update-pending' : '') ."\">$row->reading</td><td class=\"level njlpt" . ($row->update_njlpt ? ' update-pending' : '') ."\"><span class=\"edited\">$row->njlpt</span>";
		
		if($can_edit)
			echo "<div class=\"editing\">" . get_select_menu($level_array, '', $row->njlpt, 'update_word_jlpt('. $row->id . ', this.value)') . "</div>";
		
		echo "</td><td class=\"level njlpt_r" . ($row->update_njlpt_r ? ' update-pending' : '') ."\"><span class=\"edited\">$row->njlpt_r</span>";
		
		if($can_edit)
			echo "<div class=\"editing\">" . get_select_menu($level_array, '', $row->njlpt_r, 'update_word_jlpt_r('. $row->id . ', this.value)') . "</div>"; 
		
		echo "</td><td class=\"english\">$row->gloss_english</td>";
		echo '</tr>';
	}
	echo '</table>';
	
    echo $navig;
	
if($can_edit) {
?>
<script type="text/javascript">
	
	var update_color = '<?php echo ($_SESSION['user']->isEditor() ? '#6FA' : 'orange')?>';
	var extra_text = '<?php echo ($_SESSION['user']->isEditor() ? '' : ' <span style="font-weight:bold;">It will be applied after an editor reviews it.</span>')?>';

	function get_user_cmt() {
		<?php
			if(!$_SESSION['user']->isEditor())
				echo 'return prompt("Please enter a comment for this suggested update (source etc.): ", "");';
			else
				echo "return '';";
		?>
	}

	function update_word_usually_kana(id, is_usually_kana)  
	{
		var cmt = get_user_cmt();	   
		$.get('<?php echo SERVER_URL ?>ajax/edit_jmdict/?jmdict_id=' + id + '&usually_kana=' + is_usually_kana + '&user_cmt=' + encodeURIComponent(cmt), function (data) {
			$('tr#jmdict_' + id + ' td.word').css('background-color', update_color);
			$('tr#jmdict_' + id + ' td.reading').css('background-color', update_color);
			$('#ajax-result').html(data);
			$('#ajax-result').show();
            // if(is_usually_kana == 'true')
            //     $('tr#jmdict_' + id).addClass('usually-kana');
            // else
            //     $('tr#jmdict_' + id).removeClass('usually-kana');
			
			setTimeout(function() { $('#ajax-result').hide() }, 4000);
		});
	}

	function update_word_jlpt(id, jlpt)  
	{
		var cmt = get_user_cmt();	   
		$.get('<?php echo SERVER_URL ?>ajax/edit_jmdict/?jmdict_id=' + id + '&njlpt=' + jlpt + '&user_cmt=' + encodeURIComponent(cmt), function (data) {
			$('tr#jmdict_' + id + ' td.njlpt').css('background-color', update_color);
			$('#ajax-result').html(data + extra_text);
			$('#ajax-result').show();
			setTimeout(function() { $('#ajax-result').hide() }, 4000);
		});
	}

	function update_word_jlpt_r(id, jlpt)  
	{
		var cmt = get_user_cmt();	   
		$.get('<?php echo SERVER_URL ?>ajax/edit_jmdict/?jmdict_id=' + id + '&njlpt_r=' + jlpt + '&user_cmt=' + encodeURIComponent(cmt), function (data) {
			$('tr#jmdict_' + id + ' td.njlpt_r').css('background-color', update_color);
			$('#ajax-result').html(data);
			$('#ajax-result').show();
			setTimeout(function() { $('#ajax-result').hide() }, 4000);
		});
	}

</script><?php
}
?>