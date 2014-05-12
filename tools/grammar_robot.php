<?php


if(!@$_SESSION['user'] || !$_SESSION['user']->isEditor())
	die("only editors");

mb_internal_encoding("UTF-8");

// if(! $_SESSION['user']->is_admin())
	// $user_id = $_SESSION['user']->get_id();
	
	$res = mysql_query("SELECT gs.set_id, gs.name FROM grammar_sets gs");
	$grammar_setsv = array(-1 => '[none]');
	while($row = mysql_fetch_object($res))
		$grammar_sets[$row->setID] = $row->name;

	$set_id = (int) @$_REQUEST['set_id'];
	if(! $set_id)
		$set_id = (int) @$params['set_id'];
	
	echo 'Select set: ';
	display_select_menu($grammar_sets, 'set_id', $set_id, "window.location.href = 'https://kanjibox.net/kb/tools/grammar_robot/set_id/' + this.value + '/';", '-');
	echo '<br/>';

	if(@$set_id)
	{
	$answers = [];

	?>
	<form id="make-wrong-answers-form" action="/kb/ajax/grammar_robot_step_2/" method="post" onsubmit="if($('.choice > input:checked').length >= 4) { return true; } else { alert('Please first at least 4 answers in this set.'); return false; };">
	<div style="margin-top:20px;">
		<?php
	$res_correct_answers = mysql_query("SELECT j.*, jg.gloss_english as gloss, COUNT(*) AS c FROM grammar_questions sq JOIN jmdict j ON j.id = sq.jmdict_id JOIN jmdict_ext jg ON jg.jmdict_id = j.id LEFT JOIN users_ext ux ON ux.user_id = sq.user_id WHERE sq.set_id = $set_id GROUP BY j.id ORDER BY c ASC, j.word ASC") or die(mysql_error());
	while($answer = mysql_fetch_object($res_correct_answers)) {
		$answers[$answer->id] = array('correct' => $answer->c, 'wrong' => 0, 'jmdict' => $answer);
		$word = ($answer->usually_kana || $answer->word == $answer->reading ? $answer->reading : "$answer->word 【" . $answer->reading . "】");
		echo "<label class=\"choice\"><input type=\"checkbox\" name=\"answer_ids[$answer->id]\" id=\"answer_ids[$answer->id]\" onchange=\"handleCheckboxChange(this, $answer->c);\"></input> $word</label> ";
	}
		?>
	</div><p style="clear:both;"><a href="#" onclick="$('.choice > input:not(:checked)').prop('checked', true).change(); return false;">[select all]</a>&nbsp;&nbsp;&nbsp;<a href="#" onclick="$('.choice > input:checked').prop('checked', false).change() ; return false;">[deselect all]</a></p>

	<p><strong>Total Answers:</strong> <input type="text" id="answers_tot" disabled="true" value="0" size="3"></input> | <strong>Total Questions:</strong> <input type="text" id="questions_tot" disabled="true" value="0" size="3"></input></p>
	<input type="hidden" name="set_id" id="set_id" value="<?php echo $set_id; ?>"></input>
	<p style="text-align:center;"><input type="submit" name="make-wrong-answers" value="Suggest Wrong Answers"></input></p>
</form>
<div id="ajax-result"></div>
<div id="ajax-wrong-answers"></div>

	<?php
}


?>
<script>

$(document).ready(function() {
	$('form#make-wrong-answers-form').ajaxForm({
		target: '#ajax-wrong-answers',
		beforeSubmit: function() { $('#ajax-wrong-answers').html('Loading...') },
	});	
})

function handleCheckboxChange(box, val) {
	if(box.checked) { 
		$(box).parent().css('background-color', '#AAF');
		var tot = parseInt($('#questions_tot').val(), 10) + val; 
		$('#questions_tot').val(tot); 
		$('#answers_tot').val(parseInt($('#answers_tot').val(), 10) + 1); 
	} 
	else { 
		$(box).parent().css('background-color', '');
		var tot = parseInt($('#questions_tot').val(), 10) - val; 
		$('#questions_tot').val(tot);
		$('#answers_tot').val(parseInt($('#answers_tot').val(), 10) - 1); 

	}
}
</script>