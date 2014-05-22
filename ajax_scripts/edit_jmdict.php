<div>
    <?php
    if (!$_SESSION['user']) {
        die('need to be logged in');
    }

    if ($_REQUEST['delete_id']) {
        if (!$_SESSION['user']->isAdministrator()) {
            die("admins only");
        }

        $deleteID = (int) $_REQUEST['delete_id'];
        DB::delete('DELETE FROM jmdict_ext_deleted WHERE jmdict_id = :deleteID', [':deleteID' => $deleteID]);
        echo '<br/>';
        DB::delete('DELETE FROM jmdict_deleted WHERE id = :deleteID', [':deleteID' => $deleteID]);
        echo '<br/>';
    }
    if ($_REQUEST['reimport_id']) {
        if (!$_SESSION['user']->isEditor()) {
            die('editors only');
        }

        $reimportID = (int) $_REQUEST['reimport_id'];
        DB::insert('INSERT INTO jmdict_ext (SELECT * FROM jmdict_ext_deleted WHERE jmdict_id = :reimportID)',
            [':reimportID' => $reimportID]);
        echo '<br/>';
        DB::insert('INSERT INTO jmdict (SELECT * FROM jmdict_deleted WHERE id = :reimportID)',
            [':reimportID' => $reimportID]);
        echo '<br/>';
        DB::delete('DELETE FROM jmdict_ext_deleted WHERE jmdict_id = :reimportID', [':reimportID' => $reimportID]);
        echo '<br/>';
        DB::delete('DELETE FROM jmdict_deleted WHERE id = :reimportID', [':reimportID' => $reimportID]);
        echo '<br/>';
    }
    if ($_REQUEST['archive_id']) {
        if (!$_SESSION['user']->isEditor()) {
            die("editors only");
        }

        $archiveID = (int) $_REQUEST['archive_id'];
        DB::insert('INSERT INTO jmdict_ext_deleted (SELECT * FROM jmdict_ext WHERE jmdict_id = :archiveID)',
            [':archiveID' => $archiveID]);
        echo '<br/>';
        DB::insert('INSERT INTO jmdict_deleted (SELECT * FROM jmdict WHERE id = :archiveID)',
            [':archiveID' => $archiveID]);
        echo '<br/>';
        DB::delete('DELETE FROM jmdict_ext WHERE jmdict_id = :archiveID', [':archiveID' => $archiveID]);
        echo '<br/>';
        DB::delete('DELETE FROM jmdict WHERE id = :archiveID', [':archiveID' => $archiveID]);
        echo '<br/>';
        if (isset($_REQUEST['replace_id'])) {
            execute_query("UPDATE IGNORE  `learning_set_vocab` SET jmdict_id = " . $_REQUEST['replace_id'] . " WHERE  `jmdict_id` = $archiveID");
            echo '<br/>';
            DB::delete('DELETE FROM `learning_set_vocab` WHERE `jmdict_id` = :archiveID', [':archiveID' => $archiveID]);
            execute_query("UPDATE IGNORE `jmdict_learning` SET jmdict_id = " . $_REQUEST['replace_id'] . " WHERE jmdict_id = $archiveID");
            echo '<br/>';
        }
    } elseif (isset($_REQUEST['jmdict_id'])) {
        if (isset($_REQUEST['njlpt'])) {
            echo post_db_correction('jmdict', 'id', (int) $_REQUEST['jmdict_id'], 'njlpt', (int) $_REQUEST['njlpt'],
                $_SESSION['user']->isEditor(), '', '', false, @$_REQUEST['user_cmt']);
        } elseif (isset($_REQUEST['njlpt_r'])) {
            echo post_db_correction('jmdict', 'id', (int) $_REQUEST['jmdict_id'], 'njlpt_r', (int) $_REQUEST['njlpt_r'],
                $_SESSION['user']->isEditor(), '', '', false, @$_REQUEST['user_cmt']);
        } elseif (isset($_REQUEST['katakana'])) {
            echo post_db_correction('jmdict', 'id', (int) $_REQUEST['jmdict_id'], 'katakana',
                (int) ($_REQUEST['katakana'] == 'true'), $_SESSION['user']->isEditor(), '', '', false,
                @$_REQUEST['user_cmt']);
        } elseif (isset($_REQUEST['usually_kana'])) {
            echo post_db_correction('jmdict', 'id', (int) $_REQUEST['jmdict_id'], 'usually_kana',
                (int) ($_REQUEST['usually_kana'] == 'true'), $_SESSION['user']->isEditor(), '', '', false,
                @$_REQUEST['user_cmt']);
        } elseif (isset($_REQUEST['word'])) {
            echo post_db_correction('jmdict', 'id', (int) $_REQUEST['jmdict_id'], 'word', $_REQUEST['word'],
                $_SESSION['user']->isEditor(), '', '', false, @$_REQUEST['user_cmt']);
        } elseif (isset($_REQUEST['reading'])) {
            echo post_db_correction('jmdict', 'id', (int) $_REQUEST['jmdict_id'], 'reading', $_REQUEST['reading'],
                $_SESSION['user']->isEditor(), '', '', false, @$_REQUEST['user_cmt']);
        } else {
            echo 'Need a jlpt/jlp_r/katakana value';
        }
    } elseif ($_POST['copy_jmdict_id']) {
        if (!$_SESSION['user']->isEditor()) {
            die('editors only');
        }

        if ((int) @$_POST['new_jmdict_id']) {
            $id = (int) $_POST['new_jmdict_id'];
        } else {
            $id = (int) $_POST['copy_jmdict_id'];
        }

        foreach (['word', 'reading'] as $key) {
            if (isset($_POST[$key])) {
                $$key = "'" . DB::getConnection()->quote($_POST[$key]) . "'";
            } else {
                $$key = "`$key`";
            }
        }

        $table_status = '';
        $res = mysql_query("SELECT id FROM jmdict$table_status WHERE id = " . (int) $_POST['copy_jmdict_id']) or die(mysql_error());
        if (mysql_num_rows($res) == 0) {
            $table_status = '_deleted';
            $res = mysql_query("SELECT id FROM jmdict$table_status WHERE id = " . (int) $_POST['copy_jmdict_id']) or die(mysql_error());
        }
        if (mysql_num_rows($res) == 0) {
            die('Unknown ID: ' . $_POST['copy_jmdict_id']);
        }

        while (true) {
            $res = mysql_query("SELECT id FROM jmdict WHERE id = $id UNION SELECT id FROM jmdict_deleted WHERE id = $id") or die(mysql_error());
            if (mysql_num_rows($res) == 0) {
                break;
            } else {
                $id++;
            }
        }

        DB::insert("INSERT INTO jmdict$table_status (SELECT $id, $word, $reading, `pri_nf`, `usually_kana`, `pos_redux`, `katakana`, `njlpt`, `njlpt_r` FROM jmdict$table_status WHERE id = :copy_jmdict_id)",
            [':copy_jmdict_id' => $_POST['copy_jmdict_id']]);
        DB::insert("INSERT INTO jmdict_ext$table_status (SELECT $id, `gloss_english`, `gloss_french`, `gloss_german`, `gloss_spanish`, `gloss_russian`, `gloss_finnish`, `gloss_polish`, `gloss_turkish`, `gloss_swedish`, `gloss_italian`, `gloss_thai`, `length`, `not_from_file`, `pos`, `misc`, `pri_news`, `pri_ichi`, `pri_gai`, `pri_spec`, `full_readings` FROM jmdict_ext$table_status WHERE jmdict_id = :copy_jmdict_id)",
            [':copy_jmdict_id' => $_POST['copy_jmdict_id']]);

        $res = mysql_query("SELECT word, reading FROM jmdict$table_status WHERE id = $id") or die("Can't get record: " . mysql_error());
        $row = mysql_fetch_object($res);
        echo "Successfully inserted new vocab entry in jmdict$table_status: $row->word [$row->reading] (id: $id)";
    }
    ?>
</div>