<?php

require_once 'Question.php';

class Kana extends Question
{

    public $tableLearning = 'kana_learning';
    public $tableLearningIndex = 'kana_id';
    public $quizType = 'kana';

    public function __construct($mode, $level, $grade = -2, $data = null)
    {
        parent::__construct($mode, $level, $grade, $data);
    }

    public function displayChoices($nextSID = '')
    {
        $submitURL = SERVER_URL . 'ajax/submit_answer/?sid=' . $this->data['sid'] . '&amp;time_created=' . (int) $this->created . '&amp;';
        $choices = $this->data['choices'];
        shuffle($choices);
        foreach ($choices as $choice) {
            echo '<div class="choice kanji" onclick="submit_answer(\'' . $this->data['sid'] . '\', \'' . $nextSID . '\', \'' . $submitURL . 'answer_id=' . (int) $choice->id . '\'); return false;">' . $choice->kana . '</div>';
        }

        echo '<div class="choice skip" onclick="submit_answer(\'' . $this->data['sid'] . '\',  \'' . $nextSID . '\', \'' . $submitURL . 'answer_id=' . SKIP_ID . '\'); return false;">&nbsp;?&nbsp;</div>';
    }

    public function displayHint()
    {
        $solution = $this->getSolution();
        echo $solution->roma . ' (' . ($solution->type == 'kata' ? 'katakana' : 'hiragana') . ')';
    }

    public function displayCorrection($answerID)
    {
        $solution = $this->getSolution();

        echo "<span class=\"kanji\">" . $solution->kana . "</span> [" . ($solution->type == 'kata' ? 'katakana' : 'hiragana') . "] - " . $solution->roma;

        if ($answerID != SKIP_ID && !$this->isSolution($answerID)) {
            echo "<br/><br/>";
            $wrong = $this->getKanaID((int) $answerID);
            if (!$wrong) {
                log_error('Unknown Kana ID: ' . $answerID, false, true);
            }
            echo "<span class=\"kanji\">" . $wrong->kana . "</span> [" . ($wrong->type == 'kata' ? 'katakana' : 'hiragana') . "] - " . $wrong->roma;
        }
    }

    public function getDBData($howMany, $grade, $userID = -1)
    {
        if ($this->isQuiz()) {
            log_error('Quiz mode not supported for kana.', false, true);
        } elseif (!$_SESSION['user']) {
            $picks = $this->getRandomKanas($howMany * 4);
        } else {
            $picks = $this->getRandomWeightedKanas($userID, $howMany * 4);
        }

        for ($i = 0; $i < count($picks) - 3; $i+=4) {
            $choice = [];
            $choice[0] = $picks[$i];
            $exclude = [$choice[0]->roma];
            for ($j = 1; $picks[$i]->type == $choice[0]->type && $j < 4; $j++) {
                $choice[$j] = $picks[$i + $j];
                $exclude[] = $choice[$j]->roma;
            }
            if ($j < 4) {
                if ($_SESSION['user']) {
                    $filler = $this->getRandomKanas((4 - $j), $choice[0]->type, $exclude);
                } else {
                    $filler = $this->getRandomWeightedKanas($userID, (4 - $j), $choice[0]->type, $exclude);
                }
            }

            while ($j++ < 4) {
                $choice[$j] = $filler[3 - $j];
            }

            $sid = 'sid_' . md5('himitsu' . time() . '-' . rand(1, 100000));
            $data[$sid] = ['sid' => $sid, 'choices' => $choice, 'solution' => $choice[0]];

            //###DEBUG
            if ($choice[0]->id == $choice[1]->id || $choice[0]->id == $choice[2]->id || $choice[0]->id == $choice[3]->id || $choice[1]->id == $choice[2]->id || $choice[1]->id == $choice[3]->id || $choice[2]->id == $choice[3]->id) {
                log_error('IDENTICAL KANA: ' . print_r($choice, true) . "\n\n" . print_r($picks, true), true, true);
            }
        }

        return $data;
    }

    public function getKanaID($kanaID)
    {
        $query = 'SELECT `id`, `kana`, UPPER(`roma`) AS `roma`, `type` FROM `kanas` WHERE `id` = ?';

        try {
            $stmt = DB::getConnection()->prepare($query);
            $stmt->execute([$kanaID]);
            return $stmt->fetch(PDO::FETCH_CLASS);
        } catch (PDOException $e) {
            log_db_error($query, $e->getMessage());
        }
    }

    public function getRandomWeightedKanas($userID, $howMany = 1, $type = null, $exclude = null)
    {
        $inputValues = [$userID];
        $whereType = '1';
        if (isset($type)) {
            $whereType = 'k.type = ?';
            $inputValues[] = $type;
        }

        $whereExclude = ($exclude ? 'k.roma NOT IN (' . implode(', ', $exclude) . ')' : '1');
        $query = 'SELECT * FROM (SELECT  k.`id`, k.`kana`,  UPPER(k.`roma`) AS `roma`, k.`type`, IF(l.curve IS NULL, 1000, curve)+1000*rand() as xcurve from kanas k left join ' . $this->tableLearning . ' l on l.user_id = ? AND k.id = l.' . $this->tableLearningIndex . " WHERE $whereType AND $whereExclude ORDER BY xcurve DESC LIMIT " . $howMany . ') as temp ORDER BY type';

        try {
            $stmt = DB::getConnection()->prepare($query);
            $stmt->execute($inputValues);

            if ($stmt->rowCount() < $howMany) {
                log_error('Can\'t get enough randomized kanas: ' . $query, false, true);
            }

            if ($howMany == 1) {
                return $stmt->fetchObject();
            }

            return $stmt->fetchAll(PDO::FETCH_CLASS);
        } catch (PDOException $e) {
            log_db_error($query, $e->getMessage(), false, true);
        }
    }

    public function getRandomKanas($howMany = 1, $type = null, $exclude = null)
    {
        $whereType = ($type ? 'k.type = :type' : '1');
        $whereExclude = ($exclude ? 'k.roma NOT IN (' . implode(', ', $exclude) . ')' : '1');
        $query = 'SELECT * FROM (SELECT  k.`id`, k.`kana`,  UPPER(k.`roma`) AS `roma`, k.`type` from kanas k WHERE ' . $whereType . ' AND ' . $whereExclude . ' ORDER BY RAND() LIMIT :showmany) as temp ORDER BY type';

        try {
            $stmt = DB::getConnection()->prepare($query);
            if (!empty($type)) {
                $stmt->bindValue(':type', $type, PDO::PARAM_STR);
            }
            $stmt->bindValue(':showmany', $howMany, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() < $howMany) {
                log_error('Can\'t get enough randomized kanas: ' . $query, false, true);
            }

            if ($howMany == 1) {
                return $stmt->fetchObject();
            }

            return $stmt->fetchAll(PDO::FETCH_CLASS);
        } catch (PDOException $e) {
            log_db_error($query, $e->getMessage(), false, true);
        }
    }
}
