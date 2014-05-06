<?php

require_once 'Question.php';

mb_internal_encoding('UTF-8');
mb_regex_encoding('UTF-8');
require_once ABS_PATH . 'libs/utf8_lib.php';

class Reading extends Question
{

    public $tableLearning = 'reading_learning';
    public $tableLearningIndex = 'jmdict_id';
    public $quizType = 'reading';
    public $defaultSize = 10;
    private $fastMode = false;

    public function __construct($mode, $level, $grade = 0, $data = null)
    {
        parent::__construct($mode, $level, $grade, $data);
    }

    public function displayChoices($nextSid = '')
    {

        $submitURL = SERVER_URL . 'ajax/submit_answer/?sid=' . $this->data['sid'] . '&amp;time_created=' . (int) $this->created . '&amp;';
        $choices = $this->data['choices'];
        shuffle($choices);

        if ($this->isQuiz()) {
            $anticheat = '<div class="cheat-trick" style="display:block;">瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着</div>';
        } else {
            $anticheat = '';
        }

        foreach ($choices as $choice) {
            echo '<div class="choice japanese" lang="ja" xml:lang="ja" onclick="submit_answer(\'' . $this->data['sid'] . '\', \'' . $nextSid . '\', \'' . $submitURL . 'answer_id=' . (int) $choice->id . '\'); return false;">' . $anticheat . $choice->reading . '</div>';
        }

        echo '<div class="choice skip" onclick="submit_answer(\'' . $this->data['sid'] . '\',  \'' . $nextSid . '\', \'' . $submitURL . 'answer_id=' . SKIP_ID . '\'); return false;">&nbsp;?&nbsp;</div>';
    }

    public function displayHint()
    {
        $solution = $this->getSolution();
        echo '<div class="japanese" lang="ja" xml:lang="ja">' . $solution->word . '</div>';

        if (!$this->isQuiz()) {
            if ($_SESSION['user']->get_pref('drill', 'show_reading_translation')) {
                if ($solution->missing_lang) {
                    echo '<div class="missing_lang">' . $solution->fullgloss;
                    if (!$_SESSION['user']->is_on_translator_probation()) {
                        echo ' <a class="" href="#" onclick="show_vocab_translate_dialog(\'' . SERVER_URL . '\', \'' . $solution->id . '\', \'' . $this->data['sid'] . '\'); return false;"><img src="' . SERVER_URL . 'img/flags/' . $_SESSION['user']->get_pref('lang',
                            'vocab_lang') . '.png" class="missing_lang_icon' . ($this->isQuiz() ? ' disabled' : '') . '" /></a>';
                    }
                    echo '</div>';
                } else {
                    if ($_SESSION['user']->get_pref('lang', 'vocab_lang') != 'en' && substr($solution->fullgloss, 0, 3) == '(~)') {
                        if ($this->isQuiz()) {
                            echo '<div class="missing_lang">' . $solution->gloss_english . ' <a class="" href="#" onclick="show_vocab_translate_dialog(\'' . SERVER_URL . '\', \'' . $solution->id . '\', \'' . $this->data['sid'] . '\'); return false;"><img src="' . SERVER_URL . 'img/flags/' . $_SESSION['user']->get_pref('lang',
                                'vocab_lang') . '.png" class="missing_lang_icon disabled" /></a></div>' . ' <small><em>(incomplete translation)</em></small>';
                        } else {
                            echo '<div class="missing_lang">' . substr($solution->fullgloss, 3) . ' <a class="" href="#" onclick="show_vocab_translate_dialog(\'' . SERVER_URL . '\', \'' . $solution->id . '\', \'' . $this->data['sid'] . '\'); return false;"><img src="' . SERVER_URL . 'img/flags/' . $_SESSION['user']->get_pref('lang',
                                'vocab_lang') . '.png" class="missing_lang_icon" /></a></div>  <small><em>(translation may need improving)</em></small>';
                        }
                    } else {
                        echo $solution->fullgloss;
                    }
                }
            } else {
                echo make_toggle_visibility("<span class=\"translation\">" . $solution->fullgloss . "</span><br/>");
            }
        }
    }

    public function isAboveLevel($grade, $jlptLevel, $orSame = true)
    {
        $add = ($orSame ? 1 : 0);
        if ($grade[0] == 'N') {
            return ($jlptLevel < $add + (int) $grade[1]);
        } elseif ($grade) {
            return ($jlptLevel < 5 + $add - ceil($grade * 4 / 9));
        } else {
            return false;
        }
    }

    public function displayCorrection($answerID)
    {
        $solution = $this->getSolution();

        $wrongReading = '';
        if ($answerID != SKIP_ID && !$this->isSolution($answerID)) {
            foreach ($this->data['choices'] as $choice) {
                if ($choice->id == $answerID) {
                    $wrongReading = ', <span class="wrong" lang="ja" xml:lang="ja">' . $choice->reading . '</span>';
                }
            }
        }

        foreach ($solution->readings as $key => $val) {
            if ($val == $solution->reading) {
                $solution->readings[$key] = '<span class="main_reading" lang="ja" xml:lang="ja">' . $val . '</span>';
            }
        }

        $displayWordSolution = preg_replace('/([^\\x{3040}-\\x{30FF}])/ue',
            "'<span class=\"kanji_detail\" href=\"#\" onclick=\"show_kanji_details(\'\\1\', \''. SERVER_URL . 'ajax/kanji_details/kanji/'.urlencode('\\1').'\');  return false;\">\\1</span>'",
            $solution->word);


        echo '<span class="main">' . $displayWordSolution . ': ' . implode(', ', $solution->readings) . $wrongReading . '</span><br/>';

        if ($solution->missing_lang) {
            echo '<div class="missing_lang">' . $solution->fullgloss;
            if (!$_SESSION['user']->is_on_translator_probation()) {
                echo ' <a class="" href="#" onclick="show_vocab_translate_dialog(\'' . SERVER_URL . '\', \'' . $solution->id . '\', \'' . $this->data['sid'] . '\'); return false;"><img src="' . SERVER_URL . 'img/flags/' . $_SESSION['user']->get_pref('lang',
                    'vocab_lang') . '.png" class="missing_lang_icon' . ($this->isQuiz() ? ' disabled' : '') . '" /></a>';
            }
            echo '</div>';
        } else {
            echo '<br/> ' . $solution->fullgloss . '<br/>';
        }

        echo '<br/><span class="comment">Kanji breakdown:</span>';
        foreach ($solution->kanji_prons as $kanji => $prons) {
            $kuns = $ons = [];
            foreach ($prons as $pron) {
                ($pron->type == 'on') ? $ons[] = $pron->pron : $kuns[] = $pron->pron;
            }
            echo '<br/>• ' . $kanji . ': ' . (@count($kuns) ? implode(', ', $kuns) . ' - ' : '') . (@count($ons) ? implode(', ',
                    $ons) : '');
        }
    }

    public function learnSet($userID, $learningSet, $learnOthers = false)
    {
        return parent::learn_set($userID, $learningSet, $learnOthers);
    }

    public function getDBData($howMany, $grade)
    {
        if ($this->isQuiz() || !empty($_SESSION['user'])) {
            $picks = $this->getRandomReadings($grade, $grade, $howMany);
        } elseif ($this->isDrill()) {
            $picks = $this->getWeightedReadings($grade, $grade, $howMany);
        } else {
            $picks = $this->getSetWeightedReadings($howMany);
        }

        foreach ($picks as $pick) {
            $pick->jmdict_id = $pick->id;

            $pick->readings = [];
            $preProcessed = false;
            $query = 'SELECT j.reading as main_reading, r.*, (rd.jmdict_id IS NOT NULL) AS pre_processed FROM jmdict j LEFT JOIN jmdict_reading r ON r.jmdict_id = j.id LEFT JOIN reading_decoys rd ON rd.jmdict_id = j.id WHERE j.word = ? GROUP BY j.reading, r.jmdict_reading_id';

            try {
                $stmt = DB::getConnection()->prepare($query);
                $stmt->execute([$pick->word]);

                while ($row = $stmt->fetchObject()) {
                    if (!in_array($row->main_reading, $pick->readings)) {
                        $pick->readings[] = $row->main_reading;
                    }

                    if ($row->reading && !in_array($row->reading, $pick->readings)) {
                        $pick->readings[] = $row->reading;
                    }

                    if ($row->pre_processed) {
                        $preProcessed = true;
                    }
                }
                $stmt = null;
            } catch (PDOException $e) {
                log_db_error($query, $e->getMessage(), false, true);
            }

            $pick->kanji_prons = [];

            if ($preProcessed) {
                $str = mb_ereg_replace('(.)々', '\\1\\1', $pick->word);
                preg_match_all('/([^\\x{3040}-\\x{30FF}]?)([\\x{3040}-\\x{30FF}]*)/u', $str, $matches, PREG_SET_ORDER);
                array_pop($matches); // last elem is empty

                $kanjis = [];
                foreach ($matches as $match) {
                    if (!empty($match[1])) {
                        $kanjis[] = $match[1];
                    }
                }

                if (count($kanjis)) {
                    $kanjiCSV = '\'' . implode('\', \'', $kanjis) . '\'';
                    $query = 'SELECT k.id, k.kanji, p.pron, p.type, 1 as coef_prob FROM kanjis k JOIN pron p ON p.kanji_id = k.id AND p.type != \'nanori\' WHERE k.kanji IN (' . $kanjiCSV . ')';
                    try {
                        $stmt = DB::getConnection()->prepare($query);
                        $stmt->execute();
                        while ($row = $stmt->fetchObject()) {
                            $pick->kanji_prons[$row->kanji][] = $row;
                        }
                        $stmt = null;
                    } catch (PDOException $e) {
                        log_db_error($query, $e->getMessage(), false, true);
                    }
                }

                $query = 'SELECT reading_decoy, score FROM reading_decoys rd WHERE rd.jmdict_id = ? ORDER BY POW(RAND(), 10000/GREATEST(rd.score, 1)) DESC LIMIT 3';
                try {
                    $stmt = DB::getConnection()->prepare($query);
                    $stmt->execute([(int) $pick->jmdict_id]);

                    $sims = [];
                    while ($row = $stmt->fetchObject()) {
                        $sims[$row->reading_decoy] = $row->score / 10000;
                    }
                    $stmt = null;
                } catch (PDOException $e) {
                    log_db_error($query, $e->getMessage(), false, true);
                }
            }

            if (count($sims) < 3) {
                $this->initTwisters();
                $sims = $this->getSimilarReadings($pick->word, $pick->readings, 3, $grade, $pick->kanji_prons);
            }

            if (count($sims) < 3) {
                log_error("Not enough reading choices for " . $pick->word . ": " . print_r($sims, true) . "\n\n" . print_r($pick,
                        true), true, true);
            }

            $choice = [0 => $pick];
            foreach ($sims as $variant => $prob) {
                $choice[] = array2obj(['reading' => $variant, 'prob' => $prob]);
            }

            if ($choice[0]->reading == $choice[1]->reading || $choice[0]->reading == $choice[2]->reading || $choice[0]->reading == $choice[3]->reading || $choice[1]->reading == $choice[2]->reading || $choice[1]->reading == $choice[3]->reading || $choice[2]->reading == $choice[3]->reading) {
                log_error("IDENTICAL READINGS: " . print_r($choice, true) . "\n\n" . print_r($picks, true), true, false);
            }
            $indices = range(1, 4);
            shuffle($indices);

            foreach ($choice as $i => $var) {
                $choice[$i]->id = $indices[$i];
            }

            $sid = 'sid_' . md5('himitsu' . time() . '-' . rand(1, 100000));
            $data[$sid] = ['sid' => $sid, 'choices' => $choice, 'solution' => $choice[0]];
        }

        $this->fastMode = false;
        return $data;
    }

    public function getRandomReadings($grade1 = -1, $grade2 = -1, $howMany = 1)
    {
        if ((int) $howMany < 1) {
            log_error('get_random_readings called with how_many = ' . $howMany, false, true);
        }

        if ($grade1[0] == 'N') {
            $grade1 = $grade1[1];
            $grade2 = $grade2[1];
            $levelField = 'njlpt';
            $harder = '<=';
            $easier = '>=';
            $strictlyHarder = '<';
            $easiest = '5';
        } else {
            $levelField = 'pri_nf';
            $harder = '>=';
            $easier = '<=';
            $strictlyHarder = '>';
            $easiest = '1';
        }

        $query = 'SELECT j.*, jx.pos, ' . Vocab::get_query_gloss() . ' FROM (SELECT j.`id` AS `id`, j.`word` AS `word`, j.`reading`, `j`.`njlpt` AS `njlpt`, `j`.`njlpt_r` AS `njlpt_r`  FROM `jmdict` j WHERE j.word != j.reading AND j.katakana = \'0\' AND j.usually_kana = 0 AND j.njlpt > 0 ';

        if ($grade1 > 0) {
            $grade1 = (int) $grade1;

            if ($levelField == 'njlpt') {
                $rLevel = ($grade2 > 0 ? $grade2 : $grade1);
            } else {
                $rLevel = $_SESSION['user']->getJLPTNumLevel();
            }

            if ($grade2 > 0) {
                $grade2 = (int) $grade2;
                if ($grade1 == $grade2) {
                    $query .= " AND (($levelField = $grade1 AND njlpt_r >= $rLevel) OR ($grade1 $strictlyHarder $levelField AND njlpt_r = $rLevel))";
                } else {
                    $query .= " AND $levelField $easier $grade2 AND (($levelField = $grade1 AND njlpt_r >= $rLevel) OR ($grade1 $strictlyHarder $levelField AND njlpt_r = $rLevel))";
                }
            } else {
                $query .= " AND $levelField $harder $easiest AND $levelField $easier $grade1 AND njlpt_r >= $rLevel";
            }
        }

        $query .= ' GROUP BY `id` ORDER BY RAND()';
        $query .= '  LIMIT ' . (int) $howMany . ') AS j LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id ';

        try {
            $stmt = DB::getConnection()->prepare($query);
            $stmt->execute();
            $readingCount = $stmt->rowCount();

            if ($readingCount < $howMany) {
                log_error('Reading mode (get_random_readings): Can\'t get enough randomized vocab: ' . $query . "\n(needed $howMany, got: " . $readingCount . ')',
                    false, true);
            }

            if ($howMany == 1) {
                return [$stmt->fetchObject()];
            }

            $readings = $stmt->fetchAll(PDO::FETCH_CLASS);
            $stmt = null;
            shuffle($readings);
            return $readings;
        } catch (PDOException $e) {
            log_db_error($query, $e->getMessage(), true, true);
        }
    }

    public function getSetWeightedReadings($howMany = 1)
    {

        $query = "SELECT j.*, jx.pos, " . Vocab::get_query_gloss() . " FROM (SELECT j.`id` AS `id`, j.`word` AS `word`, j.`reading` AS `reading`, `j`.`njlpt` AS `njlpt`, `j`.`njlpt_r` AS `njlpt_r`, IF(l.curve IS NULL, 1000, l.curve)+1000*rand() as xcurve
		FROM learning_set_vocab ls LEFT JOIN `jmdict` j ON j.id = ls.jmdict_id
		LEFT JOIN " . $this->tableLearning . " l on l.user_id = '" . (int) $_SESSION['user']->getID() . "' AND j.id = l." . $this->tableLearningIndex . "
		WHERE ls.set_id = $this->set_id AND j.word != j.reading AND j.katakana = '0' AND j.usually_kana = 0 ";

        $query .= '  ORDER BY xcurve DESC';
        $query .= '  LIMIT ' . $howMany . ') AS j LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id';

        try {
            $stmt = DB::getConnection()->prepare($query);
            $stmt->execute();
            $readingCount = $stmt->rowCount();

            if ($readingCount < $howMany) {
                die('This set does not contain enough entries to be drilled on. Please add more entries and try again.');
            }

            if ($howMany == 1) {
                return [$stmt->fetchObject()];
            }

            $readings = $stmt->fetchAll(PDO::FETCH_CLASS);
            $stmt = null;
            shuffle($readings);
            return $readings;
        } catch (PDOException $e) {
            log_db_error($query, $e->getMessage(), true, true);
        }
    }

    public function getWeightedReadings($grade1 = -1, $grade2 = -1, $howMany = 1)
    {
        if ((int) $howMany < 1) {
            log_error('get_weighted_readings called with how_many = ' . $howMany, false, true);
        }

        if ($grade1[0] == 'N') {
            $grade1 = $grade1[1];
            $grade2 = $grade2[1];
            $levelField = 'njlpt';
            $harder = '<=';
            $strictlyHarder = '<';
            $easier = '>=';
            $easiest = '5';
        } else {
            $levelField = 'pri_nf';
            $harder = '>=';
            $strictlyHarder = '>';
            $easier = '<=';
            $easiest = '1';
        }

        $query = "SELECT j.*, jx.pos, " . Vocab::get_query_gloss() . " FROM (SELECT j.`id` AS `id`, j.`word` AS `word`, j.`reading` AS `reading`, `j`.`njlpt` AS `njlpt`, `j`.`njlpt_r` AS `njlpt_r`, IF(l.curve IS NULL, 1000, l.curve)+1000*rand() as xcurve
		FROM `jmdict` j
		LEFT JOIN " . $this->tableLearning . " l on l.user_id = '" . (int) $_SESSION['user']->getID() . "' AND j.id = l." . $this->tableLearningIndex . "
		WHERE  j.word != j.reading AND j.katakana = '0' AND j.usually_kana = 0 AND j.njlpt >0 ";

        if ($grade1 > 0) {
            $grade1 = (int) $grade1;

            if ($levelField == 'njlpt') {
                $rLevel = ($grade2 > 0 ? $grade2 : $grade1);
            } else {
                $rLevel = $_SESSION['user']->getJLPTNumLevel();
            }

            if ($grade2 > 0) {
                $grade2 = (int) $grade2;
                if ($grade1 == $grade2) {
                    $query .= " AND (($levelField = $grade1 AND njlpt_r >= $rLevel) OR ($grade1 $strictlyHarder $levelField AND njlpt_r = $rLevel))";
                } else {
                    $query .= " AND $levelField $easier $grade2 AND (($levelField = $grade1 AND njlpt_r >= $rLevel) OR ($grade1 $strictlyHarder $levelField AND njlpt_r = $rLevel))";
                }
            } else {
                $query .= " AND $levelField $harder $easiest AND $levelField $easier $grade1 AND njlpt_r >= $rLevel";
            }
        }
        $query .= '  ORDER BY xcurve DESC';
        $query .= '  LIMIT ' . $howMany . ') AS j LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id';

        try {
            $stmt = DB::getConnection()->prepare($query);
            $stmt->execute();
            $readingCount = $stmt->rowCount();

            if ($readingCount < $howMany) {
                log_error('Reading mode (get_weighted_readings): Can\'t get enough randomized vocab: ' . $query . "\n(needed $howMany, got: " . $readingCount . ")",
                    false, true);
            }

            if ($howMany == 1) {
                return [$stmt->fetchObject()];
            }

            $readings = $stmt->fetchAll(PDO::FETCH_CLASS);
            $stmt = null;
            shuffle($readings);
            return $readings;
        } catch (PDOException $e) {
            log_db_error($query, $e->getMessage(), true, true);
        }
    }

    public function getSimilarReadings($word, $readings, $howMany, $grade, &$kanjiProns)
    {
        try {

            $str = mb_ereg_replace('(.)々', '\\1\\1', $word);
            preg_match_all('/([^\\x{3040}-\\x{30FF}]?)([\\x{3040}-\\x{30FF}]*)/u', $str, $matches, PREG_SET_ORDER);
            array_pop($matches); // last elem is empty

            $kanjis = [];
            foreach ($matches as $match) {
                if (!empty($match[1])) {
                    $kanjis[] = $match[1];
                }
            }

            if (count($kanjis)) {
                $kanjiCSV = '\'' . implode('\', \'', $kanjis) . '\'';
                $query = 'SELECT k.id, k.kanji, p.pron, p.type, 1 as coef_prob FROM kanjis k JOIN pron p ON p.kanji_id = k.id AND p.type != \'nanori\' WHERE k.kanji IN (' . $kanjiCSV . ')';
                try {
                    $stmt = DB::getConnection()->prepare($query);
                    $stmt->execute();
                    while ($row = $stmt->fetchObject()) {
                        $kanjiProns[$row->kanji][] = $row;
                    }
                    $stmt = null;
                } catch (PDOException $e) {
                    log_db_error($query, $e->getMessage(), false, true);
                }

                if (count($kanjiProns) <= 2 * $howMany || rand(0, 2) == 0) {
                    $query = 'SELECT k.kanji, s1.combi_sim, p.pron, p.type, (s1.combi_sim/100) as coef_prob FROM kanjis k
						JOIN sim_kanjis s1 ON s1.k1_id = k.id
						LEFT JOIN sim_kanjis s2 ON s2.k1_id = k.id AND s2.k2_id != s1.k2_id AND s2.combi_sim > s1.combi_sim
						LEFT JOIN pron p ON p.kanji_id = s1.k2_id
						WHERE s2.id IS NULL AND s1.combi_sim > 85 AND k.kanji IN (' . $kanjiCSV . ') ORDER BY RAND() LIMIT :howmany';

                    try {
                        $stmt = DB::getConnection()->prepare($query);
                        $stmt->bindValue(':howmany', 4 * $howMany, PDO::PARAM_INT);
                        $stmt->execute();
                        while ($row = $stmt->fetchObject()) {
                            $kanjiProns[$row->kanji][] = $row;
                        }
                        $stmt = null;
                    } catch (PDOException $e) {
                        log_db_error($query, $e->getMessage(), false, true);
                    }
                }
            }

            $varParts = [];
            $allKanji = true;

            foreach ($matches as $idx => $match) {
                $matches[$idx][2] = mb_convert_kana($matches[$idx][2], 'c');
            }

            $sqlPattern = '';
            $regexPattern = '/^';

            foreach ($matches as $match) {
                if (!empty($match[1])) {
                    $sqlPattern .= '_';
                    if (substr($regexPattern, -2) != '.+') {
                        $regexPattern .= '.+';
                    }
                }
                if (!empty($match[2])) {
                    $sqlPattern .= $match[2];
                    $regexPattern .= $match[2];
                }
            }

            $regexPattern .= '$/u';


            foreach ($matches as $i => $match) {
                $kanji = $match[1];
                $tail = mb_convert_kana($match[2], 'c');

                if ($tail) {
                    $allKanji = false;
                }

                if (empty($kanji)) {
                    $varParts[$i][$tail] = ['kanji' => '', 'kana' => $tail, 'prob' => 1, 'type' => 'kana'];
                } elseif (isset($kanjiProns[$kanji])) {
                    $pronKanaVars = [];

                    foreach ($kanjiProns[$kanji] as $pron) {
                        list($pronStr, $prob) = $this->getPronProb($pron, $tail, $i, count($matches));
                        $pronKanaVars[$pron->type][$pronStr] = $prob * $pron->coef_prob;
                    }

                    foreach ($pronKanaVars as $type => $onKunVars) {
                        foreach ($this->twisters as $twister) {
                            $onKunVars = $twister->twist($onKunVars);
                        }

                        $sortOrder = $this->weightedRandSelect($onKunVars);
                        $sortOrder = array_slice($sortOrder, 0, $howMany);

                        foreach ($sortOrder as $pron => $weight) {
                            $varParts[$i][$pron . $tail] = ['kanji' => $kanji, 'kana' => $pron . $tail, 'prob' => $onKunVars[$pron], 'type' => $type];
                        }
                    }
                } else {
                    log_error('Can\'t find pron for kanji: ' . $kanji, false, true);
                }
            }

            $variations = ['' => 1];
            foreach ($varParts as $i => $part) {
                $newVariations = [];
                foreach ($variations as $var => $varProb) {
                    foreach ($part as $pron) {
                        if (rand(0, count($part)) > 20) {
                            continue;
                        }

                        $coef = ($varTypes[$var] && $varTypes[$var] != $pron['type']) ? 0.6 : 1;
                        $newVar = $var . $pron['kana'];
                        $newVariations[$newVar] = ($coef * $varProb * $pron['prob']);
                        if ($varTypes[$newVar] && $varTypes[$newVar] != $pron['type']) {
                            unset($varTypes[$newVar]);
                        } else {
                            $varTypes[$newVar] = $pron['type'];
                        }
                    }
                }
                $variations = $newVariations;
            }

            if ($allKanji) {
                foreach ($readings as $reading) {
                    $variations2[$reading] = 1.2;
                }
                foreach ($this->twisters as $twister) {
                    $variations2 = $twister->twist($variations2);
                }
                foreach ($variations2 as $var => $prob) {
                    $variations[$var] = $prob;
                }
            }

            // Add similar matches
            $likeStr = '';

            for ($i = 0; $i < count($matches); $i++) {
                $pattern = '';
                foreach ($matches as $idx => $match) {
                    if (empty($match[1])) {
                        if ($i == $idx) {
                            continue 2;
                        }

                        $pattern .= $match[2];
                    } else {
                        if ($i != $idx) {
                            $pattern .= $match[1] . $match[2];
                        } else {
                            $pattern .= '_' . $match[2];
                        }
                    }
                }

                $likeStr .= " OR j.word LIKE '$pattern'";
            }


            if ($likeStr) {
                $tot = 0;
                foreach ($matches as $match) {
                    if (!$match[1]) {
                        $tot += 0.05;
                    } elseif ($match[2]) {
                        $tot += 0.3;
                    } else {
                        $tot += 0.25;
                    }
                }

                $query = 'SELECT j.word, j.reading FROM jmdict j WHERE j.word != :word AND (0' . $likeStr . ') LIMIT 10';
                try {
                    $stmt = DB::getConnection()->prepare($query);
                    $stmt->execute();
                    $stmt->bindValue(':word', $word, PDO::PARAM_STR);
                    while ($row = $stmt->fetchObject()) {
                        if (preg_match($regexPattern, $row->reading)) {
                            $variations[$row->reading] = ($tot - 0.25);
                        }
                    }
                    $stmt = null;
                } catch (PDOException $e) {
                    log_db_error($query, $e->getMessage(), false, true);
                }
            }

            foreach ($readings as $reading) {
                unset($variations[$reading]);
            }

            $variations = preg_grep("/(っ[っうんあいおえがぐぎげごばぶびべぼだづまむみめもぢでど])|んっ/u", $variations, PREG_GREP_INVERT);

            arsort($variations);
            $variations = array_slice($variations, 0, $howMany * 10);
            $selection = array_slice($this->weightedRandSelect($variations), 0, $howMany);

            if (count($selection) < $howMany) {
                $need = $howMany - count($selection);
                $query = 'SELECT r.reading FROM jmdict r WHERE r.katakana = \'0\' AND r.word != r.reading AND r.reading NOT IN (\'' . implode('\',\'',
                        $readings) . '\') AND r.reading LIKE \'' . $sqlPattern . '\' ORDER BY (r.njlpt != 0) DESC, RAND() LIMIT :need';

                try {
                    $stmt = DB::getConnection()->prepare($query);
                    $stmt->execute();
                    $stmt->bindValue(':need', $need, PDO::PARAM_INT);
                    while ($row = $stmt->fetchObject()) {
                        if (preg_match($regexPattern, $row->reading)) {
                            $selection[$row->reading] = 0;
                        }
                    }
                    $stmt = null;
                } catch (PDOException $e) {
                    log_db_error($query, $e->getMessage(), false, true);
                }

                if (count($selection) < min(10, $howMany)) {
                    $query = 'SELECT r.reading FROM jmdict r WHERE r.katakana = \'0\' AND r.reading != r.word ORDER BY RAND() LIMIT :howmany';

                    try {
                        $stmt = DB::getConnection()->prepare($query);
                        $stmt->execute();
                        $stmt->bindValue(':howmany', 10 * $howMany, PDO::PARAM_INT);

                        //Ultimate backup
                        $sanityCheck = 0;
                        while (count($selection) < min(10, $howMany)) {
                            $newVar = '';
                            foreach ($matches as $match) {
                                if (!empty($match[1])) {
                                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                                    $newVar .= $row['reading'];
                                }
                                if (!empty($match[2])) {
                                    $newVar .= $match[2];
                                }
                            }

                            if (!in_array($newVar, $readings)) {
                                $selection[$newVar] = 0.0001;
                            }

                            if ($sanityCheck++ > 100) {
                                die('Reading::get_similar_readings() - sanity_check - Backup selection failure: ' . $query);
                            }
                        }

                        $stmt = null;
                    } catch (PDOException $e) {
                        log_db_error($query, $e->getMessage(), false, true);
                    }
                }
            }
        } catch (Exception $e) {
            log_error('Exception in ' . __FILE__ . ':get_similar_readings(): ' . $e->getMessage() . "\n" . $word . "\n" . print_r($readings,
                    true) . "\n" . print_r($variations), false, true);
        }
        return $selection;
    }

    public function weightedRandSelect($population)
    {
        $sortOrder = [];

        foreach ($population as $var => $prob) {
            $sortOrder[$var] = ($prob ? pow(rand() / getrandmax(), 1 / $prob) : 0);
        }

        arsort($sortOrder);
        return $sortOrder;
    }

    public function getPronProb($pron, $tailStr, $pos, $totLen)
    {
        $prob = 1.0;
        $pronStr = mb_convert_kana($pron->pron, 'c');

        if (($dashPos = mb_strpos($pronStr, '-')) !== false) {
            $pronStr = str_replace(['-'], '', $pronStr);
            if ($dashPos == 0) {
                if ($pos == 0) {
                    $prob /= 10;
                } elseif (!$tailStr && $pos >= $totLen) {
                    $prob *= 1.5;
                }
            } else {
                if ($pos == 0) {
                    $prob *= 1.5;
                } elseif (!$tailStr && $pos >= $totLen) {
                    $prob /= 10;
                }
            }
        }

        if ($pron->type == 'kun' && (($dotPos = mb_strpos($pronStr, '.')) !== false)) {
            $pronPrefixStr = mb_substr($pronStr, 0, $dotPos);
            $pronTailStr = mb_substr($pronStr, $dotPos + 1);
            $pronTailEnd = mb_substr($pronTailStr, -1, 1);

            if ($tailStr) {
                if ($pronTailStr == $tailStr) {
                    return [$pronPrefixStr, 2 * $prob];
                }

                $tailEnd = mb_substr($tailStr, -1, 1);

                if ($pronTailEnd == $tailEnd) {
                    return [$pronPrefixStr, 1.2 * $prob];
                }

                if (mb_substr($pronTailStr, 0, mb_strlen($tailStr)) == $tailStr) {
                    return [$pronPrefixStr, 1 * $prob];
                }

                if ($this->verbToNoun($pronTailEnd) == $tailEnd) {
                    return [$pronPrefixStr, (mb_strlen($tailStr) == 1 ? 2 : 1.5) * $prob];
                }

                return [$pronPrefixStr, 0.3 * $prob];
            } else { // no kana tail in the original word
                if ($totLen == 1) {// single kanji word
                    if ($pronTailStr == $pronTailEnd) {// only one trailing char
                        return [$pronPrefixStr . $this->verbToNoun($pronTailEnd), 1 * $prob];
                    } else {
                        // more than one trailing char
                        return [$pronPrefixStr . mb_substr($pronTailStr, 0, -1) . $this->verbToNoun($pronTailEnd), 0.6 * $prob];
                    }
                } elseif (mb_strlen($pronPrefixStr) == 1) { // compound word AND pron is one kana
                    return [$pronPrefixStr, 0.5 * $prob];
                } elseif ($pronTailStr == $pronTailEnd) {
                    // compound word AND pron is longer than one kana
                    // only one trailing char
                    return [$pronPrefixStr . $this->verbToNoun($pronTailEnd), 0.4 * $prob];
                } else {
                    // more than one trailing char
                    return [$pronPrefixStr . mb_substr($pronTailStr, 0, -1) . $this->verbToNoun($pronTailEnd), 0.3 * $prob];
                }
            }
        }

        // Regular on/kun:
        if ($tailStr) {
            if ($pron->type == 'on') {
                return [$pronStr, 0.2 * $prob];
            } else {
                return [$pronStr, 0.3 * $prob];
            }
        } elseif ($totLen == 1) {
            if ($pron->type == 'on') {
                return [$pronStr, 0.8 * $prob];
            } else {
                return [$pronStr, 1.0 * $prob];
            }
        } else {
            if ($pron->type == 'on') {
                return [$pronStr, 0.9 * $prob];
            } else {
                return [$pronStr, 0.7 * $prob];
            }
        }
    }

    public function verbToNoun($verbEnding)
    {
        $endingMap = ['く' => 'き', 'ぐ' => 'ぎ', 'る' => 'り', 'む' => 'み', 'す' => 'し', 'ず' => 'じ', 'つ' => 'ち', 'ぬ' => 'に', 'ぶ' => 'び'];
        return($endingMap[$verbEnding] ? $endingMap[$verbEnding] : $verbEnding);
    }

    public function getGradeOptions()
    {
        if ($this->isQuiz()) {
            return null;
        }

        for ($i = 1; $i <= 5; $i++) {
            $options[] = ['grade' => 'N' . $i, 'label' => 'JLPT ' . $i, 'selected' => ($this->getGrade() == 'N' . $i)];
        }

        return $options;
    }

    public function getSolutionID()
    {
        $sol = $this->getSolution();
        return $sol->jmdict_id;
    }

    public function getDefaultWSizes()
    {
        return [
            LEVEL_SENSEI => [4, 6, 6, 6, 10, 10, 10, 5, 10, 5],
            LEVEL_N5 => [5, 10, 10, 10, 10, 10, 5],
            LEVEL_N4 => [5, 5, 10, 10, 10, 10, 10, 10, 5],
            LEVEL_N3 => [5, 5, 5, 10, 10, 10, 5, 10, 5],
            LEVEL_N2 => [5, 5, 5, 10, 10, 10, 5, 10, 5],
            LEVEL_N1 => [4, 4, 10, 10, 10, 10, 10, 10, 5]
        ];
    }

    public function getDefaultWGrades()
    {
        return [
            LEVEL_SENSEI => [3, 4, 5, 6, 6, 8, 9, -1, -1, -1],
            LEVEL_N5 => ['N5', 'N5', 1, 'N5', 1, 2, 'N4'],
            LEVEL_N4 => ['N5', 'N4', 'N4', 2, 'N4', 3, 'N4', 4, 'N2'],
            LEVEL_N3 => ['N5', 'N4', 'N3', 'N3', 4, 'N3', 'N3', 5, 'N2'],
            LEVEL_N2 => ['N5', 'N4', 'N3', 'N2', 5, 'N2', 'N2', 6, 'N1'],
            LEVEL_N1 => ['N4', 'N3', 'N2', 'N1', 9, 'N1', 'N1', 9, -1]
        ];
    }

    public function initTwisters()
    {
        $this->twisters = [
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
        ];
    }

    public function editButtonLink()
    {
        if ($_SESSION['user']->is_on_translator_probation() && !$_SESSION['user']->get_pref('lang', 'translator_mode')) {
            return '';
        }

        if ($_SESSION['user']->get_pref('lang', 'vocab_lang') != 'en' || $_SESSION['user']->isEditor()) {
            $solution = $this->getSolution();
            return '<a class="icon-button ui-state-default ui-corner-all" title="Languages..." href="#" onclick="show_vocab_translate_dialog(\'' . SERVER_URL . '\', \'' . $solution->jmdict_id . '\', \'' . $this->data['sid'] . '\'); return false;">✍</a>';
        } else {
            return '';
        }
    }

    public function feedbackFormOptions()
    {
        foreach ($this->data['choices'] as $choice) {
            $readings[$choice->reading] = $choice->reading;
        }

        if (!$this->isQuiz()) {
            $forms[] = ['type' => 'reading_wrong_level', 'title' => 'Wrong level', 'param_1' => $this->getSolutionID(), 'param_1_title' => 'Reading(s) for \'' . $this->getSolution()->word . '\' do not belong at this JLPT level.', 'param_3_title' => ' Reading (optional):', 'param_3' => $readings, 'param_1_required' => true, 'param_2_required' => false];
        }

        $forms[] = ['type' => 'reading_other', 'title' => 'Other...', 'param_1' => $this->getSolutionID(), 'param_1_title' => 'Problem on this word', 'param_3_title' => 'with reading (optional):', 'param_3' => $readings, 'param_1_required' => true, 'param_2_required' => false];

        return $forms;
    }

    public function hasFeedbackOptions()
    {
        return true;
    }
}
