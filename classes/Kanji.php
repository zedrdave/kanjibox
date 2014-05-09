<?php

require_once 'Question.php';

define('OPTIONS_UNIQUE_PRON', 1);
define('OPTIONS_UNIQUE_ENG', 2);

class Kanji extends Question
{

    public $tableLearning = 'learning';
    public $tableLearningIndex = 'kanji_id';
    public $quizType = 'kanji';
    public static $langStrings = ['en' => 'english', 'ru' => 'russian', 'sp' => 'spanish', 'fr' => 'french', 'sv' => 'swedish', 'pl' => 'polish', 'de' => 'german', 'fi' => 'finnish', 'it' => 'italian', 'tr' => 'turkish', 'th' => 'thai'];

    public function __construct($mode, $level, $grade = -2, $data = null)
    {
        $_SESSION = null;
        parent::__construct($mode, $level, $grade, $data);
    }

    public function displayChoices($nextSID = '')
    {
        $submitURL = SERVER_URL . 'ajax/submit_answer/?sid=' . $this->data['sid'] . '&amp;time_created=' . (int) $this->created . '&amp;';
        $choices = $this->data['choices'];
        shuffle($choices);

        if ($this->isQuiz()) {
            $anticheat = '<div class="cheat-trick" style="display:block;">瞞着 瞞着 瞞着</div>';
        } else {
            $anticheat = '';
        }

        foreach ($choices as $choice) {
            echo '<div class="choice japanese" lang="ja" xml:lang="ja" onclick="submit_answer(\'' . $this->data['sid'] . '\', \'' . $nextSID . '\', \'' . $submitURL . 'answer_id=' . (int) $choice->id . '\'); return false;">' . $anticheat . $choice->kanji . '</div>';
        }

        echo '<div class="choice skip" onclick="submit_answer(\'' . $this->data['sid'] . '\',  \'' . $nextSID . '\', \'' . $submitURL . 'answer_id=' . SKIP_ID . '\'); return false;">&nbsp;?&nbsp;</div>';
    }

    public function displayHint()
    {
        $solution = $this->getSolution();
        $meanStr = $solution->mean_str . ($solution->traditional ? ' (旧)' : '');
        $solution->prons = Kanji::getPronunciations($solution);
        if (!$solution->prons) {
            log_error('Kanji::display_hint() - Empty prons array for id: ' . $solution->id);
        }

        echo '<div class="japanese" lang="ja" xml:lang="ja">' . $solution->prons . '</div>';
        if ($this->isQuiz() || $_SESSION['user']->get_pref('drill', 'show_english')) {
            if ($solution->missing_lang) {
                echo '<div class="missing_lang meaning">' . $meanStr;
                if (!$_SESSION['user']->is_on_translator_probation()) {
                    echo ' <a class="" href="#" onclick="show_kanji_translate_dialog(\'' . SERVER_URL . '\', \'' . $solution->id . '\', \'' . $this->data['sid'] . '\'); return false;"><img src="' . SERVER_URL . 'img/flags/' . $_SESSION['user']->get_pref('lang',
                        'kanji_lang') . '.png" class="missing_lang_icon' . ($this->isQuiz() ? ' disabled' : '') . '" /></a>';
                }
                echo '</div>';
            } elseif ($_SESSION['user']->get_pref('lang', 'kanji_lang') != 'en' && substr($meanStr, 0, 3) == '(~)') {
                if ($this->isQuiz()) {
                    echo '<div class="missing_lang meaning">' . $solution->meaning_english . ($solution->traditional ? ' (旧)' : '') . ' <a class="" href="#" onclick="show_kanji_translate_dialog(\'' . SERVER_URL . '\', \'' . $solution->id . '\', \'' . $this->data['sid'] . '\'); return false;"><img src="' . SERVER_URL . 'img/flags/' . $_SESSION['user']->get_pref('lang',
                        'kanji_lang') . '.png" class="missing_lang_icon disabled" /></a>  <br/><small><em>(incomplete translation)</em></small></div>';
                } else {
                    echo '<div class="missing_lang meaning">' . substr($meanStr, 3) . ' <a class="" href="#" onclick="show_kanji_translate_dialog(\'' . SERVER_URL . '\', \'' . $solution->id . '\', \'' . $this->data['sid'] . '\'); return false;"><img src="' . SERVER_URL . 'img/flags/' . $_SESSION['user']->get_pref('lang',
                        'kanji_lang') . '.png" class="missing_lang_icon" /></a> <br/><small><em>(translation may need improving)</em></small></div>';
                }
            } else {
                echo '<div class="meaning">' . $meanStr . '</div>';
            }
        } else {
            echo make_toggle_visibility('<span class="meaning">' . $meanStr . '</span><br/>');
        }
    }

    public function displayCorrection($answerID)
    {
        $showExamples = (!$this->isQuiz() && $_SESSION['user']->get_pref('drill', 'show_examples'));

        $solution = $this->getSolution();

        echo '<span class="kanji main" lang="ja" xml:lang="ja">' . $solution->kanji . '</span> ';
        echo '✦ <span lang="ja" xml:lang="ja">' . $solution->prons . '</span> ✦ <span class="meaning">' . $solution->mean_str . ($solution->traditional ? ' (旧)' : '') . '</span>';
        if ($showExamples) {
            if ($example = $this->getKanjiExamplesStr($solution->id)) {
                echo '<div class="example_str" lang="ja" xml:lang="ja">Ex: ' . $example . '</div>';
            }
        }
        if ($answerID != SKIP_ID && !$this->isSolution($answerID)) {
            echo '<br/><br/>';

            if (!$wrong = $this->getKanjiID((int) $answerID)) {
                log_error('Unknown kanji ID: ' . (int) $answerID, false, true);
            }
            echo '<span class="kanji main" lang="ja" xml:lang="ja">' . $wrong->kanji . "</span> ";
            echo '✦ <span lang="ja" xml:lang="ja">' . Kanji::getPronunciations($wrong) . '</span> ✦ <span class="wrong_meaning">' . $wrong->mean_str . ($wrong->traditional ? ' (旧)' : '') . '</span>';

            if ($showExamples) {
                if ($example = $this->getKanjiExamplesStr($wrong->id)) {
                    echo '<div class="example_str" lang="ja" xml:lang="ja">Ex: ' . $example . '</div>';
                }
            }
        }
    }

    public function getDBData($howMany, $grade, $userID = -1, $cumulative = false, $options = 0)
    {
        if ($this->isQuiz()) {
            if ($cumulative) {
                if ($grade == 'J0' || $grade == '0' || $grade == '-1') { //bit of a hack
                    $picks = $this->get_random_kanjis(-1, -1, $howMany * 2);
                } else {
                    $picks = $this->get_random_kanjis('N5', $grade, $howMany * 2);
                }
            } else {
                $picks = $this->get_random_kanjis($grade, $grade, $howMany * 2);
            }
        } elseif ($this->isLearningSet()) {
            $picks = $this->geSetWeightedKanjis($userID, $howMany);
        } else {
            $picks = $this->getRandomWeightedKanjis($userID, $grade, $grade, $howMany * 2);
        }

        if ($grade > 100) {
            log_error("Impossible grade (please report this problem): $grade\n" . print_r($this, true), true, false);
        }

        for ($i = 0; $i < count($picks) - 1; $i+=2) {
            $choice = [];
            $choice[0] = $picks[$i];
            $choice[2] = $picks[$i + 1];
            $choice[1] = $this->getOtherKanji($choice[0]->id, $grade, [$choice[2]->id, $choice[0]->id], 1, $options);
            $choice[3] = $this->getOtherKanji($choice[2]->id, $grade, [$choice[0]->id, $choice[1]->id, $choice[2]->id],
                1, $options);
            $sid = 'sid_' . md5('himitsu' . time() . '-' . rand(1, 100000));
            $data[$sid] = ['sid' => $sid, 'choices' => $choice, 'solution' => $choice[0]];

            //###DEBUG
            if ($choice[0]->id == $choice[1]->id || $choice[0]->id == $choice[2]->id || $choice[0]->id == $choice[3]->id || $choice[1]->id == $choice[2]->id || $choice[1]->id == $choice[3]->id || $choice[2]->id == $choice[3]->id) {
                log_error('IDENTICAL KANJI (please report this problem): ' . print_r($choice, true) . "\n\n" . print_r($picks,
                        true), true, true);
            }
        }

        return $data;
    }

    public static function getRandomKanjis($grade1 = -1, $grade2 = -1, $howMany = 1, $exclude = null, $options = 0)
    {
        if ($grade1[0] == 'N') {
            $grade1 = $grade1[1];
            if ($grade2[0] == 'N') {
                $grade2 = $grade2[1];
            }

            $levelField = 'njlpt';
            $harder = '<=';
            $easier = '>=';
            $easiest = '5';
        } else {
            $levelField = 'grade';
            $harder = '>=';
            $easier = '<=';
            $easiest = '1';
        }

        $query = 'SELECT  `id`, `kanji`, `traditional`, `prons`, ' . Kanji::getQueryMeaning() . ' FROM `kanjis` k LEFT JOIN kanjis_ext kx ON kx.kanji_id = k.id WHERE';

        if ($grade1 <= 0 && $howMany == 1) {
            $query .= ' `id` >= (SELECT FLOOR( MAX(`id`) * RAND()) FROM `kanjis` )';
        } else {
            $query .= ' 1';
        }

        if ($exclude) {
            $query .= " AND k.id  NOT IN (" . implode(',', $exclude) . ')';
        }

        if ($grade1 > 0) {
            if ($grade2 > 0) {
                $query .= " AND `$levelField` $harder " . (int) $grade1 . " AND `$levelField` $easier " . (int) $grade2;
            } else {
                $query .= " AND `$levelField` $harder $easiest AND `$levelField` $easier " . (int) $grade1;
            }
            $query .= ' ORDER BY RAND()';
        } elseif ($howMany > 1) {
            $query .= ' ORDER BY RAND()';
        } else {
            $query .= ' ORDER BY `id`';
        }

        $query .= '  LIMIT ' . $howMany;

        try {
            $stmt = DB::getConnection()->prepare($query);
            $stmt->execute();
            $rowCount = $stmt->rowCount();

            if ($rowCount < $howMany) {
                log_db_error($query, 'Can\'t get enough randomized kanjis');
            }

            if ($howMany == 1) {
                return $stmt->fetchObject();
            }

            $kanjis = $stmt->fetchAll(PDO::FETCH_CLASS);
            $stmt = null;
            shuffle($kanjis);

            return $kanjis;
        } catch (PDOException $e) {
            log_db_error($query, $e->getMessage(), true, true);
        }
    }

    //STATIC needed for use by multiplayer
    public static function getRandomWeightedKanjis($userID, $grade1 = -1, $grade2 = -1, $howMany = 1,
        $queryWhereExtra = '')
    {
        if ($grade1[0] == 'N') {
            $grade1 = $grade1[1];
            $grade2 = $grade2[1];
            $levelField = 'njlpt';
            $harder = '<=';
            $easier = '>=';
            $easiest = '5';
        } else {
            $levelField = 'grade';
            $harder = '>=';
            $easier = '<=';
            $easiest = '1';
        }

        $query = "SELECT  k.`id`, k.`kanji`, k.`traditional`, `prons`, " . Kanji::getQueryMeaning() . ", IF(l.curve IS NULL, 1000, curve)+1000*rand() as xcurve from kanjis k LEFT JOIN kanjis_ext kx ON kx.kanji_id = k.id left join learning l on l.user_id = ? AND k.id = l.kanji_id WHERE 1 ";

        if ($grade1 > 0) {
            if ($grade2 > 0) {
                $query .= " AND `$levelField` $harder " . (int) $grade1 . " AND `$levelField` $easier " . (int) $grade2;
            } else {
                $query .= " AND `$levelField` $harder $easiest AND `$levelField` $easier " . (int) $grade1;
            }
        }

        $translatorMode = (isset($_SESSION['user']) && $_SESSION['user']->get_pref('lang', 'kanji_lang') != 'en' && $_SESSION['user']->get_pref('lang',
                'translator_mode'));

        if ($translatorMode) {
            if ($_SESSION['user']->is_on_translator_probation()) {
                $query .= ' AND kx.meaning_' . Kanji::$langStrings[$_SESSION['user']->get_pref('lang', 'kanji_lang')] . " LIKE '(~)%'";
            } else {
                $query .= ' AND (kx.meaning_' . Kanji::$langStrings[$_SESSION['user']->get_pref('lang', 'kanji_lang')] . ' IS NULL OR kx.meaning_' . Kanji::$langStrings[$_SESSION['user']->get_pref('lang',
                        'kanji_lang')] . " = '' OR kx.meaning_" . Kanji::$langStrings[$_SESSION['user']->get_pref('lang',
                        'kanji_lang')] . " LIKE '(~)%')";
            }
        }

        $query .= $queryWhereExtra;

        $query .= '  ORDER BY xcurve DESC';
        $query .= '  LIMIT ' . $howMany;

        try {
            $stmt = DB::getConnection()->prepare($query);
            $stmt->execute([$userID]);
            $rowCount = $stmt->rowCount();

            if ($rowCount <= 1) {
                if ($translatorMode) {
                    if ($rowCount == 1) {
                        $kanjis[] = Kanji::getRandomKanjis($grade1, $grade2, 1);
                    } else {
                        die('No kanji left to translate at this level. Please change level or turn off Translator mode.');
                    }
                } else {
                    log_error('Can\'t get enough randomised kanjis: ' . $query, true, true);
                }
            }

            $kanjis = $stmt->fetchAll(PDO::FETCH_CLASS);
            $stmt = null;
            shuffle($kanjis);

            return $kanjis;
        } catch (PDOException $e) {
            log_db_error($query, $e->getMessage(), true, true);
        }
    }

    public function geSetWeightedKanjis($userID, $howMany = 1)
    {
        $query = "SELECT  k.`id`, k.`kanji`, k.`traditional`, `prons`, " . Kanji::getQueryMeaning() . ", IF(l.curve IS NULL, 1000, curve)+1000*rand() as xcurve FROM learning_set_kanji ls LEFT JOIN kanjis k ON k.id = ls.kanji_id LEFT JOIN kanjis_ext kx ON kx.kanji_id = k.id left join learning l on l.user_id = '" . (int) $userID . "' AND k.id = l.kanji_id WHERE ls.set_id = $this->set_id ";

        $translatorMode = false;
        if ($translatorMode) {
            $query .= ' AND (kx.meaning_' . Kanji::$langStrings[$_SESSION['user']->get_pref('lang', 'kanji_lang')] . ' IS NULL OR kx.meaning_' . Kanji::$langStrings[$_SESSION['user']->get_pref('lang',
                    'kanji_lang')] . " = '')";
        }

        $query .= '  ORDER BY xcurve DESC';
        $query .= '  LIMIT ' . $howMany;

        try {
            $stmt = DB::getConnection()->prepare($query);
            $stmt->execute([$userID]);
            $rowCount = $stmt->rowCount();

            if ($rowCount <= 1) {
                if ($translatorMode) {
                    die('No kanji left to translate at this level. Please change level or turn off Translator mode.');
                } else {
                    log_error('get_set_weighted_kanjis: Can\'t get enough randomised kanjis: ' . $query, true, true);
                }
            }

            $kanjis = $stmt->fetchAll(PDO::FETCH_CLASS);
            $stmt = null;
            shuffle($kanjis);

            return $kanjis;
        } catch (PDOException $e) {
            log_db_error($query, $e->getMessage(), true, true);
        }
    }

    public function getOtherKanji($kanjiID, $grade, $exclude = null, $howMany = 1, $options = 0)
    {
        if (!$kanji = $this->getSimilarKanjis($kanjiID, $howMany, $this->getNextGrade($grade), -1, $exclude, '',
            $options)) {
            if (!$kanji = $this->getSimilarKanjis($kanjiID, $howMany, -1, -1, $exclude, '', $options)) {
                if (!$kanji = $this->getRandomKanjis($grade, $grade, $howMany, $exclude, $options, 0)) {
                    $kanji = $this->getRandomKanjis($grade, -1, $howMany, $exclude, $options, 0);
                }
            }
        }

        return $kanji;
    }

    public static function getSimilarKanjis($kanjiID, $howmany = 1, $grade1 = -1, $grade2 = -1, $exclude = 0,
        $queryWhereExtra = '', $options = 0)
    {
        if (!$kanjiID) {
            log_error('get_similar_kanjis error: kanji_id = ' . $kanjiID, true, true);
        }

        if ($grade1[0] == 'N') {
            $grade1 = (int) $grade1[1];
            $grade2 = (int) $grade2[1];
            $levelField = 'njlpt';
            $harder = '<=';
            $easier = '>=';
            $easiest = '5';
        } else {
            $grade1 = (int) $grade1;
            $grade2 = (int) $grade2;
            $levelField = 'grade';
            $harder = '>=';
            $easier = '<=';
            $easiest = '1';
        }

        $query = 'SELECT  k.`id`,  k.`kanji`,  k.`traditional`, `prons`, ' . Kanji::getQueryMeaning() . ',
s.main_rad AS main_rad_sim,
s.sim AS multi_rad_sim,
s.stroke_dist,
s.classic_rad AS classic_rad_sim,
s.combi_sim + 5*RAND() AS combi_sim,
k.`njlpt`
      FROM `sim_kanjis` s
      JOIN `kanjis` k ON k.id = s.`k2_id`
        LEFT JOIN kanjis_ext kx ON k.id = kx.kanji_id
      LEFT JOIN kanji_variants v ON v.kanji_id = k.id
      LEFT JOIN kanji_variants v2 ON v2.kanji_id = :kanji_id AND v2.variant_id = v.variant_id
      WHERE v2.variant_id IS NULL AND s.`k1_id` = :kanji_id';

        if ($grade1 > -1) {
            if ($grade2 > -1) {
                $query .= " AND k.`$levelField` $harder :grade1 AND k.`$levelField` $easier :grade2";
            } else {
                $query .= " AND k.`$levelField` $harder $easiest AND k.`$levelField` $easier :grade1";
            }
        }

        if ($exclude) {
            if ($options == OPTIONS_UNIQUE_PRON) {
                $query .= ' AND k.id  NOT IN (' . implode(',', $exclude) . ')';
            } else {
                $query .= ' AND prons NOT IN (SELECT prons FROM kanjis_ext WHERE kanji_id IN (' . implode(',', $exclude) . '))';
            }
        }
        $query .= $queryWhereExtra;
        $query .= ' GROUP BY k.id ORDER BY combi_sim DESC, k.njlpt DESC LIMIT :howmany';

        try {
            $stmt = DB::getConnection()->prepare($query);
            $stmt->bindValue(':kanji_id', $kanjiID, PDO::PARAM_INT);
            $stmt->bindValue(':howmany', $howmany, PDO::PARAM_INT);

            if ($grade1 > -1) {
                if ($grade2 > -1) {
                    $stmt->bindValue(':grade1', $grade1, PDO::PARAM_INT);
                    $stmt->bindValue(':grade2', $grade2, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue(':grade1', $grade1, PDO::PARAM_INT);
                }
            }

            $stmt->execute();

            if ($howmany == 1) {
                return $stmt->fetchObject();
            }

            $kanjis = $stmt->fetchAll(PDO::FETCH_CLASS);
            $stmt = null;

            return $kanjis;
        } catch (PDOException $e) {
            log_db_error($query, $e->getMessage(), true, true);
        }
    }

    public function getKanjiID($kanjiID)
    {
        $query = 'SELECT `id`, `kanji`, `traditional`, `prons`, ' . Kanji::getQueryMeaning() . ' FROM `kanjis` k LEFT JOIN kanjis_ext kx ON kx.kanji_id = k.id WHERE `id` = :id';
        try {
            $stmt = DB::getConnection()->prepare($query);
            $stmt->bindValue(':id', $kanjiID, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchObject();
        } catch (PDOException $e) {
            log_db_error($query, $e->getMessage(), true, true);
        }
    }

    public function getKanji($kanji)
    {
        $query = 'SELECT `id`, `kanji`, `traditional`, `prons`, ' . Kanji::getQueryMeaning() . ' FROM `kanjis` k LEFT JOIN kanjis_ext kx ON kx.kanji_id = k.id WHERE `kanji` = :kanji';
        try {
            $stmt = DB::getConnection()->prepare($query);
            $stmt->bindValue(':kanji', $kanji, PDO::PARAM_STR);
            $stmt->execute();

            return $stmt->fetchObject();
        } catch (PDOException $e) {
            log_db_error($query, $e->getMessage(), true, true);
        }
    }

    public function updateMeaningStr($newMeaning)
    {
        $solution = $this->getSolution();
        $solution->mean_str = $newMeaning;
        $solution->missing_lang = false;
    }

    public static function getMeaningStr($kanjiID, $trad = false)
    {
        $query = 'SELECT `meaning` FROM `english` WHERE `kanji_id` = :id LIMIT 3';
        try {
            $stmt = DB::getConnection()->prepare($query);
            $stmt->bindValue(':id', $kanjiID, PDO::PARAM_INT);
            $stmt->execute();

            $meanings = $stmt->fetchAll(PDO::FETCH_CLASS);
        } catch (PDOException $e) {
            log_db_error($query, $e->getMessage(), true, true);
        }

        return implode(', ', $meanings) . ($trad ? ' (traditional)' : '');
    }

    public static function getPronunciations($dbRec)
    {
        if (!empty($dbRec->prons)) {
            return $dbRec->prons;
        }

        $query = 'SELECT `pron` FROM `pron` WHERE `kanji_id` = :id and `type` != \'nanori\' order by `type` DESC';
        try {
            $stmt = DB::getConnection()->prepare($query);
            $stmt->bindValue(':id', $dbRec->id, PDO::PARAM_INT);
            $stmt->execute();

            $prons = $stmt->fetchAll(PDO::FETCH_CLASS);
        } catch (PDOException $e) {
            log_db_error($query, $e->getMessage(), true, true);
        }

        return implode(', ', $prons);
    }

    public function getKanjiExamplesStr($kanjiID)
    {
        $query = 'SELECT j.`id`, `word`, `reading`, ' . Vocab::get_query_gloss() . ' FROM `kanji2word` k2w LEFT JOIN `jmdict` j ON j.id = k2w.word_id LEFT JOIN jmdict_ext jx on jx.jmdict_id = j.id WHERE k2w.`kanji_id` = :id GROUP BY j.id ORDER BY pri + rand() * 3 ASC LIMIT 3';
        try {
            $stmt = DB::getConnection()->prepare($query);
            $stmt->bindValue(':id', $kanjiID, PDO::PARAM_INT);
            $stmt->execute();

            $row = $stmt->fetchObject();
            if ($row) {
                $str = '<!-- ' . $row->id . '-->' . '<span class="kanji">' . $row->word . '</span>' . ' [' . $row->reading . '] - ' . $row->fullgloss;
            } else {
                return '';
            }

            if ($stmt->rowCount() > 1) {
                $tempstr = '';
                while ($row = $stmt->fetchObject()) {
                    $tempstr .= '<!-- ' . $row->id . '-->' . '<span class="kanji">' . $row->word . '</span>' . ' [' . $row->reading . '] - ' . $row->fullgloss . '<br/>';
                }
                $tempstr = mb_substr($tempstr, 0, -5);
                $str .= make_toggle_visibility($tempstr);
            }

            return $str;
        } catch (PDOException $e) {
            log_db_error($query, $e->getMessage(), true, true);
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

        for ($i = 1; $i <= 9; $i++) {
            $options[] = ['grade' => $i, 'label' => 'Grade ' . $i, 'selected' => ($this->getGrade() == $i)];
        }

        return $options;
    }

    public function feedbackFormOptions()
    {
        foreach ($this->data['choices'] as $choice) {
            $kanjiIDs[$choice->id] = $choice->kanji;
        }
        $forms[] = ['type' => 'kanji_same_def', 'title' => 'Confusing choices - Similar definitions', 'param_1' => $kanjiIDs, 'param_1_title' => 'Between ', 'param_2_title' => ' and ', 'param_2' => $kanjiIDs, 'param_1_required' => true, 'param_2_required' => true];
        $forms[] = ['type' => 'kanji_tradit', 'title' => 'Traditional form of the same kanji', 'param_1' => $kanjiIDs, 'param_1_title' => 'This kanji ', 'param_2_title' => ' is the traditional variant of ', 'param_2' => $kanjiIDs, 'param_1_required' => true, 'param_2_required' => true];

        if (!$this->isQuiz()) {
            $forms[] = ['type' => 'kanji_wrong_level', 'title' => 'Wrong level', 'param_1' => $kanjiIDs, 'param_1_title' => 'This kanji doesn\'t belong at this JLPT level:', 'param_1_required' => true, 'param_2_required' => false];
        }
        $forms[] = ['type' => 'kanji_other', 'title' => 'Other...', 'param_1' => $kanjiIDs, 'param_1_title' => 'Kanji 1:', 'param_2_title' => ' - Kanji 2 (optional):', 'param_2' => $kanjiIDs, 'param_1_required' => true, 'param_2_required' => false];

        return $forms;
    }

    public function hasFeedbackOptions()
    {
        return true;
    }

    public function getDefaultWGrades()
    {
        $array = parent::getDefaultWGrades();
        $array[LEVEL_SENSEI] = [3, 4, 5, 6, 6, 8, 9, -1, -1, -1];

        return $array;
    }

    public function editButtonLink()
    {
        if ($_SESSION['user']->is_on_translator_probation() && !$_SESSION['user']->get_pref('lang', 'translator_mode')) {
            return '';
        }

        if ($_SESSION['user']->get_pref('lang', 'kanji_lang') != 'en' || $_SESSION['user']->isEditor()) {
            $solution = $this->getSolution();

            return '<a class="icon-button ui-state-default ui-corner-all" title="Languages..." href="#" onclick="show_kanji_translate_dialog(\'' . SERVER_URL . '\', \'' . $solution->id . '\', \'' . $this->data['sid'] . '\'); return false;">✍</a>';
        } else {
            return '';
        }
    }

    public static function getQueryMeaning()
    {
        global $_SESSION;

        if (isset($_SESSION)) {
            $lang = $_SESSION['user']->get_pref('lang', 'kanji_lang');
        } else {
            $lang = 'en';
        }

        if ($lang == 'en') {
            $glossQuery = 'kx.meaning_english AS mean_str, 0 AS missing_lang';
        } elseif (isset(Kanji::$langStrings[$lang])) {
            $glossQuery = 'IFNULL(kx.meaning_' . Kanji::$langStrings[$lang] . ', kx.meaning_english) AS mean_str, (kx.meaning_' . Kanji::$langStrings[$lang] . ' IS NULL) AS missing_lang, kx.meaning_english';
        } else {
            log_error('Unknown language: ' . $lang, true, true);
        }

        return $glossQuery;
    }
}
