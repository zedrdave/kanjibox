<?php

require_once 'libs/lib.php';
require_once get_mode() . '.config.php';

if (!init_app() || empty($_SESSION['user'])) {
    die();
}

echo 'UniqueID=' . $_SESSION['user']->getID() . '<br/>Name=' . ($_SESSION['user']->getFirstName() ? $_SESSION['user']->getFirstName() : ($_SESSION['user']->getEmail() ? strstr($_SESSION['user']->getEmail(),
            '@', true) : 'user_' . $_SESSION['user']->getID())) . '<br/>Email=' . $_SESSION['user']->getEmail();
