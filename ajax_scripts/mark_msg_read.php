<?php

if (empty($_SESSION['user'])) {
    log_error('You need to be logged-in to access this function.', false, true);
}

$query = 'UPDATE `messages` SET msg_read = 1 WHERE user_id_to = ? AND message_id = ?';
try {
    $stmt = DB::getConnection()->prepare($query);
    $stmt->execute([$_SESSION['user']->getID(), $params['id']]);
    $stmt = null;

    echo 'Marked as read!';
} catch (PDOException $e) {
    log_db_error($query, $e->getMessage(), false, true);
}