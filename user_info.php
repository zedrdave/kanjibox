<?php

require_once 'libs/lib.php';
require_once get_mode() . '.config.php';

if (!init_app() || empty($_SESSION['user'])) {
    die();
}

echo 'UniqueID=' . $_SESSION['user']->getID() . '<br/>Name=' . ($_SESSION['user']->get_first_name() ? $_SESSION['user']->get_first_name() : ($_SESSION['user']->get_email() ? strstr($_SESSION['user']->get_email(),
            '@', true) : 'user_' . $_SESSION['user']->getID())) . '<br/>Email=' . $_SESSION['user']->get_email();
