<?php
force_logged_in_app();

require_once ABS_PATH . 'libs/stats_lib.php';
include_css('stats.css');
include_js('ajax.js');

$levels = Session::$level_names;
?>
<div class="subtabs">
    <?php
    if (!empty($params['type'])) {
        $cur_type = $params['type'];
    } else {
        $cur_type = 'main';
    }

    $subtabs = ['main' => 'Summary', TYPE_KANA => 'Kana stats', TYPE_KANJI => 'Kanji stats', TYPE_VOCAB => 'Vocab stats', TYPE_READING => 'Reading stats'];
    if (!$subtabs[$cur_type]) {
        log_error('Unknown stats type: ' . $cur_type, true, true);
    }

    $width = (int) (800 / count($subtabs)) - 8;
    foreach ($subtabs as $type => $label) {
        echo '<a href="' . get_page_url(PAGE_STATS, array('type' => $type)) . '" class="' . ($cur_type == $type ? "selected" : '') . '" onclick="do_load(\'' . SERVER_URL . 'ajax/stats/type/' . $type . '\', \'frame-stats\'); $(this).addClass(\'loading\');$(this).css(\'backgroundImage\', \'url(' . SERVER_URL . 'img/small-ajax-loader.gif)\'); return false;" style="width: ' . $width . 'px">' . $label . '</a>';
    }
    ?>
</div>
<div style="clear:both;" ></div>
<?php
$kanjiLearningCount = DB::count('SELECT COUNT(*) FROM learning l WHERE l.user_id = ? LIMIT 1', [$_SESSION['user']->getID()]);
$vocabLearningCount = DB::count('SELECT COUNT(*) FROM jmdict_learning jl WHERE jl.user_id=? LIMIT 1', [$_SESSION['user']->getID()]);
$readLearningCount = DB::count('SELECT COUNT(*) FROM reading_learning rl WHERE rl.user_id=? LIMIT 1', [$_SESSION['user']->getID()]);

$ajax_url = SERVER_URL . 'ajax/stats/type/';

require_once ABS_PATH . 'libs/stats_lib.php';
?>
<fieldset class="stats">
    <?php
    if (!empty($_POST['reset-stats'])) {
        $sql = 'DELETE FROM ';
        switch ($_POST['reset-stats']) {
            case 'kanji':
                $sql .= 'learning';
                break;
            case 'vocab':
                $sql .= 'jmdict_learning';
                break;
            case 'reading':
                $sql .= 'reading_learning';
                break;
            case 'kana':
                $sql .= 'kana_learning';
                break;
            default:
                die('Unknown reset category');
                break;
        }

        $sql .= ' WHERE user_id = ' . (int) $_SESSION['user']->getID();

        if (!mysql_query($sql))
            echo '<div class="error_msg">SQL error: ' . mysql_error() . '</div>';
        else
            echo '<div class="success_msg">All ' . ($_POST['reset-stats'] == 'main' ? 'your' : $_POST['reset-stats']) . ' stats have been reset</div>';
    }

    switch ($cur_type) {
        case 'kanji':
            ?>
            <legend>Kanjis</legend>
            [<a href="<?php echo get_page_url(PAGE_PLAY, array('type' => 'kanji')); ?>">Play</a>]
            <?php
            if ($kanjiLearningCount == 0) {
                echo "<div class=\"mynotice\">You do not have any learning statistics for kanji yet. You need to <a href=\"" . get_page_url(PAGE_PLAY, array('type' => 'kanji')) . "\">practice</a> a little bit before the charts get updated.</div>";
            }
            ?>
            <table class="resultbox" >
                <tr><td class="kyuu">
                        <?php echo printJLPTLevels($_SESSION['user']->getID(), 5); ?>
                    </td>
                    <td>
                        <?php
                        echo printGradeLevels($_SESSION['user']->getID(), 1);
                        ?>
                    </td>
                </tr>
                <tr><td class="kyuu">
                        <?php echo printJLPTLevels($_SESSION['user']->getID(), 4); ?>
                    </td>
                    <td>
                        <?php
                        echo printGradeLevels($_SESSION['user']->getID(), 2);
                        ?>
                    </td>
                </tr>
                <tr><td class="kyuu">
                        <?php echo printJLPTLevels($_SESSION['user']->getID(), 3); ?>
                    </td>
                    <td>
                        <?php
                        echo printGradeLevels($_SESSION['user']->getID(), 3);
                        echo printGradeLevels($_SESSION['user']->getID(), 4);
                        ?>
                    </td>
                </tr>
                <tr><td class="kyuu">
                        <?php echo printJLPTLevels($_SESSION['user']->getID(), 2); ?>
                    </td>
                    <td>
                        <?php
                        echo printGradeLevels($_SESSION['user']->getID(), 5);
                        echo printGradeLevels($_SESSION['user']->getID(), 6);
                        ?>
                    </td>
                </tr>
                <tr><td class="kyuu">
                        <?php echo printJLPTLevels($_SESSION['user']->getID(), 1); ?>
                    </td>
                    <td>
                        <?php
                        echo printGradeLevels($_SESSION['user']->getID(), 7);
                        echo printGradeLevels($_SESSION['user']->getID(), 8);
                        echo printGradeLevels($_SESSION['user']->getID(), 9);
                        ?>
                    </td>
                </tr>
            </table>
            <?php
            break;

        case 'vocab':
            echo '<legend>Vocabulary</legend>';
            if ($vocabLearningCount == 0) {
                echo "<div class=\"mynotice\">You do not have any learning statistics for vocabulary yet. You need to <a href=\"" . get_page_url(PAGE_PLAY, array('type' => 'vocab')) . "\">practice</a> a little bit before the charts get updated.</div>";
            }
            echo print_vocab_jlpt_levels($_SESSION['user']->getID(), 5);
            echo print_vocab_jlpt_levels($_SESSION['user']->getID(), 4);
            echo print_vocab_jlpt_levels($_SESSION['user']->getID(), 3);
            echo print_vocab_jlpt_levels($_SESSION['user']->getID(), 2);
            echo print_vocab_jlpt_levels($_SESSION['user']->getID(), 1);
            break;

        case 'reading':
            echo '<legend>Reading</legend>';

            if ($readLearningCount == 0) {
                echo "<div class=\"mynotice\">You do not have any reading statistics for vocabulary yet. You need to <a href=\"" . get_page_url(PAGE_PLAY, array('type' => 'reading')) . "\">practice</a> a little bit before the charts get updated.</div>";
            }

            echo printReadingJLPTLevels($_SESSION['user']->getID(), 5);
            echo printReadingJLPTLevels($_SESSION['user']->getID(), 4);
            echo printReadingJLPTLevels($_SESSION['user']->getID(), 3);
            echo printReadingJLPTLevels($_SESSION['user']->getID(), 2);
            echo printReadingJLPTLevels($_SESSION['user']->getID(), 1);
            break;

        case 'kana':
            echo '<legend>Kana</legend>';
            echo printKanaLevels($_SESSION['user']->getID());
            break;

        case 'main':
            if ($kanjiLearningCount == 0) {
                echo "<div class=\"mynotice\">You do not have any learning statistics for kanjis yet. You need to <a href=\"" . get_page_url(PAGE_PLAY, array('type' => 'kanji')) . "\">practice</a> a little bit before the charts get updated.</div>";
            }
            if ($vocabLearningCount == 0) {
                echo "<div class=\"mynotice\">You do not have any learning statistics for vocabulary yet. You need to <a href=\"" . get_page_url(PAGE_PLAY, array('type' => 'vocab')) . "\">practice</a> a little bit before the charts get updated.</div>";
            }
            if ($readLearningCount == 0) {
                echo "<div class=\"mynotice\">You do not have any reading statistics for vocabulary yet. You need to <a href=\"" . get_page_url(PAGE_PLAY, array('type' => 'reading')) . "\">practice</a> a little bit before the charts get updated.</div>";
            }

            $level = $_SESSION['user']->getLevel();
            $jlpt_level = $_SESSION['user']->getNJLPTLevel();
            echo '<legend>' . $levels[$level] . ($level != $jlpt_level ? '/' . $levels[$jlpt_level] : '') . '</legend>';

            if ($jlpt_level == LEVEL_J4 || $jlpt_level == LEVEL_N5) {
                echo 'here';
                echo printKanaLevels($_SESSION['user']->getID(), 710, 'Kana');
            }
            if ($level == $jlpt_level) {
                $num = Question::levelToGrade($level);
                $num = $num[1];
                echo printJLPTLevels($_SESSION['user']->getID(), $num, 710, 'Kanji');
                echo print_vocab_jlpt_levels($_SESSION['user']->getID(), $num, 710, 'Vocab');
                echo printReadingJLPTLevels($_SESSION['user']->getID(), $num, 710, 'Reading');
            } else {
                $num = (int) Question::levelToGrade($level);
                if ($num > 0) {
                    echo printGradeLevels($_SESSION['user']->getID(), $num, 710, 'Kanji - Grade ' . $num);
                } else {
                    echo printJLPTLevels($_SESSION['user']->getID(), 1, 710, 'Kanji - N1');
                }
                $num = Question::levelToGrade($jlpt_level);
                $num = $num[1];
                echo print_vocab_jlpt_levels($_SESSION['user']->getID(), $num, 710, 'Vocab - ' . $levels[$jlpt_level]);
                echo printReadingJLPTLevels($_SESSION['user']->getID(), $num, 710, 'Reading - ' . $levels[$jlpt_level]);
            }
            break;

        default:
            echo 'unknown stats type';
            break;
    }

    // For now: only allow resetting each category at a time
    if ($cur_type != 'main') {
        ?>
        <a href="#" class="reset" onclick="$(this).hide();
                    $('input.reset').show('bounce', {}, 200);
                    return false;">Reset Stats ▷</a>
        <form action="<?php get_page_url(PAGE_STATS, array('type' => $type)) ?>" method="POST">
            <input type="hidden" name="reset-stats" value="<?php echo $cur_type ?>" />
            <input type="submit" class="reset" style="display:none;" onclick="return (confirm('Are you SURE your want to erase <?php echo ($cur_type == 'main') ? 'ALL your stats' : 'all your ' . $cur_type . ' stats' ?>? This cannot be recovered.'));" value="Reset Stats"/>
        </form>
        <?php
    }
    ?>
</fieldset>