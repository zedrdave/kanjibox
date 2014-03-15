<?php

// $res = mysql_query('SELECT COUNT(*) FROM examples') or die(mysql_error());
// 
// print_r(mysql_fetch_array($res));


if(!@$_SESSION['user'] || !$_SESSION['user']->is_editor())
	die("editors only");

mb_internal_encoding('UTF-8');

?>

<fieldset class="section" id="word">
	<legend>Word</legend>
	<form class="ajax-form" action="<?php echo SERVER_URL ?>ajax/get_jmdict/" method="post">
		<input type="text" name="word" value="" size="12" /> 
		<input type="submit" name="search" value="Search"></input><br/><input type="checkbox" name="exact_match" /> Exact match
	</form>	
	<hr/>
		<div class="ajax-result"></div>
</fieldset>

<fieldset class="section" id="sentences">
	<legend>Sentence</legend>
	<div id="ajax-result"></div>
	<form class="ajax-form" action="<?php echo SERVER_URL ?>ajax/get_sentence/" method="post">
		<p>Contains: <input type="text" name="word" value="" size="12" /> (does <span style="color:#800">NOT</span> contain: <input type="text" name="not_word" value="" size="10" />)
		<small>Preferably JLPT: <?php echo get_jlpt_menu('njlpt', 5) ?></small></p>
		<p style="text-align: center;"><input type="submit" name="search" value="Search" /></p>
		<p style="text-align: right"><a id="create-sentence-button" href="#" onclick="$('#create-sentence').show(); $(this).hide(); return false;">[create new sentence]</a></p>
	</form>
	<div id="create-sentence" style="display:none;">
		<hr/>
		<form class="ajax-form" action="<?php echo SERVER_URL ?>ajax/edit_sentence/" method="post">
			<p>Jp: <input type="text" name="example_str" id="example_str" style="width:90%;"></input></p>
			<p>En: <input type="text" name="english" id="example_str" style="width:90%;"></input></p>
			<?php
			/*
			<p>JLPT: <select id="njlpt" name="njlpt">
		<?php
		for($i = 5; $i >= 0; $i--)
			echo "<option value=\"$i\">N$i</option>";
			?>
		</select> | Reading: <select id="njlpt_r" name="njlpt_r">
		<?php
		for($i = 5; $i >= 0; $i--)
			echo "<option value=\"$i\">N$i</option>";
			?>
		</select></p>
			*/
			?>
		<p style="text-align: center;"><input type="submit" name="create" value="Create" /></p>
		</form>
	</div>
	<hr/>
		<div class="ajax-result"></div>
</fieldset>

<fieldset class="section" id="question">
	<legend>Question</legend>
	<div class="ajax-result"><em>Load word and sentence first...</em></div>
</fieldset>
<script type="text/javascript">

	$(document).ready(function()  
	{
		$('#sentences > .ajax-form').ajaxForm({
			target: '#sentences > .ajax-result',
			beforeSubmit: function() { $('#sentences > .ajax-result').html('Loading...') },
		});	

		$('#word > .ajax-form').ajaxForm({
			target: '#word > .ajax-result',
			beforeSubmit: function() {$('#word > .ajax-result').html('Loading...') },
		});
		
		$('#create-sentence > .ajax-form').ajaxForm({
			target: '#sentences > .ajax-result',
			beforeSubmit: function() {$('#sentences > .ajax-result').html('Loading...'); $('#create-sentence-button').show(); $('#create-sentence').hide(); },
		});
		
		<?php
		if(@$_REQUEST['edit_question_id'])
			echo "\n load_question(" . (int) $_REQUEST['edit_question_id'] .");\n";
		?>
	});
	
	function show_question_save_button() {
		$('#save-question-info').show();
	}

	function delete_question(question_id) {
		if(confirm("Are you SURE you want to delete this question?")) {
			$('#question > .ajax-result').html('Loading...');
			$('#question > .ajax-result').load('<?php echo SERVER_URL ?>ajax/edit_question/?delete_id=' + question_id);
		}
	}

	function delete_wrong_answer(question_id, answer_id) {
		$('#question > .ajax-result').html('Reloading...');
		$('#question > .ajax-result').load('<?php echo SERVER_URL ?>ajax/edit_question/question_id/' + question_id + '/?delete_answer_id=' + answer_id);
	}

	function move_selection(offset) {
		$('#pos_start').val(Math.max(parseInt($('#pos_start').val()) + offset, 0));
		$('#pos_end').val(Math.max(parseInt($('#pos_end').val()) + offset, 0));
		show_question_save_button();
	}

	function extend_selection(offset) {
		$('#pos_end').val(Math.max(parseInt($('#pos_end').val()) + offset, 0));
		show_question_save_button();
	}

	
	function highlight_text() {
		var rep = str.substring(0, $('#pos_start').val()) + '<span class="highlighted">' + str.substring($('#pos_start').val(), $('#pos_end').val()) + '</span>' + str.substring($('#pos_end').val());
		$('p#question-str').html(rep);
		$('#pos_start_txt').html($('#pos_start').val());
		$('#pos_end_txt').html($('#pos_end').val());
	}
	
	function load_new_question(sentence_id)  
	{
		var jmdict_id = 0;
						
		if($('#jmdict-id').html() != null)
			jmdict_id = parseInt($('#jmdict-id').html());

		if(jmdict_id <= 0 || jmdict_id == NaN)
			alert('Select a word first...');
		else {
			$('#question > .ajax-result').html('Loading...');
			$('#question > .ajax-result').load('<?php echo SERVER_URL ?>ajax/edit_question/?sentence_id=' + sentence_id + '&jmdict_id=' + jmdict_id);
		}
	}
	
	function load_question(question_id)  
	{
		$('#question > .ajax-result').html('Loading...');
		$('#question > .ajax-result').load('<?php echo SERVER_URL ?>ajax/edit_question/question_id/' + question_id);
	}

	function add_wrong_answer(jmdict_id)  
	{
		var question_id = parseInt($('#question-id').html());
		if(question_id <= 0) {
			alert("No question to add this choice to...");
			return;
		}
		$('#question > .ajax-result').html('Loading...');
		$('#question > .ajax-result').load('<?php echo SERVER_URL ?>ajax/edit_question/question_id/' + question_id + "/?add_wrong_answer=" + jmdict_id);
	}
	
</script>