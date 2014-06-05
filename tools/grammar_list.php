<?php
if (!$_SESSION['user'] || !$_SESSION['user']->isEditor()) {
    die('editors only');
}

if (!$_SESSION['user']->isAdministrator() && $_SESSION['user']->getID() != 46796) {
    $userID = $_SESSION['user']->getID();
    $setID = 0;
} else {
    try {
        $stmt = DB::getConnection()->prepare('SELECT u.*, ux.* FROM grammar_questions sq LEFT JOIN users u ON u.id = sq.user_id JOIN users_ext ux ON ux.user_id = u.id GROUP BY u.id');
        $stmt->execute();

        $menu = [];
        while ($row = $stmt->fetchObject()) {
            $menu[$row->user_id] = $row->first_name . ' ' . mb_substr($row->last_name, 0, 1, 'UTF-8') . '.';
        }

        echo 'User: ';
        if (!empty($params['user_id'])) {
            $userID = (int) $params['user_id'];
        } elseif (!empty($_REQUEST['user_id'])) {
            $userID = $_REQUEST['user_id'];
        } else {
            $userID = 0;
        }
        display_select_menu($menu, 'user_id', $userID,
            "window.location.href = '/kb/tools/grammar_list/user_id/' + this.value;", '-');

        $stmt = null;
    } catch (PDOException $e) {
        log_db_error($query, $e->getMessage(), false, true);
    }

    try {
        $stmt = DB::getConnection()->prepare('SELECT gs.set_id, gs.name FROM grammar_sets gs');
        $stmt->execute();

        $menu = [-1 => '[none]'];
        while ($row = $stmt->fetchObject()) {
            $menu[$row->id] = $row->name;
        }

        $stmt = null;
    } catch (PDOException $e) {
        log_db_error($query, $e->getMessage(), false, true);
    }

    if (!empty($params['set_id'])) {
        $setID = (int) $params['set_id'];
    } elseif (!empty($_REQUEST['set_id'])) {
        $setID = $_REQUEST['set_id'];
    } else {
        $setID = 0;
    }

    echo ' | Set: ';
    display_select_menu($menu, 'set_id', $setID,
        "window.location.href = '/kb/tools/grammar_list/?set_id=' + this.value;", '-');
    echo '<br/>';
}

try {
    $stmt = DB::getConnection()->prepare('SELECT * FROM grammar_sets ORDER BY short_name');
    $stmt->execute();

    $grammarSets = [];
    while ($row = $stmt->fetchObject()) {
        $grammarSets[$row->id] = $row->name;
    }

    $stmt = null;
} catch (PDOException $e) {
    log_db_error($query, $e->getMessage(), false, true);
}

$demoRow = DB::count('SELECT COUNT(*) FROM grammar_questions sq WHERE in_demo = 1 ' . ($userID ? 'AND sq.user_id = ' . $userID : '') . ($setID > 0 ? ' AND sq.set_id = ' . $setID : '') . ($setID == -1 ? ' AND (sq.set_id <= 0 OR sq.set_id IS NULL)' : ''));

$res = mysql_query("SELECT sq.*, e.*, j.njlpt AS word_jlpt, j.*, jg.gloss_english as gloss, ux.first_name, ux.last_name FROM grammar_questions sq JOIN examples e ON sq.sentence_id = e.example_id JOIN jmdict j ON j.id = sq.jmdict_id JOIN jmdict_ext jg ON jg.jmdict_id = j.id LEFT JOIN users_ext ux ON ux.user_id = sq.user_id WHERE " . ($userID ? "sq.user_id = $userID" : "1") . ($setID > 0 ? " AND sq.set_id = $setID" : "") . ($setID == -1 ? " AND (sq.set_id <= 0 OR sq.set_id IS NULL)" : "") . " ORDER BY set_id ASC, in_demo DESC LIMIT 300") or die(mysql_error());



        try {
            $stmt = DB::getConnection()->prepare($query);
            $stmt->execute();
            $stmt = null;
        } catch (PDOException $e) {
            log_db_error($query, $e->getMessage(), false, true);
        }






echo '<p>Total: ' . mysql_num_rows($res) . ' questions (' . $demoRow . ' demo)</p>';

$answers = [];
$res_correct_answers = mysql_query("SELECT j.*, jg.gloss_english as gloss, COUNT(*) AS c FROM grammar_questions sq JOIN jmdict j ON j.id = sq.jmdict_id JOIN jmdict_ext jg ON jg.jmdict_id = j.id LEFT JOIN users_ext ux ON ux.user_id = sq.user_id WHERE " . ($userID ? "sq.user_id = $userID" : "1") . ($setID > 0 ? " AND sq.set_id = $setID" : "") . ($setID == -1 ? " AND (sq.set_id <= 0 OR sq.set_id IS NULL)" : "") . " GROUP BY j.id") or die(mysql_error());
while ($answer = mysql_fetch_object($res_correct_answers)) {
    $answers[$answer->id] = ['correct' => $answer->c, 'wrong' => 0, 'jmdict' => $answer];
}

$res_wrong_answers = mysql_query("SELECT j.*, jg.gloss_english as gloss, COUNT(*) AS c FROM grammar_questions sq LEFT JOIN grammar_answers ga ON ga.question_id = sq.question_id JOIN jmdict j ON j.id = ga.jmdict_id JOIN jmdict_ext jg ON jg.jmdict_id = j.id LEFT JOIN users_ext ux ON ux.user_id = sq.user_id WHERE " . ($userID ? "sq.user_id = $userID" : "1") . ($setID > 0 ? " AND sq.set_id = $setID" : "") . ($setID == -1 ? " AND (sq.set_id <= 0 OR sq.set_id IS NULL)" : "") . " GROUP BY j.id") or die(mysql_error());
while ($answer = mysql_fetch_object($res_wrong_answers)) {
    if (!isset($answers[$answer->id])) {
        $answers[$answer->id] = ['correct' => 0, 'wrong' => $answer->c, 'jmdict' => $answer];
    } else {
        $answers[$answer->id]['wrong'] = $answer->c;
    }
}

if ($_REQUEST['show_breakdown']) {
    echo "<p><div id=\"breakdown\">";
} else {
    echo "<p><a href=\"#\" onclick=\"\$('#breakdown').toggle(); return false;\">Breakdown &raquo;</a> <div id=\"breakdown\" style=\"display:none;\">";
}

foreach ($answers as $answer) {
    $ratioWrong = ($answer['wrong'] / ($answer['correct'] + $answer['wrong']));
    $ratioCorrect = ($answer['correct'] / ($answer['correct'] + $answer['wrong']));
    echo '<div class="grammar-breakdown-bar" style="background-color:#9AFF84; width:' . 100 * $ratioCorrect . 'px;">' . ($ratioCorrect > 0 ? $answer['correct'] : '' ) . '</div><div class="grammar-breakdown-bar" style="background-color:#F7A181; width:' . 100 * $ratioWrong . 'px;">' . ($ratioWrong > 0 ? $answer['wrong'] : '') . '</div><div class="grammar-breakdown-text">' . ($answer['jmdict']->usually_kana || $answer['jmdict']->reading == $answer['jmdict']->word ? $answer['jmdict']->reading : $answer['jmdict']->word . '【' . $answer['jmdict']->reading . '】') . ($ratioWrong < 0.01 || $ratioCorrect < 0.01 ? ' <span style="color:red">(unbalanced)</span>' : '') . '</div><div style="clear: both;"></div>';
}
echo '</div></p>';


echo '<div id="ajax-result"></div>';

while ($question = mysql_fetch_object($res)) {
    if (!$first++ && $userID) {
        echo "Displaying editor: $question->first_name " . mb_substr($question->last_name, 0, 1, 'UTF-8') . "<br/>";
    }

    $res_ans = mysql_query('SELECT *, jx.gloss_english as gloss FROM grammar_answers sa LEFT JOIN jmdict j ON sa.jmdict_id = j.id LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id WHERE sa.question_id = ' . (int) $question->question_id . ' GROUP BY sa.jmdict_id') or die(mysql_error());

    echo '<fieldset class="question-list-item"' . (mysql_num_rows($res_ans) < 3 ? ' style="border: 2px solid #A00; background-color: #FEE;"' : '') . '>'
    ?>
    <legend>ID: <span id="question-id"><?php echo $question->question_id?></span> (Ed. <?php
        echo $question->first_name . ' ' . mb_substr($question->last_name, 0, 1, 'UTF-8') . '.'
        ?>) [<a href="http://kanjibox.net/kb/tools/grammar_editor/?edit_question_id=<?php echo $question->question_id?>">edit</a>]</legend>
    <p class="question-str"><?php
        if ($question->pos_start >= 0 && $question->pos_end > $question->pos_start) {
            echo mb_substr($question->example_str, 0, $question->pos_start, 'utf-8') . '<span class="highlighted">' . mb_substr($question->example_str,
                $question->pos_start, $question->pos_end - $question->pos_start, 'utf-8') . '</span>' . mb_substr($question->example_str,
                $question->pos_end, -1, 'utf-8');
        } else {
            echo $question->example_str;
        }
        ?>
    </p>
    <?php
    if ($question->pos_start == 0 && $question->pos_end == 0) {
        echo ' <span class="notice">(set answer position)</span>';
    } else {
        $substr = mb_substr($question->example_str, $question->pos_start, $question->pos_end - $question->pos_start,
            'utf-8');
        if ($substr != $question->word && $substr != $question->reading) {
            echo ' <span class="notice">(selection not matching answer)</span>';
        }
    }
    ?>
    <p>Set: <?php
        display_select_menu($grammarSets, 'question_id_' . $question->question_id . '_set_id', $question->id,
            "update_set_id($question->question_id,this.value);", '-');
        ?> | JLPT: N<?php echo $question->njlpt?> | Demo: <input type="checkbox" name="question_id_<?php echo $question->question_id?>_in_demo'" <?php echo $question->in_demo ? 'checked="checked"' : ''?> onchange="update_demo(<?php echo $question->question_id?>, this.checked);" /></p>
    <p class="question-en"><?php echo $question->english?></p>
    <br/>
    Correct answer:<br/>
    <p><span class="good-answer" id="picked-answer"><?php echo $question->word?></span> 【<?php echo ($question->reading)?>】 (N<?php echo ($question->word_jlpt)?>) - <small><?php echo $question->gloss?></small></p>

    <br/>
    <?php
    echo "Wrong answers (" . mysql_num_rows($res_ans);
    if (mysql_num_rows($res_ans) < 3) {
        echo ' - NOT ENOUGH!';
    }
    echo "):<br/>";

    while ($bad_answer = mysql_fetch_object($res_ans)) {
        ?>
        <p class="spaced-line"><span class="bad-answer"><?php echo $bad_answer->word?></span> 【<?php echo $bad_answer->reading?>】 (N<?php echo ($bad_answer->njlpt)?>)  <small><?php echo $bad_answer->gloss?></small> <a href="#" onclick="delete_answer(<?php echo $question->question_id . ', ' . $bad_answer->jmdict_id?>, this);
                return false;">[delete]</a></p>

        <?php
    }
}
