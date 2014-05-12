<div><?php
    if (!isset($_SESSION['user']) && !$_SESSION['user']) {
        die('need to be logged in');
    }

    if (!empty($_REQUEST['set_login'])) {
        echo ($_SESSION['user']->updateLogin($_REQUEST['set_login']));
    }
    if (!empty($_REQUEST['set_password'])) {
        echo ($_SESSION['user']->updatePassword($_REQUEST['set_password']));
    }

    if (!empty($_REQUEST['set_first_name']) || !empty($_REQUEST['set_last_name'])) {
        echo ($_SESSION['user']->updateName($_REQUEST['set_first_name'], $_REQUEST['set_last_name']));
    }
    ?>
</div>