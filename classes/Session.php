<?php

class Session
{
	private $question_loader;
	
	private $session_mode;	
	private $session_level;
	
	private $session_w_sizes;
	private $session_w_grades;
	private $session_w_points;
	
	private $cur_wave;
	private $wave_level;
	private $wave_size;
	private $wave_points;
	
	private $last_wave_score = 0;
	private $tot_score = 0;
	private $score_id = 0;
	private $cur_correct = 0;
	private $cur_total = 0;
	
	private $cur_rank = -1;
	
	private $set_size = -1;
	
	private $start_time;
	
	private $questions;
	
	public $game_over = false;
	
	private static $level_pass = array(LEVEL_1 => 0.3, LEVEL_2 => 0.4, LEVEL_3 => 0.5, LEVEL_SENSEI => 0.6, LEVEL_J4 => 0.5, LEVEL_J3 => 0.5, LEVEL_J2 => 0.6, LEVEL_J1 => 0.6, LEVEL_N5 => 0.5, LEVEL_N4 => 0.5, LEVEL_N3 => 0.6, LEVEL_N2 => 0.6, LEVEL_N1 => 0.6);
	
	 public static $level_names = array(LEVEL_N5 => 'JLPT N5', LEVEL_N4 => 'JLPT N4', LEVEL_N3 => 'JLPT N3', LEVEL_N2 => 'JLPT N2', LEVEL_N1 => 'JLPT N1', LEVEL_SENSEI => 'Sensei');


	function __construct($q_class, $_level, $_mode, $_grade_or_set_id = -2)
	{
		if($_mode != DRILL_MODE && $_mode != QUIZ_MODE && $_mode != SETS_MODE && $_mode != GRAMMAR_SETS_MODE)
			log_error('Unknown mode: ' . $_mode, false, true);
			
			
		if(! @Session::$level_names[$_level])
		{
			log_error('Unknown level: ' . $_level . "\nSession::level_names:\n" . print_r(Session::$level_names, true), true, false);
			$_level = LEVEL_N3;
		}	
		$this->session_mode = $_mode;
		$this->session_level = $_level;
		$this->question_class = ucfirst($q_class);
		require_once(ABS_PATH . 'classes/' . $this->question_class . '.php');
		
		if(! $this->question_loader = new $this->question_class($_mode, $_level, $_grade_or_set_id))
			log_error("Can't instantiate session class: " . $this->question_class, true, true);
		
		if($this->is_quiz())
		{
			$array = $this->question_loader->get_default_w_sizes();
			$this->session_w_sizes =  $array[$_level];
			$array = $this->question_loader->get_default_w_grades();
			$this->session_w_grades = $array[$_level];
			$array =  $this->question_loader->get_default_w_points();
			$this->session_w_points = $array[$_level];
			
			$this->cur_rank = $_SESSION['user']->get_rank($this->get_type(), true);
			
			$this->start_time = time();
		}
		else
			$this->wave_size = $this->question_loader->default_size;

		$this->wave_grade = $this->question_loader->get_grade();
				
		$this->cur_wave = 0;
		$this->past_questions = array();
		
		
		if (! $this->init_wave())
			log_error("Can't init first wave.", false, true);
		
		
	}
	
	function cleanup_before_destroy()
	{
		if(@$_SESSION['user'] && $_SESSION['user']->is_logged_in() && $this->question_loader)
			$this->question_loader->learn_set($_SESSION['user']->get_id(), $this->questions);
	
		$cleaned_up = true;
	}
	
	function __destruct()
	{
	}
		
	function display_wave()
	{
		if(!$this->questions || $this->all_answered())
			if($this->is_quiz())
			{
				if($this->was_last_wave())
					return $this->display_final_screen();
				elseif(! $this->passed_wave())
					return $this->display_fail_screen();
				else
					$this->init_wave();
			}
			else
				$this->init_wave();
			
		if($this->is_quiz())
			$this->display_quiz_header();

		$skipped = $i = 0;
		$next_sid = 'end_of_wave_wait';
		
		foreach(array_reverse($this->questions) as $question)
			if($question->answered)
				$skipped++;
			else
			{
				$my_questions[] = array($question, $next_sid);
				$next_sid = $question->get_sid();
			}
		
		if($this->is_quiz())
		{
			$tot_size = 0;
			$extra_pref = '<div class="prog-bar">';
			$extra_suf = '';
			foreach ($this->session_w_sizes as $w => $size)
				$tot_size += $size;
			$scale = 760 / $tot_size;

			foreach ($this->session_w_sizes as $w => $size)
			{
				if($w < $this->get_cur_wave())
					$extra_pref .= '<div class="wave completed" style="width:'. (int) ($size * $scale) . 'px;" ></div>';
				elseif($w > $this->get_cur_wave())
					$extra_suf .= '<div class="wave upcoming" style="width:'. (int) ($size * $scale) . 'px;" ></div>';
			}

			$extra_suf .= '<div style="clear:both"></div></div>';
			
			foreach(array_reverse($my_questions) as $array)
				$array[0]->display_question(($i++ == 0), $array[1], ($_SESSION['user']->get_pref('quiz', 'show_prog_bar') ? $extra_pref . '<div class="wave ongoing" style="width: ' . ((int) ($skipped + $i-1) * $scale) . 'px" ></div>' .  '<div class="wave pending" style="width: ' . ((int) (count($this->questions) - $skipped - $i + 1) * $scale) . 'px"></div>' . $extra_suf : ''));

		}
		else		
			foreach(array_reverse($my_questions) as $array)
				$array[0]->display_question(($i++ == 0), $array[1]);

		echo '<div id="end_of_wave_wait" ' . ($i > 0 ? 'style="display:none;"' : '') . '>
		<p>Loading next wave, please wait...</p>
		<img alt="load icon" src="' . SERVER_URL . 'img/ajax-loader.gif"/>
		</div>';
		
		if($this->is_quiz())
			$this->display_quiz_footer();

		echo '<div id="ajax-result"></div>';
		echo '<div id="ajax_edit_form" style="display:none;"></div>';
		echo '<div id="solutions"></div>';
		insert_js_snippet('sol_displayed = 0;');
		
		return true;
	}
	
	function display_final_screen()
	{
		$this->game_over = true;
		$this->tot_score += $this->question_loader->completion_bonuses[$this->session_level];
		?>
		<div class="result_screen">
			<img class="mascots" src="<?php echo SERVER_URL ?>img/won.png" alt="lost" />
			<p>Congratulation, you succesfully finished <i><?php echo Session::$level_names[$this->session_level]; ?></i> level.</p>
			<?php 
			$this->show_final_score(); 
			?>
		</div>
		<?php
		
		return false;
	}
	
	function display_fail_screen()
	{
		$this->game_over = true;
		?>
		<div class="result_screen">
			<img class="mascots" src="<?php echo SERVER_URL ?>img/lost.png" alt="lost" />
			<p>Sorry but you need to score at least <?php echo ($this->get_passing_rate() * 100) ?>% of each wave when playing <i><?php echo Session::$level_names[$this->session_level]; ?></i> level, you did only <?php echo floor(100 * $this->get_success_rate())  . "% (" . $this->get_cur_wave_correct() . "/" . count($this->questions) . ")" ?> on this wave.</p>
				<?php 
				$this->show_final_score(); 
				?>
		</div>
		<?php
		return false;
	}
	
	function show_final_score()
	{		
		$this->game_over = true;
		
		echo '<p>Your final score is <strong>' .  (int) $this->tot_score .  ' Pt' . ($this->tot_score > 1 ? 's' : '') . '</strong></p>';
		if($_SESSION['user']->is_logged_in())
		{
			$is_new_highscore = $this->save_score();
			$new_rank = $_SESSION['user']->get_rank($this->get_type(), false, 0);
			echo '<div class="rank"><img src="' . SERVER_URL . 'img/ranks/rank_' . $new_rank->short_name . '.png" alt="' . $new_rank->pretty_name . '" /></div>';

			if($is_new_highscore)
			{
				echo '<p class="highscore">This is a  new personal highscore for you!</p>';
				$_SESSION['user']->cache_highscores();
				//$_SESSION['user']->update_profile_box();
			}
				
			//DEBUG:
			//$_SESSION['user']->publish_rank_story($this->get_type(), $new_rank);

			if($new_rank->rank > 0 && ($this->cur_rank->short_name != $new_rank->short_name))
			{
				if($_SESSION['user']->get_pref('notif', 'post_news'))
					$_SESSION['user']->publish_rank_story($this->get_type(), $new_rank);
				
				if ($new_rank->rank == 1)
					echo '<p class="highscore topscore">Congratulations, your are the new ' . $this->get_nice_type() . ' <em>' . $new_rank->pretty_name . '</em> of level ' . $this->get_nice_level() . '! <small>(<a href="' . get_page_url(PAGE_SCORES, array('type' => $this->get_type()))  . '">See all highscores here</a>)</small></p>';
				elseif ($new_rank->rank <= 5)
					echo '<p class="highscore topscore">Congratulations, you just became a ' . $this->get_nice_type() . ' <em>' . $new_rank->pretty_name . '</em> and entered the current global highscore list at position #' . $new_rank->rank . ' for level ' . $this->get_nice_level() . '. <small>(<a href="' . get_page_url(PAGE_SCORES, array('type' => $this->get_type()))  . '">See all highscores here</a>)</small></p>';
				else
					echo '<p class="highscore">You were just promoted to ' . ucfirst($this->get_type()) . ' <em>' . $new_rank->pretty_name . '</em> for level ' . $this->get_nice_level() . '. <small>(<a href="' . get_page_url(PAGE_SCORES, array('type' => $this->get_type()))  . '">See current highscores here</a>)</small></p>';
			}
			else
				echo '<p>Your current global Kanji Box ranking is: <strong>' . $new_rank->pretty_name . '</strong> <small>(<a href="' . get_page_url(PAGE_SCORES, array('type' => $this->get_type()))  . '">See current highscores here</a>)</small></p>';
		
		}
		else
			echo '<p>In order to save your score and rank (as well as access other features of Kanji Box), you need to <a href="' . get_page_url(PAGE_PLAY, array('save_first_game' => 1)) . '" requirelogin="1">log into this application</a>.</p>';

		$this->display_play_again();
		
	}
	
	function init_wave()
	{
		if($this->is_quiz())
		{
			if($this->was_last_wave() || $this->game_over)
				return false;
				
			$this->wave_grade = $this->session_w_grades[$this->cur_wave];
			$this->wave_size = $this->session_w_sizes[$this->cur_wave];
			$this->wave_points = $this->session_w_points[$this->cur_wave];
		}
		
		$this->last_wave_score = 0;
		
		if(@$this->questions)
			foreach($this->questions as $sid => $question)
			{
				if(! $question->is_answered())
					log_error('Wave was discarded before completion: ' . $sid . " not answered\nSession->past_questions: " . print_r($this->past_questions, true), true, false);
				$this->past_questions[$sid] = time();
			}
			
		
		$this->questions = $this->question_loader->load_questions($this->wave_size, $this->get_cur_grade());
		
		return true;
	}

	function load_next_wave()
	{
		if(@$_SESSION['user'])
		{
			$this->question_loader->learn_set($_SESSION['user']->get_id(), $this->questions);
			//$_SESSION['user']->update_profile_box();
		}
		
		if(! $this->all_answered())
			return false;

		if (! $this->passed_wave())
			return false;
		if($this->is_quiz())
			$this->save_score();

		$this->cur_wave++;
		return $this->init_wave();			
	}
	
	function display_quiz_header()
	{
	?>
		<table id="quiz-header" class="twocols">
			<tr><td>
			<div class="info">Level: <?php echo Session::$level_names[$this->session_level] ?> - Wave: <?php echo ($this->cur_wave + 1); ?> <a href="#" onclick="do_load('<?php echo SERVER_URL ?>ajax/stop_quiz/', 'session_frame'); return false;">[stop]</a></div></td>
			<td style="text-align: right; padding: 0; margin: 0;">
				<div id="countdown" name="countdown" ></div>
				<?php 
					insert_js_snippet('init_countdown(' . $this->question_loader->get_quiz_time() . ');'); 
					if(reset($this->questions)->is_asked())
						insert_js_snippet('set_coutdown_to(0);'); 
				?>
			</td>
			</tr>
		</table>
		<?php
	}
	
	function display_quiz_footer()
	{
		echo '<div id="quiz_footer"><div id="score">' . $this->get_score_str() .  '</div></div>';
	}
	
	function get_score_str()
	{
		return (int) $this->tot_score . ' Pt' . ($this->tot_score > 1 ? 's' : '') . ' - (' . $this->cur_correct . '/' . ($this->cur_total) . ')';
	}
	
	function was_last_wave()
	{
		return ($this->cur_wave >= count($this->session_w_sizes) || $this->cur_wave >= count($this->session_w_grades) || $this->cur_wave >= count($this->session_w_points));
	}
	
	function passed_wave()
	{
		return (!$this->is_quiz() || ($this->get_success_rate() >= $this->get_passing_rate()));
	}
	
	function get_passing_rate()
	{
		$array = Session::$level_pass;
		return $array[$this->session_level];
	}
	
	function display_solution($sid, $answer_id)
	{
		if(! isset($this->questions[$sid]))
		{
			//	log_error('Repeat question id: ' . $sid . "\n\nHow long ago: " . (time() - $this->past_questions[$sid]) . "\nSession->past_questions: " . print_r($this->past_questions, true), false, false);
			if(! @$this->past_questions[$sid])
			{			
				// log_error('Unknown question id: ' . $sid . "\n\nSession->past_questions: " . print_r($this->past_questions, true), true, false);
				return '*unknown*';
			}
			else
				return '*duplicate*';
		}
		elseif($this->questions[$sid]->is_answered())
		{
			return '*duplicate*';
		}
		
		if($answer_id == SKIP_ID)
			$class =  'skipped';
		elseif($this->questions[$sid]->is_solution($answer_id))
			$class =  'correct';
		else
			$class =  'wrong';
		
		$div_id = 'sol_' . $sid;
		echo '<div id="' . $div_id . '" class="solution ' . $class . '">';
		
		$this->questions[$sid]->display_correction($answer_id);

		$edit_button = $this->questions[$sid]->edit_button_link();
		if($this->questions[$sid]->has_feedback_options() || $edit_button) {
			echo '<div class="icon-buttons">';
			echo $edit_button;
			if($this->questions[$sid]->has_feedback_options())
				echo '<a class="icon-button ui-state-default ui-corner-all" title="Report a problem with this question..." href="#" onclick="show_feedback_dialog(\'' . SERVER_URL . '\', \'' . $sid . '\'); return false;"><span class="ui-icon ui-icon-comment"></span></a>';	
			echo '</div>'; // <div style="clear:both;">
		}
		echo '</div>';

		return $class;
	}
	
	function register_answer($sid, $answer_id, $time = 0)
	{
		if(!@$this->questions[$sid] || $this->questions[$sid]->is_answered())
			return false;
		
		$this->cur_total++;
		if ($this->questions[$sid]->register_answer($answer_id, $time))
			$this->cur_correct++;
		if($this->is_quiz())
		{
			$score_diff = ceil($this->questions[$sid]->score_coef() * $this->wave_points);
			$this->last_wave_score += $score_diff;
			$this->tot_score = max(0, $this->tot_score + $score_diff);
		}
	}
	
	function get_question($sid) {
		return @$this->questions[$sid];
	}

	function all_asked()
	{
		foreach($this->questions as $question)
			if(! $question->asked)
				return false;
		
		return true;
	}
	
	function all_answered()
	{
		foreach($this->questions as $question)
			if(! $question->answered)
				return false;
		
		return true;
	}
	
	function get_success_rate()
	{
		$tot = $success = 0;
		foreach($this->questions as $question)
			if($question->is_answered())
			{
				$tot++;
				if($question->is_correct())
					$success++;
			}
		if($tot != 0)
			return (float) ($success / $tot);
		else
			return 1;
	}
	
	function get_cur_wave_correct()
	{
		$tot = 0;
		foreach($this->questions as $question)
			if($question->is_answered() && $question->is_correct())
				$tot++;
		return $tot;
	}

	function save_score()
	{
		if (! $_SESSION['user']->is_logged_in() || $this->tot_score == 0)
			return false;
			
		if ($this->score_id)
			$query = 'UPDATE `games` SET ';
		else
			 $query = 'INSERT INTO `games` SET `user_id`=' .  $_SESSION['user']->get_id() . ', `level` = \'' . mysql_real_escape_string($this->session_level) . '\', `date_started` = \'' . date("Y-m-d H:i:s", $this->start_time) . '\', ';
	
		$query .= '`score` = ' . ((int) $this->tot_score) . ', `date_ended` = \'' . date("Y-m-d H:i:s") . '\'';
		$query .= ", `type` = '".  $this->get_type() . "'";

		if ($this->score_id)
		{
			$query .= ' WHERE `id` = ' . (int) $this->score_id;
			mysql_query_debug($query) or log_db_error($query);
		}
		else
		{
			mysql_query_debug($query) or log_db_error($query);
			$this->score_id = mysql_insert_id();
		}	

		if($this->score_id && $this->is_highscore())
		{
			$query = 'UPDATE users SET ' .  $this->get_type() .'_highscore_id = ' . (int) $this->score_id . ' WHERE id = ' .  $_SESSION['user']->get_id();
			// if($_SESSION['user']->is_admin())
			// {
			// 	echo $query;
			// 	die();
			// }
			mysql_query_debug($query) or log_db_error($query);
			return true;
		}
		else
			return false;
	}
	

	function is_highscore()
	{
		if (!$_SESSION['user']->is_logged_in() || $this->tot_score == 0)
			return false;
			
		if(! $this->score_id)
			return $this->save_score();
		
		return $_SESSION['user']->is_highscore($this->score_id, $this->session_level, $this->question_loader->quiz_type);	
	}
	
	function get_grade_options()
	{
		return $this->question_loader->get_grade_options();
	}

	function get_params()
	{
		return array('mode' => $this->get_mode(), 'type' => $this->get_type(), 'level' => $this->get_level());
	}
	
	function stop_quiz()
	{
		?>
		<div class="result_screen">
			<p>You stopped this game before completion.</p>
				<?php $this->show_final_score(); ?>
		</div>
		<?php
		return false;
	}
	
	function display_play_again()
	{
		echo '<p class="play_again"><a href="' . get_page_url(PAGE_PLAY, array('type' => $this->get_type(), 'level' => $this->get_level(), 'mode' => $this->get_mode())) . '">Play Again</a></p>';
	}
	
	function feedback_form_options($sid)
	{
		if (!$_SESSION['user']->is_logged_in() || !isset($this->questions[$sid]))
			return false;
			
		return $this->questions[$sid]->feedback_form_options();
	}

	function get_set_count()		{
		if($this->set_size <= 0) {
			$set_id = $this->get_set_id();
			if(! $set_id)
				return 0;
			if($this->get_type() == 'kanji')
				$type = 'kanji';
			else
				$type = 'vocab';
			$res = mysql_query("SELECT COUNT(*) AS c FROM learning_set_" . $type . " WHERE set_id = " . (int) $set_id) or die(mysql_error());
			$row = mysql_fetch_object($res);
			
			$this->set_size = $row->c;
		}
		return $this->set_size;
	}

	function is_drill()	{		return ($this->session_mode == DRILL_MODE);	}
	function is_quiz()	{		return ($this->session_mode == QUIZ_MODE);	}
	function is_learning_set()	{		return ($this->session_mode == SETS_MODE);	}
	function is_grammar_set()	{		return ($this->session_mode == GRAMMAR_SETS_MODE);	}
	
	function get_type()		{		return $this->question_loader->quiz_type;	}
	function get_set_id()		{		return $this->question_loader->set_id;	}
	function get_nice_type()		{		return ucfirst($this->question_loader->quiz_type);	}
	function get_level()		{		return $this->session_level;	}
	function get_nice_level()		{		return Session::$level_names[$this->session_level];	}
	function get_mode()	{		return $this->session_mode;	}
	function get_cur_wave()	{		return $this->cur_wave;	}
	function get_cur_grade()	{		return $this->wave_grade;	}

	//Debug
	function set_cur_wave($_cur_wave)	{		$this->cur_wave = $_cur_wave;	}

}


?>
