<div>
    <?php
    if (empty($_SESSION['user']) || !$_SESSION['user']->isEditor()) {
        die('editors only');
    }

    if (!empty($params['action']) && $params['action'] == 'save' && !empty($_REQUEST['board_content'])) {
        $f = fopen(dirname(__FILE__) . '/../tools/notes.txt', 'w');
        fwrite($f, $_REQUEST['board_content']);
        fclose($f);
        echo 'Saved board edit...';
    } else {
        echo 'No action taken...';
    }
    ?>
</div>
