<?php
require_once('Question.php');

class Kana extends Question
{
	public $table_learning = 'kana_learning';
	public $table_learning_index = 'kana_id';
	public $quiz_type = 'kana';
	

	function __construct($_mode, $_level, $_grade = -2, $_data = NULL)
	{
		parent::__construct($_mode, $_level, $_grade, $_data);		
	}
	
	
	function display_choices($next_sid = '')
	{
		$submit_url = SERVER_URL . 'ajax/submit_answer/?sid=' . $this->data['sid'] . '&amp;time_created=' . (int) $this->created . '&amp;';
		$choices = $this->data['choices'];
		shuffle($choices);
		foreach($choices as $choice)
			echo '<div class="choice kanji" onclick="submit_answer(\'' .  $this->data['sid'] . '\', \'' . $next_sid . '\', \'' . $submit_url . 'answer_id=' . (int) $choice->id . '\'); return false;">' . $choice->kana . '</div>';
		
		echo '<div class="choice skip" onclick="submit_answer(\'' .  $this->data['sid'] . '\',  \'' . $next_sid . '\', \'' . $submit_url . 'answer_id=' . SKIP_ID .'\'); return false;">&nbsp;?&nbsp;</div>';
	}
	
	function display_hint()
	{
		$solution = $this->get_solution();
		echo $solution->roma . ' (' . ($solution->type == 'kata' ? 'katakana' : 'hiragana') . ')';
	}
	
	
	function display_correction($answer_id)
	{
		$solution = $this->get_solution();
		
		echo "<span class=\"kanji\">" . $solution->kana . "</span> ["  . ($solution->type == 'kata' ? 'katakana' : 'hiragana') . "] - " . $solution->roma;

		if ($answer_id != SKIP_ID && !$this->is_solution($answer_id))
		{
			echo "<br/><br/>";
			$wrong = $this->get_kana_id((int) $answer_id);
			if (! $wrong)
				log_error('Unknown Kana ID: ' . $answer_id, false, true);
			echo "<span class=\"kanji\">" . $wrong->kana . "</span> ["  . ($wrong->type == 'kata' ? 'katakana' : 'hiragana') . "] - " . $wrong->roma;
		}
	}


	function get_db_data($how_many, $grade, $user_id = -1)
	{
		if($this->is_quiz())
			log_error('Quiz mode not supported for kana.', false, true);
		elseif(!@$_SESSION['user'])
			$picks = $this->get_random_kanas ($how_many * 4);
		else
			$picks = $this->get_random_weighted_kanas ($user_id, $how_many * 4);
		
		for($i = 0; $i < count($picks) - 3; $i+=4)
		{
			$choice = array();
			$choice[0] = $picks[$i];
			$exclude = array($choice[0]->roma);
			for($j = 1; $picks[$i]->type == $choice[0]->type && $j < 4; $j++)
			{
				$choice[$j] =  $picks[$i+$j];
				$exclude[] = $choice[$j]->roma;
			}
			if($j < 4)
				if(@$_SESSION['user'])
					$filler = $this->get_random_kanas((4 - $j), $choice[0]->type, $exclude);
				else
					$filler = $this->get_random_weighted_kanas($user_id, (4 - $j), $choice[0]->type, $exclude);
			
			while($j++ < 4)	
				$choice[$j] =  $filler[3-$j];
			
			$sid = 'sid_' . md5('himitsu' . time() . '-' . rand(1, 100000));
			$data[$sid] = array('sid' => $sid, 'choices' => $choice, 'solution' => $choice[0]);
		
		//###DEBUG
			if($choice[0]->id == $choice[1]->id || $choice[0]->id == $choice[2]->id || $choice[0]->id == $choice[3]->id || $choice[1]->id == $choice[2]->id || $choice[1]->id == $choice[3]->id || $choice[2]->id == $choice[3]->id)
				log_error ("IDENTICAL KANA: " . print_r($choice, true) . "\n\n" . print_r($picks, true), true, true);
		}
	
		return $data;
	}
	
	function get_kana_id ($kana_id) 
	{	
	  $query = "SELECT  `id`, `kana`, UPPER(`roma`) AS `roma`, `type` FROM `kanas` WHERE `id` = '" . mysql_real_escape_string($kana_id) . "'";
	
	  $res = mysql_query_debug($query) or log_db_error($query);
	  return mysql_fetch_object($res);
	}
	
	function get_random_weighted_kanas($user_id, $how_many = 1, $type = NULL, $exclude = NULL)
	{
		$where_type = ($type ? 'k.type = \''. mysql_real_escape_string($type). "'" : '1');
		$where_exclude = ($exclude ? 'k.roma NOT IN (' . implode(', ', $exclude) . ')' : '1');
		$query = "SELECT * FROM (SELECT  k.`id`, k.`kana`,  UPPER(k.`roma`) AS `roma`, k.`type`, IF(l.curve IS NULL, 1000, curve)+1000*rand() as xcurve from kanas k left join " . $this->table_learning . " l on l.user_id = '" . (int) $user_id . "' AND k.id = l.". $this->table_learning_index . " WHERE $where_type AND $where_exclude ORDER BY xcurve DESC LIMIT " . $how_many . ') as temp ORDER BY type';
		
		// echo $query;
	  $res = mysql_query_debug($query)  or log_db_error($query);
	  if (mysql_num_rows($res) < $how_many)
		log_error("Can't get enough randomized kanas: " . $query, false, true);
	
		if ($how_many == 1)
			return $row;
	
		$kanas = array();
		while ($row = mysql_fetch_object($res))
			$kanas[] = $row;
		
		return $kanas;
	}

	function get_random_kanas($how_many = 1, $type = NULL, $exclude = NULL)
	{
		$where_type = ($type ? 'k.type = \''. mysql_real_escape_string($type). "'" : '1');
		$where_exclude = ($exclude ? 'k.roma NOT IN (' . implode(', ', $exclude) . ')' : '1');
		$query = "SELECT * FROM (SELECT  k.`id`, k.`kana`,  UPPER(k.`roma`) AS `roma`, k.`type` from kanas k WHERE $where_type AND $where_exclude ORDER BY RAND() LIMIT " . $how_many . ') as temp ORDER BY type';
			
	  $res = mysql_query_debug($query)  or log_db_error($query);
	  if (mysql_num_rows($res) < $how_many)
		log_error("Can't get enough randomized kanas: " . $query, false, true);
	
		if ($how_many == 1)
			return $row;
	
		$kanas = array();
		while ($row = mysql_fetch_object($res))
			$kanas[] = $row;
		
		return $kanas;
	}

}

?>