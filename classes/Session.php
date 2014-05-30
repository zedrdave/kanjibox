<?php

class Session
{

    private $questionLoader;
    private $sessionMode;
    private $sessionLevel;
    private $sessionWSizes;
    private $sessionWGrades;
    private $sessionWPoints;
    private $curWave;
    private $waveSize;
    private $wavePoints;
    private $waveGrade;
    private $lastWaveScore = 0;
    private $totScore = 0;
    private $scoreID = 0;
    private $curCorrect = 0;
    private $curTotal = 0;
    private $curRank = -1;
    private $setSize = -1;
    private $startTime;
    private $questions;
    public $gameOver = false;
    private static $levelPass = [LEVEL_1 => 0.3, LEVEL_2 => 0.4, LEVEL_3 => 0.5, LEVEL_SENSEI => 0.6, LEVEL_J4 => 0.5, LEVEL_J3 => 0.5, LEVEL_J2 => 0.6, LEVEL_J1 => 0.6, LEVEL_N5 => 0.5, LEVEL_N4 => 0.5, LEVEL_N3 => 0.6, LEVEL_N2 => 0.6, LEVEL_N1 => 0.6];
    public static $levelNames = [LEVEL_N5 => 'JLPT N5', LEVEL_N4 => 'JLPT N4', LEVEL_N3 => 'JLPT N3', LEVEL_N2 => 'JLPT N2', LEVEL_N1 => 'JLPT N1', LEVEL_SENSEI => 'Sensei'];

    public function __construct($qClass, $level, $mode, $gradeOrSetID = -2)
    {
        if ($mode != DRILL_MODE && $mode != QUIZ_MODE && $mode != SETS_MODE && $mode != GRAMMAR_SETS_MODE) {
            log_error('Unknown mode: ' . $mode, false, true);
        }

        if (empty(Session::$levelNames[$level])) {
            log_error('Unknown level: ' . $level . "\nSession::level_names:\n" . print_r(Session::$levelNames, true),
                true, false);
            $level = LEVEL_N3;
        }
        $this->sessionMode = $mode;
        $this->sessionLevel = $level;
        $this->questionClass = ucfirst($qClass);
        require_once ABS_PATH . 'classes/' . $this->questionClass . '.php';

        if (!$this->questionLoader = new $this->questionClass($mode, $level, $gradeOrSetID)) {
            log_error('Can\'t instantiate session class: ' . $this->questionClass, true, true);
        }

        if ($this->isQuiz()) {
            $array = $this->questionLoader->getDefaultWSizes();
            $this->sessionWSizes = $array[$level];
            $array = $this->questionLoader->getDefaultWGrades();
            $this->sessionWGrades = $array[$level];
            $array = $this->questionLoader->getDefaultWPoints();
            $this->sessionWPoints = $array[$level];
            $this->curRank = $_SESSION['user']->getRank($this->getType(), true);

            $this->startTime = time();
        } else {
            $this->waveSize = $this->questionLoader->defaultSize;
        }

        $this->waveGrae = $this->questionLoader->getGrade();

        $this->curWave = 0;
        $this->pastQuestions = [];


        if (!$this->initWave()) {
            log_error('Can\'t init first wave.', false, true);
        }
    }

    public function cleanupBeforeDestroy()
    {
        if (!empty($_SESSION['user']) && $_SESSION['user']->isLoggedIn() && $this->questionLoader) {
            $this->questionLoader->learnSet($_SESSION['user']->getID(), $this->questions);
        }
    }

    public function __destruct()
    {
        
    }

    public function displayWave()
    {
        if (!$this->questions || $this->allAnswered()) {
            if ($this->isQuiz()) {
                if ($this->wasLastWave()) {
                    return $this->displayFinalScreen();
                } elseif (!$this->passedWave()) {
                    return $this->displayFailScreen();
                } else {
                    $this->initWave();
                }
            } else {
                $this->initWave();
            }
        }

        if ($this->isQuiz()) {
            $this->displayQuizHeader();
        }

        $skipped = $i = 0;
        $nextSID = 'end_of_wave_wait';

        foreach (array_reverse($this->questions) as $question) {
            if ($question->answered) {
                $skipped++;
            } else {
                $myQuestions[] = [$question, $nextSID];
                $nextSID = $question->getSID();
            }
        }
        if ($this->isQuiz()) {
            $totSize = 0;
            $extraPref = '<div class="prog-bar">';
            $extraSuf = '';
            foreach ($this->sessionWSizes as $w => $size) {
                $totSize += $size;
            }
            $scale = 760 / $totSize;

            foreach ($this->sessionWSizes as $w => $size) {
                if ($w < $this->getCurWave()) {
                    $extraPref .= '<div class="wave completed" style="width:' . (int) ($size * $scale) . 'px;" ></div>';
                } elseif ($w > $this->getCurWave()) {
                    $extraSuf .= '<div class="wave upcoming" style="width:' . (int) ($size * $scale) . 'px;" ></div>';
                }
            }

            $extraSuf .= '<div style="clear:both"></div></div>';

            foreach (array_reverse($myQuestions) as $array)
                $array[0]->displayQuestion(($i++ == 0), $array[1],
                    ($_SESSION['user']->getPreference('quiz', 'show_prog_bar') ? $extraPref . '<div class="wave ongoing" style="width: ' . ((int) ($skipped + $i - 1) * $scale) . 'px" ></div>' . '<div class="wave pending" style="width: ' . ((int) (count($this->questions) - $skipped - $i + 1) * $scale) . 'px"></div>' . $extraSuf : ''));
        } else {
            foreach (array_reverse($myQuestions) as $array) {
                $array[0]->displayQuestion(($i++ == 0), $array[1]);
            }
        }

        echo '<div id="end_of_wave_wait" ' . ($i > 0 ? 'style="display:none;"' : '') . '>
		<p>Loading next wave, please wait...</p>
		<img alt="load icon" src="' . SERVER_URL . 'img/ajax-loader.gif"/>
		</div>';

        if ($this->isQuiz()) {
            $this->displayQuizFooter();
        }

        echo '<div id="ajax-result"></div>';
        echo '<div id="ajax_edit_form" style="display:none;"></div>';
        echo '<div id="solutions"></div>';
        insert_js_snippet('sol_displayed = 0;');

        return true;
    }

    public function displayFinalScreen()
    {
        $this->gameOver = true;
        $this->totScore += $this->questionLoader->completionBonuses[$this->sessionLevel];
        ?>
        <div class="result_screen">
            <img class="mascots" src="<?php echo SERVER_URL;?>img/won.png" alt="lost" />
            <p>Congratulation, you succesfully finished <i><?php echo Session::$levelNames[$this->sessionLevel];?></i> level.</p>
            <?php
            $this->showFinalScore();
            ?>
        </div>
        <?php
        return false;
    }

    public function displayFailScreen()
    {
        $this->gameOver = true;
        ?>
        <div class="result_screen">
            <img class="mascots" src="<?php echo SERVER_URL?>img/lost.png" alt="lost" />
            <p>Sorry but you need to score at least <?php echo ($this->getPassingRate() * 100)?>% of each wave when playing <i><?php echo Session::$levelNames[$this->sessionLevel];?></i> level, you did only <?php echo floor(100 * $this->getSuccessRate()) . "% (" . $this->getCurWaveCorrect() . "/" . count($this->questions) . ")"?> on this wave.</p>
            <?php
            $this->showFinalScore();
            ?>
        </div>
        <?php
        return false;
    }

    public function showFinalScore()
    {
        $this->gameOver = true;

        echo '<p>Your final score is <strong>' . (int) $this->totScore . ' Pt' . ($this->totScore > 1 ? 's' : '') . '</strong></p>';
        if ($_SESSION['user']->isLoggedIn()) {
            $isNewHighscore = $this->saveScore();
            $newRank = $_SESSION['user']->getRank($this->getType(), false, 0);
            echo '<div class="rank"><img src="' . SERVER_URL . 'img/ranks/rank_' . $newRank->short_name . '.png" alt="' . $newRank->pretty_name . '" /></div>';

            if ($isNewHighscore) {
                echo '<p class="highscore">This is a  new personal highscore for you!</p>';
                $_SESSION['user']->cacheHighscores();
            }

            if ($newRank->rank > 0 && ($this->curRank->short_name != $newRank->short_name)) {
                if ($_SESSION['user']->getPreference('notif', 'post_news')) {
                    $_SESSION['user']->publishRankStory($this->getType(), $newRank);
                }

                if ($newRank->rank == 1) {
                    echo '<p class="highscore topscore">Congratulations, your are the new ' . $this->getNiceType() . ' <em>' . $newRank->pretty_name . '</em> of level ' . $this->getNiceLevel() . '! <small>(<a href="' . get_page_url(PAGE_SCORES,
                        ['type' => $this->getType()]) . '">See all highscores here</a>)</small></p>';
                } elseif ($newRank->rank <= 5) {
                    echo '<p class="highscore topscore">Congratulations, you just became a ' . $this->getNiceType() . ' <em>' . $newRank->pretty_name . '</em> and entered the current global highscore list at position #' . $newRank->rank . ' for level ' . $this->getNiceLevel() . '. <small>(<a href="' . get_page_url(PAGE_SCORES,
                        ['type' => $this->getType()]) . '">See all highscores here</a>)</small></p>';
                } else {
                    echo '<p class="highscore">You were just promoted to ' . ucfirst($this->getType()) . ' <em>' . $newRank->pretty_name . '</em> for level ' . $this->getNiceLevel() . '. <small>(<a href="' . get_page_url(PAGE_SCORES,
                        ['type' => $this->getType()]) . '">See current highscores here</a>)</small></p>';
                }
            } else {
                echo '<p>Your current global Kanji Box ranking is: <strong>' . $newRank->pretty_name . '</strong> <small>(<a href="' . get_page_url(PAGE_SCORES,
                    ['type' => $this->getType()]) . '">See current highscores here</a>)</small></p>';
            }
        } else {
            echo '<p>In order to save your score and rank (as well as access other features of Kanji Box), you need to <a href="' . get_page_url(PAGE_PLAY,
                ['save_first_game' => 1]) . '" requirelogin="1">log into this application</a>.</p>';
        }

        $this->displayPlayAgain();
    }

    public function initWave()
    {
        if ($this->isQuiz()) {
            if ($this->wasLastWave() || $this->gameOver) {
                return false;
            }

            $this->waveGrade = $this->sessionWGrades[$this->curWave];
            $this->waveSize = $this->sessionWSizes[$this->curWave];
            $this->wavePoints = $this->sessionWPoints[$this->curWave];
        }

        $this->lastWaveScore = 0;

        if ($this->questions) {
            foreach ($this->questions as $sid => $question) {
                if (!$question->isAnswered()) {
                    log_error('Wave was discarded before completion: ' . $sid . " not answered\nSession->pastQuestions: " . print_r($this->pastQuestions,
                            true), true, false);
                }
                $this->pastQuestions[$sid] = time();
            }
        }

        $this->questions = $this->questionLoader->loadQuestions($this->waveSize, $this->getCurGrade());

        return true;
    }

    public function loadNextWave()
    {
        if (!empty($_SESSION['user'])) {
            $this->questionLoader->learnSet($_SESSION['user']->getID(), $this->questions);
        }

        if (!$this->allAnswered()) {
            return false;
        }

        if (!$this->passedWave()) {
            return false;
        }
        if ($this->isQuiz()) {
            $this->saveScore();
        }
        $this->curWave++;
        return $this->initWave();
    }

    public function displayQuizHeader()
    {
        ?>
        <table id="quiz-header" class="twocols">
            <tr><td>
                    <div class="info">Level: <?php echo Session::$levelNames[$this->sessionLevel]?> - Wave: <?php echo ($this->curWave + 1);?> <a href="#" onclick="do_load('<?php echo SERVER_URL?>ajax/stop_quiz/', 'session_frame');
                            return false;">[stop]</a></div></td>
                <td style="text-align: right; padding: 0; margin: 0;">
                    <div id="countdown" name="countdown" ></div>
                    <?php
                    insert_js_snippet('init_countdown(' . $this->questionLoader->getQuizTime() . ');');
                    if (reset($this->questions)->isAsked()) {
                        insert_js_snippet('set_coutdown_to(0);');
                    }
                    ?>
                </td>
            </tr>
        </table>
        <?php
    }

    public function displayQuizFooter()
    {
        echo '<div id="quiz_footer"><div id="score">' . $this->getScoreStr() . '</div></div>';
    }

    public function getScoreStr()
    {
        return (int) $this->totScore . ' Pt' . ($this->totScore > 1 ? 's' : '') . ' - (' . $this->curCorrect . '/' . ($this->curTotal) . ')';
    }

    public function wasLastWave()
    {
        return ($this->curWave >= count($this->sessionWSizes) || $this->curWave >= count($this->sessionWGrades) || $this->curWave >= count($this->sessionWPoints));
    }

    public function passedWave()
    {
        return (!$this->isQuiz() || ($this->getSuccessRate() >= $this->getPassingRate()));
    }

    public function getPassingRate()
    {
        return Session::$levelPass[$this->sessionLevel];
    }

    public function displaySolution($sid, $answerID)
    {
        if (!isset($this->questions[$sid])) {
            if (!$this->pastQuestions[$sid]) {
                return '*unknown*';
            } else {
                return '*duplicate*';
            }
        } elseif ($this->questions[$sid]->isAnswered()) {
            return '*duplicate*';
        }

        if ($answerID == SKIP_ID) {
            $class = 'skipped';
        } elseif ($this->questions[$sid]->isSolution($answerID)) {
            $class = 'correct';
        } else {
            $class = 'wrong';
        }

        $divID = 'sol_' . $sid;
        echo '<div id="' . $divID . '" class="solution ' . $class . '">';

        $this->questions[$sid]->displayCorrection($answerID);

        $editButton = $this->questions[$sid]->editButtonLink();
        if ($this->questions[$sid]->hasFeedbackOptions() || $editButton) {
            echo '<div class="icon-buttons">';
            echo $editButton;
            if ($this->questions[$sid]->hasFeedbackOptions()) {
                echo '<a class="icon-button ui-state-default ui-corner-all" title="Report a problem with this question..." href="#" onclick="show_feedback_dialog(\'' . SERVER_URL . '\', \'' . $sid . '\'); return false;"><span class="ui-icon ui-icon-comment"></span></a>';
            }
            echo '</div>';
        }
        echo '</div>';

        return $class;
    }

    public function registerAnswer($sid, $answerID, $time = 0)
    {
        if (!$this->questions[$sid] || $this->questions[$sid]->isAnswered()) {
            return false;
        }

        $this->curTotal++;
        if ($this->questions[$sid]->registerAnswer($answerID, $time)) {
            $this->curCorrect++;
        }
        if ($this->isQuiz()) {
            $scoreDiff = ceil($this->questions[$sid]->scoreCoef() * $this->wavePoints);
            $this->lastWaveScore += $scoreDiff;
            $this->totScore = max(0, $this->totScore + $scoreDiff);
        }
    }

    public function getQuestion($sid)
    {
        return $this->questions[$sid];
    }

    public function allAsked()
    {
        foreach ($this->questions as $question) {
            if (!$question->asked) {
                return false;
            }
        }

        return true;
    }

    public function allAnswered()
    {
        foreach ($this->questions as $question) {
            if (!$question->answered) {
                return false;
            }
        }

        return true;
    }

    public function getSuccessRate()
    {
        $tot = $success = 0;
        foreach ($this->questions as $question) {
            if ($question->isAnswered()) {
                $tot++;
                if ($question->isCorrect()) {
                    $success++;
                }
            }
        }
        if ($tot != 0) {
            return (float) ($success / $tot);
        } else {
            return 1;
        }
    }

    public function getCurWaveCorrect()
    {
        $tot = 0;
        foreach ($this->questions as $question) {
            if ($question->isAnswered() && $question->isCorrect()) {
                $tot++;
            }
        }
        return $tot;
    }

    public function saveScore()
    {
        if (!$_SESSION['user']->isLoggedIn() || $this->totScore == 0) {
            return false;
        }

        $clause = '`score` = ' . ((int) $this->totScore);
        $clause .= ', `date_ended` = \'' . date("Y-m-d H:i:s") . '\'';
        $clause .= ", `type` = '" . $this->getType() . "'";

        if ($this->scoreID) {
            DB::update('UPDATE `games` SET ' . $clause . ' WHERE `id` = :scoreid', [':scoreid' => $this->scoreID]);
        } else {
            $this->scoreID = DB::insert('INSERT INTO `games` SET `user_id`=:userid, `level` = :level, `date_started` = :starttime, ' . $clause,
                    [':userid' => $_SESSION['user']->getID(), ':level' => $this->sessionLevel, ':starttime' => date('Y-m-d H:i:s',
                        $this->startTime)]);
        }

        if ($this->scoreID && $this->isHighscore()) {
            DB::update('UPDATE users SET ' . $this->getType() . '_highscore_id = :scoreid WHERE id = :id',
                [':scoreid' => $this->scoreID, ':id' => $_SESSION['user']->getID()]);
            return true;
        } else {
            return false;
        }
    }

    public function isHighscore()
    {
        if (!$_SESSION['user']->isLoggedIn() || $this->totScore == 0) {
            return false;
        }

        if (!$this->scoreID) {
            return $this->saveScore();
        }

        return $_SESSION['user']->isHighscore($this->scoreID, $this->sessionLevel, $this->questionLoader->quizType);
    }

    public function getGradeOptions()
    {
        return $this->questionLoader->getGradeOptions();
    }

    public function getParams()
    {
        return ['mode' => $this->getMode(), 'type' => $this->getType(), 'level' => $this->getLevel()];
    }

    public function stopQuiz()
    {
        ?>
        <div class="result_screen">
            <p>You stopped this game before completion.</p>
            <?php $this->showFinalScore();?>
        </div>
        <?php
        return false;
    }

    public function displayPlayAgain()
    {
        echo '<p class="play_again"><a href="' . get_page_url(PAGE_PLAY,
            ['type' => $this->getType(), 'level' => $this->getLevel(), 'mode' => $this->getMode()]) . '">Play Again</a></p>';
    }

    public function feedbackFormOptions($sid)
    {
        if (!$_SESSION['user']->isLoggedIn() || !isset($this->questions[$sid])) {
            return false;
        }

        return $this->questions[$sid]->feedbackFormOptions();
    }

    public function getSetCount()
    {
        if ($this->setSize <= 0) {
            $setID = $this->getSetID();
            if (!$setID) {
                return 0;
            }
            if ($this->getType() == 'kanji') {
                $type = 'kanji';
            } else {
                $type = 'vocab';
            }
            $this->setSize = DB::count('SELECT COUNT(*) FROM learning_set_' . $type . ' WHERE set_id = ?', [$setID]);
        }
        return $this->setSize;
    }

    public function isDrill()
    {
        return ($this->sessionMode == DRILL_MODE);
    }

    public function isQuiz()
    {
        return ($this->sessionMode == QUIZ_MODE);
    }

    public function isLearningSet()
    {
        return ($this->sessionMode == SETS_MODE);
    }

    public function isGrammarSet()
    {
        return ($this->sessionMode == GRAMMAR_SETS_MODE);
    }

    public function getType()
    {
        return $this->questionLoader->quizType;
    }

    public function getSetID()
    {
        return $this->questionLoader->id;
    }

    public function getNiceType()
    {
        return ucfirst($this->questionLoader->quizType);
    }

    public function getLevel()
    {
        return $this->sessionLevel;
    }

    public function getNiceLevel()
    {
        return Session::$levelNames[$this->sessionLevel];
    }

    public function getMode()
    {
        return $this->sessionMode;
    }

    public function getCurWave()
    {
        return $this->curWave;
    }

    public function getCurGrade()
    {
        return $this->waveGrade;
    }

    public function setCurWave($curWave)
    {
        $this->curWave = $curWave;
    }
}
