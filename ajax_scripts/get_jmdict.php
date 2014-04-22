<?php
if(!@$_SESSION['user'] || !$_SESSION['user']->isEditor())
	die("editors only");

if(@$_REQUEST['mode'] == 'wrong-answers')
	$target_sel = '#wrong-answer-results';
else
	$target_sel = '#word > .ajax-result';


if(isset($_REQUEST['jmdict_id'])) {
	$res = mysql_query('SELECT * FROM jmdict j LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id WHERE j.id = ' . (int) $_REQUEST['jmdict_id']) or die(mysql_error());
}
else {
	if(empty($_REQUEST['word'])) {
		echo 'Need a search string';
		exit;
	}	
	if(@$_REQUEST['exact_match'])
		$where = 'j.word = \'' . mysql_real_escape_string($_REQUEST['word']) . '\' OR j.reading = \'' . mysql_real_escape_string($_REQUEST['word']) . "'";
	else
		$where = 'j.word LIKE \'%' . mysql_real_escape_string($_REQUEST['word']) . '%\' OR j.reading LIKE \'%' . mysql_real_escape_string($_REQUEST['word']) . '%\'';

	$query = 'SELECT COUNT(*) AS count FROM jmdict j LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id WHERE ' . $where;

	$res = mysql_query($query) or die(mysql_error());
	$row = mysql_fetch_object($res);
	$tot_count = $row->count;
	$limit = ($tot_count < 7 ? $tot_count : 4);
	$skip = (int) @$_REQUEST['skip'];
	
	$res = mysql_query('SELECT * FROM jmdict j LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id WHERE '. $where . ' LIMIT ' . $skip . ',' . $limit) or die(mysql_error());
	
    if(@$_REQUEST['return_json']) {
        $words = array();
    	while($word = mysql_fetch_object($res))
            $words[] = $word;
        if($words) {
    	    echo(json_encode($words));
    	}
        return;
    }
    else {
    	$navig = '';
    	if($limit < $tot_count) {
    		$url = SERVER_URL  . "ajax/get_jmdict/?word=" . urlencode($_REQUEST['word']) . '&mode=' . @$_REQUEST['mode'] . "&exact_match=" . (int) (@$_REQUEST['exact_match'] != '') . "&skip=";

    		if($skip > 0)
    			$navig .= '<a href="#" onclick="$(\'' . $target_sel . '\').load(\'' . $url . ($skip - $limit) . '\'); return false;">&laquo;</a>';
    		$navig .= ' [' . ($skip + 1) . '~' . ($skip+$limit) . ']/' . $tot_count . ' ';
    		if($skip + $limit < $tot_count)
    			$navig .= '<a href="#" onclick="$(\'' . $target_sel . '\').load(\'' . $url . ($skip + $limit) . '\'); return false;">&raquo;</a>';
    	}
    	echo $navig;
    }
    
}

$count = mysql_num_rows($res);

if($count == 0) {
	echo 'No match';
	exit;
}

if($count == 1) {
	$word = mysql_fetch_object($res);
		
	echo "<div class=\"db-group selected-item\">";
	
	echo "<p class=\"db-info-jp\">" . ($word->katakana ? "<strong>$word->word</strong>" : (!$word->usually_kana && $word->word != $word->reading ? "<strong>$word->word</strong>【" . $word->reading . "】 " : "<strong>" . $word->reading . "</strong>")) . "<a href=\"#\" onclick=\"$(this).hide();$('#sels_$word->id').show();return false;\" id=\"jltp_$word->id\">(N$word->njlpt, R-N$word->njlpt_r)</a><span id=\"sels_$word->id\" style=\"display:none;\"><select id=\"njltp_sel_$word->id\" onchange=\"update_word_jlpt($word->id,this.value)\">";
	for($i = 5; $i >= 0; $i--)
		echo "<option value=\"$i\"" . ($word->njlpt == $i ? ' selected' : '') . ">N$i</option>";
	echo "</select><select id=\"njltp_r_sel_$word->id\" onchange=\"update_word_jlpt_r($word->id,this.value); return false;\">";
	for($i = 5; $i >= 0; $i--)
		echo "<option value=\"$i\""  . ($word->njlpt_r == $i ? ' selected' : '') . ">N$i</option>";
	echo "</select></span></p>";
	echo "<p class=\"db-info-small\"><em>$word->gloss_english</em></p>";

	if(@$_REQUEST['mode'] == 'wrong-answers') {
		echo "<p class=\"editor-choices\"><a href=\"#\" onclick=\"add_wrong_answer(". $word->id . "); return false;\">[add as choice]</a></p>";
	}
	else {
		echo '<p>ID: <span id="jmdict-id">' . $word->jmdict_id . '</span></p>';
		$questions_res = mysql_query('SELECT sq.*, ex.example_str FROM grammar_questions sq JOIN examples ex ON ex.example_id = sq.sentence_id WHERE sq.jmdict_id = ' . $word->jmdict_id) or die(mysql_error());
		if(mysql_num_rows($questions_res) > 0) {
			echo '<ul class="sentences"><lh onclick="$(\'ul.sentences li\').toggle();" style="color:blue;">Used in questions: &raquo;</lh>';
			while($question = mysql_fetch_object($questions_res)) {
				echo "<li style=\"display:none;\"><i>" . mb_substr($question->example_str, 0, $question->pos_start, "utf-8") . '<span class="small-highlighted">'. mb_substr($question->example_str, $question->pos_start, $question->pos_end - $question->pos_start, "utf-8") . '</span>' . mb_substr($question->example_str, $question->pos_end, 1000, "utf-8") . "</i> <strong>(N" . $question->njlpt . ")</strong> [<a href=\"#\" onclick=\"load_question(" . $question->question_id . "); return false;\">edit</a>]</li>";
			}
			echo "</ul>";
		}
		
		echo '<p><a href="#" onclick="get_sentence_with_jmdict_id(' . $word->jmdict_id . ', ' . $word->njlpt . '); return false;">Search example sentences &raquo;</a></p>';
		
	}
	echo "</div>";

}
else {
	while($word = mysql_fetch_object($res)) {
		echo "<div class=\"db-group ajax-clickable\" onclick=\"$('". $target_sel . "').load('". SERVER_URL . "ajax/get_jmdict/?jmdict_id=$word->id&mode=" . @$_REQUEST['mode'] . "');\">";
		echo "<p class=\"db-info-jp\">". ($word->katakana ? "<strong>$word->word</strong>" :  (!$word->usually_kana && $word->word != $word->reading ? "<strong>$word->word</strong>【" . $word->reading . "】 " : "<strong>" . $word->reading . "</strong>")) . " (N" . $word->njlpt . ", R-N" . $word->njlpt_r . ")</p>";
		echo "<p class=\"db-info-small\"><em>$word->gloss_english</em></p>";
		echo "</div>";
	}
}

?>

<script type="text/javascript">

	function get_sentence_with_jmdict_id(id, njlpt)  
	{
		$('#sentences > .ajax-result').html('<i>Loading...</i>');
		$('#sentences > .ajax-result').load('<?php echo SERVER_URL ?>ajax/get_sentence/?jmdict_id=' + id + '&njlpt=' + njlpt);
	}

	function update_word_jlpt(id, jlpt)  
	{
		$.get('<?php echo SERVER_URL ?>ajax/edit_jmdict/?jmdict_id=' + id + '&njlpt=' + jlpt, function (data) {
			$('#njltp_sel_' + id).css('border', '2px solid green')
		});
	}

	function update_word_jlpt_r(id, jlpt)  
	{
		$.get('<?php echo SERVER_URL ?>ajax/edit_jmdict/?jmdict_id=' + id + '&njlpt_r=' + jlpt, function (data) {
			$('#njltp_r_sel_' + id).css('border', '2px solid green')
		});
	}

</script>