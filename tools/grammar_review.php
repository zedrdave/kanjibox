<?php
if (!@$_SESSION['user'] || !$_SESSION['user']->isEditor())
    die("only editors");

mb_internal_encoding("UTF-8");

// if(! $_SESSION['user']->is_admin())
// $user_id = $_SESSION['user']->get_id();

$res = mysql_query("SELECT gs.set_id, gs.name FROM grammar_sets gs");
$grammar_sets = array(-1 => '[none]');
while ($row = mysql_fetch_object($res))
    $grammar_sets[$row->set_id] = $row->name;

$set_id = (int) @$_REQUEST['set_id'];
if (!$set_id)
    $set_id = (int) @$params['set_id'];

echo 'Set: ';
display_select_menu($grammar_sets, 'set_id', $set_id,
    "window.location.href = 'https://kanjibox.net/kb/tools/grammar_review/set_id/' + this.value + '/';", '-');
echo '<br/>';


$res = mysql_query("SELECT sq.*, e.*, j.njlpt AS word_jlpt, ux.first_name, ux.last_name, ga.jmdict_id AS answer_jmdict_id, IF(j2.usually_kana, j2.reading, j2.word) AS decoy, r.status AS review_status, r.user_id AS reviewer_id FROM grammar_questions sq JOIN examples e ON sq.sentence_id = e.example_id JOIN jmdict j ON j.id = sq.jmdict_id JOIN jmdict_ext jg ON jg.jmdict_id = j.id LEFT JOIN users_ext ux ON ux.user_id = sq.user_id LEFT JOIN grammar_answers ga ON ga.question_id = sq.question_id LEFT JOIN jmdict j2 ON j2.id = ga.jmdict_id LEFT JOIN grammar_answer_reviews r ON r.question_id = sq.question_id AND r.jmdict_id = ga.jmdict_id WHERE " . (@$user_id ? "sq.user_id = $user_id" : "1") . ($set_id > 0 ? " AND sq.set_id = $set_id" : "") . ($set_id == -1 ? " AND (sq.set_id <= 0 OR sq.set_id IS NULL)" : "") . " ORDER BY (review_status IS NULL) DESC, in_demo DESC, set_id ASC") or die(mysql_error());

$row_count = DB::count('SELECT COUNT(*) FROM grammar_questions sq LEFT JOIN grammar_answers ga ON ga.question_id = sq.question_id LEFT JOIN grammar_answer_reviews r ON r.question_id = sq.question_id AND r.jmdict_id = ga.jmdict_id WHERE r.question_id IS NULL ' . ($user_id ? "AND sq.user_id = $user_id" : '') . ($set_id > 0 ? " AND sq.set_id = $set_id" : '') . ($set_id == -1 ? ' AND (sq.set_id <= 0 OR sq.set_id IS NULL)' : ''),
        []);

echo "<p><strong>Still need reviewing: $row_count->c sentences</strong> + Already reviewed: " . (mysql_num_rows($res) - $row_count->c) . " = Total: " . mysql_num_rows($res) . "</p>";

echo '<div id="ajax-result"></div>';

$last_qid = -1;
$displayed_status_header = false;

echo '<h2>Need reviewing:</h2>';

while ($question = mysql_fetch_object($res)) {
    $str = $question->example_str;
    if ($last_qid != $question->question_id) {
        if ($last_qid > 0)
            echo '</div>';
        if ($question->review_status && !$displayed_status_header) {
            echo "<h2>Already reviewed:</h2>";
            echo "<p>[<a href=\"#\" onclick=\"\$('div.already-reviewed').show(); \$('div.already-reviewed p').show(); return false;\">show all answers</a>]</p>";
            $displayed_status_header = true;
        }
        echo '<div class="db-group' . ($displayed_status_header ? ' already-reviewed' : '') . '" style="padding: 3px;">';

        echo '<p>' . mb_substr($str, 0, $question->pos_start) . '<span class="highlighted-correct">' . mb_substr($str,
            $question->pos_start, $question->pos_end - $question->pos_start) . '</span>' . mb_substr($str,
            $question->pos_end) . "<span style=\"font-size:90%;color:#777;\">#<a href=\"http://kanjibox.net/kb/tools/grammar_editor/?edit_question_id=$question->question_id\">$question->question_id</a></span>" . ($displayed_status_header && $_SESSION['user']->isAdministrator() ? ' <a href="#" onclick="delete_question(' . $question->question_id . ', this); return false;" style="color:red;">[delete]</a>' : '' ) . '</p>';


        $last_qid = $question->question_id;
    }
    if ($question->answer_jmdict_id > 0)
        $str = mb_substr($str, 0, $question->pos_start) . '<span class="highlighted-wrong">' . $question->decoy . '</span>' . mb_substr($str,
                $question->pos_end);
    else
        $str = '<span style="color:#600;">' . $str . '</span>';

    echo "<p style=\"margin:5px; padding:0;\" class=\"reviewed-" . ($question->review_status) . "\"><input type=\"checkbox\" name=\"answer[$question->question_id][$question->answer_jmdict_id]\" id=\"answer[$question->question_id][$question->answer_jmdict_id]\" onchange=\"update_review_status($question->question_id, $question->answer_jmdict_id, this.checked ? 'ok' : 'problem');\" " . ($question->review_status == 'ok' ? ' checked' : '') . "></input><label id=\"answer_str_" . $question->question_id . "_$question->answer_jmdict_id\" for=\"answer[$question->question_id][$question->answer_jmdict_id]\" style=\"" . ($question->review_status == 'ok' ? 'color: #888;' : ($question->review_status == 'problem' ? 'border: 1px solid #A00; text-decoration: line-through; ' : '')) . "\">$str</label>";

    echo " <span style=\"font-size:90%;color:#777;\">#<a href=\"http://kanjibox.net/kb/tools/grammar_editor/?edit_question_id=$question->question_id\">$question->question_id</a>-$question->answer_jmdict_id</span>" . ' <input id="' . "flag[$question->question_id][$question->answer_jmdict_id]" . '" type="checkbox" class="flag-button" onchange="update_review_status(' . $question->question_id . ', ' . $question->answer_jmdict_id . ',  this.checked ? \'problem\' : \'\')" ' . ($question->review_status == 'problem' ? 'checked="checked"' : '') . '></input><label for="' . "flag[$question->question_id][$question->answer_jmdict_id]" . '">Flag</label>';

    if ($displayed_status_header && $_SESSION['user']->isAdministrator() && $question->review_status == 'problem')
        echo ' <a href="#" onclick="delete_answer(' . $question->question_id . ', ' . $question->answer_jmdict_id . ', this); return false;" style="color:red;">[delete]</a>';

    echo "</p>\n";
}
echo '</div>';
?>
<script>

    $(function() {
        $(".flag-button").button({
            icons: {
                primary: "ui-icon-alert"
            },
            text: false
        })

        $('.already-reviewed p.reviewed-problem').parent().show();
    })

    function delete_question(question_id, selector) {
        if (confirm("Are you sure you want to delete this entire question?")) {
            $.ajax({
                url: '<?php echo SERVER_URL?>ajax/edit_question/?delete_id=' + question_id,
                type: 'GET',
                success: function(data) {
                    if (data != '') {
                        alert(data.replace(/<(?:.|\n)*?>/gm, ''));
                        $('#ajax-result').show().html(data);
                        setTimeout(function() {
                            $('#ajax-result').hide().html('')
                        }, 10000);
                    }

                    $(selector).parent().css('text-decoration', 'line-through')
                    $(selector).parent().css('text-decoration-color', '#F00')

                },
                timeout: 1000,
                error: function(x, t, m) {
                    alert("There was a connection error and your change was NOT saved.\nIf this problem persists, please reload the page.")
                }
            });
        }
    }

    function delete_answer(question_id, answer_id, selector) {
        $.ajax({
            url: '<?php echo SERVER_URL?>ajax/edit_question/question_id/' + question_id + '/?no_content=1&delete_answer_id=' + answer_id,
            type: 'GET',
            success: function(data) {
                if (data != '') {
                    // alert(data.replace(/<(?:.|\n)*?>/gm, ''));
                    $('#ajax-result').show().html(data);
                    setTimeout(function() {
                        $('#ajax-result').hide().html('')
                    }, 10000);
                }

                $(selector).parent().css('text-decoration', 'line-through')
                $(selector).parent().css('text-decoration-color', '#F00')

            },
            timeout: 1000,
            error: function(x, t, m) {
                alert("There was a connection error and your change was NOT saved.\nIf this problem persists, please reload the page.")
            }
        });
    }

    function update_review_status(question_id, jmdict_id, status)
    {
        var selector = '#answer_str_' + question_id + '_' + jmdict_id

        if (status == 'ok')
            $(selector).css('color', '#888').css('border', '1px solid #000')
        else if (status == 'problem')
            $(selector).css('text-decoration', 'line-through').css('border', '1px solid #A00')
        else
            $(selector).css('color', '#000').css('border', 'none')

        $.ajax({
            url: '<?php echo SERVER_URL?>ajax/edit_question/question_id/' + question_id + '/jmdict_id/' + jmdict_id + '/?update_reviewed=' + status,
            type: 'POST',
            success: function(data) {
                if (data != '') {
                    alert(data.replace(/<(?:.|\n)*?>/gm, ''));
                    $('#ajax-result').show().html(data);
                    setTimeout(function() {
                        $('#ajax-result').hide().html('')
                    }, 10000);
                }
                else {
                    if (status == 'ok')
                        $(selector).css('text-decoration', 'none')
                    else
                        $(selector).css('text-decoration', 'line-through')

                }
            },
            timeout: 1000,
            error: function(x, t, m) {
                alert("There was a connection error and your change was NOT saved.\nIf this problem persists, please reload the page.")
            }
        });
    }

</script>