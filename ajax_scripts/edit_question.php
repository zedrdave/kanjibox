<?php
if (!@$_SESSION['user'] || !$_SESSION['user']->isEditor())
    die("editors only");

if (!empty($_REQUEST['delete_id'])) {
    $delete_id = (int) $_REQUEST['delete_id'];
    mysql_query('UPDATE grammar_sets SET date_last = NOW() WHERE set_id = (SELECT set_id FROM grammar_questions WHERE question_id = ' . $delete_id . ' LIMIT 1)') or die(mysql_error());

    mysql_query('DELETE FROM grammar_answers WHERE question_id = ' . $delete_id) or die(mysql_error());
    mysql_query('DELETE FROM grammar_questions WHERE question_id = ' . $delete_id . ' LIMIT 1') or die(mysql_error());

    echo '<div class="message">Deleted question ID: ' . $delete_id . '</div>';
    return;
}

if (isset($_REQUEST['sentence_id']) && !empty($_REQUEST['jmdict_id'])) {
    $res = mysql_query('SELECT * FROM examples WHERE example_id = ' . (int) $_REQUEST['sentence_id']) or die(mysql_error());
    if (mysql_num_rows($res) == 0)
        die('Unknown sentence ID: ' . $_REQUEST['sentence_id']);
    $sent = mysql_fetch_object($res);

    $res = mysql_query('SELECT j.*, jg.gloss_english AS gloss FROM jmdict j JOIN jmdict_ext jg ON jg.jmdict_id = j.id WHERE j.id = ' . (int) $_REQUEST['jmdict_id'] . ' GROUP BY j.id') or die(mysql_error());
    if (mysql_num_rows($res) == 0)
        die('Unknown word ID: ' . $_REQUEST['jmdict_id']);
    $word = mysql_fetch_object($res);


    $res = mysql_query("SELECT * FROM grammar_questions WHERE sentence_id = " . (int) $_REQUEST['sentence_id'] . " AND jmdict_id = " . (int) $_REQUEST['jmdict_id']);
    if (mysql_num_rows($res) > 0) {
        echo '<div class="message">Question using this sentence and word already exists:</div>';
        $question = mysql_fetch_object($res);
        $params['question_id'] = $question->question_id;
    } else {
        $pos_end = 0;
        $pos_start = mb_strpos($sent->example_str, $word->word, 0, 'UTF-8');
        if ($pos_start === FALSE) {
            $pos_start = mb_strpos($sent->example_str, $word->reading, 0, 'UTF-8');
            if ($pos_start === FALSE)
                echo '<div class="message">Warning! The selected sentence doesn\'t appear to contain the answer...</div>';
            else
                $pos_end = $pos_start + mb_strlen($word->reading, 'UTF-8');
        } else
            $pos_end = $pos_start + mb_strlen($word->word, 'UTF-8');

        if ($pos_start === FALSE)
            $pos_start = 0;

        mysql_query("INSERT INTO grammar_questions SET sentence_id = " . (int) $sent->example_id . ", jmdict_id = " . (int) $word->id . ", pos_start = " . (int) $pos_start . ", pos_end = " . (int) $pos_end . ", njlpt = " . ((int) $word->njlpt) . ", user_id = " . (int) $_SESSION['user']->getID()) or die(mysql_error());

        $params['question_id'] = mysql_insert_id();

        mysql_query('UPDATE grammar_sets SET date_last = NOW() WHERE set_id = (SELECT set_id FROM grammar_questions WHERE question_id = ' . $params['question_id'] . ' LIMIT 1)') or die(mysql_error());
    }
}

if (isset($params['question_id'])) {
    $res = mysql_query("SELECT * FROM grammar_questions WHERE question_id = " . (int) $params['question_id']);
    $question = mysql_fetch_object($res);

    if (!$question) {
        echo '<div class="message">This question ID does not exist: it has probably been deleted. Try refreshing the page.</div>';
        return;
    }

    if (isset($_REQUEST['add_wrong_answer'])) {
        $jmdict_id = (int) $_REQUEST['add_wrong_answer'];

        if ($jmdict_id == $question->jmdict_id) {
            echo '<div class="message">Can\'t add the right answer as a wrong choice...</div>';
        } else {
            $rowCount = DB::count('SELECT COUNT(*) FROM grammar_answers WHERE jmdict_id = ? AND question_id = ?',
                    [$jmdict_id, $question->question_id]);

            if ($rowCount > 0) {
                echo '<div class="message">Wrong answer has already been added...</div>';
            } else {
                mysql_query("INSERT INTO grammar_answers SET jmdict_id = $jmdict_id, question_id = $question->question_id") or die(mysql_error());
                mysql_query('UPDATE grammar_sets SET date_last = NOW() WHERE set_id = (SELECT set_id FROM grammar_questions WHERE question_id = ' . $question->question_id . ' LIMIT 1)') or die(mysql_error());
            }

            if (@$_REQUEST['no_content'])
                return;
        }
    }
    elseif (isset($_REQUEST['update_data'])) {
        $njlpt = (int) @$_REQUEST['njlpt'];
        if ($njlpt < 0)
            $njlpt = 0;
        elseif ($njlpt > 5) {
            $njlpt = 5;
            echo '<div class="message">JLPT level must be between 5 and 0...</div>';
        }
        $query = 'UPDATE grammar_questions SET pos_start = ' . (int) @$_REQUEST['pos_start'] . ', pos_end = ' . (int) @$_REQUEST['pos_end'] . ', njlpt = ' . $njlpt;
        if (@$_REQUEST['user_id'])
            $query .= ', user_id = ' . (int) $_REQUEST['user_id'];
        else
            $query .= ', user_id = ' . (int) $_SESSION['user']->getID();

        if (@$_REQUEST['set_id'])
            $query .= ', set_id = ' . (int) $_REQUEST['set_id'];

        $query .= ' WHERE question_id = ' . (int) $params['question_id'];

        mysql_query($query) or die(mysql_error());

        $res = mysql_query("SELECT * FROM grammar_questions WHERE question_id = " . (int) $params['question_id']);
        $question = mysql_fetch_object($res);

        mysql_query('UPDATE grammar_sets SET date_last = NOW() WHERE set_id = (SELECT set_id FROM grammar_questions WHERE question_id = ' . (int) $params['question_id'] . ' LIMIT 1)') or die(mysql_error());
    }
    elseif (isset($_REQUEST['update_set_id'])) {
        mysql_query('UPDATE grammar_sets SET date_last = NOW() WHERE set_id = (SELECT set_id FROM grammar_questions WHERE question_id = ' . (int) $params['question_id'] . ' LIMIT 1)') or die(mysql_error());

        $query = 'UPDATE grammar_questions SET set_id = ' . (int) @$_REQUEST['update_set_id'] . ' WHERE question_id = ' . (int) $params['question_id'];
        mysql_query($query) or die(mysql_error());

        mysql_query('UPDATE grammar_sets SET date_last = NOW() WHERE set_id = ' . (int) @$_REQUEST['update_set_id']) or die(mysql_error());

        echo '<div class="message">Updated set for question id: ' . (int) $params['question_id'] . '</div>';


        return;
    } elseif (isset($_REQUEST['update_in_demo'])) {
        $query = 'UPDATE grammar_questions SET in_demo = ' . (int) @$_REQUEST['update_in_demo'] . ' WHERE question_id = ' . (int) $params['question_id'];
        mysql_query($query) or die(mysql_error());
        echo '<div class="message">Updated demo status for question id: ' . (int) $params['question_id'] . '</div>';
        return;
    } elseif (isset($_REQUEST['update_reviewed'])) {
        // $res = mysql_query("SELECT * FROM grammar_answers WHERE question_id = " . (int) $params['question_id'] . " AND jmdict_id = " . (int) $params['jmdict_id']);
        // $answer = mysql_fetch_object($res);
        //         if(! $answer) {
        // 	echo '<div class="message">This answer seems to have been deleted already. Please refresh the page and try again.</div>';
        //         }
        //         else {
        $query = "REPLACE INTO grammar_answer_reviews SET user_id = " . $_SESSION['user']->getID() . ", status = '" . mysql_real_escape_string($_REQUEST['update_reviewed']) . "', question_id = " . (int) $params['question_id'] . ", jmdict_id = " . (int) $params['jmdict_id'];
        $res = mysql_query($query);
        if (!$res)
            echo '<div class="message">Database error: could not update review status: ' . mysql_error() . '<br/>' . $query . '</div>';
        // }
        return;
    }
    elseif (isset($_REQUEST['delete_answer_id'])) {
        $answer_id = (int) $_REQUEST['delete_answer_id'];
        echo '<div class="message">Deleting answer: ' . $question->question_id . '-' . $answer_id . '...</div>';

        mysql_query("DELETE FROM grammar_answers WHERE jmdict_id = " . (int) $_REQUEST['delete_answer_id'] . " AND question_id = $question->question_id") or die(mysql_error());

        mysql_query('UPDATE grammar_sets SET date_last = NOW() WHERE set_id = (SELECT set_id FROM grammar_questions WHERE question_id = ' . $question->question_id . ' LIMIT 1)') or die(mysql_error());

        if (@$_REQUEST['no_content'])
            return;

        // return;
    }



    if (!isset($sent)) {
        $res = mysql_query('SELECT * FROM examples WHERE example_id = ' . (int) $question->sentence_id) or die(mysql_error());
        if (mysql_num_rows($res) == 0)
            die('Unknown sentence ID: ' . $question->sentence_id);
        $sent = mysql_fetch_object($res);
    }

    if (!isset($word)) {
        $res = mysql_query('SELECT j.*, jg.gloss_english as gloss FROM jmdict j JOIN jmdict_ext jg ON jg.jmdict_id = j.id WHERE j.id = ' . (int) $question->jmdict_id . ' GROUP BY j.id') or die(mysql_error());
        if (mysql_num_rows($res) == 0)
            die('Unknown word ID: ' . $question->jmdict_id);
        $word = mysql_fetch_object($res);
    }
    ?>
    <form class="ajax-form" id="save-question-data-form" action="<?php echo SERVER_URL?>ajax/edit_question/question_id/<?php echo $question->question_id;?>/" method="post">
        <p><a class="delete-button" href="#" onclick="delete_question(<?php echo $question->question_id?>);
                return false;">×</a> ID: <span id="question-id"><?php echo $question->question_id?></span> 
            - Set: <select name="set_id" id="set_id" onchange="show_question_save_button();"><option value="0">Select a set...</option><?php
    $res = mysql_query("SELECT * FROM grammar_sets");
    while ($row = mysql_fetch_object($res)) {
        echo "<option value=\"$row->setID\"" . ($row->setID == @$question->setID ? ' selected' : '') . ">$row->setID. $row->name</option>";
    }
    ?></select>
        </p>
        <p id="question-str"><?php echo $sent->example_str?></p>
        <input type="hidden" name="update_data" value="1"/>
        <p>Answer position: <a href="#" onclick="move_selection(-1);
                    highlight_text();
                    return false;">←</a> <a href="#" onclick="extend_selection(-1);
                            highlight_text();
                            return false;">-</a> <input type="hidden" name="pos_start" id="pos_start" value="<?php echo $question->pos_start?>" />[<span id="pos_start_txt" /> ~ <span id="pos_end_txt" />]<input type="hidden" name="pos_end" id="pos_end" value="<?php echo $question->pos_end?>" onchange="show_question_save_button();" /> <a href="#" onclick="extend_selection(1);
                                    highlight_text();
                                    return false;">+</a> <a href="#" onclick="move_selection(1);
                                            highlight_text();
                                            return false;">→</a><?php
            if ($question->pos_start == 0 && $question->pos_end == 0)
                echo ' <span class="notice">(set answer position)</span>';
            else {
                $substr = mb_substr($sent->example_str, $question->pos_start, $question->pos_end - $question->pos_start,
                    'utf-8');
                if ($substr != $word->word && $substr != $word->reading)
                    echo ' <span class="notice">(selection not matching answer)</span>';
            }
    ?></p>
        <p>JLPT: N<input type="text" size="1" name="njlpt" value="<?php echo $question->njlpt?>" onchange="show_question_save_button();" /></p>
            <?php
            if ($_SESSION['user']->isAdministrator())
                echo '<p>User id: <input type="text" size="10" name="user_id" value="' . $question->user_id . '" onchange="show_question_save_button();" /></p>';
            else
                echo '<input type="hidden" name="user_id" value="' . $question->user_id . '" />';
            ?>
        <input type="submit" id="save-question-info" class="save-button" style="display:none;" name="save" value="Save" />
    </form>
    <br/>
    Correct answer:<br/>
    <p><span class="good-answer" id="picked-answer"><?php echo $word->word?></span> 【<?php echo ($word->reading)?>】 (N<?php echo ($word->njlpt)?>) - <small><?php echo $word->gloss?></small></p>

    <br/>
        <?php
        $res = mysql_query('SELECT *, jx.gloss_english as gloss FROM grammar_answers sa LEFT JOIN jmdict j ON sa.jmdict_id = j.id LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id WHERE sa.question_id = ' . (int) $question->question_id . ' GROUP BY sa.jmdict_id') or die(mysql_error());

        echo "Wrong answers (" . mysql_num_rows($res) . "):<br/>";

        while ($bad_answer = mysql_fetch_object($res)) {
            ?>
        <p class="spaced-line"><span class="bad-answer"><?php echo ($bad_answer->usually_kana || $bad_answer->reading == $bad_answer->word ? $bad_answer->reading : $bad_answer->word . '【' . $bad_answer->reading . '】')?></span> (N<?php echo ($bad_answer->njlpt)?>) <a class="delete-button" href="#" onclick="delete_wrong_answer( < ? echo $question - > question_id.', '.$bad_answer - > jmdict_id; ? > ); return false;">×</a> - <small><?php echo $bad_answer->gloss?></small></p>
        <?php
    }
    ?>
    <hr/>
    Add choices: <form class="ajax-form" id="wrong-answer-form" action="<?php echo SERVER_URL?>ajax/get_jmdict/" method="post">
        <input type="hidden" name="mode" value="wrong-answers"/>
        <input type="text" name="word" value="" size="12" />　 (<input type="checkbox" name="exact_match" /> Exact match)　
        <input type="submit" name="search" value="Search"></input>
    </form>	

    <div id="wrong-answer-results"></div>
    <?php
    $query = 'SELECT sub.*, jx.*, gar.status AS review_status FROM (SELECT j.* FROM grammar_questions sq LEFT JOIN grammar_answers sa ON sa.question_id = sq.question_id LEFT JOIN jmdict j ON j.id = sa.jmdict_id WHERE sq.jmdict_id = ' . (int) $question->jmdict_id . ' AND sa.jmdict_id IS NOT NULL UNION SELECT j.* FROM grammar_answers sa LEFT JOIN grammar_questions sq ON sq.question_id = sa.question_id LEFT JOIN jmdict j ON j.id = sq.jmdict_id WHERE sa.jmdict_id = ' . (int) $question->jmdict_id . ' UNION SELECT j.* FROM grammar_answers sa LEFT JOIN grammar_answers sa2 ON sa2.question_id = sa.question_id LEFT JOIN jmdict j ON j.id = sa2.jmdict_id WHERE sa.jmdict_id = ' . (int) $question->jmdict_id . ') as sub LEFT JOIN jmdict_ext jx ON jx.jmdict_id = sub.id LEFT JOIN grammar_answers sa ON sa.jmdict_id = sub.id AND sa.question_id = ' . (int) $question->question_id . ' LEFT JOIN grammar_answer_reviews gar ON gar.question_id = ' . (int) $question->question_id . ' AND gar.jmdict_id = jx.jmdict_id WHERE sa.jmdict_id IS NULL AND sub.id != ' . (int) $question->jmdict_id . ' GROUP BY sub.id ORDER BY gar.status != \'problem\'';
    // echo $query;
    $res = mysql_query($query) or die(mysql_error());
    $target_sel = '#wrong-answer-results';
    if (mysql_num_rows($res) > 0)
        echo "<br/>Suggestions:";
    while ($word = mysql_fetch_object($res)) {
        echo "<div class=\"db-group ajax-clickable\" onclick=\"$('" . $target_sel . "').load('" . SERVER_URL . "ajax/get_jmdict/?jmdict_id=$word->id&mode=wrong-answers');\">";
        echo "<p class=\"db-info-jp\">" . (!$word->usually_kana && $word->reading != $word->word ? "<strong>$word->word</strong>【" . $word->reading . "】" : "<strong>$word->reading</strong>") . " (N" . ($word->njlpt) . ", R-N" . ($word->njlpt_r) . ")" . ($word->review_status == 'problem' ? ' <span style="color:red;">(!)</span>' : '') . "</p>";
        echo "<p class=\"db-info-small\"><em>$word->gloss_english</em></p>";
        echo "</div>";
    }
} else {
    echo 'Need question ID or sentence-and-word';
    return;
}
?>
<script type="text/javascript">

    $(document).ready(function() {
        str = "<?php echo str_replace('"', '\\"', $sent->example_str);?>";
        highlight_text();

        $('#wrong-answer-form').ajaxForm({
            target: '#wrong-answer-results',
            beforeSubmit: function() {
                $('#wrong-answer-results').html('reloading...')
            },
        });

        $('#save-question-data-form').ajaxForm({
            target: '#question > .ajax-result',
            beforeSubmit: function() {
                $('#question > .ajax-result').html('reloading...')
            },
        });

    });


</script>
