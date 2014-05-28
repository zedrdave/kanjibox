<div style="border: 1px solid black; background-color:yellow;padding:3px;margin-bottom:4px;">
    <?php
    if (!$_SESSION['user']) {
        die('logged-in only');
    }

    if (!empty($params['id'])) {
        $kanjiID = $params['id'];

        if ($_SESSION['user']->isEditor()) {

            if (isset($_REQUEST['njlpt'])) {
                echo post_db_correction('kanjis', 'id', $kanjiID, 'njlpt', (int) $_REQUEST['njlpt'], true);
            }

            if (isset($_REQUEST['grade'])) {
                echo post_db_correction('kanjis', 'id', $kanjiID, 'grade', (int) $_REQUEST['grade'], true);
            }
        }
    } else {
        echo 'Unknown kanji id';
    }
    ?>
</div>