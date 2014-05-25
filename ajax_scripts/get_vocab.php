<?php

if (empty($_SESSION['user'])) {
    log_error('You need to be logged to access this function.', false, true);
}

if (!empty($params['jmdict_id']) && empty($_SESSION['cur_session'])) {
    log_error('You need to be using Drill or Quiz mode to access this feature.', false, true);
}

$jmdictID = 1000160;
if (!empty($params['jmdict_id'])) {
    $jmdictID = (int) $params['jmdict_id'];
}

$query = 'SELECT j.word, j.reading, j.usually_kana, j.katakana, j.njlpt, j.njlpt_r, jx.gloss_english AS fullgloss FROM jmdict j LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id WHERE j.id = :jmdictID';
try {
    $stmt = DB::getConnection()->prepare($query);
    $stmt->bindValue(':jmdictID', $jmdictID, PDO::PARAM_INT);
    $stmt->execute();

    $row = $stmt->fetchObject();
    if (empty($row)) {
        echo 'Word not found: ' . $jmdictID;
    } else {
        $userID = (int) $_SESSION['user']->getID();
        if (!empty($_REQUEST['learn_reading'])) {
            try {
                DB::getConnection()->beginTransaction();
                DB::insert('INSERT IGNORE INTO reading_learning (user_id, jmdict_id, date_first) VALUES (' . $userID . ', ' . $jmdictID . ', NOW())');
                DB::update('UPDATE reading_learning SET total = total+1, curve = LEAST(2000, tan(atan(curve/1000-1)+0.15)*1000+1000) where `user_id` = ' . $userID . ' AND jmdict_id = ' . $jmdictID);
                DB::getConnection()->commit();
            } catch (PDOException $ex) {
                DB::getConnection()->rollBack();
                log_error($e->getMessage(), false, true);
            }
        }
        if (!empty($_REQUEST['learn_vocab'])) {
            try {
                DB::getConnection()->beginTransaction();
                DB::insert('INSERT IGNORE INTO jmdict_learning (user_id, jmdict_id, date_first) VALUES (' . $userID . ', ' . $jmdictID . ', NOW())');
                DB::update('UPDATE jmdict_learning SET total = total+1, curve = LEAST(2000, tan(atan(curve/1000-1)+0.15)*1000+1000) where `user_id` = ' . $userID . ' AND jmdict_id = ' . $jmdictID);
                DB::getConnection()->commit();
            } catch (PDOException $ex) {
                DB::getConnection()->rollBack();
                log_error($e->getMessage(), false, true);
            }
        }
        echo (!empty($_REQUEST['word']) ? $_REQUEST['word'] : ($row->usually_kana ? $row->reading : $row->word)) . '<span class="definition">' . ($row->usually_kana ? $row->reading : $row->word . ($row->katakana || $row->word == $row->reading ? '' : ' 「' . $row->reading . '」')) . '<br/>' . $row->fullgloss . '<br/><span class="level">JLPT: N' . $row->njlpt . ' (Reading: N' . $row->njlpt_r . ')</span></span>';
    }

    $stmt = null;
} catch (PDOException $e) {
    log_db_error($query, $e->getMessage(), false, true);
}
