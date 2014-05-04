<?php
require_once('Question.php');

class Vocab extends Question
{
	public $table_learning = 'jmdict_learning';
	public $table_learning_index = 'jmdict_id';
	public $quiz_type = 'vocab';
	
	public static $lang_strings = array('en' => 'english', 'de' => 'german', 'fr' => 'french', 'sp' => 'spanish', 'ru' => 'russian', 'sv' => 'swedish', 'fi' => 'finnish', 'pl' => 'polish', 'it' => 'italian', 'tr' => 'turkish', 'th' => 'thai');
	

	function __construct($_mode, $_level, $_grade_or_set_id = 0, $_data = NULL)
	{
		parent::__construct($_mode, $_level, $_grade_or_set_id, $_data);
	}
	

	function displayChoices($next_sid = '')
	{
		$submit_url = SERVER_URL . 'ajax/submit_answer/?sid=' . $this->data['sid'] . '&amp;time_created=' . (int) $this->created . '&amp;';

		$choices = $this->data['choices'];
		shuffle($choices);

		if($this->isQuiz())
			$anticheat = '<div class="cheat-trick" style="display:block;">瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着</div>';		
		else
			$anticheat = '';

		$reading_pref = $_SESSION['user']->get_pref('drill', 'show_reading');
		$hide_rare_kanji_pref =  $_SESSION['user']->get_pref('general', 'hide_rare_kanji');
		foreach($choices as $choice)
		{
			// if($_SESSION['user']->is_admin())
			if(($choice->usually_kana == 1) && $hide_rare_kanji_pref)
				echo '<div class="choice japanese" lang="ja" xml:lang="ja" onclick="submit_answer(\'' .  $this->data['sid'] . '\', \'' . $next_sid . '\', \'' . $submit_url . 'answer_id=' . (int) $choice->id . '\'); return false;">' . $anticheat . $choice->reading . '</div>';
			else {
				echo '<div class="choice japanese" lang="ja" xml:lang="ja" onclick="submit_answer(\'' .  $this->data['sid'] . '\', \'' . $next_sid . '\', \'' . $submit_url . 'answer_id=' . (int) $choice->id . '\'); return false;">' . $anticheat . $choice->word;
			
						
				if ($choice->word != $choice->reading && !$choice->katakana
						&& ((!$this->isQuiz() 
								&& $reading_pref != 'never'
								&& ($reading_pref == 'always' || $this->is_above_level($this->getGrade(), $choice->njlpt_r)))
							|| ($this->isQuiz() && $this->is_above_level($this->level_to_grade($this->getLevel()), $choice->njlpt_r))))
					echo '<div class="furigana">' . $choice->reading . "</div>"; 

				echo '</div>';
			}
			
		}
		
		echo '<div class="choice skip" onclick="submit_answer(\'' .  $this->data['sid'] . '\',  \'' . $next_sid . '\', \'' . $submit_url . 'answer_id=' . SKIP_ID .'\'); return false;">&nbsp;?&nbsp;</div>';
	}
	
	function displayHint()
	{
		$solution = $this->getSolution();
		if(@$solution->missing_lang) {
			echo '<div class="missing_lang">' . $solution->fullgloss;
			if(! $_SESSION['user']->is_on_translator_probation())
			 	echo ' <a class="" href="#" onclick="show_vocab_translate_dialog(\'' . SERVER_URL . '\', \'' . $solution->id . '\', \'' . $this->data['sid'] .'\'); return false;"><img src="' . SERVER_URL . 'img/flags/' . $_SESSION['user']->get_pref('lang', 'vocab_lang') . '.png" class="missing_lang_icon' . ($this->isQuiz() ? ' disabled' : '') . '" /></a>';
			echo '</div>';
		}
		else {
			if($_SESSION['user']->get_pref('lang', 'vocab_lang') != 'en' && substr($solution->fullgloss, 0, 3) == '(~)') {
				if($this->isQuiz())
					echo '<div class="missing_lang">' . $solution->gloss_english . ' <a class="" href="#" onclick="show_vocab_translate_dialog(\'' . SERVER_URL . '\', \'' . $solution->id . '\', \'' . $this->data['sid'] .'\'); return false;"><img src="' . SERVER_URL . 'img/flags/' . $_SESSION['user']->get_pref('lang', 'vocab_lang') . '.png" class="missing_lang_icon disabled" /></a></div>' . ' <small><em>(incomplete translation)</em></small>';
				else
					echo '<div class="missing_lang">' . substr($solution->fullgloss, 3) . ' <a class="" href="#" onclick="show_vocab_translate_dialog(\'' . SERVER_URL . '\', \'' . $solution->id . '\', \'' . $this->data['sid'] .'\'); return false;"><img src="' . SERVER_URL . 'img/flags/' . $_SESSION['user']->get_pref('lang', 'vocab_lang') . '.png" class="missing_lang_icon" /></a></div>  <small><em>(translation may need improving)</em></small>';
			}
			else
				echo $solution->fullgloss;
		}
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
		$kanjis = [];
		$solution = $this->getSolution();
		$encoding = mb_detect_encoding($solution->word);
		
		if ($answer_id != SKIP_ID && !$this->isSolution($answer_id))
		{
			if (! $wrong = $this->get_vocab_id((int) $answer_id))
				log_error('Unknown Vocab ID: ' . $answer_id, true, true);			
		}			

		$hide_rare_kanji_pref =  $_SESSION['user']->get_pref('general', 'hide_rare_kanji');
		
		if($solution->usually_kana && $hide_rare_kanji_pref)
			$display_word_solution = $solution->reading;
		else
			$display_word_solution = preg_replace('/([^\\x{3040}-\\x{30FF}])/ue', "'<span class=\"kanji_detail\" href=\"#\" onclick=\"show_kanji_details(\'\\1\', \''. SERVER_URL . 'ajax/kanji_details/kanji/'.urlencode('\\1').'\');  return false;\">\\1</span>'", $solution->word);
				
		$audio_lookup = ($solution->lookup_string != '' ? $solution->lookup_string  : ($solution->usually_kana || $solution->full_readings != '' ? $solution->reading : $solution->word));
		
		echo '<span class="main" lang="ja" xml:lang="ja">' . $display_word_solution . "</span> " . (($solution->reading != $solution->word && !$solution->katakana && $display_word_solution != $solution->reading)? " [" . $solution->reading . "]" : '') .  ' <a href="#" onclick="play_tts(\'' . $audio_lookup . '\', \'' . get_audio_hash($audio_lookup) . '\'); return false;" class="tts-link"><img src="' . SERVER_URL . '/img/speaker.png" alt="play"/></a>' . " - " . $solution->fullgloss;
		
		if($this->isLearningSet() && $this->set->can_edit())
			echo '<a class="remove-from-set" title="Remove from set" href="#" onclick="remove_entry_from_set(\'' . SERVER_URL . 'ajax/edit_learning_set/\', ' . $this->set_id . ', ' . $this->getSolution()->id . ', \'#ajax-result\'); return false;">【×】</a>' . "\n";
		
		//Example sentence:
		$query = "SELECT e.example_str, e.english AS example_english, e.example_id, ep.pos_start, ep.pos_end FROM example_parts ep LEFT JOIN examples e ON e.example_id = ep.example_id WHERE ep.jmdict_id = " . (int) $solution->id . " ORDER BY ep.prime_example DESC, e.njlpt DESC, e.njlpt_r DESC, RAND() LIMIT 1";
		$res = mysql_query($query) or log_db_error($query);
		if($example = mysql_fetch_object($res)) {
			$strings = Text::getSentenceWithHints($example, false, $example->pos_start, $example->pos_end, 0);
			echo '<p class="example-sentence" lang="ja" xml:lang="ja"><span class="rei">例</span>' . $strings[1] . "</p>\n";
		}
		
		if (@$wrong)
		{
			
			if($wrong->usually_kana && $hide_rare_kanji_pref)
				$display_word_wrong = $wrong->reading;
			else
				$display_word_wrong = preg_replace('/([^\\x{3040}-\\x{30FF}])/ue', "'<span class=\"kanji_detail\" href=\"#\" onclick=\"show_kanji_details(\'\\1\', \''. SERVER_URL . 'ajax/kanji_details/kanji/'.urlencode('\\1').'\');  return false;\">\\1</span>'", $wrong->word);
			echo "<br/><br/>";

    		$audio_lookup = ($wrong->lookup_string != '' ? $wrong->lookup_string  : ($wrong->usually_kana || $wrong->full_readings != '' ? $wrong->reading : $wrong->word));
            
			echo "<span class=\"main\" lang=\"ja\" xml:lang=\"ja\">" . $display_word_wrong . "</span>" . (($wrong->reading != $wrong->word  && !$solution->katakana && $wrong->reading != $display_word_wrong) ? " [" . $wrong->reading . "]" : '') . ' <a href="#" onclick="play_tts(\'' . $audio_lookup . '\', \'' . get_audio_hash($audio_lookup) . '\'); return false;" class="tts-link"><img src="' . SERVER_URL . '/img/speaker.png" alt="play"/></a>' . " - " . $wrong->fullgloss;
		}
	}


	function getDBData($how_many, $grade, $user_id = -1)
	{
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
			$exclude = array($pick->id);
			
			foreach($sims as $sim)
			{
				$choice[$i++] = $sim;
				$exclude[] = $sim->id;
			}
			if (count($sims) < 3)
			{
				$sims = $this->get_random_vocab($grade, $grade, 3 - count($sims), $exclude);
				foreach($sims as $sim)
					$choice[$i++] = $sim;
			}

			$sid = 'sid_' . md5('himitsu' . time() . '-' . rand(1, 100000));
			$data[$sid] = array('sid' => $sid, 'choices' => $choice, 'solution' => $choice[0]);
		
			// if($choice[0]->id == $choice[1]->id || $choice[0]->id == $choice[2]->id || $choice[0]->id == $choice[3]->id || $choice[1]->id == $choice[2]->id || $choice[1]->id == $choice[3]->id || $choice[2]->id == $choice[3]->id)
			// {
			// 	log_error ("IDENTICAL Vocab picks: " . print_r($choice, true) . "\n" . print_r($picks, true), false, true);
			// }
		}
	
		return $data;
	}
	
	function get_random_vocab ($grade1 = -1, $grade2 = -1, $how_many = 1, $exclude = NULL) 
	{		
		//DEBUG:
		// if($_SESSION['user']->is_admin())
		// {
		// 	$query = "	SELECT j.`id` AS `id`, j.`word` AS `word`, j.`reading` , " . $this->get_query_gloss() . ", j.pos_redux, `j`.`jlpt` AS `jlpt`, `j`.`jlpt_r` AS `jlpt_r`, conf.group_id AS group_id FROM `jmdict` j LEFT JOIN jmdict_confusing conf ON conf.jmdict_id = j.id WHERE j.word = '教える' ORDER BY RAND() LIMIT 5";
		// 	$res = mysql_query_debug($query) or die(mysql_error());
		// 	$row = mysql_fetch_object($res);
		// 	
		// 	return array($row);
		// }
		$extra = '';
		
		if ($grade1[0] == 'N')
		{
			$grade1 = $grade1[1];
			$grade2 = $grade2[1];
			$level_field = 'njlpt'; 
			$harder = '<=';
			$easier = '>=';
			$easiest = '5';	
		}
		else
		{
			$level_field = 'pri_nf'; 
			$harder = '>=';
			$easier = '<=';
			$easiest = '1';
			
			if($grade1 > 0) {
				if($grade1 < 3)
					$extra = 'AND njlpt >= 4 ';
				elseif($grade1 < 5)
					$extra = 'AND njlpt >= 3 ';
				elseif($grade1 < 7)
					$extra = 'AND njlpt > 0 ';
			}
		}
	
		$query = 'SELECT j.*, '. Vocab::get_query_gloss() . ', conf.group_id AS group_id, jal.lookup_string, jx.full_readings FROM (SELECT j.`id` AS `id`, j.`word` AS `word`, j.`reading`, j.pos_redux, `j`.`njlpt` AS `njlpt`, `j`.`njlpt_r` AS `njlpt_r`, j.usually_kana, j.katakana FROM `jmdict` j
	WHERE 1 ' . $extra;
	
		if ($grade1 > 0)
		{
			if ($grade2 > 0)
				$query .= " AND `$level_field` $harder " . (int) $grade1 . " AND `$level_field` $easier " . (int) $grade2;
			else
				$query .= " AND `$level_field` $harder $easiest AND `$level_field` $easier " . (int) $grade1; 
		}
		if($exclude)
			$query .= ' AND j.id NOT IN (' . implode(', ', $exclude) . ')';
			
		$query .= ' ORDER BY RAND()';
	
		$query .= '  LIMIT ' . $how_many . ') AS j LEFT JOIN jmdict_confusing conf ON conf.jmdict_id = j.id LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id LEFT JOIN jmdict_audio_lookup jal ON jal.jmdict_id = j.id';
		
		
		$res = mysql_query_debug($query) or log_db_error($query);
		if (mysql_num_rows($res) < $how_many)
			log_error("Can't get enough randomized words: " . $query, true, true);

		if ($how_many == 1)
			return array(mysql_fetch_object($res));
	
		$vocabs = [];
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
			$level_field = 'njlpt'; 
			$harder = '<=';
			$easier = '>=';
			$easiest = '5';
		}
		else
		{
			$level_field = 'pri_nf'; 
			$harder = '>=';
			$easier = '<=';
			$easiest = '1';
		}
		$query_where = " WHERE 1 ";
		if ($grade1 > 0)
		{
			if ($grade2 > 0)
				$query_where .= " AND `$level_field` $harder " . (int) $grade1 . " AND `$level_field` $easier " . (int) $grade2;
			else
				$query_where .= " AND `$level_field` $harder $easiest AND `$level_field` $easier " . (int) $grade1; 
		}
		
			
		$gloss_query = Vocab::get_query_gloss();
		
		$translator_mode = ($_SESSION['user']->get_pref('lang', 'vocab_lang') != 'en' && $_SESSION['user']->get_pref('lang', 'translator_mode'));
		
		if($translator_mode)
			$query = "SELECT $gloss_query, conf.group_id, jal.lookup_string, jx.full_readings, ";
		else
			$query = "SELECT j.*, $gloss_query, conf.group_id, jal.lookup_string, jx.full_readings FROM (SELECT ";
			
		$query .= "j.`id` AS `id`, j.`word` AS `word`, j.`reading` AS `reading`, j.pos_redux, `j`.`njlpt` AS `njlpt`, `j`.`njlpt_r` AS `njlpt_r`, IF(l.curve IS NULL, 1000, l.curve)+1000*rand() as xcurve, j.usually_kana, j.katakana
		FROM `jmdict` j 
		LEFT JOIN $this->table_learning l on l.user_id = '" . (int) $user_id . "' AND j.id = l.$this->table_learning_index ";
		
		if(! $translator_mode)
			$query .= $query_where . ') AS j ';
			
		$query .= 'LEFT JOIN jmdict_confusing conf ON conf.jmdict_id = j.id LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id LEFT JOIN jmdict_audio_lookup jal ON jal.jmdict_id = j.id';
		
		if($translator_mode) {
			if($_SESSION['user']->is_on_translator_probation())
				$query .= $query_where . " AND jx.gloss_" . Vocab::$lang_strings[$_SESSION['user']->get_pref('lang', 'vocab_lang')] . " LIKE '(~)%'";
			else
				$query .= $query_where . " AND (jx.gloss_" . Vocab::$lang_strings[$_SESSION['user']->get_pref('lang', 'vocab_lang')] . ' IS NULL OR jx.gloss_' . Vocab::$lang_strings[$_SESSION['user']->get_pref('lang', 'vocab_lang')] . " = '' OR jx.gloss_" . Vocab::$lang_strings[$_SESSION['user']->get_pref('lang', 'vocab_lang')] . " LIKE '(~)%')";
		}
		
		$query .= 	' ORDER BY xcurve DESC';
		$query .= ' LIMIT ' . $how_many ;
	
		// if($_SESSION['user']->is_admin())
		// 	echo $query . "<br/>";

		$res = mysql_query_debug($query) or log_db_error($query);
		if (mysql_num_rows($res) == 0) {
			if($translator_mode)
				die('No vocab left to translate at this level. Please change level or turn off Translator mode.');
			else
				log_error("Can't get enough randomized vocab: " . $query, true, true);
		}
			
		if ($how_many == 1)
			return array(mysql_fetch_object($res));
	
		$kanjis = [];
		while ($row = mysql_fetch_object($res))
			$kanjis[] = $row;
		
		shuffle($kanjis);
		return $kanjis;
	
	}
	
	
	function get_set_weighted_vocab($user_id, $how_many = 1)
	{
		$query_where = " WHERE lse.set_id = $this->set_id";
			
		$gloss_query = Vocab::get_query_gloss();
		
		// $translator_mode = ($_SESSION['user']->get_pref('lang', 'vocab_lang') != 'en' && $_SESSION['user']->get_pref('lang', 'translator_mode'));
		$translator_mode = false;
		
		if($translator_mode)
			$query = "SELECT $gloss_query, conf.group_id, jal.lookup_string, jx.full_readings, ";
		else
			$query = "SELECT j.*, $gloss_query, conf.group_id, jal.lookup_string, jx.full_readings FROM (SELECT ";
			
		$query .= "j.`id` AS `id`, j.`word` AS `word`, j.`reading` AS `reading`, j.pos_redux, `j`.`njlpt` AS `njlpt`, `j`.`njlpt_r` AS `njlpt_r`, IF(l.curve IS NULL, 1000, l.curve)+1000*rand() as xcurve, j.usually_kana, j.katakana
		FROM learning_set_vocab lse LEFT JOIN `jmdict` j ON j.id = lse.jmdict_id
		LEFT JOIN $this->table_learning l on l.user_id = '" . (int) $user_id . "' AND j.id = l.$this->table_learning_index ";
		
		if(! $translator_mode)
			$query .= $query_where . ') AS j ';
			
		$query .= 'LEFT JOIN jmdict_confusing conf ON conf.jmdict_id = j.id LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id LEFT JOIN jmdict_audio_lookup jal ON jal.jmdict_id = j.id';
		
		if($translator_mode)
			$query .= $query_where . " AND (jx.gloss_" . Vocab::$lang_strings[$_SESSION['user']->get_pref('lang', 'vocab_lang')] . ' IS NULL OR jx.gloss_' . Vocab::$lang_strings[$_SESSION['user']->get_pref('lang', 'vocab_lang')] . " = '' OR jx.gloss_" . Vocab::$lang_strings[$_SESSION['user']->get_pref('lang', 'vocab_lang')] . " LIKE '(~)%')";

		$query .= 	' ORDER BY xcurve DESC';
		$query .= ' LIMIT ' . $how_many ;
	
		// if($_SESSION['user']->is_admin())
		// 	echo $query . "<br/>";

		$res = mysql_query_debug($query) or log_db_error($query);
		if (mysql_num_rows($res) == 0) {
			if($translator_mode)
				die('No vocab left to translate at this level. Please change level or turn off Translator mode.');
			else
				die('Not enough vocabulary in this set yet. Please add more entries and try again.');
		}
			
		if ($how_many == 1)
			return array(mysql_fetch_object($res));
	
		$kanjis = [];
		while ($row = mysql_fetch_object($res))
			$kanjis[] = $row;
		
		shuffle($kanjis);
		return $kanjis;
	
	}
	
	
	function get_similar_vocab($jmdict, $howmany = 1, $grade1 = -1, $grade2 = -1) 
	{		
		if ($grade1[0] == 'N')
		{
			$grade1 = $grade1[1];
			$grade2 = $grade2[1];
			$level_field = 'njlpt'; 
			$harder = '<=';
			$easier = '>=';
			$easiest = '5';
		}
		else
		{
			$level_field = 'pri_nf'; 
			$harder = '>=';
			$easier = '<=';
			$easiest = '1';
		}
	
		$join_conf = ($jmdict->group_id != 0);
	
		$query = 'SELECT j.*, ' . Vocab::get_query_gloss() . ', jal.lookup_string, jx.full_readings FROM (SELECT j.`id` AS `id`, j.`word` AS `word`, j.`reading`, j.pos_redux, `j`.`njlpt` AS `njlpt`, `j`.`njlpt_r` AS `njlpt_r`, j.usually_kana, j.katakana FROM `jmdict` j ';
		
		if($join_conf)
			$query .= 'LEFT JOIN jmdict_confusing conf ON conf.jmdict_id = j.id AND conf.group_id = ' . ((int) $jmdict->group_id) . '
 WHERE conf.group_id IS NULL AND ';
		else
			$query .= 'WHERE ';
			
		$query .= 'j.id != \'' . mysql_real_escape_string($jmdict->id) . '\' 
			AND j.word != \'' . mysql_real_escape_string($jmdict->word)  . '\'
			AND j.pos_redux = \'' . mysql_real_escape_string($jmdict->pos_redux) . '\' ';
	
		if ($grade1 > 0)
		{
			if ($grade2 > 0)
				$query .= " AND `$level_field` $harder " . (int) $grade1 . " AND `$level_field` $easier " . (int) $grade2;
			else
				$query .= " AND `$level_field` $harder $easiest AND `$level_field` $easier " . (int) $grade1; 
		}
	
		if($join_conf)
			$query .= ' GROUP BY IFNULL(conf.group_id, j.id)';
		
		$query .= ' ORDER BY ABS(j.njlpt - ' . (int) $jmdict->njlpt . ') ASC, RAND() LIMIT ' . $howmany . ') AS j LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id LEFT JOIN jmdict_audio_lookup jal ON jal.jmdict_id = j.id';
	
		// if($_SESSION['user']->is_admin())
		// 	echo $query . '<br/><br/>';
			
		$res = mysql_query_debug($query) or log_db_error($query);
	  
		if ($howmany == 1)
			return array(mysql_fetch_object($res));
	
		$vocabs = [];
		while ($row = mysql_fetch_object($res))
			$vocabs[] = $row;
		
		return $vocabs;
	}

	function get_vocab_id($vocab_id) 
	{	
		 $query = "SELECT j.`id` AS `id`, j.`word` AS `word`, j.`reading` AS `reading`, " . Vocab::get_query_gloss() . ", j.katakana, j.usually_kana, jal.lookup_string, jx.full_readings FROM `jmdict` j LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id LEFT JOIN jmdict_audio_lookup jal ON jal.jmdict_id = j.id WHERE j.`id` = '" . mysql_real_escape_string($vocab_id) . "' LIMIT 1";
		
		  $res = mysql_query_debug($query) or log_db_error($query, '', false, true);
		  return mysql_fetch_object($res);
	}

	function getGradeOptions()
	{
		if($this->isQuiz())
			return NULL;
		
		for($i = 1; $i <= 5; $i++)
			$options[] = array('grade' => 'N' . $i, 'label' => 'JLPT '. $i, 'selected' => ($this->getGrade() == 'N' . $i));
		
		return $options;
	}
	
	public function getDefaultWGrades()
	{
		$array = parent::get_default_w_grades();
		
		$array[LEVEL_SENSEI] = array(3, 	4, 		5, 		6, 		6, 		8,		9, 		-1, 	-1,	-1);
				
		return $array;
	}

	public function feedbackFormOptions()
	{
		foreach($this->data['choices'] as $choice)
			$words[$choice->id] = $choice->word;
			
		$forms[] = array('type' => 'vocab_same_def', 'title' => 'Confusing choices - Similar definitions', 'param_1' => $words, 'param_1_title' => 'Between ', 'param_2_title' => ' and ', 'param_2' => $words, 'param_1_required' => true, 'param_2_required' => true);
		
		$forms[] = array('type' => 'vocab_furigana', 'title' => 'Need furigana at this level', 'param_1' => $words, 'param_1_title' => 'This word should have furigana at this JLPT level:', 'param_1_required' => true, 'param_2_required' => false);

		if(! $this->isQuiz())
			$forms[] = array('type' => 'vocab_wrong_level', 'title' => 'Wrong level', 'param_1' => $words, 'param_1_title' => 'This word doesn\'t belong at this JLPT level:', 'param_1_required' => true, 'param_2_required' => false);
		
		$forms[] = array('type' => 'vocab_other', 'title' => 'Other...', 'param_1' => $words, 'param_1_title' => 'Word 1:', 'param_2_title' => ' - Word 2 (optional):', 'param_2' => $words, 'param_1_required' => true, 'param_2_required' => false);
		
		return $forms;
	}

	function hasFeedbackOptions()
	{
		return true;
	}
	
	function editButtonLink()
	{
		if($_SESSION['user']->is_on_translator_probation() && !$_SESSION['user']->get_pref('lang', 'translator_mode'))
			return '';
		
		if($_SESSION['user']->get_pref('lang', 'vocab_lang') != 'en' || $_SESSION['user']->isEditor()) {
			$solution = $this->getSolution();
		
			return '<a class="icon-button ui-state-default ui-corner-all" title="Languages..." href="#" onclick="show_vocab_translate_dialog(\'' . SERVER_URL . '\', \'' . $solution->id . '\', \'' . $this->data['sid'] .'\'); return false;">✍</a>';
		}
		else
			return '';
	}
	
	static function get_query_gloss()
	{
		$lang = $_SESSION['user']->get_pref('lang', 'vocab_lang');
		
		
		if($lang == 'en')
			$gloss_query = "jx.gloss_english AS `fullgloss`, 0 AS missing_lang";
		elseif(isset(Vocab::$lang_strings[$lang]))
			$gloss_query = "IFNULL(jx.gloss_" . Vocab::$lang_strings[$lang] . ", jx.gloss_english) AS `fullgloss`, (jx.gloss_" . Vocab::$lang_strings[$lang] . " IS NULL) AS missing_lang, jx.gloss_english AS gloss_english";
		else
			log_error('Unknown language: ' . $lang, true, true);
		
		return $gloss_query;
	}
}

?>