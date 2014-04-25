<?php

require_once 'Question.php';

mb_internal_encoding('UTF-8');
mb_regex_encoding('UTF-8');
require_onceABS_PATH . 'libs/utf8_lib.php';

class Reading extends Question {

    public $table_learning = 'reading_learning';
    public $table_learning_index = 'jmdict_id';
    public $quiz_type = 'reading';
    public $default_size = 10;
    private $fast_mode = false;

    function __construct($_mode, $_level, $_grade = 0, $_data = NULL) {
        parent::__construct($_mode, $_level, $_grade, $_data);
    }

    function display_choices($next_sid = '') {

        $submit_url = SERVER_URL . 'ajax/submit_answer/?sid=' . $this->data['sid'] . '&amp;time_created=' . (int) $this->created . '&amp;';
        $choices = $this->data['choices'];
        shuffle($choices);

        if ($this->is_quiz()) {
            $anticheat = '<div class="cheat-trick" style="display:block;">瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着</div>';
        } else {
            $anticheat = '';
        }

        foreach ($choices as $choice) {
            echo '<div class="choice japanese" lang="ja" xml:lang="ja" onclick="submit_answer(\'' . $this->data['sid'] . '\', \'' . $next_sid . '\', \'' . $submit_url . 'answer_id=' . (int) $choice->id . '\'); return false;">' . $anticheat . $choice->reading . '</div>';
        }

        echo '<div class="choice skip" onclick="submit_answer(\'' . $this->data['sid'] . '\',  \'' . $next_sid . '\', \'' . $submit_url . 'answer_id=' . SKIP_ID . '\'); return false;">&nbsp;?&nbsp;</div>';
    }

    function display_hint() {
        $solution = $this->get_solution();
        echo '<div class="japanese" lang="ja" xml:lang="ja">' . $solution->word . '</div>';

        if (!$this->is_quiz()) {
            if ($_SESSION['user']->get_pref('drill', 'show_reading_translation')) {
                if (@$solution->missing_lang) {
                    echo '<div class="missing_lang">' . $solution->fullgloss;
                    if (!$_SESSION['user']->is_on_translator_probation())
                        echo ' <a class="" href="#" onclick="show_vocab_translate_dialog(\'' . SERVER_URL . '\', \'' . $solution->id . '\', \'' . $this->data['sid'] . '\'); return false;"><img src="' . SERVER_URL . 'img/flags/' . $_SESSION['user']->get_pref('lang', 'vocab_lang') . '.png" class="missing_lang_icon' . ($this->is_quiz() ? ' disabled' : '') . '" /></a>';
                    echo '</div>';
                }
                else {
                    if ($_SESSION['user']->get_pref('lang', 'vocab_lang') != 'en' && substr($solution->fullgloss, 0, 3) == '(~)') {
                        if ($this->is_quiz())
                            echo '<div class="missing_lang">' . $solution->gloss_english . ' <a class="" href="#" onclick="show_vocab_translate_dialog(\'' . SERVER_URL . '\', \'' . $solution->id . '\', \'' . $this->data['sid'] . '\'); return false;"><img src="' . SERVER_URL . 'img/flags/' . $_SESSION['user']->get_pref('lang', 'vocab_lang') . '.png" class="missing_lang_icon disabled" /></a></div>' . ' <small><em>(incomplete translation)</em></small>';
                        else
                            echo '<div class="missing_lang">' . substr($solution->fullgloss, 3) . ' <a class="" href="#" onclick="show_vocab_translate_dialog(\'' . SERVER_URL . '\', \'' . $solution->id . '\', \'' . $this->data['sid'] . '\'); return false;"><img src="' . SERVER_URL . 'img/flags/' . $_SESSION['user']->get_pref('lang', 'vocab_lang') . '.png" class="missing_lang_icon" /></a></div>  <small><em>(translation may need improving)</em></small>';
                    } else
                        echo $solution->fullgloss;
                }
            }
            else {
                echo make_toggle_visibility("<span class=\"translation\">" . $solution->fullgloss . "</span><br/>");
            }
        }
    }

    function is_above_level($grade, $jlpt_level, $or_same = true) {
        $add = ($or_same ? 1 : 0);
        if ($grade[0] == 'N')
            return ($jlpt_level < $add + (int) $grade[1]);
        elseif ($grade)
            return ($jlpt_level < 5 + $add - ceil($grade * 4 / 9));
        else
            return false;
    }

    function display_correction($answer_id) {
        $kanjis = array();
        $solution = $this->get_solution();

        $wrong_reading = '';
        if ($answer_id != SKIP_ID && !$this->is_solution($answer_id))
            foreach ($this->data['choices'] as $choice)
                if ($choice->id == $answer_id)
                    $wrong_reading = ', <span class="wrong" lang="ja" xml:lang="ja">' . $choice->reading . '</span>';

        foreach ($solution->readings as $key => $val)
            if ($val == $solution->reading)
                $solution->readings[$key] = '<span class="main_reading" lang="ja" xml:lang="ja">' . $val . '</span>';

        $display_word_solution = preg_replace('/([^\\x{3040}-\\x{30FF}])/ue', "'<span class=\"kanji_detail\" href=\"#\" onclick=\"show_kanji_details(\'\\1\', \''. SERVER_URL . 'ajax/kanji_details/kanji/'.urlencode('\\1').'\');  return false;\">\\1</span>'", $solution->word);


        echo '<span class="main">' . $display_word_solution . ': ' . implode(', ', $solution->readings) . $wrong_reading . '</span><br/>';

        if (@$solution->missing_lang) {
            echo '<div class="missing_lang">' . $solution->fullgloss;
            if (!$_SESSION['user']->is_on_translator_probation())
                echo ' <a class="" href="#" onclick="show_vocab_translate_dialog(\'' . SERVER_URL . '\', \'' . $solution->id . '\', \'' . $this->data['sid'] . '\'); return false;"><img src="' . SERVER_URL . 'img/flags/' . $_SESSION['user']->get_pref('lang', 'vocab_lang') . '.png" class="missing_lang_icon' . ($this->is_quiz() ? ' disabled' : '') . '" /></a>';
            echo '</div>';
        } else
            echo '<br/> ' . $solution->fullgloss . '<br/>';

        echo '<br/><span class="comment">Kanji breakdown:</span>';
        foreach ($solution->kanji_prons as $kanji => $prons) {
            $kuns = $ons = array();
            foreach ($prons as $pron)
                ($pron->type == 'on') ? $ons[] = $pron->pron : $kuns[] = $pron->pron;
            echo '<br/>• ' . $kanji . ': ' . (@count($kuns) ? implode(', ', $kuns) . ' - ' : '') . (@count($ons) ? implode(', ', $ons) : '');
        }
    }

    function learn_set($user_id, $learning_set, $learn_others = false) {
        return parent::learn_set($user_id, $learning_set, $learn_others);
    }

    function get_db_data($how_many, $grade, $user_id = -1) {
        if ($this->is_quiz() || !@$_SESSION['user'])
            $picks = $this->get_random_readings($grade, $grade, $how_many);
        elseif ($this->is_drill())
            $picks = $this->get_weighted_readings($_SESSION['user']->getID(), $grade, $grade, $how_many);
        else
            $picks = $this->get_set_weighted_readings($_SESSION['user']->getID(), $how_many);
        //	echo '<pre>';
        //	print_r($picks);
        //	echo '</pre>';
//$time = microtime();		
        foreach ($picks as $pick) {
            $pick->jmdict_id = $pick->id;

            $query = 'SELECT j.reading as main_reading, r.*, (rd.jmdict_id IS NOT NULL) AS pre_processed FROM jmdict j LEFT JOIN jmdict_reading r ON r.jmdict_id = j.id LEFT JOIN reading_decoys rd ON rd.jmdict_id = j.id WHERE j.word = \'' . $pick->word . '\' GROUP BY j.reading, r.jmdict_reading_id';
            $res = mysql_query_debug($query) or log_db_error($query, false, true);

            $pick->readings = array();
            $pre_processed = false;
            while ($row = mysql_fetch_object($res)) {
                if (!in_array($row->main_reading, $pick->readings))
                    $pick->readings[] = $row->main_reading;
                if ($row->reading && !in_array($row->reading, $pick->readings))
                    $pick->readings[] = $row->reading;

                if ($row->pre_processed)
                    $pre_processed = true;
            }

            // if($_SESSION['user']->is_admin()) {
            // 	echo '<pre>';
            // 	print_r($pick);
            // 	echo '</pre>';
            // 	// $pick->word = "木";
            // }

            $pick->kanji_prons = array();

            if ($pre_processed) {
                $str = mb_ereg_replace('(.)々', '\\1\\1', $pick->word);
                preg_match_all('/([^\\x{3040}-\\x{30FF}]?)([\\x{3040}-\\x{30FF}]*)/u', $str, $matches, PREG_SET_ORDER);
                array_pop($matches); // last elem is empty

                $kanjis = array();
                foreach ($matches as $match)
                    if (!empty($match[1]))
                        $kanjis[] = $match[1];

                if (count($kanjis)) {
                    $kanji_csv = '\'' . implode('\', \'', $kanjis) . '\'';

                    $query = 'SELECT k.id, k.kanji, p.pron, p.type, 1 as coef_prob FROM kanjis k JOIN pron p ON p.kanji_id = k.id AND p.type != \'nanori\' WHERE k.kanji IN (' . $kanji_csv . ')';

                    $res = mysql_query_debug($query) or log_db_error($query, false, true);

                    while ($row = mysql_fetch_object($res))
                        $pick->kanji_prons[$row->kanji][] = $row;
                }

                // $sort_order[$var] = ($prob ? pow(rand() / getrandmax(), 1/$prob) : 0);

                $query = "SELECT reading_decoy, score FROM reading_decoys rd WHERE rd.jmdict_id = " . (int) $pick->jmdict_id . " ORDER BY POW(RAND(), 10000/GREATEST(rd.score, 1)) DESC LIMIT 3";
                $res = mysql_query_debug($query) or log_db_error($query, false, true);

                $sims = array();
                while ($row = mysql_fetch_object($res))
                    $sims[$row->reading_decoy] = $row->score / 10000;
            }

            if (count($sims) < 3) {
                $this->init_twisters();

                $sims = $this->get_similar_readings($pick->word, $pick->readings, 3, $grade, $pick->kanji_prons);
            }

            if (count($sims) < 3)
                log_error("Not enough reading choices for " . $pick->word . ": " . print_r($sims, true) . "\n\n" . print_r($pick, true), true, true);

            $choice = array(0 => $pick);
            foreach ($sims as $variant => $prob)
                $choice[] = array2obj(array('reading' => $variant, 'prob' => $prob));

            if ($choice[0]->reading == $choice[1]->reading || $choice[0]->reading == $choice[2]->reading || $choice[0]->reading == $choice[3]->reading || $choice[1]->reading == $choice[2]->reading || $choice[1]->reading == $choice[3]->reading || $choice[2]->reading == $choice[3]->reading)
                log_error("IDENTICAL READINGS: " . print_r($choice, true) . "\n\n" . print_r($picks, true), true, false);


            $indices = range(1, 4);
            shuffle($indices);

            foreach ($choice as $i => $var)
                $choice[$i]->id = $indices[$i];

            $sid = 'sid_' . md5('himitsu' . time() . '-' . rand(1, 100000));
            $data[$sid] = array('sid' => $sid, 'choices' => $choice, 'solution' => $choice[0]);
        }
//echo "#" . max(0, round((microtime()-$time)*1000)) . 'ms';

        $this->fast_mode = false;
        return $data;
    }

    function get_random_readings($grade1 = -1, $grade2 = -1, $how_many = 1) {
        //DEBUG:
        // if($_SESSION['user']->is_admin())
        // {
        // 	$query = "SELECT j.`id` AS `id`, j.`word` AS `word`, j.`reading` , jx.gloss_english AS j.`fullgloss`, j.pos, `j`.`jlpt` AS `jlpt`, `j`.`jlpt_r` AS `jlpt_r` FROM `jmdict` j WHERE j.id = 1552890 GROUP BY `id`";
        // 	$res = mysql_query_debug($query) or die(mysql_error());
        // 	$row = mysql_fetch_object($res);
        // 	
        // 	return array($row);
        // }

        if ((int) $how_many < 1)
            log_error('get_random_readings called with how_many = ' . $how_many, false, true);

        if ($grade1[0] == 'N') {
            $grade1 = $grade1[1];
            $grade2 = $grade2[1];
            $level_field = 'njlpt';
            $harder = '<=';
            $easier = '>=';
            $strictly_harder = '<';
            $easiest = '5';
        } else {
            $level_field = 'pri_nf';
            $harder = '>=';
            $easier = '<=';
            $strictly_harder = '>';
            $easiest = '1';
        }


        $query = 'SELECT j.*, jx.pos, ' . Vocab::get_query_gloss() . ' FROM (SELECT j.`id` AS `id`, j.`word` AS `word`, j.`reading`, `j`.`njlpt` AS `njlpt`, `j`.`njlpt_r` AS `njlpt_r`  FROM `jmdict` j WHERE j.word != j.reading AND j.katakana = \'0\' AND j.usually_kana = 0 AND j.njlpt > 0 ';

        if ($grade1 > 0) {
            $grade1 = (int) $grade1;

            if ($level_field == 'njlpt')
                $r_level = ($grade2 > 0 ? $grade2 : $grade1);
            else
                $r_level = $_SESSION['user']->getJLPTNumLevel();

            if ($grade2 > 0) {
                $grade2 = (int) $grade2;
                if ($grade1 == $grade2)
                    $query .= " AND (($level_field = $grade1 AND njlpt_r >= $r_level) OR ($grade1 $strictly_harder $level_field AND njlpt_r = $r_level))";
                else
                    $query .= " AND $level_field $easier $grade2 AND (($level_field = $grade1 AND njlpt_r >= $r_level) OR ($grade1 $strictly_harder $level_field AND njlpt_r = $r_level))";
            } else
                $query .= " AND $level_field $harder $easiest AND $level_field $easier $grade1 AND njlpt_r >= $r_level";
        }

        $query .= ' GROUP BY `id` ORDER BY RAND()';

        $query .= '  LIMIT ' . (int) $how_many . ') AS j LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id ';

        $res = mysql_query_debug($query) or log_db_error($query);
        if (mysql_num_rows($res) < $how_many)
            log_error("Reading mode (get_random_readings): Can't get enough randomized vocab: " . $query . "\n(needed $how_many, got: " . mysql_num_rows($res) . ")", false, true);

        if ($how_many == 1)
            return array(mysql_fetch_object($res));

        $readings = array();
        while ($row = mysql_fetch_object($res))
            $readings[] = $row;

        return $readings;
    }

    function get_set_weighted_readings($user_id, $how_many = 1) {

        $query = "SELECT j.*, jx.pos, " . Vocab::get_query_gloss() . " FROM (SELECT j.`id` AS `id`, j.`word` AS `word`, j.`reading` AS `reading`, `j`.`njlpt` AS `njlpt`, `j`.`njlpt_r` AS `njlpt_r`, IF(l.curve IS NULL, 1000, l.curve)+1000*rand() as xcurve
		FROM learning_set_vocab ls LEFT JOIN `jmdict` j ON j.id = ls.jmdict_id
		LEFT JOIN " . $this->table_learning . " l on l.user_id = '" . (int) $_SESSION['user']->getID() . "' AND j.id = l." . $this->table_learning_index . "
		WHERE ls.set_id = $this->set_id AND j.word != j.reading AND j.katakana = '0' AND j.usually_kana = 0 ";

        $query .= '  ORDER BY xcurve DESC';

        $query .= '  LIMIT ' . $how_many . ') AS j LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id';

        // if($_SESSION['user']->is_admin())
        // 	echo "<pre>$query</pre>";

        $res = mysql_query_debug($query) or log_db_error($query);
        if (mysql_num_rows($res) < $how_many)
            die("This set does not contain enough entries to be drilled on. Please add more entries and try again.");
        // log_error("Reading mode (get_set_weighted_readings): Can't get enough randomized vocab: " . $query . "\n(needed $how_many, got: " . mysql_num_rows($res) . ")", false, true);

        if ($how_many == 1)
            return array(mysql_fetch_object($res));

        $readings = array();
        while ($row = mysql_fetch_object($res))
            $readings[] = $row;
        shuffle($readings);
        return $readings;
    }

    function get_weighted_readings($user_id, $grade1 = -1, $grade2 = -1, $how_many = 1) {
        if ((int) $how_many < 1)
            log_error('get_weighted_readings called with how_many = ' . $how_many, false, true);

        if ($grade1[0] == 'N') {
            $grade1 = $grade1[1];
            $grade2 = $grade2[1];
            $level_field = 'njlpt';
            $harder = '<=';
            $strictly_harder = '<';
            $easier = '>=';
            $easiest = '5';
        } else {
            $level_field = 'pri_nf';
            $harder = '>=';
            $strictly_harder = '>';
            $easier = '<=';
            $easiest = '1';
        }

        $query = "SELECT j.*, jx.pos, " . Vocab::get_query_gloss() . " FROM (SELECT j.`id` AS `id`, j.`word` AS `word`, j.`reading` AS `reading`, `j`.`njlpt` AS `njlpt`, `j`.`njlpt_r` AS `njlpt_r`, IF(l.curve IS NULL, 1000, l.curve)+1000*rand() as xcurve
		FROM `jmdict` j
		LEFT JOIN " . $this->table_learning . " l on l.user_id = '" . (int) $_SESSION['user']->getID() . "' AND j.id = l." . $this->table_learning_index . "
		WHERE  j.word != j.reading AND j.katakana = '0' AND j.usually_kana = 0 AND j.njlpt >0 ";

        if ($grade1 > 0) {
            $grade1 = (int) $grade1;

            if ($level_field == 'njlpt')
                $r_level = ($grade2 > 0 ? $grade2 : $grade1);
            else
                $r_level = $_SESSION['user']->getJLPTNumLevel();

            if ($grade2 > 0) {
                $grade2 = (int) $grade2;
                if ($grade1 == $grade2)
                    $query .= " AND (($level_field = $grade1 AND njlpt_r >= $r_level) OR ($grade1 $strictly_harder $level_field AND njlpt_r = $r_level))";
                else
                    $query .= " AND $level_field $easier $grade2 AND (($level_field = $grade1 AND njlpt_r >= $r_level) OR ($grade1 $strictly_harder $level_field AND njlpt_r = $r_level))";
            } else
                $query .= " AND $level_field $harder $easiest AND $level_field $easier $grade1 AND njlpt_r >= $r_level";
        }

        $query .= '  ORDER BY xcurve DESC';

        $query .= '  LIMIT ' . $how_many . ') AS j LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id';

        // if($_SESSION['user']->is_admin())
        // 	echo "<pre>$query</pre>";

        $res = mysql_query_debug($query) or log_db_error($query);
        if (mysql_num_rows($res) < $how_many)
            log_error("Reading mode (get_weighted_readings): Can't get enough randomized vocab: " . $query . "\n(needed $how_many, got: " . mysql_num_rows($res) . ")", false, true);

        if ($how_many == 1)
            return array(mysql_fetch_object($res));

        $readings = array();
        while ($row = mysql_fetch_object($res))
            $readings[] = $row;
        shuffle($readings);
        return $readings;
    }

    function get_similar_readings($word, $readings, $how_many, $grade, &$kanji_prons) {
        try {

            $str = mb_ereg_replace('(.)々', '\\1\\1', $word);
            preg_match_all('/([^\\x{3040}-\\x{30FF}]?)([\\x{3040}-\\x{30FF}]*)/u', $str, $matches, PREG_SET_ORDER);
            array_pop($matches); // last elem is empty

            $kanjis = array();
            foreach ($matches as $match)
                if (!empty($match[1]))
                    $kanjis[] = $match[1];

            if (count($kanjis)) {
                $kanji_csv = '\'' . implode('\', \'', $kanjis) . '\'';
                $query = 'SELECT k.id, k.kanji, p.pron, p.type, 1 as coef_prob FROM kanjis k JOIN pron p ON p.kanji_id = k.id AND p.type != \'nanori\' WHERE k.kanji IN (' . $kanji_csv . ')';

                $res = mysql_query_debug($query) or log_db_error($query, false, true);

                while ($row = mysql_fetch_object($res))
                    $kanji_prons[$row->kanji][] = $row;

                if (count($kanji_prons) <= 2 * $how_many || rand(0, 2) == 0) {
                    $query = 'SELECT k.kanji, s1.combi_sim, p.pron, p.type, (s1.combi_sim/100) as coef_prob FROM kanjis k 
						JOIN sim_kanjis s1 ON s1.k1_id = k.id 
						LEFT JOIN sim_kanjis s2 ON s2.k1_id = k.id AND s2.k2_id != s1.k2_id AND s2.combi_sim > s1.combi_sim 
						LEFT JOIN pron p ON p.kanji_id = s1.k2_id
						WHERE s2.id IS NULL AND s1.combi_sim > 85 AND k.kanji IN (' . $kanji_csv . ') ORDER BY RAND() LIMIT ' . (4 * $how_many);

                    $res = mysql_query_debug($query) or log_db_error($query, false, true);
                    while ($row = mysql_fetch_object($res))
                        $kanji_prons[$row->kanji][] = $row;
                }
            }

            $var_parts = array();
            $all_kanji = true;

            foreach ($matches as $idx => $match)
                $matches[$idx][2] = mb_convert_kana($matches[$idx][2], 'c');

            $sql_pattern = '';
            $regex_pattern = '/^';

            foreach ($matches as $match) {
                if (!empty($match[1])) {
                    $sql_pattern .= '_';
                    if (substr($regex_pattern, -2) != '.+')
                        $regex_pattern .= '.+';
                }
                if (!empty($match[2])) {
                    $sql_pattern .= $match[2];
                    $regex_pattern .= $match[2];
                }
            }

            $regex_pattern .= '$/u';


            foreach ($matches as $i => $match) {
                $kanji = $match[1];
                $tail = mb_convert_kana($match[2], 'c');

                if ($tail)
                    $all_kanji = false;

                if (empty($kanji))
                    $var_parts[$i][$tail] = array('kanji' => '', 'kana' => $tail, 'prob' => 1, 'type' => 'kana');
                elseif (isset($kanji_prons[$kanji])) {
                    $pron_kana_vars = array();

                    foreach ($kanji_prons[$kanji] as $pron) {
                        list($pron_str, $prob) = $this->get_pron_prob($pron, $tail, $i, count($matches));
                        $pron_kana_vars[$pron->type][$pron_str] = $prob * $pron->coef_prob;
                    }

                    foreach ($pron_kana_vars as $type => $on_kun_vars) {
                        foreach ($this->twisters as $twister)
                            $on_kun_vars = $twister->twist($on_kun_vars);

                        $sort_order = $this->weighted_rand_select($on_kun_vars);
                        $sort_order = array_slice($sort_order, 0, $how_many);

                        foreach ($sort_order as $pron => $weight)
                            $var_parts[$i][$pron . $tail] = array('kanji' => $kanji, 'kana' => $pron . $tail, 'prob' => $on_kun_vars[$pron], 'type' => $type);
                    }
                } else
                    log_error("Can't find pron for kanji: " . $kanji, false, true);
            }


            $variations = array('' => 1);
            //mix and match
            $last_type = '';

            foreach ($var_parts as $i => $part) {
                $new_variations = array();
                foreach ($variations as $var => $var_prob)
                    foreach ($part as $pron) {
                        if (rand(0, count($part)) > 20)
                            continue;

                        $coef = (@$var_types[$var] && $var_types[$var] != $pron['type']) ? 0.6 : 1;
                        $new_var = $var . $pron['kana'];
                        $new_variations[$new_var] = ($coef * $var_prob * $pron['prob']);
                        if (@$var_types[$new_var] && $var_types[$new_var] != $pron['type'])
                            unset($var_types[$new_var]);
                        else
                            $var_types[$new_var] = $pron['type'];
                    }
                $variations = $new_variations;
            }

            if ($all_kanji) {
                foreach ($readings as $reading)
                    $variations_2[$reading] = 1.2;
                foreach ($this->twisters as $twister)
                    $variations_2 = $twister->twist($variations_2);
                foreach ($variations_2 as $var => $prob)
                    $variations[$var] = $prob;
            }


            // Add similar matches
            $like_str = '';

            for ($i = 0; $i < count($matches); $i++) {
                $pattern = '';
                foreach ($matches as $idx => $match) {
                    if (empty($match[1])) {
                        if ($i == $idx)
                            continue 2;

                        $pattern .= $match[2];
                    }
                    else {
                        if ($i != $idx)
                            $pattern .= $match[1] . $match[2];
                        else
                            $pattern .= '_' . $match[2];
                    }
                }

                $like_str .= " OR j.word LIKE '$pattern'";
            }


            if ($like_str) {
                $tot = 0;
                foreach ($matches as $match) {
                    if (!$match[1])
                        $tot += 0.05;
                    elseif ($match[2])
                        $tot += 0.3;
                    else
                        $tot += 0.25;
                }

                $query = 'SELECT j.word, j.reading FROM jmdict j WHERE j.word != \'' . $word . '\' AND (0' . $like_str . ') LIMIT 10';

                $res = mysql_query_debug($query) or log_db_error($query, false, true);

                while ($row = mysql_fetch_object($res))
                    if (preg_match($regex_pattern, $row->reading))
                        $variations[$row->reading] = ($tot - 0.25);
            }

            foreach ($readings as $reading)
                unset($variations[$reading]);

            $variations = preg_grep("/(っ[っうんあいおえがぐぎげごばぶびべぼだづまむみめもぢでど])|んっ/u", $variations, PREG_GREP_INVERT);

            arsort($variations);
            $variations = array_slice($variations, 0, $how_many * 10);
            $selection = array_slice($this->weighted_rand_select($variations), 0, $how_many);

            //	print_r($selection);

            if (count($selection) < $how_many) {
                $need = $how_many - count($selection);

                // echo 'Using random backup for ' . ($need) . ' using pattern: <strong>' . $sql_pattern . '</strong><br/>';				

                $query = 'SELECT r.reading FROM jmdict r WHERE r.katakana = \'0\' AND r.word != r.reading AND r.reading NOT IN (\'' . implode('\',\'', $readings) . '\') AND r.reading LIKE \'' . $sql_pattern . '\' ORDER BY (r.njlpt != 0) DESC, RAND() LIMIT ' . $need;
                $res = mysql_query_debug($query) or log_db_error($query, false, true);

                while ($row = mysql_fetch_object($res))
                    if (preg_match($regex_pattern, $row->reading))
                        $selection[$row->reading] = 0;

                if (count($selection) < min(10, $how_many)) {
                    $query = 'SELECT r.reading FROM jmdict r WHERE r.katakana = \'0\' AND r.reading != r.word ORDER BY RAND() LIMIT ' . (int) (10 * $how_many);
                    $res = mysql_query_debug($query) or die($query . ' - ' . mysql_error());

                    //Ultimate backup
                    $sanity_check = 0;
                    while (count($selection) < min(10, $how_many)) {
                        $new_var = '';
                        foreach ($matches as $match) {
                            if (!empty($match[1])) {
                                $row = mysql_fetch_assoc($res);
                                $new_var .= $row['reading'];
                            }
                            if (!empty($match[2]))
                                $new_var .= $match[2];
                        }

                        if (!in_array($new_var, $readings))
                            $selection[$new_var] = 0.0001;

                        if ($sanity_check++ > 100)
                            die("Reading::get_similar_readings() - sanity_check - Backup selection failure: " . $query);
                    }
                }
            }
        } catch (Exception $e) {
            log_error('Exception in ' . __FILE__ . ':get_similar_readings(): ' . $e->getMessage() . "\n" . $word . "\n" . print_r($readings, true) . "\n" . print_r($variations), false, true);
        }
        return $selection;
    }

    function weighted_rand_select($population) {
        $sort_order = array();

        foreach ($population as $var => $prob)
            $sort_order[$var] = ($prob ? pow(rand() / getrandmax(), 1 / $prob) : 0);

        arsort($sort_order);
        return $sort_order;
    }

    function get_pron_prob($pron, $tail_str, $pos, $tot_len) {
        $prob = 1.0;
        $pron_str = mb_convert_kana($pron->pron, 'c');

        if (($dash_pos = mb_strpos($pron_str, '-')) !== false) {
            $pron_str = str_replace(array('-'), '', $pron_str);
            if ($dash_pos == 0) { // -XXX
                if ($pos == 0)
                    $prob /= 10;
                elseif (!$tail_str && $pos >= $tot_len)
                    $prob *= 1.5;
            }
            else { // XXX-
                if ($pos == 0)
                    $prob *= 1.5;
                elseif (!$tail_str && $pos >= $tot_len)
                    $prob /= 10;
            }
        }

        if ($pron->type == 'kun' && (($dot_pos = mb_strpos($pron_str, '.')) !== false)) {
            $pron_prefix_str = mb_substr($pron_str, 0, $dot_pos);
            $pron_tail_str = mb_substr($pron_str, $dot_pos + 1);
            $pron_tail_end = mb_substr($pron_tail_str, -1, 1);

            if ($tail_str) {
                if ($pron_tail_str == $tail_str)
                    return array($pron_prefix_str, 2 * $prob);

                $tail_end = mb_substr($tail_str, -1, 1);

                if ($pron_tail_end == $tail_end)
                    return array($pron_prefix_str, 1.2 * $prob);

                if (mb_substr($pron_tail_str, 0, mb_strlen($tail_str)) == $tail_str)
                    return array($pron_prefix_str, 1 * $prob);

                if ($this->verb_to_noun($pron_tail_end) == $tail_end)
                    return array($pron_prefix_str, (mb_strlen($tail_str) == 1 ? 2 : 1.5) * $prob);

                return array($pron_prefix_str, 0.3 * $prob);
            }
            else { // no kana tail in the original word
                if ($tot_len == 1) // single kanji word
                    if ($pron_tail_str == $pron_tail_end) // only one trailing char
                        return array($pron_prefix_str . $this->verb_to_noun($pron_tail_end), 1 * $prob);
                    else // more than one trailing char
                        return array($pron_prefix_str . mb_substr($pron_tail_str, 0, -1) . $this->verb_to_noun($pron_tail_end), 0.6 * $prob);
                elseif (mb_strlen($pron_prefix_str) == 1)  // compound word AND pron is one kana
                    return array($pron_prefix_str, 0.5 * $prob);
                else  // compound word AND pron is longer than one kana
                if ($pron_tail_str == $pron_tail_end) // only one trailing char
                    return array($pron_prefix_str . $this->verb_to_noun($pron_tail_end), 0.4 * $prob);
                else // more than one trailing char
                    return array($pron_prefix_str . mb_substr($pron_tail_str, 0, -1) . $this->verb_to_noun($pron_tail_end), 0.3 * $prob);
            }
        }

        // Regular on/kun:
        if ($tail_str) {
            if ($pron->type == 'on')
                return array($pron_str, 0.2 * $prob);
            else
                return array($pron_str, 0.3 * $prob);
        }
        elseif ($tot_len == 1) {
            if ($pron->type == 'on')
                return array($pron_str, 0.8 * $prob);
            else
                return array($pron_str, 1.0 * $prob);
        }
        else {
            if ($pron->type == 'on')
                return array($pron_str, 0.9 * $prob);
            else
                return array($pron_str, 0.7 * $prob);
        }
    }

    function verb_to_noun($verb_ending) {
        $array = array('く' => 'き', 'ぐ' => 'ぎ', 'る' => 'り', 'む' => 'み', 'す' => 'し', 'ず' => 'じ', 'つ' => 'ち', 'ぬ' => 'に', 'ぶ' => 'び');
        return(@$array[$verb_ending] ? $array[$verb_ending] : $verb_ending);
    }

    function get_grade_options() {
        if ($this->is_quiz())
            return NULL;

        for ($i = 1; $i <= 5; $i++)
            $options[] = array('grade' => 'N' . $i, 'label' => 'JLPT ' . $i, 'selected' => ($this->get_grade() == 'N' . $i));

        return $options;
    }

    function get_solution_id() {
        $sol = $this->get_solution();
        return $sol->jmdict_id;
    }

    public function get_default_w_sizes() {
        return array(
            LEVEL_SENSEI => array(4, 6, 6, 6, 10, 10, 10, 5, 10, 5),
            LEVEL_N5 => array(5, 10, 10, 10, 10, 10, 5),
            LEVEL_N4 => array(5, 5, 10, 10, 10, 10, 10, 10, 5),
            LEVEL_N3 => array(5, 5, 5, 10, 10, 10, 5, 10, 5),
            LEVEL_N2 => array(5, 5, 5, 10, 10, 10, 5, 10, 5),
            LEVEL_N1 => array(4, 4, 10, 10, 10, 10, 10, 10, 5)
        );
    }

    public function get_default_w_grades() {
        return array(
            LEVEL_SENSEI => array(3, 4, 5, 6, 6, 8, 9, -1, -1, -1),
            LEVEL_N5 => array('N5', 'N5', 1, 'N5', 1, 2, 'N4'),
            LEVEL_N4 => array('N5', 'N4', 'N4', 2, 'N4', 3, 'N4', 4, 'N2'),
            LEVEL_N3 => array('N5', 'N4', 'N3', 'N3', 4, 'N3', 'N3', 5, 'N2'),
            LEVEL_N2 => array('N5', 'N4', 'N3', 'N2', 5, 'N2', 'N2', 6, 'N1'),
            LEVEL_N1 => array('N4', 'N3', 'N2', 'N1', 9, 'N1', 'N1', 9, -1)
        );
    }

    public function init_twisters() {
        $this->twisters = array(
            new KanaTwister("/([ぢづず])/u", '$transfo = array("ぢ"=>"じ", "づ"=>"ず", "ず"=>"つ"); return $transfo[$match];', 2),
            new KanaTwister("/([ぢづ])/u", 'return remove_tenten($match);', 1.5),
            new KanaTwister("/(([おこごほぼぽそとど])お)/u", 'return $sub_match . "う";', 2),
            new KanaTwister("/(([おこごほぼぽそとど])う)/u", 'return $sub_match . "お";', 0.2),
            new KanaTwister("/(([くぐすずつふぶぷる])う)/u", 'return $sub_match;', 1.5),
            new KanaTwister("/(([えけげせぜてでへべぺねめれいきぎちひびぴみに])い)/u", 'return $sub_match;', 1.5),
            new KanaTwister("/(ん([なぬにのね]))/u", 'return $sub_match;', 2),
            new KanaTwister("/(ん([やよゆ]))/u", 'return "に" . make_small($sub_match);', 1.5),
            new KanaTwister("/(に([やよゆ]))/u", 'return "に" . make_small($sub_match);', 1.2),
            new KanaTwister("/(に([ゃょゅ]))/u", 'return "ん" . make_big($sub_match);', 1.5),
            new KanaTwister("/(に([ゃょゅ]))/u", 'return "に" . make_big($sub_match);', 1.2),
            new KanaTwister("/(っ([かくきこけたつちとてぱぷぴぽぺ]))/u", 'return $sub_match;', 1.2),
            new KanaTwister("/([きぎしじちひびぴにみり][ょゅ])(?:[^うん]|$)/u", 'return $match . "う";', 1.2),
            new KanaTwister("/(([きぎしじちひびぴにみり][ょゅ])う)(?:[^ん]|$)/u", 'return $sub_match;', 1.4),
            new KanaTwister("/([おこごそぞとどのほぼぽもよろ])(?:[^うんっ]|$)/u", 'return $match . "う";', 0.8),
            new KanaTwister("/(([おこごそぞとどのほぼぽもよろ])う)(?:[^ん]|$)/u", 'return $sub_match;', 0.9),
            new KanaTwister("/([ばぶびべぼ])/u", 'return add_maru(remove_tenten($match));', 0.6),
            new KanaTwister("/(?:^|[^っ])([ぱぷぴぺぽ])/u", 'return add_tenten(remove_maru($match));', 0.7),
            new KanaTwister("/([かくきけこはふひへほたてとさすしせそ])/u", 'return add_tenten($match);', 0.5),
            new KanaTwister("/([がぐぎげごばぶびべぼだでどざずじぜぞ])/u", 'return remove_tenten($match);', 0.5),
            new KanaTwister("/([えけげせぜてでへべぺねめれいきぎちひびぴみに])(?:[^いょゃっ]|$)/u", 'return $match . "い";', 0.05),
            new KanaTwister("/([じず])/u", '$transfo = array("じ" => "ぢ", "ず" => "づ"); return $transfo[$match];', 0.1),
        );
    }

    function edit_button_link() {
        if ($_SESSION['user']->is_on_translator_probation() && !$_SESSION['user']->get_pref('lang', 'translator_mode'))
            return '';

        if ($_SESSION['user']->get_pref('lang', 'vocab_lang') != 'en' || $_SESSION['user']->isEditor()) {
            $solution = $this->get_solution();

            return '<a class="icon-button ui-state-default ui-corner-all" title="Languages..." href="#" onclick="show_vocab_translate_dialog(\'' . SERVER_URL . '\', \'' . $solution->jmdict_id . '\', \'' . $this->data['sid'] . '\'); return false;">✍</a>';
        } else
            return '';
    }

    public function feedback_form_options() {

        foreach ($this->data['choices'] as $choice)
            $readings[$choice->reading] = $choice->reading;

        if (!$this->is_quiz())
            $forms[] = array('type' => 'reading_wrong_level', 'title' => 'Wrong level', 'param_1' => $this->get_solution_id(), 'param_1_title' => 'Reading(s) for \'' . $this->get_solution()->word . '\' do not belong at this JLPT level.', 'param_3_title' => ' Reading (optional):', 'param_3' => $readings, 'param_1_required' => true, 'param_2_required' => false);

        $forms[] = array('type' => 'reading_other', 'title' => 'Other...', 'param_1' => $this->get_solution_id(), 'param_1_title' => 'Problem on this word', 'param_3_title' => 'with reading (optional):', 'param_3' => $readings, 'param_1_required' => true, 'param_2_required' => false);

        return $forms;
    }

    function has_feedback_options() {
        return true;
    }

}

?>