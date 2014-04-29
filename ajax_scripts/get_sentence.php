<?php
if (!@$_SESSION['user'] || !$_SESSION['user']->is_elite())
    die("elite only");
mb_internal_encoding('UTF-8');


$url_args = '';
if (!empty($_REQUEST['word']))
    $url_args .= 'word=' . urlencode($_REQUEST['word']) . '&';
if (!empty($_REQUEST['not_word']))
    $url_args .= 'not_word=' . urlencode($_REQUEST['not_word']) . '&';
if (@$_REQUEST['njlpt'])
    $url_args .= 'njlpt=' . (int) $_REQUEST['njlpt'] . '&';
if (@$_REQUEST['jmdict_id'])
    $url_args .= 'jmdict_id=' . (int) $_REQUEST['jmdict_id'] . '&';
if (@$_REQUEST['display_done_editing'])
    $url_args .= 'display_done_editing=1&';

$has_search_string = ($url_args != '');
if (@$_REQUEST['container_selector'])
    $container_selector = strip_tags($_REQUEST['container_selector']);
else
    $container_selector = '#sentences > .ajax-result';

$url_args .= 'container_selector=' . urlencode($container_selector) . '&';

if (isset($_REQUEST['id'])) {
    $res = mysql_query('SELECT * FROM examples WHERE example_id = ' . (int) $_REQUEST['id']) or die(mysql_error());
} else {
    if (empty($_REQUEST['word']) && empty($_REQUEST['not_word']) && empty($_REQUEST['jmdict_id'])) {
        echo 'Need a search string';
        exit;
    }

    $query_body = 'FROM examples e ';
    if ($_REQUEST['jmdict_id']) {
        $query_body .= ' JOIN example_parts p ON p.example_id = e.example_id AND p.jmdict_id = ' . (int) $_REQUEST['jmdict_id'];
    }

    $query_where = ' WHERE 1 ';

    if ($_REQUEST['word']) {
        $searchWord = $_REQUEST['word'];
        $query_where .= ' AND e.example_str LIKE \'%?%\'';
    }
    if ($_REQUEST['not_word']) {
        $searchWord = $_REQUEST['not_word'];
        $query_where .= ' AND e.example_str NOT LIKE \'%?%\'';
    }

    $tot_count = DB::count('SELECT COUNT(*) ' . $query_body . $query_where, [$searchWord]);
    $limit = ($tot_count < 10 ? $tot_count : 5);
    $skip = (int) @$_REQUEST['skip'];

    $query_where .= ' ORDER BY ';
    if (@$_REQUEST['njlpt']) {
        $query_where .= 'ABS(e.njlpt - ' . (int) $_REQUEST['njlpt'] . ') ASC, ABS(e.njlpt_r - ' . (int) $_REQUEST['njlpt'] . ') ASC, LENGTH(e.example_str) ASC, ';
    } else {
        $query_where .= 'e.njlpt DESC, e.njlpt_r DESC, LENGTH(e.example_str) ASC, ';
    }
    $query_where .= 'e.has_prime_example DESC';

    $query = 'SELECT e.* ' . $query_body . $query_where . ' LIMIT ' . $skip . ',' . $limit;
    $res = mysql_query($query) or die(mysql_error());


    $navig = '';


    if ($limit < $tot_count) {
        $url = SERVER_URL . "ajax/get_sentence/?" . $url_args . "skip=";
        if ($skip > 0)
            $navig .= '<a href="#" onclick="$(\'' . $container_selector . '\').load(\'' . $url . ($skip - $limit) . '\'); return false;">&laquo;</a>';
        $navig .= ' [' . ($skip + 1) . '~' . ($skip + $limit) . ']/' . $tot_count . ' ';
        if ($skip + $limit < $tot_count)
            $navig .= '<a href="#" onclick="$(\'' . $container_selector . '\').load(\'' . $url . ($skip + $limit) . '\'); return false;">&raquo;</a>';
    }
    echo $navig;
}
$count = mysql_num_rows($res);

if ($count == 0) {
    echo 'No match';
    exit;
}

if ($count == 1) {
    $sentence = mysql_fetch_object($res);

    if (@$params['edit']) {
        echo "<div class=\"db-group selected-item\">";

        if (@$_REQUEST['display_done_editing'])
            echo "<p style=\"margin-bottom:10px;\"><a href=\"#\" onclick=\"$('$container_selector').load('" . SERVER_URL . "ajax/get_sentence/?id=$sentence->example_id&$url_args'); return false;\">&laquo; done editing</a></p>";

        echo "<p>ID: $sentence->example_id</p>";

        $res_parts = mysql_query("SELECT ep.*, j.*, jx.gloss_english AS gloss, jd.id AS id_deleted, jd.word AS word_deleted, jd.reading AS reading_deleted, jxd.gloss_english AS gloss_deleted FROM example_parts ep LEFT JOIN jmdict j ON j.id = ep.jmdict_id LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id LEFT JOIN jmdict_deleted jd ON jd.id = ep.jmdict_id LEFT JOIN jmdict_ext_deleted jxd ON jxd.jmdict_id = jd.id  WHERE example_id = " . $sentence->example_id . " AND (j.id IS NOT NULL OR jd.id IS NOT NULL) ORDER BY ep.pos_start") or die(mysql_error());

        $parsed_sent_jp = '';
        if (@$params['show_vocab'])
            $parts_html = "<ul id=\"parts_" . $sentence->example_id . "\"  class=\"example_parts\">";
        else
            $parts_html = "<a href=\"#\" onclick=\"$('#parts_" . $sentence->example_id . "').show(); $(this).hide(); return false;\"><i>Show vocab &raquo;</i></a><ul id=\"parts_" . $sentence->example_id . "\"  class=\"example_parts\" style=\"display:none;\">";

        $last_pos_end = 0;
        while ($part = mysql_fetch_object($res_parts)) {
            if ($last_pos_end > 0) {
                $parts_html .= "<ul style=\"margin-bottom: 5px;\"><lh><a href=\"#\" onclick=\"if(confirm('Are you sure you want to merge these two parts?')) { \$(this).parent().hide(); extend_sentence_part($sentence->example_id, $last_pos_end, $part->pos_end, '#part_" . $sentence->example_id . "_" . $part->pos_start . "');}; return false;\">Merge with next</a></lh></ul>";
                $parts_html .= '</div></li>';
            }

            if ($part->pos_start > $last_pos_end)
                $parsed_sent_jp .= mb_substr($sentence->example_str, $last_pos_end, $part->pos_start - $last_pos_end);

            $sent_part = mb_substr($sentence->example_str, $part->pos_start, $part->pos_end - $part->pos_start);
            $parsed_sent_jp .= "<span class=\"sentence_part\" id=\"sent_part_$sentence->example_id" . "_$part->pos_start\" onmouseover=\"$('#part_$sentence->example_id" . "_$part->pos_start').css('border', '2px solid red'); \" onmouseout=\"$('#part_$sentence->example_id" . "_$part->pos_start').css('border', '');\">" . $sent_part . '</span>';
            $last_pos_end = $part->pos_end;

            $parts_html .= "<li class=\"part\"><span class=\"sentence_part\" id=\"part_$sentence->example_id" . "_$part->pos_start\" onmouseover=\"$('#sent_part_$sentence->example_id" . "_$part->pos_start').css('border', '2px solid red'); \" onmouseout=\"$('#sent_part_$sentence->example_id" . "_$part->pos_start').css('border', '');\">";

            if ($part->id_deleted) {
                $parts_html .= '<a href="/kb/tools/vocab_editor/?id=' . $part->id_deleted . '&show_disabled=on&submit=Find" style="color:orange;">[Not in Active DB:] </a>';
                $part->word = $part->word_deleted;
                $part->reading = $part->reading_deleted;
                $part->gloss = $part->gloss_deleted;
            }

            if ($sent_part != $part->word)
                $parts_html .= $sent_part . '　▶ ';
            if ($part->word != $part->reading && !$part->katakana)
                $parts_html .= " $part->word 【" . $part->reading . "】";
            else
                $parts_html .= " $part->word";

            $parts_html .= "[N$part->njlpt, R-N$part->njlpt_r]: $part->gloss</span>";
            $res_alt = mysql_query("SELECT j.*, jx.gloss_english AS gloss FROM jmdict j LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id WHERE j.id != " . $part->jmdict_id . " AND (j.word = '" . mysql_real_escape_string($part->word) . "' OR j.reading = '" . mysql_real_escape_string($part->reading) . "'" . ($sent_part != $part->word ? " OR j.word = '" . mysql_real_escape_string($sent_part) . "'" : '') . ($sent_part != $part->reading ? " OR j.reading = '" . mysql_real_escape_string($sent_part) . "'" : '') . ")") or die(mysql_error());

            $parts_html .= " <a href=\"#\" onclick=\"\$(this).hide(); \$('#edit_part_" . $sentence->example_id . "_$part->pos_start').show(); return false;\">[edit &raquo;]</a> <div id=\"edit_part_" . $sentence->example_id . '_' . $part->pos_start . "\" style=\"display:none;\"><ul id=\"alt_parts_" . $sentence->example_id . '_' . $part->jmdict_id . "\"><lh><a href=\"#\" onclick=\"$(this).parent().siblings().show(); $(this).parent().hide(); return false;\">Replace by &raquo;</a></lh>";
            while ($alt_part = mysql_fetch_object($res_alt))
                $parts_html .= "<li id=\"alt_part_$sentence->example_id" . "_$part->pos_start" . "_$alt_part->id\" style=\"display:none;\"><a href=\"#\" onclick=\"update_sentence_part($sentence->example_id, $part->pos_start, $alt_part->id); return false;\">Replace by:</a> $alt_part->word 【" . $alt_part->reading . "】[N$alt_part->njlpt, R-N$alt_part->njlpt_r]: $alt_part->gloss</li>";

            if ($_SESSION['user']->isEditor())
                $parts_html .= "<li style=\"display:none;\" id=\"alt_part_free_text_" . $sentence->example_id . "_" . $part->pos_start . "\">Replace by <input type=\"text\" size=\"10\" onchange=\"add_alt_sentence_part(this.value, " . $sentence->example_id . "," . $part->pos_start . ");\"/> <div id=\"replace_sel_$sentence->example_id" . "_$part->pos_start\"></div></li>";
            $parts_html .= '</ul>';

            $parts_html .= "<ul style=\"\"><lh><a href=\"#\" onclick=\"\$(this).parent().siblings().show(); $(this).parent().hide(); return false;\">Change range &raquo;</a></lh>";

            if ($part->pos_start >= 1)
                $parts_html .= "<li style=\"display:none;\"><a href=\"#\" onclick=\"change_sentence_part($sentence->example_id, $part->pos_start, $part->pos_end, " . ($part->pos_start - 1) . ", $part->pos_end, '#part_" . $sentence->example_id . "_" . $part->pos_start . "'); return false;\">Extend left by one character</a></li>";

            if (mb_strlen($sent_part) > 1) {
                $parts_html .= "<li style=\"display:none;\"><a href=\"#\" onclick=\"change_sentence_part($sentence->example_id, $part->pos_start, $part->pos_end, " . ($part->pos_start + 1) . ", $part->pos_end, '#part_" . $sentence->example_id . "_" . $part->pos_start . "'); return false;\">Shrink left by one character</a></li>";
                $parts_html .= "<li style=\"display:none;\"><a href=\"#\" onclick=\"change_sentence_part($sentence->example_id, $part->pos_start, $part->pos_end, $part->pos_start, " . ($part->pos_end - 1) . ", '#part_" . $sentence->example_id . "_" . $part->pos_start . "'); return false;\">Shrink right by one character</a></li>";
            }
            $parts_html .= "<li style=\"display:none;\"><a href=\"#\" onclick=\"change_sentence_part($sentence->example_id, $part->pos_start, $part->pos_end, $part->pos_start, " . ($part->pos_end + 1) . ", '#part_" . $sentence->example_id . "_" . $part->pos_start . "'); return false;\">Extend right by one character</a></li>";
            $parts_html .= "</ul>";

            if (mb_strlen($sent_part) > 1) {
                $parts_html .= "<ul style=\"\"><lh><a href=\"#\" onclick=\"\$(this).parent().siblings().show(); $(this).parent().hide(); return false;\">Split &raquo;</a></lh>";
                for ($i = 1; $i < mb_strlen($sent_part); $i++)
                    $parts_html .= "<li style=\"display:none;\"><a href=\"#\" onclick=\"split_sentence_part($sentence->example_id, $part->pos_start, $i, '#part_" . $sentence->example_id . "_" . $part->pos_start . "'); return false;\">Split as: " . mb_substr($sent_part,
                            0, $i) . " + " . mb_substr($sent_part, $i) . "</a></li>";

                $parts_html .= "</ul>";
            }

            $parts_html .= "<ul style=\"\"><lh><a href=\"#\" onclick=\"if(confirm('Are you sure you want to delete this fragment?')) { \$(this).parent().hide(); delete_sentence_part($sentence->example_id, $part->pos_start, $part->jmdict_id, '#part_" . $sentence->example_id . "_" . $part->pos_start . "');}; return false;\">Delete</a></lh></ul>";
        }
        $parts_html .= "</div></li></ul>";


        if ($last_pos_end < mb_strlen($sentence->example_str))
            $parsed_sent_jp .= mb_substr($sentence->example_str, $last_pos_end);

        echo "<span onclick=\"\$(this).hide(); \$('#jp_edit_$sentence->example_id').show()\" id=\"sentence_jp_$sentence->example_id\" class=\"sentence_jp\">$parsed_sent_jp</span><input type=\"edit\" id=\"jp_edit_$sentence->example_id\" name=\"example_str\" size=\"60\" style=\"display:none;\" value=\"" . str_replace('"',
            '\"', $sentence->example_str) . "\" onchange=\"update_sentence_str($sentence->example_id, this.value);\" onblur=\"\$(this).hide(); \$('#sentence_jp_$sentence->example_id').show();\"></input>";

        echo "<a href=\"#\" onclick=\"$(this).hide();$('#sels_$sentence->example_id').show(); return false;\" id=\"ex_$sentence->example_id\"> [N$sentence->njlpt, R-N$sentence->njlpt_r]</a><span id=\"sels_$sentence->example_id\" style=\"display:none;\"><select id=\"njltp_sel_$sentence->example_id\" onchange=\"update_sentence_jlpt($sentence->example_id, this.value)\">";
        for ($i = 5; $i >= 0; $i--)
            echo "<option value=\"$i\"" . ($sentence->njlpt == $i ? ' selected' : '') . ">N$i</option>";
        echo "</select> R: <select id=\"njltp_r_sel_$sentence->example_id\" onchange=\"update_sentence_jlpt_r($sentence->example_id,this.value); return false;\">";
        for ($i = 5; $i >= 0; $i--)
            echo "<option value=\"$i\"" . ($sentence->njlpt_r == $i ? ' selected' : '') . ">N$i</option>";
        echo "</select></span><br/>";

        echo "<p style=\"font-size:100%; margin: 0; padding: 0;\" id=\"sentence_en_$sentence->example_id\" onclick=\"$(this).hide(); $('#edit_english_$sentence->example_id').show();\">" . ($sentence->english ? $sentence->english : '<em>Click here to add English translation</em>') . "</p><input type=\"text\" id=\"edit_english_$sentence->example_id\" onchange=\"update_sentence_english($sentence->example_id, this.value);\" value=\"" . htmlentities($sentence->english,
            ENT_COMPAT, 'UTF-8') . "\" size=\"60\" style=\"display:none;\" onblur=\"\$(this).hide(); \$('#sentence_en_$sentence->example_id').show();\" /> ";

        echo $parts_html;

        if ($_SESSION['user']->isEditor()) {

            echo "<p>Status: " . get_select_menu(array('unknown' => 'Unknown', 'reviewed' => 'Reviewed', 'modified' => 'Modified', 'need_work' => 'Need work', 'reimport_06_2011' => 'reimport_06_2011', 'kanjibox' => 'KanjiBox'),
                'status_' . $sentence->example_id, $sentence->status,
                "update_sentence_status($sentence->example_id, this.value); return false;");
            echo '<br/><a href="#" onclick="confirm_delete_sentence(' . $sentence->example_id . '); return false;">[delete sentence]</a>';
            echo "</p>";
        }
        // if($_SESSION['user']->is_admin() && @$_REQUEST['jmdict_id']) {
        // 	// print_r($_REQUEST);
        // 	$jmdict_id = (int) $_REQUEST['jmdict_id'];
        // 	$res = mysql_query("SELECT * FROM example_answers ea WHERE example_id = $sentence->example_id AND jmdict_id = $jmdict_id");
        // 	if(mysql_num_rows($res) > 0)
        // 		echo "<p><a id=\"delete_sent_$sentence->example_id\" href=\"#\" style=\"color:red;\" onclick=\"confirm_delete_answer($sentence->example_id, $jmdict_id); return false;\">[delete question]</a></p>";
        // 	else
        // 		echo "<p style=\"color:red;font-style:italic;\">(question deleted)</p>";
        // 	
        // }
        ?>

        <script type="text/javascript">
            function confirm_delete_sentence(id)
            {
                if (confirm('Are you SURE you want to delete this sentence?')) {
                    $.get('<?php echo SERVER_URL?>ajax/edit_sentence/?delete_id=' + id + '&container_selector=<?php echo urlencode($container_selector)?>', function(data) {
                        // $('#ajax-result')
                        // $('#delete_sent_' + id).css('text-decoration', 'line-through');
                        $('<?php echo $container_selector?>').html(data);
                    });
                }
            }

            function confirm_delete_answer(id, jmdict_id)
            {
                if (confirm('Are you SURE you want to delete this question?')) {
                    $.get('<?php echo SERVER_URL?>ajax/edit_sentence/?delete_id=' + id + '&delete_jmdict_id=' + jmdict_id + '&short_reply=1', function(data) {
                        $('#ajax-result').html(data);
                        $('#delete_sent_' + id).css('text-decoration', 'line-through');
                        setTimeout(function() {
                            $('#ajax-result').html('')
                        }, 5000);
                    });
                }
            }

            function update_sentence_status(id, status)
            {
                $.get('<?php echo SERVER_URL?>ajax/edit_sentence/?id=' + id + '&short_reply=1&status=' + status, function(data) {

                    $('#status_' + id).css('border', '2px solid green')
                    $('#ajax-result').html(data);
                    setTimeout(function() {
                        $('#ajax-result').html('')
                    }, 2000);
                });
            }


            function update_sentence_jlpt(id, jlpt)
            {
                $.get('<?php echo SERVER_URL?>ajax/edit_sentence/?id=' + id + '&short_reply=1&njlpt=' + jlpt, function(data) {

                    $('#njltp_sel_' + id).css('border', '2px solid green')
                    $('#ajax-result').html(data);
                    setTimeout(function() {
                        $('#ajax-result').html('')
                    }, 2000);
                });
            }

            function update_sentence_jlpt_r(id, jlpt)
            {
                $.get('<?php echo SERVER_URL?>ajax/edit_sentence/?id=' + id + '&short_reply=1&njlpt_r=' + jlpt, function(data) {

                    $('#njltp_r_sel_' + id).css('border', '2px solid green')
                    $('#ajax-result').html(data);
                    setTimeout(function() {
                        $('#ajax-result').html('')
                    }, 2000);
                });
            }

            function update_sentence_str(id, str)
            {
                $.get('<?php echo SERVER_URL?>ajax/edit_sentence/?id=' + id + '&short_reply=1&example_str=' + encodeURIComponent(str), function(data) {

                    $('#edit_example_str_' + id).css('border', '2px solid green');
                    $('#ajax-result').html(data);
                    setTimeout(function() {
                        $('#ajax-result').html('')
                    }, 2000);
                });
            }

            function update_sentence_english(id, en)
            {
                $.get('<?php echo SERVER_URL?>ajax/edit_sentence/?id=' + id + '&short_reply=1&english=' + encodeURIComponent(en), function(data) {

                    $('#edit_english_' + id).css('border', '2px solid green')
                    $('#ajax-result').html(data);
                    setTimeout(function() {
                        $('#ajax-result').html('')
                    }, 2000);
                });
            }

            function update_sentence_part(id, pos_start, jmdict_id) {
                $('#edit_english_' + id).css('border', '2px solid green')
                $('#part_' + id + '_' + pos_start).css('color', 'red').css('text-decoration', 'line-through');
                $('#alt_part_' + id + '_' + pos_start + '_' + jmdict_id).css('color', 'green');
                $.get('<?php echo SERVER_URL?>ajax/edit_sentence/?id=' + id + '&pos_start=' + pos_start + '&new_jmdict_id=' + jmdict_id + '&container_selector=<?php echo urlencode($container_selector)?>', function(data) {
                    $('<?php echo $container_selector?>').html(data);
                });
            }

            function add_alt_sentence_part(part, example_id, pos_start) {
                $.getJSON('<?php echo SERVER_URL?>ajax/get_jmdict/?exact_match=1&word=' + part + '&return_json=1', function(data) {
                    for (var i = 0; i < data.length; i++) {
                        console.log(data[i])

                        var str = "<li id=\"alt_part_" + example_id + "_" + pos_start + "_" + data[i].id + "\" style=\"display:block;\"><a href=\"#\" onclick=\"update_sentence_part(" + example_id + ", " + pos_start + ", " + data[i].id + "); return false;\">Replace by:</a> " + data[i].word + " 【" + data[i].reading + "】[N" + data[i].njlpt + ", R-N" + data[i].njlpt_r + "]: " + data[i].gloss_english + "</li>";
                        $(str).insertBefore('#alt_part_free_text_' + example_id + "_" + pos_start);
                    }
                });
            }

            function extend_sentence_part(id, pos_end, merge_until_pos_end, selector) {
                $(selector).parent().css('text-decoration', 'border');

                $.get('<?php echo SERVER_URL?>ajax/edit_sentence/?id=' + id + '&pos_end=' + pos_end + '&merge_until_pos_end=' + merge_until_pos_end + '&container_selector=<?php echo urlencode($container_selector)?>', function(data) {
                    $('<?php echo $container_selector?>').html(data);
                });
            }

            function change_sentence_part(id, pos_start, pos_end, new_pos_start, new_pos_end, selector) {
                $(selector).parent().css('text-decoration', 'border');

                $.get('<?php echo SERVER_URL?>ajax/edit_sentence/?id=' + id + '&pos_start=' + pos_start + '&pos_end=' + pos_end + '&new_pos_start=' + new_pos_start + '&new_pos_end=' + new_pos_end + '&container_selector=<?php echo urlencode($container_selector)?>', function(data) {
                    $('<?php echo $container_selector?>').html(data);
                });
            }

            function split_sentence_part(id, pos_start, split_at_pos, selector) {
                $(selector).parent().css('border', '1px solid blue');

                $.get('<?php echo SERVER_URL?>ajax/edit_sentence/?id=' + id + '&pos_start=' + pos_start + '&split_at_pos=' + split_at_pos + '&container_selector=<?php echo urlencode($container_selector)?>', function(data) {
                    $('<?php echo $container_selector?>').html(data);
                });
            }

            function delete_sentence_part(id, pos_start, delete_jmdict_id, selector) {
                $(selector).parent().css('text-decoration', 'line-through');

                $.get('<?php echo SERVER_URL?>ajax/edit_sentence/?id=' + id + '&pos_start=' + pos_start + '&delete_jmdict_id=' + delete_jmdict_id + '&container_selector=<?php echo urlencode($container_selector)?>', function(data) {
                    $('<?php echo $container_selector?>').html(data);
                });
            }
        </script>
        </div>

        <?php
    } else {
        if ($has_search_string) {
            $url = SERVER_URL . "ajax/get_sentence/?" . $url_args . "&skip=" . (int) @$_REQUEST['skip'];

            echo '<a href="#" onclick="$(\'' . $container_selector . '\').load(\'' . $url . '\'); return false;">[back to list of sentences]</a>';
        }

        echo "<div class=\"db-group selected-item\">";
        echo "<p class=\"db-info-jp\"><strong>$sentence->example_str</strong></p>";
        echo "<p class=\"db-info\"><em>$sentence->english</em></p>";
        echo "<p class=\"db-info-small\">JLPT: <strong>N" . ($sentence->njlpt) . "</strong> | JLPT (reading): <strong>" . ($sentence->njlpt_r) . "</strong> | Status: <strong>$sentence->status</strong>" . ($sentence->has_prime_example ? ' | ☆' : '') . "</p>";
        echo "<p class=\"editor-choices\"><a href=\"#\" onclick=\"$('$container_selector').load('" . SERVER_URL . "ajax/get_sentence/edit/yes/?id=$sentence->example_id&$url_args&display_done_editing=1'); return false;\">[edit sentence]</a> <a href=\"#\" onclick=\"load_new_question($sentence->example_id); return false;\">[create new question]</a></p>";

        $questions_res = mysql_query('SELECT * FROM grammar_questions sq LEFT JOIN jmdict j ON j.id = sq.jmdict_id WHERE sq.sentence_id = ' . $sentence->example_id) or die(mysql_error());

        echo '<ul class="sentences">';
        while ($question = mysql_fetch_object($questions_res)) {
            echo "<li><i>" . mb_substr($sentence->example_str, 0, $question->pos_start, "utf-8") . '<span class="small-highlighted">' . mb_substr($sentence->example_str,
                $question->pos_start, $question->pos_end - $question->pos_start, "utf-8") . '</span>' . mb_substr($sentence->example_str,
                $question->pos_end, 1000, "utf-8") . "</i> (N" . $question->njlpt . ") [<a href=\"#\" onclick=\"load_question(" . $question->question_id . "); return false;\">edit</a>]</li>";
        }

        echo "</ul></div>";
    }
} else {
    while ($sentence = mysql_fetch_object($res)) {

        echo "<div class=\"db-group ajax-clickable\" onclick=\"$('$container_selector').load('" . SERVER_URL . "ajax/get_sentence/?id=$sentence->example_id&skip=$skip&$url_args'); return false\">";
        echo "<p class=\"db-info-jp\"><strong>$sentence->example_str</strong></p>";
        echo "<p class=\"db-info\"><em>$sentence->english</em></p>";
        echo "<p class=\"db-info-small\">JLPT: <strong>N" . ($sentence->njlpt) . "</strong> | JLPT (reading): <strong>" . ($sentence->njlpt_r) . "</strong> | Status: <strong>$sentence->status</strong>" . ($sentence->has_prime_example ? ' | ☆' : '') . "</p>";
        echo "</div>";
    }
}
?>