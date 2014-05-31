<?php

abstract class Question
{

    public $defaultSize = 10;
    public $asked = false;
    public $answered = false;
    public $correct = false;
    public $learnt = false;
    private $answeredID = -1;
    protected $mode;
    protected $level;
    protected $data;
    protected $grade;
    protected $learnable = true;
    public $completionBonuses = [LEVEL_1 => 20, LEVEL_2 => 50, LEVEL_3 => 70, LEVEL_SENSEI => 100, LEVEL_N5 => 20, LEVEL_N4 => 30, LEVEL_N3 => 35, LEVEL_N2 => 50, LEVEL_N1 => 70];

    public function __construct($mode, $level, $gradeOrSetID = -2, $data = null)
    {
        $this->mode = $mode;
        $this->level = $level;

        if ($this->mode == SETS_MODE) {
            $this->id = $gradeOrSetID;
            $this->set = new LearningSet($this->id);
            $this->grade = $this->levelToGrade($this->level);
        } elseif ($this->mode == GRAMMAR_SETS_MODE) {
            $this->id = $gradeOrSetID;
            $this->grade = $this->levelToGrade($this->level);
        } else {
            if ($gradeOrSetID >= -1) {
                $this->grade = $gradeOrSetID;
            } else {
                $this->grade = $this->levelToGrade($this->level);
            }
        }

        $this->data = $data;
        $this->created = time();
    }

    public function getSID()
    {
        return $this->data['sid'];
    }

    public function isDrill()
    {
        return ($this->mode == DRILL_MODE);
    }

    public function isQuiz()
    {
        return ($this->mode == QUIZ_MODE);
    }

    public function isLearningSet()
    {
        return ($this->mode == SETS_MODE);
    }

    public function isGrammarSet()
    {
        return ($this->mode == GRAMMAR_SETS_MODE);
    }

    abstract public function displayChoices($nextSID = 0);

    abstract public function displayHint();

    public function displayQuestion($first = false, $nextSID = '', $insertHTML = '')
    {
        $this->asked = true;
        echo '<div class="question question_' . strtolower(get_class($this)) . '" id="' . $this->getSID() . '" ' . ($first ? '' : 'style="display:none;"') . ' >';
        echo $insertHTML;
        echo '<div class="choices">';
        $this->displayChoices($nextSID);
        echo '<div style="clear:both;"></div></div>';
        echo '<div class="hint">';
        if ($this->isQuiz() && $this->useAnticheatOnHint()) {
            echo '<div class="cheat-trick" style="display:block;">瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 瞞着 </div>';
        }
        $this->displayHint();
        echo '</div>';

        $editButton = $this->editButtonLink();
        if ($this->hasFeedbackOptions() || $editButton) {
            echo '<div class="icon-buttons">';
            echo $editButton;
            if ($this->hasFeedbackOptions()) {
                echo '<a class="icon-button ui-state-default ui-corner-all" title="Report a problem with this question..." href="#" onclick="show_feedback_dialog(\'' . SERVER_URL . '\', \'' . $this->getSID() . '\'); return false;"><span class="ui-icon ui-icon-comment"></span></a>';
            }
            echo '</div>';
        }
        echo '</div>';
    }

    public function useAnticheatOnHint()
    {
        return true;
    }

    public function getSolution()
    {
        return $this->data['solution'];
    }

    public function registerAnswer($answerID, $time = 0)
    {
        $this->answered = true;
        $this->answeredID = $answerID;
        $this->correct = $this->isSolution($answerID);
        $this->time = $time;
        return $this->correct;
    }

    public function scoreCoef()
    {
        if (!$this->answered) {
            return 0;
        }

        if ($this->answeredID == SKIP_ID) {
            return 0;
        }

        if ($this->correct) {
            return min(1, max(0.1, 1.15 * $this->time / $this->get_quiz_time()));
        } else {
            return - (min(0.5, max(0.1, 0.62 * $this->time / $this->get_quiz_time())));
        }
    }

    public function isSolution($sid)
    {
        $sol = $this->getSolution();
        return ($sid && ($sid == $sol->id));
    }

    public function getSolutionID()
    {
        $sol = $this->getSolution();
        return $sol->id;
    }

    public function displayCorrection($answerID)
    {
        echo "### DEBUG: should override this class<br/>";

        if ($answerID == SKIP_ID) {
            $class = 'skipped';
        } elseif ($this->isSolution($answerID)) {
            $class = 'correct';
        } else {
            $class = 'wrong';
        }

        echo $class;
    }

    abstract public function getDBData($howmany, $grade, $userID = -1);

    public function loadQuestions($howMany, $grade)
    {
        if ($this->isDrill() || $this->isLearningSet() || $this->isGrammarSet()) {
            if ($_SESSION['user']->getID()) {
                $items = $this->getDBData($howMany, $grade, $_SESSION['user']->getID());
            } else {
                force_reload('You need to be logged in order to use drill mode.');
            }
        } else {
            $items = $this->getDBData($howMany, $grade);
        }

        if ($items && count($items)) {
            $className = get_class($this);
            $questions = [];
            foreach ($items as $item) {
                $questions[$item['sid']] = new $className($this->mode, $this->level,
                    (($this->isLearningSet() || $this->isGrammarSet()) ? $this->id : $grade), $item);
            }
            return $questions;
        } else {
            return null;
        }
    }

    public function getNextGrade($grade)
    {
        if ($grade[0] == 'N') {
            if ($grade == 'N1') {
                return -1;
            } else {
                return 'N' . ($grade[1] - 1);
            }
        } else {
            if ($grade >= 9) {
                return -1;
            } else {
                return $grade + 1;
            }
        }
    }

    public function learnSet($userID, $learningSet, $learnOthers = true)
    {
        if (!$this->isLearnable()) {
            return true;
        }

        if (!count($learningSet)) {
            return false;
        }
        $initValues = $goodIDs = $badIDs = [];
        foreach ($learningSet as $question) {
            if (!$question->isAnswered() || $question->isLearnt()) {
                continue;
            }

            $initValues[] = '(' . $userID . ', ' . $question->getSolutionID() . ', ' . 'NOW())';

            if ($question->isCorrect()) {
                $goodIDs[] = $question->getSolutionID();
            } else {
                $badIDs[] = $question->getSolutionID();
                if ($learnOthers && $question->getAnsweredID() != SKIP_ID) {
                    $initValues[] = '(' . $userID . ', ' . $question->getAnsweredID() . ', ' . 'NOW())';
                    $badIDs[] = $question->getAnsweredID();
                }
            }

            $question->learnt = true;
        }

        if (!count($initValues)) {
            return false;
        }

        $dbh = DB::getConnection();
        try {
            $dbh->beginTransaction();
            DB::insert('INSERT IGNORE INTO ' . $this->table_learning . ' (user_id, ' . $this->table_learning_index . ', date_first) VALUES ' . implode(',',
                    $initValues));

            if (count($badIDs)) {
                DB::update('UPDATE ' . $this->table_learning . ' SET total = total+1, curve = LEAST(2000, tan(atan(curve/1000-1)+0.15)*1000+1000) where `user_id` = :userid AND ' . $this->table_learning_index . ' IN (' . implode(',',
                        $badIDs) . ')', [':userid' => $userID]);
            }

            if (count($goodIDs)) {
                DB::update('UPDATE ' . $this->table_learning . ' SET total = total+1, curve = GREATEST(100, tan(atan(curve/1000-1)-0.2)*1000+1000) where `user_id` = :userid AND ' . $this->table_learning_index . ' IN (' . implode(',',
                        $goodIDs) . ')', [':userid' => $userID]);
            }

            $dbh->commit();
        } catch (PDOException $e) {
            $dbh->rollBack();
            log_db_error(null, $e->getMessage(), false, true);
        }

        return true;
    }

    public function getGradeOptions()
    {
        return null;
    }

    public static function levelToGrade($level)
    {
        switch ($level) {
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
                $grade = $level;
                break;
        }

        return $grade;
    }

    public static function levelToNum($level)
    {
        switch ($level) {
            case LEVEL_SENSEI:
            // 	$num = 0;
            // break;
            case LEVEL_N5:
            case LEVEL_N4:
            case LEVEL_N3:
            case LEVEL_N2:
            case LEVEL_N1:
                $num = $level;
                break;
            default:
                $num = -1;
                break;
        }

        return $num;
    }

    public function getLevelNum()
    {
        return $this->levelToNum($this->level);
    }

    public function getDefaultWSizes()
    {
        return [
            LEVEL_SENSEI => [4, 6, 6, 6, 10, 15, 15, 5, 10, 5],
            LEVEL_N5 => [5, 10, 10, 15, 10, 10, 5],
            LEVEL_N4 => [5, 5, 15, 10, 15, 10, 10, 10, 5],
            LEVEL_N3 => [5, 5, 5, 15, 10, 15, 5, 10, 5],
            LEVEL_N2 => [5, 5, 5, 15, 10, 15, 5, 10, 5],
            LEVEL_N1 => [4, 4, 15, 15, 10, 10, 15, 10, 5]
        ];
    }

    public function getDefaultWGrades()
    {
        return [
            LEVEL_SENSEI => [3, 4, 5, 6, 6, 8, 9, -1, -1, -1],
            LEVEL_N5 => ['N5', 'N5', 1, 'N5', 1, 2, 'N4'],
            LEVEL_N4 => ['N5', 'N4', 'N4', 2, 'N4', 3, 'N4', 4, 'N3'],
            LEVEL_N3 => ['N5', 'N4', 'N3', 'N3', 4, 'N3', 'N3', 5, 'N2'],
            LEVEL_N2 => ['N5', 'N4', 'N3', 'N2', 5, 'N2', 'N2', 6, 'N1'],
            LEVEL_N1 => ['N4', 'N3', 'N2', 'N1', 9, 'N1', 'N1', 9, -1]
        ];
    }

    public function getDefaultWPoints()
    {
        return [
            LEVEL_SENSEI => [1, 2, 2, 8, 15, 30, 50, 65, 80, 75],
            LEVEL_N5 => [1, 4, 6, 12, 20, 34, 16],
            LEVEL_N4 => [1, 4, 8, 12, 16, 12, 35, 50, 45],
            LEVEL_N3 => [1, 4, 8, 16, 22, 18, 35, 65, 45],
            LEVEL_N2 => [1, 4, 8, 16, 22, 18, 35, 65, 45],
            LEVEL_N1 => [1, 4, 8, 16, 24, 18, 36, 70, 55]
        ];
    }

    public function feedbackFormOptions()
    {
        return 'No feedback options for this quiz';
    }

    public function hasFeedbackOptions()
    {
        return false;
    }

    public function editButtonLink()
    {
        return '';
    }

    public static function getQuizTime()
    {
        return 31;
    }

    public function isLearnable()
    {
        return $this->learnable;
    }

    public function isCorrect()
    {
        return $this->correct;
    }

    public function isAnswered()
    {
        return $this->answered;
    }

    public function isAsked()
    {
        return $this->asked;
    }

    public function isLearnt()
    {
        return $this->learnt;
    }

    public function getAnsweredID()
    {
        return $this->answeredID;
    }

    public function getLevel()
    {
        return $this->level;
    }

    public function getGrade()
    {
        return $this->grade;
    }
}
