<?php

if (empty($_SESSION['user'])) {
    log_error('You need to be logged to access this function.', false, true);
}

$query = 'INSERT INTO user_feedbacks SET user_id = :userid, param_1 = :param1, param_2 = :param2, param_3 = :param3, comment = :comment, type = :type';
$fID = DB::insert(
    $query,
    [
    ':userid' => $_SESSION['user']->getID(),
    ':param1' => $_REQUEST['param_1'],
    ':param2' => $_REQUEST['param_2'],
    ':param3' => $_REQUEST['param_3'],
    ':comment' => $_REQUEST['comment'],
    ':type' => $_REQUEST['type']
    ]
);

if (!empty($fID)) {
    echo 'Feedback recorded... Thanks!';
} else {
    echo 'Database Error: could not record feedback.';
}
