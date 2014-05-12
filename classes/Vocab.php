<?php

require_once 'Question.php';

class Vocab extends Question
{

    public $tableLearning = 'jmdict_learning';
    public $tableLearningIndex = 'jmdict_id';
    public $quizType = 'vocab';
    public static $langStrings = ['en' => 'english', 'de' => 'german', 'fr' => 'french', 'sp' => 'spanish', 'ru' => 'russian', 'sv' => 'swedish', 'fi' => 'finnish', 'pl' => 'polish', 'it' => 'italian', 'tr' => 'turkish', 'th' => 'thai'];

    public function __construct($mode, $level, $gradeOrSetID = 0, $data = null)
    {
        parent::__construct($mode, $level, $gradeOrSetID, $data);
    }

    public function displayChoices($nextSID = '')
    {
        $submitURL = SERVER_URL . 'ajax/submit_answer/?sid=' . $this->data['sid'] . '&amp;time_created=' . (int) $this->created . '&amp;';

        $choices = $this->data['choices'];
        shuffle($choices);

        if ($this->isQuiz()) {
            $anticheat = '<div class="cheat-trick" style="display:block;">瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着</div>';
        } else {
            $anticheat = '';
        }

        $readingPref = $_SESSION['user']->get_pref('drill', 'show_reading');
        $hideRareKanjiPref = $_SESSION['user']->get_pref('general', 'hide_rare_kanji');
        foreach ($choices as $choice) {
            // if($_SESSION['user']->is_admin())
            if (($choice->usually_kana == 1) && $hideRareKanjiPref) {
                echo '<div class="choice japanese" lang="ja" xml:lang="ja" onclick="submit_answer(\'' . $this->data['sid'] . '\', \'' . $nextSID . '\', \'' . $submitURL . 'answer_id=' . (int) $choice->id . '\'); return false;">' . $anticheat . $choice->reading . '</div>';
            } else {
                echo '<div class="choice japanese" lang="ja" xml:lang="ja" onclick="submit_answer(\'' . $this->data['sid'] . '\', \'' . $nextSID . '\', \'' . $submitURL . 'answer_id=' . (int) $choice->id . '\'); return false;">' . $anticheat . $choice->word;

                if ($choice->word != $choice->reading && !$choice->katakana
                    && ((!$this->isQuiz()
                    && $readingPref != 'never'
                    && ($readingPref == 'always' || $this->isAboveLevel($this->getGrade(), $choice->njlpt_r)))
                    || ($this->isQuiz() && $this->isAboveLevel($this->levelToGrade($this->getLevel()), $choice->njlpt_r)))) {
                    echo '<div class="furigana">' . $choice->reading . '</div>';
                }
                echo '</div>';
            }
        }

        echo '<div class="choice skip" onclick="submit_answer(\'' . $this->data['sid'] . '\',  \'' . $nextSID . '\', \'' . $submitURL . 'answer_id=' . SKIP_ID . '\'); return false;">&nbsp;?&nbsp;</div>';
    }

    public function displayHint()
    {
        $solution = $this->getSolution();
        if (@$solution->missing_lang) {
            echo '<div class="missing_lang">' . $solution->fullgloss;
            if (!$_SESSION['user']->isOnTranslatorProbation()) {
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

        if ($answerID != SKIP_ID && !$this->isSolution($answerID)) {
            if (!$wrong = $this->getVocabID((int) $answerID)) {
                log_error('Unknown Vocab ID: ' . $answerID, true, true);
            }
        }

        $hideRareKanjiPref = $_SESSION['user']->get_pref('general', 'hide_rare_kanji');

        if ($solution->usually_kana && $hideRareKanjiPref) {
            $displayWordSolution = $solution->reading;
        } else {
            $displayWordSolution = preg_replace('/([^\\x{3040}-\\x{30FF}])/ue',
                "'<span class=\"kanji_detail\" href=\"#\" onclick=\"show_kanji_details(\'\\1\', \''. SERVER_URL . 'ajax/kanji_details/kanji/'.urlencode('\\1').'\');  return false;\">\\1</span>'",
                $solution->word);
        }
        $audioLookup = ($solution->lookup_string != '' ? $solution->lookup_string : ($solution->usually_kana || $solution->full_readings != '' ? $solution->reading : $solution->word));

        echo '<span class="main" lang="ja" xml:lang="ja">' . $displayWordSolution . "</span> " . (($solution->reading != $solution->word && !$solution->katakana && $displayWordSolution != $solution->reading) ? " [" . $solution->reading . "]" : '') . ' <a href="#" onclick="play_tts(\'' . $audioLookup . '\', \'' . get_audio_hash($audioLookup) . '\'); return false;" class="tts-link"><img src="' . SERVER_URL . '/img/speaker.png" alt="play"/></a>' . " - " . $solution->fullgloss;

        if ($this->isLearningSet() && $this->set->canEdit()) {
            echo '<a class="remove-from-set" title="Remove from set" href="#" onclick="remove_entry_from_set(\'' . SERVER_URL . 'ajax/edit_learning_set/\', ' . $this->set_id . ', ' . $this->getSolution()->id . ', \'#ajax-result\'); return false;">【×】</a>' . "\n";
        }

        //Example sentence:
        $query = 'SELECT e.example_str, e.english AS example_english, e.example_id, ep.pos_start, ep.pos_end FROM example_parts ep LEFT JOIN examples e ON e.example_id = ep.example_id WHERE ep.jmdict_id = ' . (int) $solution->id . ' ORDER BY ep.prime_example DESC, e.njlpt DESC, e.njlpt_r DESC, RAND() LIMIT 1';
        try {
            $stmt = DB::getConnection()->prepare($query);
            $stmt->bindValue(':solutionid', $solution->id, PDO::PARAM_INT);
            $stmt->execute();
            $example = $stmt->fetchObject();
            if (!empty($example)) {
                $strings = Text::getSentenceWithHints($example, false, $example->pos_start, $example->pos_end, 0);
                echo '<p class="example-sentence" lang="ja" xml:lang="ja"><span class="rei">例</span>' . $strings[1] . '</p>' . PHP_EOL;
            }
            $stmt = null;
        } catch (PDOException $e) {
            log_db_error($query, $e->getMessage(), false, true);
        }

        if ($wrong) {
            if ($wrong->usually_kana && $hideRareKanjiPref) {
                $displayWordWrong = $wrong->reading;
            } else {
                $displayWordWrong = preg_replace('/([^\\x{3040}-\\x{30FF}])/ue',
                    "'<span class=\"kanji_detail\" href=\"#\" onclick=\"show_kanji_details(\'\\1\', \''. SERVER_URL . 'ajax/kanji_details/kanji/'.urlencode('\\1').'\');  return false;\">\\1</span>'",
                    $wrong->word);
            }
            echo '<br/><br/>';

            $audioLookup = ($wrong->lookup_string != '' ? $wrong->lookup_string : ($wrong->usually_kana || $wrong->full_readings != '' ? $wrong->reading : $wrong->word));

            echo '<span class="main" lang="ja" xml:lang="ja">' . $displayWordWrong . '</span>' . (($wrong->reading != $wrong->word && !$solution->katakana && $wrong->reading != $displayWordWrong) ? ' [' . $wrong->reading . ']' : '') . ' <a href="#" onclick="play_tts(\'' . $audioLookup . '\', \'' . get_audio_hash($audioLookup) . '\'); return false;" class="tts-link"><img src="' . SERVER_URL . '/img/speaker.png" alt="play"/></a>' . ' - ' . $wrong->fullgloss;
        }
    }

    public function getDBData($howMany, $grade, $userID = -1)
    {
        if ($this->isQuiz()) {
            $picks = $this->getRandomVocab($grade, $grade, $howMany);
        } elseif ($this->isLearningSet()) {
            $picks = $this->getSetWeightedVocab($userID, $howMany);
        } else {
            $picks = $this->getRandomWeightedVocab($userID, $grade, $grade, $howMany);
        }

        foreach ($picks as $pick) {
            $choice = [0 => $pick];
            $sims = $this->getSimilarVocab($choice[0], 3, $grade);
            $i = 1;
            $exclude = [$pick->id];

            foreach ($sims as $sim) {
                $choice[$i++] = $sim;
                $exclude[] = $sim->id;
            }
            if (count($sims) < 3) {
                $sims = $this->getRandomVocab($grade, $grade, 3 - count($sims), $exclude);
                foreach ($sims as $sim) {
                    $choice[$i++] = $sim;
                }
            }

            $sid = 'sid_' . md5('himitsu' . time() . '-' . rand(1, 100000));
            $data[$sid] = ['sid' => $sid, 'choices' => $choice, 'solution' => $choice[0]];
        }

        return $data;
    }

    public function getRandomVocab($grade1 = -1, $grade2 = -1, $howMany = 1, $exclude = null)
    {
        $extra = '';

        if ($grade1[0] == 'N') {
            $grade1 = $grade1[1];
            $grade2 = $grade2[1];
            $levelField = 'njlpt';
            $harder = '<=';
            $easier = '>=';
            $easiest = '5';
        } else {
            $levelField = 'pri_nf';
            $harder = '>=';
            $easier = '<=';
            $easiest = '1';

            if ($grade1 > 0) {
                if ($grade1 < 3) {
                    $extra = 'AND njlpt >= 4 ';
                } elseif ($grade1 < 5) {
                    $extra = 'AND njlpt >= 3 ';
                } elseif ($grade1 < 7) {
                    $extra = 'AND njlpt > 0 ';
                }
            }
        }

        $query = 'SELECT j.*, ' . Vocab::getQueryGloss() . ', conf.group_id AS group_id, jal.lookup_string, jx.full_readings FROM (SELECT j.`id` AS `id`, j.`word` AS `word`, j.`reading`, j.pos_redux, `j`.`njlpt` AS `njlpt`, `j`.`njlpt_r` AS `njlpt_r`, j.usually_kana, j.katakana FROM `jmdict` j
	WHERE 1 ' . $extra;

        if ($grade1 > 0) {
            if ($grade2 > 0) {
                $query .= " AND `$levelField` $harder " . (int) $grade1 . " AND `$levelField` $easier " . (int) $grade2;
            } else {
                $query .= " AND `$levelField` $harder $easiest AND `$levelField` $easier " . (int) $grade1;
            }
        }
        if ($exclude) {
            $query .= ' AND j.id NOT IN (' . implode(', ', $exclude) . ')';
        }

        $query .= ' ORDER BY RAND()';
        $query .= '  LIMIT :howmany) AS j LEFT JOIN jmdict_confusing conf ON conf.jmdict_id = j.id LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id LEFT JOIN jmdict_audio_lookup jal ON jal.jmdict_id = j.id';

        try {
            $stmt = DB::getConnection()->prepare($query);
            $stmt->bindValue(':howmany', $howMany, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() < $howMany) {
                log_error('Can\'t get enough randomized words: ' . $query, true, true);
            }

            if ($howMany == 1) {
                return [$stmt->fetchObject()];
            }

            return $stmt->fetchAll(PDO::FETCH_CLASS);
        } catch (PDOException $e) {
            log_db_error($query, $e->getMessage());
        }
    }

    public function getRandomWeightedVocab($userID, $grade1 = -1, $grade2 = -1, $howMany = 1)
    {
        if ($grade1[0] == 'N') {
            $grade1 = $grade1[1];
            $grade2 = $grade2[1];
            $levelField = 'njlpt';
            $harder = '<=';
            $easier = '>=';
            $easiest = '5';
        } else {
            $levelField = 'pri_nf';
            $harder = '>=';
            $easier = '<=';
            $easiest = '1';
        }
        $queryWhere = ' WHERE 1 ';
        if ($grade1 > 0) {
            if ($grade2 > 0) {
                $queryWhere .= " AND `$levelField` $harder " . (int) $grade1 . " AND `$levelField` $easier " . (int) $grade2;
            } else {
                $queryWhere .= " AND `$levelField` $harder $easiest AND `$levelField` $easier " . (int) $grade1;
            }
        }

        $glossQuery = Vocab::getQueryGloss();

        $translatorMode = ($_SESSION['user']->get_pref('lang', 'vocab_lang') != 'en' && $_SESSION['user']->get_pref('lang',
                'translator_mode'));

        if ($translatorMode) {
            $query = "SELECT $glossQuery, conf.group_id, jal.lookup_string, jx.full_readings, ";
        } else {
            $query = "SELECT j.*, $glossQuery, conf.group_id, jal.lookup_string, jx.full_readings FROM (SELECT ";
        }

        $query .= "j.`id` AS `id`, j.`word` AS `word`, j.`reading` AS `reading`, j.pos_redux, `j`.`njlpt` AS `njlpt`, `j`.`njlpt_r` AS `njlpt_r`, IF(l.curve IS NULL, 1000, l.curve)+1000*rand() as xcurve, j.usually_kana, j.katakana
		FROM `jmdict` j
		LEFT JOIN $this->tableLearning l on l.user_id = '" . (int) $userID . "' AND j.id = l.$this->tableLearningIndex ";

        if (!$translatorMode) {
            $query .= $queryWhere . ') AS j ';
        }

        $query .= 'LEFT JOIN jmdict_confusing conf ON conf.jmdict_id = j.id LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id LEFT JOIN jmdict_audio_lookup jal ON jal.jmdict_id = j.id';

        if ($translatorMode) {
            if ($_SESSION['user']->isOnTranslatorProbation()) {
                $query .= $queryWhere . " AND jx.gloss_" . Vocab::$langStrings[$_SESSION['user']->get_pref('lang',
                        'vocab_lang')] . " LIKE '(~)%'";
            } else {
                $query .= $queryWhere . " AND (jx.gloss_" . Vocab::$langStrings[$_SESSION['user']->get_pref('lang',
                        'vocab_lang')] . ' IS NULL OR jx.gloss_' . Vocab::$langStrings[$_SESSION['user']->get_pref('lang',
                        'vocab_lang')] . " = '' OR jx.gloss_" . Vocab::$langStrings[$_SESSION['user']->get_pref('lang',
                        'vocab_lang')] . " LIKE '(~)%')";
            }
        }

        $query .= ' ORDER BY xcurve DESC';
        $query .= ' LIMIT :howmany';

        try {
            $stmt = DB::getConnection()->prepare($query);
            $stmt->bindValue(':howmany', $howMany, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() == 0) {
                if ($translatorMode) {
                    die('No vocab left to translate at this level. Please change level or turn off Translator mode.');
                } else {
                    log_error('Can\'t get enough randomized vocab: ' . $query, true, true);
                }
            }

            if ($howMany == 1) {
                return [$stmt->fetchObject()];
            }

            $kanjis = $stmt->fetchAll(PDO::FETCH_CLASS);
            $stmt = null;
            shuffle($kanjis);
            return $kanjis;
        } catch (PDOException $e) {
            log_db_error($query, $e->getMessage());
        }
    }

    public function getSetWeightedVocab($userID, $howMany = 1)
    {
        $queryWhere = ' WHERE lse.set_id = ' . $this->set_id;

        $glossQuery = Vocab::getQueryGloss();
        $translatorMode = false;

        if ($translatorMode) {
            $query = "SELECT $glossQuery, conf.group_id, jal.lookup_string, jx.full_readings, ";
        } else {
            $query = "SELECT j.*, $glossQuery, conf.group_id, jal.lookup_string, jx.full_readings FROM (SELECT ";
        }

        $query .= "j.`id` AS `id`, j.`word` AS `word`, j.`reading` AS `reading`, j.pos_redux, `j`.`njlpt` AS `njlpt`, `j`.`njlpt_r` AS `njlpt_r`, IF(l.curve IS NULL, 1000, l.curve)+1000*rand() as xcurve, j.usually_kana, j.katakana
		FROM learning_set_vocab lse LEFT JOIN `jmdict` j ON j.id = lse.jmdict_id
		LEFT JOIN $this->tableLearning l on l.user_id = '" . (int) $userID . "' AND j.id = l.$this->tableLearningIndex ";

        if (!$translatorMode) {
            $query .= $queryWhere . ') AS j ';
        }

        $query .= 'LEFT JOIN jmdict_confusing conf ON conf.jmdict_id = j.id LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id LEFT JOIN jmdict_audio_lookup jal ON jal.jmdict_id = j.id';

        if ($translatorMode) {
            $query .= $queryWhere . " AND (jx.gloss_" . Vocab::$langStrings[$_SESSION['user']->get_pref('lang',
                    'vocab_lang')] . ' IS NULL OR jx.gloss_' . Vocab::$langStrings[$_SESSION['user']->get_pref('lang',
                    'vocab_lang')] . " = '' OR jx.gloss_" . Vocab::$langStrings[$_SESSION['user']->get_pref('lang',
                    'vocab_lang')] . " LIKE '(~)%')";
        }

        $query .= ' ORDER BY xcurve DESC';
        $query .= ' LIMIT :howmany';

        try {
            $stmt = DB::getConnection()->prepare($query);
            $stmt->bindValue(':howmany', $howMany, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() == 0) {
                if ($translatorMode) {
                    die('No vocab left to translate at this level. Please change level or turn off Translator mode.');
                } else {
                    die('Not enough vocabulary in this set yet. Please add more entries and try again.');
                }
            }

            if ($howMany == 1) {
                return [$stmt->fetchObject()];
            }

            $kanjis = $stmt->fetchAll(PDO::FETCH_CLASS);
            $stmt = null;
            shuffle($kanjis);
            return $kanjis;
        } catch (PDOException $e) {
            log_db_error($query, $e->getMessage());
        }
    }

    public function getSimilarVocab($jmdict, $howmany = 1, $grade1 = -1, $grade2 = -1)
    {
        if ($grade1[0] == 'N') {
            $grade1 = $grade1[1];
            $grade2 = $grade2[1];
            $levelField = 'njlpt';
            $harder = '<=';
            $easier = '>=';
            $easiest = '5';
        } else {
            $levelField = 'pri_nf';
            $harder = '>=';
            $easier = '<=';
            $easiest = '1';
        }

        $joinConf = ($jmdict->group_id != 0);

        $query = 'SELECT j.*, ' . Vocab::getQueryGloss() . ', jal.lookup_string, jx.full_readings FROM (SELECT j.`id` AS `id`, j.`word` AS `word`, j.`reading`, j.pos_redux, `j`.`njlpt` AS `njlpt`, `j`.`njlpt_r` AS `njlpt_r`, j.usually_kana, j.katakana FROM `jmdict` j ';

        if ($joinConf) {
            $query .= 'LEFT JOIN jmdict_confusing conf ON conf.jmdict_id = j.id AND conf.group_id = ' . ((int) $jmdict->group_id) . '
 WHERE conf.group_id IS NULL AND ';
        } else {
            $query .= 'WHERE ';
        }

        $query .= 'j.id != :dictid AND j.word != :dictword AND j.pos_redux = :dictpos_redux';

        if ($grade1 > 0) {
            if ($grade2 > 0) {
                $query .= " AND `$levelField` $harder " . (int) $grade1 . " AND `$levelField` $easier " . (int) $grade2;
            } else {
                $query .= " AND `$levelField` $harder $easiest AND `$levelField` $easier " . (int) $grade1;
            }
        }

        if ($joinConf) {
            $query .= ' GROUP BY IFNULL(conf.group_id, j.id)';
        }

        $query .= ' ORDER BY ABS(j.njlpt - ' . (int) $jmdict->njlpt . ') ASC, RAND() LIMIT :howmany) AS j LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id LEFT JOIN jmdict_audio_lookup jal ON jal.jmdict_id = j.id';

        try {
            $stmt = DB::getConnection()->prepare($query);
            $stmt->bindValue(':dictid', $jmdict->id, PDO::PARAM_INT);
            $stmt->bindValue(':dictword', $jmdict->word, PDO::PARAM_STR);
            $stmt->bindValue(':dictpos_redux', $jmdict->pos_redux, PDO::PARAM_INT);
            $stmt->bindValue(':howmany', $howmany, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() == 0) {
                if ($translatorMode) {
                    die('No vocab left to translate at this level. Please change level or turn off Translator mode.');
                } else {
                    die('Not enough vocabulary in this set yet. Please add more entries and try again.');
                }
            }

            if ($howmany == 1) {
                return [$stmt->fetchObject()];
            }

            $vocabs = $stmt->fetchAll(PDO::FETCH_CLASS);
            $stmt = null;
            shuffle($vocabs);
            return $vocabs;
        } catch (PDOException $e) {
            log_db_error($query, $e->getMessage());
        }
    }

    public function getVocabID($vocabID)
    {
        $query = 'SELECT j.`id` AS `id`, j.`word` AS `word`, j.`reading` AS `reading`, ' . Vocab::getQueryGloss() . ', j.katakana, j.usually_kana, jal.lookup_string, jx.full_readings FROM `jmdict` j LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id LEFT JOIN jmdict_audio_lookup jal ON jal.jmdict_id = j.id WHERE j.`id` = :vocabid LIMIT 1';
        try {
            $stmt = DB::getConnection()->prepare($query);
            $stmt->bindValue(':vocabid', $vocabID, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchObject();
        } catch (PDOException $e) {
            log_db_error($query, $e->getMessage(), false, true);
        }
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

    public function getDefaultWGrades()
    {
        $array = parent::getDefaultWGrades();
        $array[LEVEL_SENSEI] = [3, 4, 5, 6, 6, 8, 9, -1, -1, -1];

        return $array;
    }

    public function feedbackFormOptions()
    {
        foreach ($this->data['choices'] as $choice) {
            $words[$choice->id] = $choice->word;
        }

        $forms[] = ['type' => 'vocab_same_def', 'title' => 'Confusing choices - Similar definitions', 'param_1' => $words, 'param_1_title' => 'Between ', 'param_2_title' => ' and ', 'param_2' => $words, 'param_1_required' => true, 'param_2_required' => true];
        $forms[] = ['type' => 'vocab_furigana', 'title' => 'Need furigana at this level', 'param_1' => $words, 'param_1_title' => 'This word should have furigana at this JLPT level:', 'param_1_required' => true, 'param_2_required' => false];

        if (!$this->isQuiz()) {
            $forms[] = ['type' => 'vocab_wrong_level', 'title' => 'Wrong level', 'param_1' => $words, 'param_1_title' => 'This word doesn\'t belong at this JLPT level:', 'param_1_required' => true, 'param_2_required' => false];
        }

        $forms[] = ['type' => 'vocab_other', 'title' => 'Other...', 'param_1' => $words, 'param_1_title' => 'Word 1:', 'param_2_title' => ' - Word 2 (optional):', 'param_2' => $words, 'param_1_required' => true, 'param_2_required' => false];

        return $forms;
    }

    public function hasFeedbackOptions()
    {
        return true;
    }

    public function editButtonLink()
    {
        if ($_SESSION['user']->isOnTranslatorProbation() && !$_SESSION['user']->get_pref('lang', 'translator_mode')) {
            return '';
        }

        if ($_SESSION['user']->get_pref('lang', 'vocab_lang') != 'en' || $_SESSION['user']->isEditor()) {
            $solution = $this->getSolution();

            return '<a class="icon-button ui-state-default ui-corner-all" title="Languages..." href="#" onclick="show_vocab_translate_dialog(\'' . SERVER_URL . '\', \'' . $solution->id . '\', \'' . $this->data['sid'] . '\'); return false;">✍</a>';
        } else {
            return '';
        }
    }

    public static function getQueryGloss()
    {
        $lang = $_SESSION['user']->get_pref('lang', 'vocab_lang');

        if ($lang == 'en') {
            $glossQuery = 'jx.gloss_english AS `fullgloss`, 0 AS missing_lang';
        } elseif (isset(Vocab::$langStrings[$lang])) {
            $glossQuery = 'IFNULL(jx.gloss_' . Vocab::$langStrings[$lang] . ', jx.gloss_english) AS `fullgloss`, (jx.gloss_' . Vocab::$langStrings[$lang] . ' IS NULL) AS missing_lang, jx.gloss_english AS gloss_english';
        } else {
            log_error('Unknown language: ' . $lang, true, true);
        }

        return $glossQuery;
    }
}
