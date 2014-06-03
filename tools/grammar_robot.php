<?php
if (empty($_SESSION['user']) || !$_SESSION['user']->isEditor()) {
    die('Only editors');
}

mb_internal_encoding('UTF-8');

try {
    $stmt = DB::getConnection()->prepare('SELECT gs.set_id, gs.name FROM grammar_sets gs');
    $stmt->execute();

    $grammarSets = [-1 => '[none]'];
    while ($row = $stmt->fetchObject()) {
        $grammarSets[$row->id] = $row->name;
    }
    $stmt = null;
} catch (PDOException $e) {
    log_db_error($query, $e->getMessage(), false, true);
}

if (empty($_REQUEST['set_id'])) {
    $setID = (int) $params['set_id'];
} else {
    $setID = (int) $_REQUEST['set_id'];
}

echo 'Select set: ';
display_select_menu($grammarSets, 'set_id', $setID,
    "window.location.href = '/kb/tools/grammar_robot/set_id/' + this.value + '/';", '-');
echo '<br/>';

if (!empty($setID)) {
    $answers = [];
    ?>
    <form id="make-wrong-answers-form" action="/kb/ajax/grammar_robot_step_2/" method="post" onsubmit="if ($('.choice > input:checked').length >= 4) {
                    return true;
                } else {
                    alert('Please first at least 4 answers in this set.');
                    return false;
                }
                ;">
        <div style="margin-top:20px;">
            <?php
            try {
                $stmt = DB::getConnection()->prepare('SELECT j.*, jg.gloss_english as gloss, COUNT(*) AS c FROM grammar_questions sq JOIN jmdict j ON j.id = sq.jmdict_id JOIN jmdict_ext jg ON jg.jmdict_id = j.id LEFT JOIN users_ext ux ON ux.user_id = sq.user_id WHERE sq.set_id = :setid GROUP BY j.id ORDER BY c ASC, j.word ASC');
                $stmt->execute([':setid' => $setID]);
                while ($answer = $stmt->fetchObject()) {
                    $answers[$answer->id] = ['correct' => $answer->c, 'wrong' => 0, 'jmdict' => $answer];
                    $word = ($answer->usually_kana || $answer->word == $answer->reading ? $answer->reading : "$answer->word 【" . $answer->reading . "】");
                    echo "<label class=\"choice\"><input type=\"checkbox\" name=\"answer_ids[$answer->id]\" id=\"answer_ids[$answer->id]\" onchange=\"handleCheckboxChange(this, $answer->c);\"></input> $word</label> ";
                }
                $stmt = null;
            } catch (PDOException $e) {
                log_db_error($query, $e->getMessage(), false, true);
            }
            ?>
        </div><p style="clear:both;"><a href="#" onclick="$('.choice > input:not(:checked)').prop('checked', true).change();
                    return false;">[select all]</a>&nbsp;&nbsp;&nbsp;<a href="#" onclick="$('.choice > input:checked').prop('checked', false).change();
                            return false;">[deselect all]</a></p>

        <p><strong>Total Answers:</strong> <input type="text" id="answers_tot" disabled="disabled" value="0" size="3"/> | <strong>Total Questions:</strong> <input type="text" id="questions_tot" disabled="disabled" value="0" size="3"/></p>
        <input type="hidden" name="set_id" id="set_id" value="<?php echo $setID;?>"/>
        <p style="text-align:center;"><input type="submit" name="make-wrong-answers" value="Suggest Wrong Answers"/></p>
    </form>
    <div id="ajax-result"></div>
    <div id="ajax-wrong-answers"></div>

    <?php
}
?>
<script>

    $(document).ready(function() {
        $('form#make-wrong-answers-form').ajaxForm({
            target: '#ajax-wrong-answers',
            beforeSubmit: function() {
                $('#ajax-wrong-answers').html('Loading...')
            },
        });
    })

    function handleCheckboxChange(box, val) {
        if (box.checked) {
            $(box).parent().css('background-color', '#AAF');
            var tot = parseInt($('#questions_tot').val(), 10) + val;
            $('#questions_tot').val(tot);
            $('#answers_tot').val(parseInt($('#answers_tot').val(), 10) + 1);
        }
        else {
            $(box).parent().css('background-color', '');
            var tot = parseInt($('#questions_tot').val(), 10) - val;
            $('#questions_tot').val(tot);
            $('#answers_tot').val(parseInt($('#answers_tot').val(), 10) - 1);

        }
    }
</script>
