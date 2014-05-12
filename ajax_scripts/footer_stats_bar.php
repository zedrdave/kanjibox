<?php

if (!$_SESSION['user']) {
    log_error('Empty SESSION[user] in footer_stats_bar.php', true);
    log_error('You need to be logged to access this function.', false, true);
}

if (!$_SESSION['user']->get_pref('drill', 'show_learning_stats')) {
    die(' - ');
}

if ($_SESSION['cur_session']) {
    $grade = $_SESSION['cur_session']->getCurGrade();
} else {
    $grade = Question::levelToGrade($_SESSION['user']->getLevel());
}

require_once ABS_PATH . 'libs/stats_lib.php';

if ($params['mode'] == SETS_MODE) {
    $count = $_SESSION['cur_session']->getSetCount();
    if ($count > 1200) {
        return;
    }
}

switch ($params['type']) {
    case 'kanji':
        if ($params['mode'] == SETS_MODE) {
            echo print_kanji_set_stats($_SESSION['user']->getID(), $_SESSION['cur_session']->getSetID(), 720, ' ');
        } elseif ($grade[0] == 'N') {
            echo printJLPTLevels($_SESSION['user']->getID(), (int) $grade[1], 600, ' ');
        } elseif ($grade >= 1 && $grade <= 9) {
            echo printGradeLevels($_SESSION['user']->getID(), (int) $grade, 600, ' ');
        }
        break;

    case 'vocab':
    case 'text':
        if ($params['mode'] == SETS_MODE) {
            echo print_vocab_set_stats($_SESSION['user']->getID(), $_SESSION['cur_session']->getSetID(), 720, ' ');
        } elseif ($grade[0] == 'N') {
            echo print_vocab_jlpt_levels($_SESSION['user']->getID(), (int) $grade[1], 600, ' ');
        }
        break;

    case 'reading':
        if ($params['mode'] == SETS_MODE) {
            echo print_reading_set_stats($_SESSION['user']->getID(), $_SESSION['cur_session']->getSetID(), 720, ' ');
        } elseif ($grade[0] == 'N') {
            echo printReadingJLPTLevels($_SESSION['user']->getID(), (int) $grade[1], 600, ' ');
        }
        break;

    case 'kana':
        echo printKanaLevels($_SESSION['user']->getID(), 720, ' ');
        break;


    default:
        log_error('footer_stats_bar: unknown stats type: ' . $params['type']);
        echo 'unknown stat type';
        break;
}
