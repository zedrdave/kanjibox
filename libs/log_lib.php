<?php

function log_public_error($msg, $fatal = false) {
    echo ('<div class="error_msg">' . $msg . '</div>');
    if ($fatal) {
        exit(-1);
    }
}

function log_error($msg, $details = false, $fatal = false) {
    global $no_file_log, $fb_id;
    if (!empty($_SESSION['user'])) {
        $user_id = $_SESSION['user']->getID();
    } elseif ($fb_id) {
        $user_id = $fb_id;
    } else {
        $user_id = 'unknown user';
    }
    $log_msg = "**************************************\n";
    $log_msg .= date('m/d/Y H:i:s') . " (" . time() . ") - " . $_SERVER['PHP_SELF'] . ' - ' . ($user_id) . "\n";
    $log_msg .= $msg . "\n";
    if ($details) {
        $log_msg .= "---\nREQUEST:\n" . print_r($_REQUEST, true) . "\n---\nSESSION:\n" . (isset($_SESSION) ? print_r($_SESSION, true) : null) . "\n";
    }
    $log_msg .= "\n";

    if ($no_file_log) {
        echo $log_msg;
    } else {
        if (!isset($_REQUEST['debug']) || defined('debug')) {
            echo ('<div class="error">' . nl2br(htmlentities($msg, ENT_COMPAT, 'UTF-8')) . '</div>');
        } elseif (defined('CFG_EMAIL_ERROR') && CFG_EMAIL_ERROR != '') {
            mail(CFG_EMAIL_ERROR, 'KanjiBox error', $log_msg);
        }
        $fhandle = fopen(KB_LOG_PREFIX . 'errors_' . date('Ymd') . '.txt', 'a');
        if ($fhandle) {
            fwrite($fhandle, $log_msg);
            fclose($fhandle);
        } else {
            echo '### Cannot open log file.';
        }

        //	error_log($log_msg, 0);
    }
    if ($fatal) {
        echo ('<div class="error">A fatal error has occured. Please try again later or contact us if the problem persists. (' . date('m/d/Y H:i:s') . ')</div>');
        exit(-1);
    }
}

function log_db_error($query, $extra = '', $details = false, $fatal = false) {
    $msg = '';
    if ($extra) {
        $msg .= $extra . "\n";
    }
    $msg .= 'SQL Error #' . mysql_errno() . "\n" . mysql_error() . "\n";
    $msg .= $query . "\n";

    log_error($msg, $details, $fatal);
}

function log_exception($e, $extra = '', $details = false, $fatal = false) {
    $msg = '';
    if ($extra) {
        $msg .= $extra . "\n";
    }
    $msg .= 'Caught Exception: ' . $e->getMessage() . " (#" . $e->getCode() . ")\n";
    log_error($msg, $details, $fatal);
}

function log_pwd_reset($msg, $error = true, $fatal = true, $private_msg = '', $details = true) {
    global $fb_id;
    if (!empty($_SESSION['user'])) {
        $user_id = $_SESSION['user']->getID();
    } elseif ($fb_id) {
        $user_id = $fb_id;
    } else {
        $user_id = 'unknown user';
    }

    $log_msg = "**************************************\n";
    $log_msg .= date('m/d/Y H:i:s') . " (" . time() . ") - " . $_SERVER['PHP_SELF'] . ' - ' . $_SERVER['REMOTE_ADDR'] . ' - ' . ($user_id) . "\n";
    $log_msg .= $msg . "\n" . $private_msg . "\n";

    if ($details) {
        $log_msg .= "---\nREQUEST:\n" . print_r($_REQUEST, true) . "\n---\nSESSION:\n" . print_r(!empty($_SESSION) ? $_SESSION : array(), true) . "\n";
    }
    $log_msg .= "\n";


    $fhandle = fopen(KB_LOG_PREFIX . 'login_errors_' . date('Ymd') . '.txt', 'a');
    if ($fhandle) {
        fwrite($fhandle, $log_msg);
        fclose($fhandle);
    } else {
        echo '### Cannot open log file.';
    }

    if ($error) {
        echo ('<div class="error_msg">' . $msg . '</div>');
    }

    if ($fatal) {
        exit(-1);
    }
}

function stopwatch($msg = 'time', $timeout = 3) {
    global $first_time, $last_time;
    if ($last_time) {
        log_error("Stopwatch: " . $msg . ' - ' . microtime(true) . ' - (' . (microtime(true) - $last_time) . ")", false, false);
    }
    $last_time = microtime(true);

    if ($first_time) {
        if (microtime(true) - $first_time > $timeout) {
            log_error('Total time: ' . (microtime(true) - $first_time) . ' exceeding ' . $timeout . ' s. - Aborting', true, true);
        }
    } else {
        $first_time = microtime(true);
    }
}

function reached_timeout($timeout) {
    global $first_time;
    if (!$first_time) {
        $first_time = microtime(true);
    }

    return ($first_time + $timeout < microtime(true));
}
