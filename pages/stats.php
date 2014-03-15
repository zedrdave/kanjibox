<?php 

force_logged_in_app('stats');

require_once(ABS_PATH . 'libs/stats_lib.php');
include_css('stats.css');
include_js('ajax.js');

$levels = Session::$level_names;

?>
<div class="subtabs">
<?php

	if(@$params['type'])
		$cur_type = $params['type'];
	else
		$cur_type = 'main';

	$subtabs = array( 'main' => 'Summary', TYPE_KANA => 'Kana stats', TYPE_KANJI => 'Kanji stats', TYPE_VOCAB => 'Vocab stats', TYPE_READING => 'Reading stats');
	if(! @$subtabs[$cur_type])
		log_error('Unknown stats type: ' . $cur_type, true, true);

	$width = (int) (800 / count($subtabs)) - 8;
	foreach($subtabs as $type => $label)
		echo '<a href="' . get_page_url(PAGE_STATS, array('type' => $type)) . '" class="' . ($cur_type == $type ? "selected" : '')  . '" onclick="do_load(\'' . SERVER_URL . 'ajax/stats/type/' . $type . '\', \'frame-stats\'); $(this).addClass(\'loading\');$(this).css(\'backgroundImage\', \'url(' . SERVER_URL . 'img/small-ajax-loader.gif)\'); return false;" style="width: ' . $width . 'px">'. $label . '</a>';

?>
</div>
<div style="clear:both;" ></div>
<?php
/*
<fb:error>
	<fb:message>Major Dysfunction...</fb:message>
	<div style="color:red; font-weight: bold;">Due to a <s>major fuckup</s> very unfortunate set of circumstances, all stats and scores for accounts opened before Feb. 26th have become corrupt and disappeared. If this is your case, please <a href="http://apps.new.facebook.com/kanjibox/recover.php">follow these instructions</a> to recover them immediately.</div>
	<div style="clear:both"></div>
</fb:error>
*/

$query = 'SELECT COUNT(*) AS c FROM learning l WHERE l.user_id = \'' . $_SESSION['user']->get_id() . '\' LIMIT 1';
$res = mysql_query($query) or log_db_error($query);
$row = mysql_fetch_object($res);
$query = 'SELECT COUNT(*) AS c FROM jmdict_learning jl WHERE jl.user_id=\'' . $_SESSION['user']->get_id() . '\' LIMIT 1';
$res = mysql_query($query) or log_db_error($query);
$row2 = mysql_fetch_object($res);
$query = 'SELECT COUNT(*) AS c FROM reading_learning rl WHERE rl.user_id=\'' . $_SESSION['user']->get_id() . '\' LIMIT 1';
$res = mysql_query($query) or log_db_error($query);
$row3 = mysql_fetch_object($res);
	
$ajax_url = SERVER_URL . 'ajax/stats/type/';

require_once(ABS_PATH . 'libs/stats_lib.php');

?>
<fieldset class="stats">
<?php

if(@$_POST['reset-stats']) {
	$sql = "DELETE FROM ";
	switch(@$_POST['reset-stats']) {
		case 'kanji':
			$sql .= 'learning';
		break;
		case 'vocab':
			$sql .= 'jmdict_learning';
		break;
		case 'reading':
			$sql .= 'reading_learning';
		break;
		case 'kana':
			$sql .= 'kana_learning';
		break;
		default:
			die('Unknown reset category');
		break;
	}
	
	$sql .= ' WHERE user_id = ' . (int) $_SESSION['user']->get_id();

	if(! mysql_query($sql))
		echo '<div class="error_msg">SQL error: ' . mysql_error() .  '</div>';
	else
		echo '<div class="success_msg">All ' . ($_POST['reset-stats'] == 'main' ? 'your' : $_POST['reset-stats']) . ' stats have been reset</div>';
}

switch($cur_type)
{
	case 'kanji':
	?>
	<legend>Kanjis</legend>
	[<a href="<?php echo get_page_url(PAGE_PLAY, array('type' => 'kanji')); ?>">Play</a>]
<?php
		if ($row->c == 0)
			echo "<div class=\"mynotice\">You do not have any learning statistics for kanji yet. You need to <a href=\"" . get_page_url(PAGE_PLAY, array('type' => 'kanji')) . "\">practice</a> a little bit before the charts get updated.</div>";
?>
		<table class="resultbox" >
		<tr><td class="kyuu">
		<?php echo print_jlpt_levels($_SESSION['user']->get_id(), 5); ?>
		</td>
		<td>
	<?php
		echo print_grades_levels($_SESSION['user']->get_id(), 1);
	?>
		</td>
		</tr>
		<tr><td class="kyuu">
		<?php echo print_jlpt_levels($_SESSION['user']->get_id(), 4); ?>
		</td>
		<td>
		<?php
		echo print_grades_levels($_SESSION['user']->get_id(), 2);
		?>
		</td>
		</tr>
		<tr><td class="kyuu">
		<?php echo print_jlpt_levels($_SESSION['user']->get_id(), 3); ?>
		</td>
		<td>
		<?php
		echo print_grades_levels($_SESSION['user']->get_id(), 3);
		echo print_grades_levels($_SESSION['user']->get_id(), 4);
		?>
		</td>
		</tr>
		<tr><td class="kyuu">
		<?php echo print_jlpt_levels($_SESSION['user']->get_id(), 2); ?>
		</td>
		<td>
		<?php
		echo print_grades_levels($_SESSION['user']->get_id(), 5);
		echo print_grades_levels($_SESSION['user']->get_id(), 6);
		?>
		</td>
		</tr>
		<tr><td class="kyuu">
		<?php echo print_jlpt_levels($_SESSION['user']->get_id(), 1); ?>
		</td>
		<td>
		<?php
		echo print_grades_levels($_SESSION['user']->get_id(), 7);
		echo print_grades_levels($_SESSION['user']->get_id(), 8);
		echo print_grades_levels($_SESSION['user']->get_id(), 9);
		?>
		</td>
		</tr>
		</table>
	<?php
		break;
		
		case 'vocab':
			echo '<legend>Vocabulary</legend>';
			if ($row2->c == 0)
				echo "<div class=\"mynotice\">You do not have any learning statistics for vocabulary yet. You need to <a href=\"" . get_page_url(PAGE_PLAY, array('type' => 'vocab')) . "\">practice</a> a little bit before the charts get updated.</div>";
			echo print_vocab_jlpt_levels($_SESSION['user']->get_id(), 5);
			echo print_vocab_jlpt_levels($_SESSION['user']->get_id(), 4);
			echo print_vocab_jlpt_levels($_SESSION['user']->get_id(), 3);
			echo print_vocab_jlpt_levels($_SESSION['user']->get_id(), 2);
			echo print_vocab_jlpt_levels($_SESSION['user']->get_id(), 1);
		break;
	
		case 'reading':
			echo '<legend>Reading</legend>';

			if ($row3->c == 0)
				echo "<div class=\"mynotice\">You do not have any reading statistics for vocabulary yet. You need to <a href=\"" . get_page_url(PAGE_PLAY, array('type' => 'reading')) . "\">practice</a> a little bit before the charts get updated.</div>";

			echo print_reading_jlpt_levels($_SESSION['user']->get_id(), 5);
			echo print_reading_jlpt_levels($_SESSION['user']->get_id(), 4);
			echo print_reading_jlpt_levels($_SESSION['user']->get_id(), 3);
			echo print_reading_jlpt_levels($_SESSION['user']->get_id(), 2);
			echo print_reading_jlpt_levels($_SESSION['user']->get_id(), 1);
		break;
		
		case 'kana':
			echo '<legend>Kana</legend>';
			echo print_kana_levels($_SESSION['user']->get_id());
		break;
		
		case 'main':
			if ($row->c == 0)
				echo "<div class=\"mynotice\">You do not have any learning statistics for kanjis yet. You need to <a href=\"" . get_page_url(PAGE_PLAY, array('type' => 'kanji')) . "\">practice</a> a little bit before the charts get updated.</div>";

			if ($row2->c == 0)
				echo "<div class=\"mynotice\">You do not have any learning statistics for vocabulary yet. You need to <a href=\"" . get_page_url(PAGE_PLAY, array('type' => 'vocab')) . "\">practice</a> a little bit before the charts get updated.</div>";

			if ($row3->c == 0)
				echo "<div class=\"mynotice\">You do not have any reading statistics for vocabulary yet. You need to <a href=\"" . get_page_url(PAGE_PLAY, array('type' => 'reading')) . "\">practice</a> a little bit before the charts get updated.</div>";


			$level = $_SESSION['user']->get_level();
			$jlpt_level = $_SESSION['user']->get_njlpt_level();
			echo '<legend>' . $levels[$level] . ($level != $jlpt_level ? '/' . $levels[$jlpt_level] : '') . '</legend>';

			if($jlpt_level == LEVEL_J4 || $jlpt_level == LEVEL_N5)
				echo print_kana_levels($_SESSION['user']->get_id(), 710, 'Kana');
			if($level == $jlpt_level)
			{
				$num = Question::level_to_grade($level);
				$num = $num[1];
				echo print_jlpt_levels($_SESSION['user']->get_id(), $num, 710, 'Kanji');
				echo print_vocab_jlpt_levels($_SESSION['user']->get_id(), $num, 710, 'Vocab');
				echo print_reading_jlpt_levels($_SESSION['user']->get_id(), $num, 710, 'Reading');
			}
			else
			{
				$num = (int) Question::level_to_grade($level);
				if($num > 0)
					echo print_grades_levels($_SESSION['user']->get_id(), $num, 710, 'Kanji - Grade ' . $num);
				else
					echo print_jlpt_levels($_SESSION['user']->get_id(), 1, 710, 'Kanji - N1');
				$num = Question::level_to_grade($jlpt_level);
				$num = $num[1];
				echo print_vocab_jlpt_levels($_SESSION['user']->get_id(), $num, 710, 'Vocab - '. $levels[$jlpt_level]);
				echo print_reading_jlpt_levels($_SESSION['user']->get_id(), $num, 710, 'Reading - ' . $levels[$jlpt_level]);
			}
		break;
		
		default:
			echo "unknown stats type";
		break;
	}
	
	// For now: only allow resetting each category at a time
	if($cur_type != 'main') {
	?>
	<a href="#" class="reset" onclick="$(this).hide(); $('input.reset').show('bounce', {}, 200); return false;">Reset Stats ▷</a>
	<form action="<?php get_page_url(PAGE_STATS, array('type' => $type)) ?>" method="POST">
		<input type="hidden" name="reset-stats" value="<?php echo $cur_type ?>"></input>
	<input type="submit" class="reset" style="display:none;" onclick="return (confirm('Are you SURE your want to erase <?php echo ($cur_type == 'main') ? 'ALL your stats' : 'all your ' . $cur_type . ' stats'  ?>? This cannot be recovered.'));" value="Reset Stats"></input>
	</form>
	<?php
	}
	?>
	</fieldset>
<?php

?>