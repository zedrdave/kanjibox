<?php

abstract class Question
{
	public $default_size = 10;
	public $asked = false;
	public $answered = false;
	public $correct = false;
	public $learnt = false;
	private $answered_id = -1;
	
	protected $mode;
	protected $level;
	protected $data;
	protected $grade;
	
	protected $learnable = true;
	
	public $completion_bonuses = array(LEVEL_1 => 20, LEVEL_2 => 50, LEVEL_3 => 70, LEVEL_SENSEI => 100, LEVEL_N5 => 20, LEVEL_N4 => 30, LEVEL_N3 => 35, LEVEL_N2 => 50, LEVEL_N1 => 70);


	
	function Question($_mode, $_level, $_grade_or_set_id = -2, $_data = NULL)
	{
		$this->mode = $_mode;
		$this->level = $_level;
		
		if($this->mode == SETS_MODE) {
			$this->set_id = $_grade_or_set_id;
			$this->set = new LearningSet($this->set_id);
			$this->grade = $this->level_to_grade($this->level);
		}
		elseif($this->mode == GRAMMAR_SETS_MODE) {
			$this->set_id = $_grade_or_set_id;
			$this->grade = $this->level_to_grade($this->level);
		}
		else {
			if($_grade_or_set_id >= -1)
				$this->grade = $_grade_or_set_id;
			else
				$this->grade = $this->level_to_grade($this->level);
		}	
	
		$this->data = $_data;
		
		$this->created = time();
	}
	
	function get_sid()
	{
		return $this->data['sid'];
	}
	
	public function is_drill()
	{
		return ($this->mode == DRILL_MODE);
	}
	
	public function is_quiz()
	{
		return ($this->mode == QUIZ_MODE);
	}

	public function is_learning_set()
	{
		return ($this->mode == SETS_MODE);
	}

	public function is_grammar_set()
	{
		return ($this->mode == GRAMMAR_SETS_MODE);
	}

	abstract function display_choices($next_sid = 0);
	abstract function display_hint();

	function display_question($first = false, $next_sid = '', $insert_html = '')
	{
		$this->asked = true;
		echo '<div class="question question_' . strtolower(get_class($this)) . '" id="' . $this->get_sid() . '" ' . ($first ? '' : 'style="display:none;"') . ' >';
		echo $insert_html;
		echo '<div class="choices">';
		$this->display_choices($next_sid);
		echo '<div style="clear:both;"></div></div>';
		echo '<div class="hint">';
		if($this->is_quiz() && $this->use_anticheat_on_hint())
			echo '<div class="cheat-trick" style="display:block;">瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 </div>';		
		$this->display_hint();
		echo '</div>';
		
		$edit_button = $this->edit_button_link();			
		if($this->has_feedback_options() || $edit_button) {
			echo '<div class="icon-buttons">';
			echo $edit_button;
			if($this->has_feedback_options())
				echo '<a class="icon-button ui-state-default ui-corner-all" title="Report a problem with this question..." href="#" onclick="show_feedback_dialog(\'' . SERVER_URL . '\', \'' . $this->get_sid() . '\'); return false;"><span class="ui-icon ui-icon-comment"></span></a>';
			
			echo '</div>'; // <div style="clear:both;">
		}
		
		echo '</div>';
	}
	
	function use_anticheat_on_hint()
	{
		return true;
	}

	function get_solution()
	{
		return $this->data['solution'];
	}
	
	function register_answer($answer_id, $time = 0)
	{
		$this->answered = true;
		$this->answered_id = $answer_id;
		$this->correct = $this->is_solution($answer_id);
		$this->time = $time;
		return $this->correct;
	}
	
	function score_coef()
	{
		if(! $this->answered)
			return 0;
			
		if($this->answered_id == SKIP_ID)
			return 0;
			
		if($this->correct)
			return min(1, max(0.1, 1.15 * $this->time / $this->get_quiz_time()));
		else
			return - (min(0.5, max(0.1, 0.62 * $this->time / $this->get_quiz_time())));
	}

	function is_solution($id)
	{	
		$sol = $this->get_solution();
		return ($id && ($id == $sol->id));
	}

	function get_solution_id()
	{
		$sol = $this->get_solution();
		return $sol->id;
	}

	function display_correction($answer_id)
	{
		echo "### DEBUG: should override this class<br/>";
		
		if($answer_id == SKIP_ID)
			$class =  'skipped';
		elseif($this->is_solution($answer_id))
			$class =  'correct';
		else
			$class =  'wrong';
		
		echo $class;
	}
		
	abstract function get_db_data($how_many, $grade, $user_id = -1);
	
	function load_questions($how_many, $grade)
	{
		if($this->is_drill() || $this->is_learning_set() || $this->is_grammar_set())
		{
			if(@$_SESSION['user']->get_id())
				$items = $this->get_db_data($how_many, $grade, $_SESSION['user']->get_id());
			else
				force_reload("You need to be logged in order to use drill mode.");
		}
		else
			$items = $this->get_db_data($how_many, $grade);
		
		if($items && count($items))
		{
			$class_name = get_class($this);
			$questions = array();
			foreach($items as $item)
				$questions[$item['sid']] = new $class_name($this->mode, $this->level, (($this->is_learning_set() || $this->is_grammar_set()) ? $this->set_id : $grade), $item);
			return $questions;
		}
		else
			return NULL;
	}
	
	function get_next_grade($grade)
	{
		if ($grade[0] == 'N')
		{
			if ($grade == 'N1')
				return -1;
			else
				return 'N' . ($grade[1]-1);
		}
		else
		{
			if ($grade >= 9)
				return -1;
			else
				return $grade+1;

		}
	}
	
	
	function learn_set($user_id, $learning_set, $learn_others = true)
	{
		if(! $this->is_learnable())
			return true;
			
		if (! count($learning_set))
			return false;

		$init_values = $good_ids = $bad_ids = array();
		foreach($learning_set as $question)
		{
			if(!$question->is_answered() || $question->is_learnt())
				continue;
				
			$init_values[] = '(' . $user_id . ', ' . $question->get_solution_id() . ', ' . 'NOW())';
			
			if ($question->is_correct())
				$good_ids[] = $question->get_solution_id();
			else
			{
				$bad_ids[] = $question->get_solution_id();
				if($learn_others && $question->get_answered_id() != SKIP_ID)
				{
					$init_values[] = '(' . $user_id . ', ' . $question->get_answered_id() . ', ' . 'NOW())';
					$bad_ids[] = $question->get_answered_id();
				}
			}
			
			$question->learnt = true;
		}
		
		if(! count($init_values))
			return false;
		
		mysql_query_debug('BEGIN');
		$query = "INSERT IGNORE INTO " . $this->table_learning . " (user_id, " . $this->table_learning_index . ", date_first) VALUES " . implode(',', $init_values);
		
		mysql_query_debug($query) or log_db_error($query, false, true);
		
		if(count($bad_ids))
		{
			$query = "UPDATE " . $this->table_learning . " SET total = total+1, curve = LEAST(2000, tan(atan(curve/1000-1)+0.15)*1000+1000) where `user_id` = '". $user_id . "' AND " . $this->table_learning_index . " IN (" . implode(',', $bad_ids) . ")";
			mysql_query_debug($query) or log_db_error ($query, false, true);
			
			// if($_SESSION['user']->is_admin())
			// 	echo "<pre>$query</pre>";
			 
		}
		
		if(count($good_ids))
		{
			$query = "UPDATE " . $this->table_learning . " SET total = total+1, curve = GREATEST(100, tan(atan(curve/1000-1)-0.2)*1000+1000) where `user_id` = '". $user_id . "' AND " . $this->table_learning_index . " IN (" . implode(',', $good_ids) . ")";
			mysql_query_debug($query) or log_db_error ($query, false, true);
		}
		
		mysql_query_debug('COMMIT');
		return true;
	}
	
	function get_grade_options()	{	return NULL;	}

	static function level_to_grade($_level)
	{
		switch($_level)
		{
			case LEVEL_1:
				$grade = 1;
			break;
			case LEVEL_2:
				$grade = 3;
			break;
			case LEVEL_3:
				$grade = 6;
			break;
			case LEVEL_SENSEI:
				$grade = -1;
			break;
			case LEVEL_N5:
				$grade = 'N5';
			break;
			case LEVEL_N4:
				$grade = 'N4';
			break;
			case LEVEL_N3:
				$grade = 'N3';
			break;
			case LEVEL_N2:
				$grade = 'N2';
			break;
			case LEVEL_N1:
				$grade = 'N1';
			break;
			default:
				$grade = $_level;
			break;
				
		}
		
		return $grade;
	}
	
	static function level_to_num($_level)
	{
		switch($_level)
		{
			case LEVEL_SENSEI:
			// 	$num = 0;
			// break;
			case LEVEL_N5:
			case LEVEL_N4:
			case LEVEL_N3:
			case LEVEL_N2:
			case LEVEL_N1:
				$num = $_level;
			break;
			default:
				$num = -1;
			break;
		}
		
		return $num;
	}
	
	function get_level_num()
	{
		return $this->level_to_num($this->level);
	}
	
	public function get_default_w_sizes()
	{ 
		return array(
		LEVEL_SENSEI => array(	4,		6,	 	6,	 	6,	 	10,	15,  	15, 	5,		10,	5),
		LEVEL_N5  => array(5,	 	10,	10,	15,	10,	10,	5),
		LEVEL_N4  => array(5,			5,		15,		10,		15,		10,		10,		10,		5),
		LEVEL_N3 => array(5, 	5,		5,		15,	10,	15,	5,		10,	5),
		LEVEL_N2 => array(5, 	5,		5,		15,	10,	15,	5,		10,	5),
		LEVEL_N1 => array(4, 	4,		15,	15,	10,	10,	15,	10,	5)
		);
	}
	
	public function get_default_w_grades()
	{
		return array(
		LEVEL_SENSEI => array(3, 	4, 		5, 		6, 		6, 		8,		9, 		-1, 	-1,	-1),
		LEVEL_N5 => array('N5', 	'N5', 			1,	 	'N5',		1,	 		2,		'N4'),
		LEVEL_N4 => array('N5', 	'N4', 		'N4',		2,			'N4',		3,		'N4',		4,		'N3'),
		LEVEL_N3 => array('N5', 	'N4', 		'N3',		'N3', 		4, 		'N3',		'N3', 		5,		'N2'),
		LEVEL_N2 => array('N5', 	'N4', 		'N3',		'N2', 		5, 		'N2',		'N2', 		6,		'N1'),
		LEVEL_N1 => array('N4', 	'N3', 		'N2', 		'N1',		9, 		'N1',		'N1',		9,		-1)
		);
	}
	
	public function get_default_w_points()
	{
		return array(
		LEVEL_SENSEI => array(1, 		2, 		2, 		8, 		15, 	30,	50, 	65, 	80,	75),
		LEVEL_N5 => array(1, 		4, 	 		6,			12,	 		20,		 34,		16),
		LEVEL_N4 => array(1, 		4, 			8, 			12, 			16,	 	12,		35,	 	50,		45),
		LEVEL_N3 => array(1, 		4,	 		8, 			16, 			22,		18,		35,		65,	 	45),
		LEVEL_N2 => array(1, 		4,	 		8, 			16, 			22,		18,		35,		65,	 	45),
		LEVEL_N1 => array(1, 		4, 			8, 			16, 			24,		18,		36,	 	70,		55)
		);
	}
	
	public function feedback_form_options()
	{
		return "No feedback options for this quiz";
	}
	
	function has_feedback_options()
	{
		return false;
	}

	function edit_button_link()
	{
		return '';
	}
	
	static function get_quiz_time() { return 31; }
	function is_learnable() { return $this->learnable; }
	function is_correct() { return $this->correct; }
	function is_answered() { return $this->answered; }
	function is_asked() { return $this->asked; }
	function is_learnt() { return $this->learnt; }
	function get_answered_id() { return $this->answered_id; }
	function get_level()		{ return $this->level;	}
	function get_grade()		{ return $this->grade; 	}
}

?>