<?php

if (empty($_SESSION['user'])) {
    log_error('You need to be logged-in to access this function.', false, true);
}

$update = DB::update('UPDATE `messages` SET msg_read = 1 WHERE :user_id_to = :userid_to AND message_id = :message_id',
        [
        'user_id_to' => $_SESSION['user']->getID(),
        'messageid' => $params['id']
        ]
);
if (!empty($update)) {
    echo 'Marked as read!';
}