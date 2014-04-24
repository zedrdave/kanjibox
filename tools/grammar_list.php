<?php


if(!@$_SESSION['user'] || !$_SESSION['user']->isEditor())
	die("editors only");

if(!$_SESSION['user']->isAdministrator() && $_SESSION['user']->getID() != 46796) {
	$user_id = $_SESSION['user']->getID();
    $set_id = 0;
}
else {
	$user_id = (int) @$params['user_id'] or (int) @$_REQUEST['user_id'];
	
	$res = mysql_query("SELECT u.*, ux.* FROM grammar_questions sq LEFT JOIN users u ON u.id = sq.user_id JOIN users_ext ux ON ux.user_id = u.id GROUP BY u.id");
	$array = array();
	while($row = mysql_fetch_object($res)) {
		$array[$row->user_id] = $row->first_name . ' ' . mb_substr($row->last_name, 0, 1, 'UTF-8') . '.';
	}

	echo 'User: ';
	display_select_menu($array, 'user_id', $user_id, "window.location.href = 'https://kanjibox.net/kb/tools/grammar_list/user_id/' + this.value;", '-');
	
	
	$res = mysql_query("SELECT gs.set_id, gs.name FROM grammar_sets gs");
	$array = array(-1 => '[none]');
	while($row = mysql_fetch_object($res))
		$array[$row->set_id] = $row->name;

		$set_id = (int) @$_REQUEST['set_id'];
		if(! $set_id)
			$set_id = (int) @$params['set_id'];
	
	echo ' | Set: ';
	display_select_menu($array, 'set_id', $set_id, "window.location.href = 'https://kanjibox.net/kb/tools/grammar_list/?set_id=' + this.value;", '-');
	echo '<br/>';
}

$grammar_sets = array();
$res = mysql_query("SELECT * FROM grammar_sets ORDER BY short_name");
while($row = mysql_fetch_object($res))
	$grammar_sets[$row->set_id] = $row->name;
	
$res_demo = mysql_query("SELECT COUNT(*) AS c FROM grammar_questions sq WHERE in_demo = 1 " . ($user_id ? "AND sq.user_id = $user_id" : "") . ($set_id > 0 ? " AND sq.set_id = $set_id" : "") . ($set_id == -1 ? " AND (sq.set_id <= 0 OR sq.set_id IS NULL)" : "")) or die(mysql_error());
$demo_row = mysql_fetch_object($res_demo);
	
$res = mysql_query("SELECT sq.*, e.*, j.njlpt AS word_jlpt, j.*, jg.gloss_english as gloss, ux.first_name, ux.last_name FROM grammar_questions sq JOIN examples e ON sq.sentence_id = e.example_id JOIN jmdict j ON j.id = sq.jmdict_id JOIN jmdict_ext jg ON jg.jmdict_id = j.id LEFT JOIN users_ext ux ON ux.user_id = sq.user_id WHERE " . ($user_id ? "sq.user_id = $user_id" : "1") . ($set_id > 0 ? " AND sq.set_id = $set_id" : "") . ($set_id == -1 ? " AND (sq.set_id <= 0 OR sq.set_id IS NULL)" : "") . " ORDER BY set_id ASC, in_demo DESC LIMIT 300") or die(mysql_error());

echo "<p>Total: " . mysql_num_rows($res) . " questions (" . $demo_row->c . " demo)</p>";

$answers = array();

$res_correct_answers = mysql_query("SELECT j.*, jg.gloss_english as gloss, COUNT(*) AS c FROM grammar_questions sq JOIN jmdict j ON j.id = sq.jmdict_id JOIN jmdict_ext jg ON jg.jmdict_id = j.id LEFT JOIN users_ext ux ON ux.user_id = sq.user_id WHERE " . ($user_id ? "sq.user_id = $user_id" : "1") . ($set_id > 0 ? " AND sq.set_id = $set_id" : "") . ($set_id == -1 ? " AND (sq.set_id <= 0 OR sq.set_id IS NULL)" : "") . " GROUP BY j.id") or die(mysql_error());
while($answer = mysql_fetch_object($res_correct_answers)) {
	$answers[$answer->id] = array('correct' => $answer->c, 'wrong' => 0, 'jmdict' => $answer);
}

$res_wrong_answers = mysql_query("SELECT j.*, jg.gloss_english as gloss, COUNT(*) AS c FROM grammar_questions sq LEFT JOIN grammar_answers ga ON ga.question_id = sq.question_id JOIN jmdict j ON j.id = ga.jmdict_id JOIN jmdict_ext jg ON jg.jmdict_id = j.id LEFT JOIN users_ext ux ON ux.user_id = sq.user_id WHERE " . ($user_id ? "sq.user_id = $user_id" : "1") . ($set_id > 0 ? " AND sq.set_id = $set_id" : "") . ($set_id == -1 ? " AND (sq.set_id <= 0 OR sq.set_id IS NULL)" : "") . " GROUP BY j.id") or die(mysql_error());
while($answer = mysql_fetch_object($res_wrong_answers)) {
	if(!isset($answers[$answer->id]))
		$answers[$answer->id] = array('correct' => 0, 'wrong' => $answer->c, 'jmdict' => $answer);
	else
		$answers[$answer->id]['wrong'] = $answer->c;
}

if(@$_REQUEST['show_breakdown'])
	echo "<p><div id=\"breakdown\">";
else
	echo "<p><a href=\"#\" onclick=\"\$('#breakdown').toggle(); return false;\">Breakdown &raquo;</a> <div id=\"breakdown\" style=\"display:none;\">";

	foreach($answers as $answer) {
		$ratio_wrong = ($answer['wrong'] / ($answer['correct'] + $answer['wrong']));
		$ratio_correct = ($answer['correct'] / ($answer['correct'] + $answer['wrong']));

		echo "<div class=\"grammar-breakdown-bar\" style=\"background-color:#9AFF84; width:" . 100 * $ratio_correct . "px;\">" . ($ratio_correct > 0 ? $answer['correct'] : '' ) . "</div><div class=\"grammar-breakdown-bar\"  style=\"background-color:#F7A181; width:" . 100 * $ratio_wrong . "px;\">" . ($ratio_wrong > 0 ? $answer['wrong'] : '') . "</div> <div class=\"grammar-breakdown-text\">" . ($answer['jmdict']->usually_kana || $answer['jmdict']->reading == $answer['jmdict']->word ? $answer['jmdict']->reading :  $answer['jmdict']->word . "【" . $answer['jmdict']->reading. "】") . ($ratio_wrong < 0.01 || $ratio_correct < 0.01 ? ' <span style="color:red">(unbalanced)</span>' : '') . "</div> <div style=\"clear: both;\"></div>";
	}
echo "</div></p>";


echo '<div id="ajax-result"></div>';

while($question = mysql_fetch_object($res)) {
	if(!@$first++ && $user_id)
		echo "Displaying editor: $question->first_name " . mb_substr($question->last_name, 0, 1, 'UTF-8') . "<br/>";

	$res_ans = mysql_query('SELECT *, jx.gloss_english as gloss FROM grammar_answers sa LEFT JOIN jmdict j ON sa.jmdict_id = j.id LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id WHERE sa.question_id = ' . (int) $question->question_id . ' GROUP BY sa.jmdict_id') or die(mysql_error());

	echo '<fieldset class="question-list-item"' . (mysql_num_rows($res_ans) < 3 ? ' style="border: 2px solid #A00; background-color: #FEE;"' : '') . '>'
	?>
	<legend>ID: <span id="question-id"><?php echo $question->question_id ?></span> (Ed. <?php echo $question->first_name . ' ' . mb_substr($question->last_name, 0, 1, 'UTF-8') . '.'?>) [<a href="http://kanjibox.net/kb/tools/grammar_editor/?edit_question_id=<?php echo $question->question_id ?>">edit</a>]</legend>
	<p class="question-str"><?php  
	if ($question->pos_start >= 0 && $question->pos_end > $question->pos_start)
		echo mb_substr($question->example_str, 0, $question->pos_start, 'utf-8') . '<span class="highlighted">' . mb_substr($question->example_str, $question->pos_start, $question->pos_end - $question->pos_start, 'utf-8') . '</span>' . mb_substr($question->example_str, $question->pos_end, -1, 'utf-8');
	else
		echo $question->example_str ;
	?></p>
<?
	if($question->pos_start == 0 && $question->pos_end == 0)
		echo ' <span class="notice">(set answer position)</span>';
	else {
		$substr = mb_substr($question->example_str, $question->pos_start, $question->pos_end - $question->pos_start, 'utf-8');
		if($substr != $question->word && $substr != $question->reading)
			echo ' <span class="notice">(selection not matching answer)</span>';
	}
	?>
	<p>Set: <?php
	display_select_menu($grammar_sets, 'question_id_' . $question->question_id . '_set_id', $question->set_id, "update_set_id($question->question_id,this.value);", '-');

	?> | JLPT: N<?php echo $question->njlpt ?> | Demo: <input type="checkbox" name="question_id_<?php echo $question->question_id ?>_in_demo'" <?php echo $question->in_demo ? 'checked="checked"' : '' ?> onchange="update_demo(<?php echo $question->question_id ?>, this.checked);" /></p>
    <p class="question-en"><?php echo $question->english ?><p>
	<br/>
	Correct answer:<br/>
	<p><span class="good-answer" id="picked-answer"><?php echo $question->word ?></span> 【<?php echo ($question->reading) ?>】 (N<?php echo ($question->word_jlpt) ?>) - <small><?php echo $question->gloss ?></small></p>

	<br/>
	<?php

	echo "Wrong answers (" . mysql_num_rows($res_ans);
	if(mysql_num_rows($res_ans) < 3)
		echo ' - NOT ENOUGH!';
	echo "):<br/>";

	while($bad_answer = mysql_fetch_object($res_ans)) {
		?>
		<p class="spaced-line"><span class="bad-answer"><?php echo $bad_answer->word ?></span> 【<?php echo $bad_answer->reading ?>】 (N<?php echo ($bad_answer->njlpt) ?>)  <small><?php echo $bad_answer->gloss ?></small> <a href="#" onclick="delete_answer(<?php echo $question->question_id . ', ' . $bad_answer->jmdict_id ?>, this); return false;">[delete]</a></p>
		<?php
	}
	echo "</fieldset>";
}

?>
<script type="text/javascript">
	function delete_answer(question_id, answer_id, selector) {
		$.ajax({
            url: '<?php echo SERVER_URL ?>ajax/edit_question/question_id/' + question_id + '/?no_content=1&delete_answer_id=' + answer_id, 
            type: 'GET',
            success: function (data) {
					if(data != '') {
						alert(data.replace(/<(?:.|\n)*?>/gm, ''));
	                $('#ajax-result').show().html(data);
	        		setTimeout(function() {
	        			$('#ajax-result').hide().html('')
	        		}, 10000);
	  	  		    $(selector).parent().css('text-decoration', 'line-through')
	  	  		    $(selector).parent().css('text-decoration-color', '#F00')					
				}				
        	},
            timeout: 1000, 
            error: function(x, t, m) {
                alert("There was a connection error and your change was NOT saved.\nIf this problem persists, please reload the page.")
            }
        });
	}

	function update_set_id(question_id, set_id)  
	{
		$.get('<?php echo SERVER_URL ?>ajax/edit_question/question_id/' + question_id + '/?update_set_id=' + set_id, function (data) {
			$('#question_id_' + question_id + '_set_id').css('border', '2px solid green')
			$('#ajax-result').show().html(data);
			setTimeout(function() { 
				$('#ajax-result').hide().html('') 
				$('#question_id_' + question_id + '_set_id').css('border', 'none')
			}, 2000);
		});
	}

	function update_demo(question_id, in_demo)  
	{
		$.get('<?php echo SERVER_URL ?>ajax/edit_question/question_id/' + question_id + '/?update_in_demo=' + (in_demo ? '1' : '0') , function (data) {
			$('#question_id_' + question_id + '_demo').css('border', '2px solid green')
			$('#ajax-result').show().html(data);
			setTimeout(function() { 
				$('#ajax-result').hide().html('') 
				$('#question_id_' + question_id + '_demo').css('border', 'none')
			}, 2000);
		});
	}

</script>