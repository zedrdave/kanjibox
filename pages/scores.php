<?php
force_logged_in_app();

require_once ABS_PATH . 'libs/stats_lib.php';
require_once ABS_PATH . 'libs/util_lib.php';
include_css('scores.css');
include_js('ajax.js');

$levels = Session::$levelNames;
?>
<script type="text/javascript">
    $(document).ready(function()
    {
        facebook_onload();
    });
</script>

<div class="subtabs">
    <?php
    if (!empty($params['type'])) {
        $curType = $params['type'];
    } else {
        $curType = 'main';
    }

    $subtabs = ['main' => 'Summary', TYPE_KANJI => 'Kanji scores', TYPE_VOCAB => 'Vocab scores', TYPE_READING => 'Reading scores', TYPE_TEXT => 'Text Scores'];
    if (!$subtabs[$curType]) {
        log_error('Unknown score type: ' . $curType, true, true);
    }

    $width = (int) (800 / count($subtabs)) - 8;
    foreach ($subtabs as $type => $label) {
        echo '<a href="' . get_page_url(PAGE_SCORES, ['type' => $type]) . '" class="' . ($curType == $type ? "selected" : '') . '" onclick="do_load(\'' . SERVER_URL . 'ajax/scores/type/' . $type . '\', \'frame-highscores\');$(this).addClass(\'loading\');$(this).css(\'backgroundImage\', \'url(' . SERVER_URL . 'img/small-ajax-loader.gif)\');return false;" style="width: ' . $width . 'px">' . $label . '</a>';
    }
    ?>
</div>

<div style="clear:both;" ></div>

<?php
if ($curType == 'main') {
    $level = $_SESSION['user']->getLevel();

    echo '<div class="summary">';
    echo '<h1>Your ' . $levels[$level] . ' Highscores</h1>';
    echo '<div id="publish_ajax_result">';

    if (!empty($params['action']) && $params['action'] == 'publish_story' && fb_connect_init() && $params['publish_type']) {
        echo $_SESSION['user']->publishStory($params['publish_type']);
    }

    echo '</div>';

    foreach ([TYPE_KANJI, TYPE_VOCAB, TYPE_READING, TYPE_TEXT] as $type) {
        ?>
        <fieldset class="line">
            <legend><?php echo ucfirst($type)?></legend>
            <?php
            $game = $_SESSION['user']->getHighscore($level, $type);
            if ($game) {
                $rank = $_SESSION['user']->getRank($type);

                echo '<table class="twocols"><tr><td><span class="label">Personal Highscore:</span><div class="value" style="text-align:left;">' . $game->score . ' Pts</div></td>';
                $rank_ratio = (($rank->tot_count - $rank->rank + 1) / $rank->tot_count);
                echo '<td><span class="label">Global KanjiBox Ranking:</span><div class="value" style="font-size:' . (int) (15 + 25 * $rank_ratio) . 'pt; color:#' . dechex(273 * round(7 - 6 * $rank_ratio)) . '">' . $rank->pretty_name . '</div></td>';
                echo '</tr></table>';

                // if($_SESSION['user']->is_admin())
                echo '<a class="fb-button" style="margin:4px; float:right;" href="#" onclick="$(\'#publish_ajax_result\').load(\'' . SERVER_URL . 'ajax/publish_story/type/' . $type . '\'); return false;">Publish on FaceBook</a>';
            } else {
                echo 'No scores yet.';
            }
            ?>

        </fieldset>
        <?php
    }
    echo '</div>';
} elseif ($fb_info = fb_connect_init()) {
    echo 'Can\'t access FB data.';
} else {
    $type_label = ucfirst($curType);
    ?>
    <fieldset class="stats">
        <legend><?php echo $type_label . ' ' . $levels[$_SESSION['user']->getLevel()];?> Level High Scores</legend>
        <table class="twocols">
            <tr>
                <td>
                    <?php
                    printGlobalboard($_SESSION['user'], $_SESSION['user']->getLevel(), $curType, 'The World:');
                    ?>
                </td>
                <td>
                    <?php
                    printFriendsboard($_SESSION['user'], $_SESSION['user']->getLevel(), $curType, 'Your Friends:',
                        false);
                    ?>
                </td>
            </tr>
        </table>
    </fieldset>

    <fieldset class="stats">
        <legend>Other Levels</legend>
        <table class="twocols">
            <tr>
                <td>
                    <?php
                    $_SESSION['user']->printHighscores($curType, "You:");
                    ?>
                </td>
                <td>
                    <?php
                    printFriendsboard($_SESSION['user'], $_SESSION['user']->getLevel(), $curType, 'Your friends:', true);
                    ?>
                </td>
            </tr>
        </table>
    </fieldset>

    <?php
}
