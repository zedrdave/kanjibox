<p>Unfortunately, Facebook no longer supports Application Tab and this feature of <a href="http://kanjibox.net/kb/">KanjiBox</a> has been disabled. Please contact me if you think you are getting this message in error.</p>
<?php

/*
// this defines some of our basic setup
require_once '../../libs/lib.php';

require_once ABS_PATH . get_mode() .'.config.php';

include_once ABS_PATH . 'facebook-client/facebook.php';
require_once ABS_PATH . 'libs/stats_lib.php';

$facebook = new Facebook($api_key, $secret);

get_db_conn();

$fb_id = (int) $_REQUEST['fb_sig_profile_user'];
$user = new User(array('fb_id' => $fb_id), false);

$levels = Session::$level_names;
include_css('stats.css');

echo '<div id="kbtab">';

echo "<p class=\"summary\"><fb:name firstnameonly=\"true\" uid=\"$fb_id\" useyou=\"false\" capitalize=\"true\" /> is training at level: <strong>" . $levels[$user->get_level()] . "</strong> on <a href=\"". get_page_url() . "\">Kanji Box</a>.</p>";

$query = 'SELECT SUM(c) as c FROM ((SELECT COUNT(*) as c FROM learning l WHERE l.user_id = ' . (int) $user->get_id() . ' LIMIT 1) UNION (SELECT COUNT(*) as c FROM jmdict_learning jl WHERE jl.user_id = ' . (int) $user->get_id() . ' LIMIT 1) UNION (SELECT COUNT(*) as c FROM reading_learning rl WHERE rl.user_id = ' . (int) $user->get_id() . ' LIMIT 1)) as t';

$res = mysql_query($query) or log_db_error($query, true, true);
$row = mysql_fetch_object($res);

$info_fields = array();

if($row->c > 0)
{
	foreach(array('kanji' => 'Kanji', 'vocab' => 'Vocabulary', 'reading' => 'Reading') as $type => $type_desc)
	{
		echo '<fieldset class="profile-box"><legend><a href="' . get_page_url(PAGE_PLAY, array('type' => $type, 'mode' => QUIZ_MODE)) .'">' . $type_desc . '</a></legend>';

		$game = $user->get_highscore($user->get_level(), $type);
		if($game)
		{
			$rank = $user->get_rank($type);
			echo '<div class="game">';
			echo '<img class="rank-icon" src="'. SERVER_URL . 'img/ranks/rank_' . $rank->short_name . '.png" />';
			echo '<div class="ranking">Rank: <strong>' . $rank->pretty_name . '</strong></div>';
			echo '<div class="highscore">Highscore: <strong>' . $game->score . ' Pts</strong></div>';
			echo '<div style="clear:both;" />';
			echo '</div>';
			

		}

		$jlpt_level = $user->get_njlpt_level();
	
		$wide_bar = 720;
		switch($type)
		{
			case 'kanji':
				if($user->get_level() == $jlpt_level)
				{
					$big = print_jlpt_levels($user->get_id(), $jlpt_level, $wide_bar, 'Learning stats - ' . $jlpt_level);

				}
				else
				{
					$num = (int) Question::level_to_grade($user->get_level());
					if($num > 0)
					{
						$big =  print_grades_levels($user->get_id(), $num, $wide_bar, 'Learning stats - Grade ' . $num);
					}
					else
					{
						$big =  print_jlpt_levels($user->get_id(), 1, $wide_bar, 'Learning stats - 1-kyuu');
					}
				}
			break;
		
			case 'vocab':
				$num = Question::level_to_grade($jlpt_level);
				$num = $num[1];
				$big =  print_vocab_jlpt_levels($user->get_id(), $num, $wide_bar, 'Learning stats - '. $num . '-kyuu');
		
			break;

			case 'reading':
				$num = Question::level_to_grade($jlpt_level);
				$num = $num[1];
				$big =  print_reading_jlpt_levels($user->get_id(), $num, $wide_bar, 'Learning stats - ' . $num . '-kyuu');
			break;
		}
		echo $big;
		echo '</fieldset>';
		
	}			
}
else
	echo "<p class=\"details\"><fb:pronoun uid=\"$fb_id\" useyou=\"false\" capitalize=\"true\" /> hasn't logged any scores or statistics yet.</p>";

echo '</div>';
*/
?>