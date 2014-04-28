<?php

require_once('Question.php');

define('SOLUTION_SPACE', '____');
mb_internal_encoding('UTF-8');

class Text extends Vocab
{
	public $table_learning = 'jmdict_learning';
	public $table_learning_index = 'jmdict_id';
	public $quiz_type = 'text';

	function __construct($_mode, $_level, $_grade = 0, $_data = NULL)
	{
		parent::__construct($_mode, $_level, $_grade, $_data);
	}
	

	function useAnticheatOnHint()
	{
		return false;
	}

	
	function displayHint()
	{
		$solution = $this->getSolution();
		
		if(!isset($solution->hint_str))
		{
			if($this->isGrammarSet())
				$strings = Text::getSentenceWithHints($solution, $this->isQuiz(), $solution->pos_start, $solution->pos_end, $solution->id);
			else
				$strings = Text::getSentenceWithHints($solution, $this->isQuiz(), -1, -1, $solution_id = $solution->id);
				
			$solution->hint_str = $strings[0];
			$solution->answer_str = $strings[1];
			if(!$strings[2])
				log_error('Invalid question: Ex ID: ' . $solution->example_id . ' jmdict_id: ' . $solution->id, false, true);
			
		}
	
		echo '<span lang="ja" xml:lang="ja">' . $solution->hint_str . '</span>';
		
		if(!$this->isQuiz()) {
			echo  make_toggle_visibility("<span class=\"meaning\">" . $solution->example_english . "</span><br/>");
		}
		else
			echo  make_toggle_visibility("<span class=\"meaning\">" . $solution->example_english . "</span><br/>", 3000);
		
		//echo $solution->fullgloss;
	}
	
	static function getSentenceWithHints($sentence, $is_quiz = false, $hide_pos_start = -1, $hide_pos_end = -1, $solution_id = -1) {
		
		if(!$sentence || !$sentence->example_id)
			return array('', '', true);
		
		$query = 'SELECT ep.*, k.id, j.word, j.reading, j.njlpt, j.njlpt_r, jx.gloss_english AS fullgloss, j.katakana, j.usually_kana, k.kanji, k.njlpt AS kanji_jlpt FROM example_parts ep LEFT JOIN jmdict j ON j.id = ep.jmdict_id LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id LEFT JOIN kanjis k ON k.id = ep.kanji_id WHERE ep.example_id = ' . (int) $sentence->example_id . ' ORDER BY pos_end DESC';
		$res = mysql_query_debug($query) or log_db_error($query);
  	
		
		$reading_pref = $_SESSION['user']->get_pref('drill', 'show_reading');
		$level = $_SESSION['user']->getLevel();
		
		if($hide_pos_start >= 0 && $hide_pos_end >= 0) {
			$hint_str = mb_substr($sentence->example_str, 0, $hide_pos_start) . str_repeat('^', $hide_pos_end - $hide_pos_start) . mb_substr($sentence->example_str, $hide_pos_end);
			$answer_str = $hint_str;
			$replaced_solution = true;
		}
		else {
			$answer_str = $sentence->example_str;
			$hint_str = $sentence->example_str;
			$replaced_solution = false;
		}
		
		
		while($row = mysql_fetch_assoc($res))
		{
			$pref = mb_substr($hint_str, 0, $row['pos_start']);
			$suf = mb_substr($hint_str, $row['pos_end']);
			$word = mb_substr($hint_str, $row['pos_start'], ($row['pos_end']-$row['pos_start']));

			$pref_answer = mb_substr($answer_str, 0, $row['pos_start']);
			$suf_answer = mb_substr($answer_str, $row['pos_end']);
			$word_answer = mb_substr($answer_str, $row['pos_start'], ($row['pos_end']-$row['pos_start']));
			
			if(($row['pos_start'] >= $hide_pos_start && $row['pos_start'] < $hide_pos_end) 
                 || ($row['pos_end'] > $hide_pos_start && $row['pos_end'] <= $hide_pos_end)
                 || ($row['pos_start'] <= $hide_pos_start && $row['pos_end'] >= $hide_pos_end)) {
				continue;
			}
			elseif($solution_id >= 0 && $row['jmdict_id'] == $solution_id)
			{
				$hint_str = $pref . SOLUTION_SPACE . $suf;
				$answer_str = $pref_answer . '<span class="hilite">' . $word . '</span>' . $suf_answer;
				$replaced_solution = true;
			}
			else //if($row['need_furi'] || $row['proper_noun'])
			{
				if($row['proper_noun'] || ($row['need_furi'] && !$row['jmdict_id']))
				{
					 $word_brokendown = preg_replace('/([^\\x{3040}-\\x{30FF}])/u', "'<span class=\"kanji_detail\" href=\"#\" onclick=\"show_kanji_details(\'\\1\', \''. SERVER_URL . 'ajax/kanji_details/kanji/'.urlencode('\\1').'\');  return false;\">\\1</span>'", $word);
					
					$hint_str = $pref . $word_brokendown . $suf;
					$answer_str = $pref_answer . $word_brokendown . $suf_answer;
				}
				elseif($row['jmdict_id'])
				{
                $word_and_reading = ($row['katakana'] || $row['usually_kana'] || $row['word'] == $row['reading']) ? $row['reading'] : $row['word'] . ' 「' . $row['reading'] . '」';
                     
					if ($level > $row['njlpt'])
						$hint_str = $pref . '<span class="hilite defined">' . $word . '<span class="definition">' . $word_and_reading . '<br/>' . $row['fullgloss'] . '<br/><span class="level">JLPT: N' . $row['njlpt'] . ' (Reading: N' .  $row['njlpt_r'] . ')</span></span></span>' . $suf;
					else if (!$is_quiz) { 
							if(($reading_pref != 'never') 
								&& ($reading_pref == 'always' || ($level > $row['njlpt_r'])))
								$hint_str = $pref . '<span class="defined" onclick="do_load_vocab(this, \'' . SERVER_URL . 'ajax/get_vocab/jmdict_id/' . $row['jmdict_id'] . '/?learn_vocab=1&word=' . $word . '\');$(this).prop(\'onclick\',\'\').unbind(\'click\')">' . $word . '<span class="definition">' . $word_and_reading . '<br/><span class="level">JLPT: N' . $row['njlpt'] . ' (Reading: N' .  $row['njlpt_r'] . ')</span><p>Click on word for translation...</p></span></span>' . $suf;
							else
								$hint_str = $pref . '<span class="defined" onclick="do_load_vocab(this, \'' . SERVER_URL . 'ajax/get_vocab/jmdict_id/' . $row['jmdict_id'] . '/?learn_vocab=1&learn_reading=1&word=' . $word . '\');$(this).prop(\'onclick\',\'\').unbind(\'click\')">' . $word . '<span class="definition"><span class="level">JLPT: N' . $row['njlpt'] . ' (Reading: N' .  $row['njlpt_r'] . ')</span><p>Click on word for reading and translation...</p></span></span>' . $suf;
					}
					else if ($is_quiz 
								&& ($level > $row['njlpt_r']))
						$hint_str = $pref . '<span class="defined">' . $word . '<span class="definition">' . $word_and_reading . '<br/><span class="level">JLPT: N' . $row['njlpt'] . ' (Reading: N' .  $row['njlpt_r'] . ')</span></span></span>' . $suf;
					
					
					$answer_str = $pref_answer . '<span class="defined">' . $word_answer . '<span class="definition">' . $word_and_reading . '<br/>' . $row['fullgloss'] . '<br/><span class="level">JLPT: N' . $row['njlpt'] . ' (Reading: N' .  $row['njlpt_r'] . ')</span></span></span>' . $suf_answer;
				}
				elseif($row['kanji'])
				{
					
					if((!$is_quiz && ($reading_pref != 'never') && ($reading_pref == 'always' || ($this->getLevelNum() > $row['kanji_jlpt'])))
							|| ($is_quiz && ($level > $row['kanji_jlpt'])))
						$hint_str = $pref . '<span class="kanji_detail" href="#" onclick="show_kanji_details(\'' . $row['kanji'] . '\', \''. SERVER_URL . 'ajax/kanji_details/kanji/'. urlencode($row['kanji']) . '\');  return false;">'. $row['kanji'] . '</span>' . $suf;

					$answer_str = $pref_answer . '<span class="kanji_detail" href="#" onclick="show_kanji_details(\'' . $row['kanji'] . '\', \''. SERVER_URL . 'ajax/kanji_details/kanji/'. urlencode($row['kanji']) . '\');  return false;">'. $row['kanji'] . '</span>' . $suf_answer;

				}
			}
		}
	
		if($hide_pos_start >= 0 && $hide_pos_end >= 0) {
			$hint_str = preg_replace('/(\^)+/', SOLUTION_SPACE, $hint_str);
			$answer_str = preg_replace('/(\^)+/', '<span class="hilite">' . mb_substr($sentence->example_str, $hide_pos_start, $hide_pos_end-$hide_pos_start) . '</span>', $answer_str);
		}
		
		return array($hint_str, $answer_str, $replaced_solution);
	}
	
	function is_above_level($grade, $jlpt_level, $or_same = true)
	{		
		$add = ($or_same ? 1: 0);
		if($grade[0] == 'N')
			return ($jlpt_level < $add + (int) $grade[1]);
		elseif($grade)
			return ($jlpt_level  < 5 + $add - ceil($grade * 4 /9));
		else
			return false;
	}

	
	function displayCorrection($answer_id)
	{
		$kanjis = array();
		$solution = $this->getSolution();
		$encoding = mb_detect_encoding($solution->word);
		
		if ($answer_id != SKIP_ID && !$this->isSolution($answer_id))
		{
			if (! $wrong = $this->get_vocab_id((int) $answer_id))
				log_error('Unknown Vocab ID: ' . $answer_id, true, true);
				
			// for($i = 0; $i < mb_strlen($wrong->word, $encoding); $i++)
			// 	$kanjis[] = mb_substr($wrong->word, $i, 1, $encoding);
		}
		
		echo '<div class="main" lang="ja" xml:lang="ja">' . $solution->answer_str . '</div><div class="secondary">' . $solution->example_english . '</div>';
		
		$display_word_solution = preg_replace('/([^\\x{3040}-\\x{30FF}])/ue', "'<span class=\"kanji_detail\" href=\"#\" onclick=\"show_kanji_details(\'\\1\', \''. SERVER_URL . 'ajax/kanji_details/kanji/'.urlencode('\\1').'\');  return false;\">\\1</span>'", $solution->word);
		
		echo '<br/><span class="" lang="ja" xml:lang="ja">' . $display_word_solution . "</span> ". ($solution->katakana || $solution->word == $solution->reading ? '' : "[" . $solution->reading . "]") . " - " . $solution->fullgloss;
		
		if (@$wrong)
		{
			$display_word_wrong = preg_replace('/([^\\x{3040}-\\x{30FF}])/ue', "'<span class=\"kanji_detail\" href=\"#\" onclick=\"show_kanji_details(\'\\1\', \''. SERVER_URL . 'ajax/kanji_details/kanji/'.urlencode('\\1').'\');  return false;\">\\1</span>'", $wrong->word);
			echo "<br/><br/>";
			echo "<span class=\"\">" . $display_word_wrong . "</span>" . ($wrong->katakana || $wrong->word == $wrong->reading ? '' : " [" . $wrong->reading . "]") . " - " . $wrong->fullgloss;
		}
	}


	function getDBData($how_many, $grade, $user_id = -1)
	{
		if($grade == -1)
			$grade = 'N1';
		
		if($this->isGrammarSet()) {
			$picks = $this->get_grammar_set_questions($user_id, $how_many);
			foreach($picks as $pick)
			{
				$choice = array(0 => $pick);
				$sims = $this->get_decoy_answers($choice[0], 3, $grade);
				$i = 1;
			
				foreach($sims as $sim)
					$choice[$i++] = $sim;

				if (count($sims) < 3)
					log_error ("Not enough similar Grammar picks: " . print_r($choice, true) . "\n" . print_r($sims, true), false, true);

				$sid = 'sid_' . md5('himitsu' . time() . '-' . rand(1, 100000));
				$data[$sid] = array('sid' => $sid, 'choices' => $choice, 'solution' => $choice[0]);
		
			}
		}
		else {
			if($this->isQuiz())
				$picks = $this->get_random_vocab ($grade, $grade, $how_many);
			elseif($this->isLearningSet())
				$picks = $this->get_set_weighted_vocab($user_id, $how_many);
			else
				$picks = $this->get_random_weighted_vocab($user_id, $grade, $grade, $how_many);
		
		
		
			foreach($picks as $pick)
			{
				$choice = array(0 => $pick);
				$sims = $this->get_similar_vocab($choice[0], 3, $grade);
				$i = 1;
			
				foreach($sims as $sim)
				{
					$choice[$i++] = $sim;
				}
				if (count($sims) < 3)
					log_error ("Not enough similar Text Vocab picks: " . print_r($choice, true) . "\n" . print_r($sims, true), false, true);
			

				$sid = 'sid_' . md5('himitsu' . time() . '-' . rand(1, 100000));
				$data[$sid] = array('sid' => $sid, 'choices' => $choice, 'solution' => $choice[0]);
		
			//###DEBUG
				if($choice[0]->id == $choice[1]->id || $choice[0]->id == $choice[2]->id || $choice[0]->id == $choice[3]->id || $choice[1]->id == $choice[2]->id || $choice[1]->id == $choice[3]->id || $choice[2]->id == $choice[3]->id)
				{
					log_error ("IDENTICAL Vocab picks: " . print_r($choice, true) . "\n" . print_r($picks, true), false, true);
				}
			}
		}
		return $data;
	}
	
	function get_grammar_set_questions ($user_id, $how_many) {
		$query = "SELECT j.*, jx.*, jx.gloss_english AS `fullgloss`, e.example_str, e.english AS example_english, IF(l.curve IS NULL, 1000, l.curve)+1000*rand() as xcurve, gq.sentence_id AS example_id, gq.question_id, gq.pos_start, gq.pos_end
		FROM grammar_questions gq
		LEFT JOIN grammar_question_learning l ON l.user_id = '" . (int) $user_id . "' AND gq.question_id = l.question_id JOIN jmdict j ON j.id = gq.jmdict_id JOIN jmdict_ext jx ON jx.jmdict_id = j.id JOIN examples e ON e.example_id = gq.sentence_id ";

		$query .= 	'WHERE gq.set_id = ' . $this->set_id . ' AND gq.in_demo = 1 ORDER BY xcurve DESC';
		$query .= ' LIMIT ' . $how_many;

		$res = mysql_query_debug($query) or log_db_error($query);
		if (mysql_num_rows($res) == 0) {
				log_error("Text Mode: Can't get enough randomized grammar questions: " . $query . " (got 0)", true, true);
		}
			
		if ($how_many == 1)
			return array(mysql_fetch_object($res));
	
		$questions = array();
		while ($row = mysql_fetch_object($res))
			$questions[] = $row;
		
		shuffle($questions);
		return $questions;
		
	}
	
	function get_random_vocab ($grade1 = -1, $grade2 = -1, $how_many = 1, $exclude = NULL) 
	{		
		if ($grade1[0] == 'N')
		{
			$grade1 = $grade1[1];
			$grade2 = $grade2[1];
			$level_field = 'j.njlpt'; 
			$harder = '<=';
			$easier = '>=';
			$easiest = '5';			
		}
		else
		{
			$level_field = 'j.pri_nf'; 
			$harder = '>=';
			$easier = '<=';
			$easiest = '1';
		}
	
		
	
		$query = 'SELECT sub.*, jx.gloss_english AS `fullgloss`, e.example_str, e.english AS example_english FROM (SELECT j.`id` AS `id`, j.`word` AS `word`, j.`reading`, j.katakana, j.usually_kana, j.pos_redux, `j`.`njlpt` AS `njlpt`, `j`.`njlpt_r` AS `njlpt_r`, ea.example_id
				FROM `jmdict` j 
				LEFT JOIN example_answers AS ea ON ea.jmdict_id = j.id
			WHERE j.njlpt > 0 ';
		
		if ($grade1 > 0)
		{
			if ($grade2 > 0)
				$query .= " AND $level_field $harder " . (int) $grade1 . " AND $level_field $easier " . (int) $grade2;
			else
				$query .= " AND $level_field $harder $easiest AND $level_field $easier " . (int) $grade1; 
		}
		if($exclude)
			$query .= ' AND j.id NOT IN (' . implode(', ', $exclude) . ')';
			
		$query .= ' ORDER BY RAND()';
	
		 $query .= '  LIMIT ' . (5 * $how_many) . ') AS sub JOIN jmdict_ext jx ON jx.jmdict_id = sub.id JOIN examples e ON e.example_id = sub.example_id GROUP BY sub.id LIMIT ' . $how_many;
		
	  $res = mysql_query_debug($query) or log_db_error($query);
	  if (mysql_num_rows($res) < $how_many)
		log_error("Can't get enough randomized words: " . $query, true, true);
	
		if ($how_many == 1)
			return array(mysql_fetch_object($res));
	
		$vocabs = array();
		while ($row = mysql_fetch_object($res))
			$vocabs[] = $row;
		
		return $vocabs;
	}
	
	function get_random_weighted_vocab($user_id, $grade1 = -1, $grade2 = -1, $how_many = 1)
	{
		if ($grade1[0] == 'N')
		{
			$grade1 = $grade1[1];
			$grade2 = $grade2[1];
			$level_field = 'j.njlpt'; 
			$harder = '<=';
			$easier = '>=';
			$easiest = '5';
		}
		else
		{
			$level_field = 'j.pri_nf'; 
			$harder = '>=';
			$easier = '<=';
			$easiest = '1';
		}
		
		$query = "SELECT sub.*, jx.gloss_english AS `fullgloss`, e.example_str, e.english AS example_english FROM (SELECT j.`id` AS `id`, j.`word` AS `word`, j.`reading` AS `reading`, j.katakana, j.usually_kana, j.pos_redux, `j`.`njlpt` AS `njlpt`, `j`.`njlpt_r` AS `njlpt_r`, IF(l.curve IS NULL, 1000, l.curve)+1000*rand() as xcurve, ea.example_id
		FROM `jmdict` j 
		LEFT JOIN example_answers AS ea ON ea.jmdict_id = j.id
		LEFT JOIN " . $this->table_learning . " l on l.user_id = '" . (int) $user_id . "' AND j.id = l." . $this->table_learning_index . "
		WHERE  j.njlpt > 0 ";
	
		if ($grade1 > 0)
		{
			if ($grade2 > 0)
				$query .= " AND $level_field $harder " . (int) $grade1 . " AND $level_field $easier " . (int) $grade2;
			else
				$query .= " AND $level_field $harder $easiest AND $level_field $easier " . (int) $grade1; 
		}
	
		// if($_SESSION['user']->is_admin())
		// {
		// 	$query .= ' ORDER BY j.id = 1169610 DESC, xcurve DESC';
		//  	$query .= '  LIMIT ' . ($how_many+5) . ') AS sub JOIN jmdict_ext jx ON jx.jmdict_id = sub.id JOIN examples e ON e.example_id = sub.example_id GROUP BY sub.id LIMIT ' . $how_many;
		// }
		// else
		// {
			$query .= 	' ORDER BY xcurve DESC';
			$query .= '  LIMIT ' . (5 * $how_many) . ') AS sub JOIN jmdict_ext jx ON jx.jmdict_id = sub.id JOIN examples e ON e.example_id = sub.example_id GROUP BY sub.id LIMIT ' . $how_many;
		// }

	  $res = mysql_query_debug($query) or log_db_error($query);
	  if (mysql_num_rows($res) < $how_many)
		log_error("Text mode: Can't get enough randomized vocab: " . $query . "\n(needed: $how_many, got: " . mysql_num_rows($res) . ")", true, true);
	
		if ($how_many == 1)
			return array(mysql_fetch_object($res));
	
		$vocab = array();
		while ($row = mysql_fetch_object($res))
			$vocab[] = $row;
		
		shuffle($vocab);
		return $vocab;
	
	}
	
	function get_set_weighted_vocab($user_id, $how_many = 1)
	{
		// $query_where = " WHERE lse.set_id = $this->set_id";
			
		// $translator_mode = ($_SESSION['user']->get_pref('lang', 'vocab_lang') != 'en' && $_SESSION['user']->get_pref('lang', 'translator_mode'));

		$query = "SELECT j.*, jx.*, jx.gloss_english AS `fullgloss`, e.example_str, e.english AS example_english, e.example_id FROM (SELECT l.`jmdict_id` AS `id`, IF(l.curve IS NULL, 1000, l.curve)+1000*rand() as xcurve, ea.example_id
		FROM learning_set_vocab ls
		JOIN example_answers AS ea ON ea.jmdict_id = ls.jmdict_id
		LEFT JOIN $this->table_learning l ON l.user_id = '" . (int) $user_id . "' AND ls.jmdict_id = l.$this->table_learning_index ";

		$query .= 	'WHERE ls.set_id = ' . $this->set_id . ' ORDER BY xcurve DESC';
		$query .= '  LIMIT ' . (5 * $how_many) . ') AS sub
		JOIN `jmdict` j ON j.id = sub.id JOIN jmdict_ext jx ON jx.jmdict_id = sub.id JOIN examples e ON e.example_id = sub.example_id GROUP BY sub.id LIMIT ' . $how_many;

		$res = mysql_query_debug($query) or log_db_error($query);
		if (mysql_num_rows($res) == 0) {
				log_error("Text Mode: Can't get enough randomized vocab: " . $query . " (got 0)", true, true);
		}
			
		if ($how_many == 1)
			return array(mysql_fetch_object($res));
	
		$kanjis = array();
		while ($row = mysql_fetch_object($res))
			$kanjis[] = $row;
		
		shuffle($kanjis);
		return $kanjis;
	
	}
	
	function get_decoy_answers ($question, $howmany = 1, $grade = -1) 
	{		
		
		$query = 'SELECT j.`id` AS `id`, j.`word` AS `word`, j.`reading`, j.katakana, j.usually_kana, jx.gloss_english AS `fullgloss`, j.pos_redux, `j`.`njlpt` AS `njlpt`, `j`.`njlpt_r` AS `njlpt_r` 
		FROM grammar_answers ga
		JOIN `jmdict` j ON j.id = ga.jmdict_id LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id ';
		
		$query .= 'WHERE ga.question_id = ' . (int) $question->question_id . '';
		$query .= ' ORDER BY RAND() LIMIT ' . $howmany;
	
		$res = mysql_query_debug($query) or log_db_error($query);
	
	  	// DEBUG
		if(mysql_num_rows($res) < $howmany)
			die('Failure in get_decoy_answers: ' . $query);
			
		if ($howmany == 1)
			return array(mysql_fetch_object($res));
	
		$vocabs = array();
		while ($row = mysql_fetch_object($res))
			$vocabs[] = $row;
		
		return $vocabs;
	}
	
	
	function get_similar_vocab ($jmdict, $howmany = 1, $grade1 = -1, $grade2 = -1) 
	{		
		$grade = $grade1;
		
		$query = 'SELECT j.`id` AS `id`, j.`word` AS `word`, j.`reading`, j.katakana, j.usually_kana, jx.gloss_english AS `fullgloss`, j.pos_redux, `j`.`njlpt` AS `njlpt`, `j`.`njlpt_r` AS `njlpt_r` 
		FROM example_decoys ed
		JOIN `jmdict` j ON j.id = ed.jmdict_id_decoy LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id ';
		
		$query .= 'WHERE ed.jmdict_id = \'' . mysql_real_escape_string($jmdict->id) . '\' 
			AND ed.example_id = \'' . (int) $jmdict->example_id . '\' 
			AND j.id != \'' . (int) ($jmdict->id)  . '\'';
	
		$query .= ' ORDER BY';
		
		if ($grade > 0 && $grade[0] == 'N')
			$query .= ' ABS(j.njlpt - ' . $grade[1] . ') ASC, ';
		elseif($grade == '0')
			$query .= ' ABS(j.njlpt - ' . (int) $grade . ') ASC, ';
		
		$query .= ' (ed.score * RAND()) DESC LIMIT ' . $howmany;
	
		$res = mysql_query_debug($query) or log_db_error($query);
	
	  	// DEBUG
		if(mysql_num_rows($res) < $howmany)
			die('Failure in get_similar_vocab: ' . $query);
			
		if ($howmany == 1)
			return array(mysql_fetch_object($res));
	
		$vocabs = array();
		while ($row = mysql_fetch_object($res))
			$vocabs[] = $row;
		
		return $vocabs;
	}

	// function get_vocab_id($vocab_id) 
	// {	
	// 	 $query = "SELECT j.`id` AS `id`, j.`word` AS `word`, j.`reading` AS `reading`, jx.gloss_english AS fullgloss, j.katakana FROM `jmdict` j LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id WHERE j.`id` = '" . mysql_real_escape_string($vocab_id) . "' LIMIT 1";
	// 	
	// 	  $res = mysql_query_debug($query) or log_db_error($query, false, true);
	// 	  return mysql_fetch_object($res);
	// }

	function getGradeOptions()
	{
		if($this->isQuiz())
			return NULL;
		
		for($i = 1; $i <= 5; $i++)
			$options[] = array('grade' => 'N' . $i, 'label' => 'JLPT '. $i, 'selected' => ($this->getGrade() == 'N' . $i));
		
		return $options;
	}
	
	public function getDefaultWSizes()
	{ 
		return array(
		LEVEL_SENSEI => array(	4,		6,	 	6,	 10,	15,  	10, 	5,	5),
		LEVEL_N5  => array(5,	 	10,	5,	10,	10,	10,	5),
		LEVEL_N4  => array(5,			5,		15,		10,		15,		10,		10,		10,		5),
		LEVEL_N3 => array(5, 	5,		5,		15,	10,	15,	5,		10,	5),
		LEVEL_N2 => array(5, 	5,		5,		15,	10,	15,	5,		10,	5),
		LEVEL_N1 => array(4, 	4,		15,	15,	10,	10,	15,	10,	5)
		);
	}
	
	
	public function getDefaultWGrades()
	{
		$array = array(LEVEL_N5 => array('N5', 	'N5', 			1,	 	'N5',		1,	 		2,		'N4'),
		LEVEL_N4 => array('N5', 	'N4', 		2,			'N4',		3,		'N4',	'N2'),
		LEVEL_N3 => array('N5', 	'N4', 		'N3',		'N3', 		4, 		'N3',		'N2', 		5,		'N2'),
		LEVEL_N2 => array('N5', 	'N4', 		'N3',		'N2', 		5, 		'N2',		'N2', 		6,		'N1'),
		LEVEL_N1 => array('N4', 	'N3', 		'N2', 		'N1',		9, 		'N1',		'N1',		9,		-1),
		LEVEL_SENSEI => array('N4', 	'N3', 		'N2', 		'N1',  'N1', 	-1,	-1));
		
		return $array;
	}

	public function feedbackFormOptions()
	{
		$solution = $this->getSolution();
		foreach($this->data['choices'] as $choice)
			$words[$choice->id] = $choice->word;
			
		$forms[] = array('type' => 'text_same_def', 'title' => 'Confusing choices - Both are correct', 'param_1' => $words, 'param_1_title' => ' ', 'param_2_title' => ' and ', 'param_2' => $words, 'param_3' => $solution->example_id, 'param_1_required' => true, 'param_2_required' => true);
		
		$forms[] = array('type' => 'text_furigana', 'title' => 'Need furigana at this level', 'param_1' => $words, 'param_1_title' => 'This word should have furigana at this JLPT level:', 'param_3' => $solution->example_id, 'param_1_required' => true, 'param_2_required' => false);

		if(! $this->isQuiz())
			$forms[] = array('type' => 'text_wrong_level', 'title' => 'Wrong level', 'param_1' => $words, 'param_1_title' => 'This word doesn\'t belong at this JLPT level:', 'param_3' => $solution->example_id, 'param_1_required' => true, 'param_2_required' => false);
		
		$forms[] = array('type' => 'text_other', 'title' => 'Other...', 'param_1' => $words, 'param_1_title' => 'Word 1:', 'param_2_title' => ' - Word 2 (optional):', 'param_2' => $words, 'param_3' => $solution->example_id, 'param_1_required' => true, 'param_2_required' => false);


		return $forms;
	}

	function hasFeedbackOptions()
	{
		return true;
	}
	
	function editButtonLink()
	{	
		if($_SESSION['user']->is_elite() && !$this->isQuiz()) {
			$solution = $this->getSolution();
			return '<a class="icon-button ui-state-default ui-corner-all" title="Edit..." href="#" onclick="do_load_with_close_button(\'' . SERVER_URL . 'ajax/get_sentence/edit/yes/?id=' . $solution->example_id . '&jmdict_id=' . $solution->id . '\', \'ajax_edit_form\'); return false;">✍</a>';
		}
		else
			return '';
	}
	
	static function getQuizTime() { return 51; }
	

}

?>