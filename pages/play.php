<?php
if (empty($_SESSION['user']) || $_SESSION['user']->getID() <= 0) {
    force_reload("You cannot access this page directly.", get_page_url('home'),
        "You need to be logged in, in order to use this feature.");
    die();
}

include_js('drill_quiz.js');

if ($_SESSION['user']->isElite() && $_SESSION['user']->getPreference('general', 'shortcuts')) {
    include_jquery('hotkeys');
    include_jquery('pulse');
    include_js('shortcuts.js');
}

//include_class('Session');
if (!empty($params['save_first_game'])) {
    if ($_SESSION['cur_session']) {
        if ($_SESSION['cur_session']->saveScore()) {
            display_user_msg('Highscore successfully saved!', MSG_SUCCESS);
        }
    } else {
        display_user_msg('No current game session to save.', MSG_ERROR);
    }
    return;
}

$game_types = ['kanji' => 'Kanji', 'vocab' => 'Vocab', 'reading' => 'Reading', 'kana' => 'Kana', 'text' => 'Text'];

if (!$game_types[$params['type']]) {
    log_error('Unknown game type: ' . $params['type'] . ' (params: ' . print_r($params, true) . ')', false, true);
}
$type = $params['type'];
$mode = (!empty($params['mode']) ? $params['mode'] : DRILL_MODE);

if (($type == 'text') && !$_SESSION['user']->isElite()) {
    echo '<div class="page_msg"><a href="http://www.facebook.com/photo.php?pid=3300719&id=5132078849"><img style="margin: 10px 20px 30px 0px; float: left; width: 350px; border: 1px solid black;" src="http://photos-c.ak.fbcdn.net/hphotos-ak-snc3/hs143.snc3/17080_281459173849_5132078849_3300719_3581359_n.jpg"></img></a> Due to its heavy CPU cost, <a href="http://www.facebook.com/photo.php?pid=3300719&id=5132078849">Text Mode</a> drilling is only available to <a href="/page/faq/#elite">エリート</a> users...<br/><br style="clear: both;"/></div>';
    return;
}

if (!empty($params['level'])) {
    $level = $params['level'];
} elseif ($_SESSION['user']->getID()) {
    $level = $_SESSION['user']->getLevel();
} else {
    $level = LEVEL_N4;
}

if (substr($level, -4) == 'kyuu') {
    $level = old_to_new_jlpt($level);
}

if (isset($_REQUEST['grade'])) {
    $grade = $_REQUEST['grade'];
}

if (!empty($_SESSION['cur_session']) && ($_SESSION['cur_session']->getMode() != $mode || $_SESSION['cur_session']->getType() != $type || $_SESSION['cur_session']->getLevel() != $level || ($_SESSION['cur_session']->isDrill() && isset($grade) && $_SESSION['cur_session']->getCurGrade() != $grade) || $_SESSION['cur_session']->gameOver || $mode != $_SESSION['cur_session']->getMode() || ($_SESSION['cur_session']->getMode() == SETS_MODE && $params['set_id'] && $_SESSION['cur_session']->getSetID() != $params['set_id']) || ($_SESSION['cur_session']->getMode() == GRAMMAR_SETS_MODE && $params['set_id'] && $_SESSION['cur_session']->getSetID() != $params['set_id'])
    )) {
    $_SESSION['cur_session']->cleanupBeforeDestroy();
    $_SESSION['cur_session'] = null;
}


if ($game_types[$type] != KANA) {
    ?>
    <div class="subtabs<?php echo ($type == TYPE_TEXT) ? ' four-tabs' : '';?>">
        <a href="<?php echo get_page_url(PAGE_PLAY, ['type' => $type, 'mode' => DRILL_MODE, 'level' => $level])?>" class="<?php echo (DRILL_MODE == $mode ? "selected" : '')?>">Drill</a>
        <a href="<?php echo get_page_url(PAGE_PLAY, ['type' => $type, 'mode' => QUIZ_MODE, 'level' => $level])?>" class="<?php echo (QUIZ_MODE == $mode ? "selected" : '')?>">Quiz</a>
        <a href="<?php echo get_page_url(PAGE_PLAY, ['type' => $type, 'mode' => SETS_MODE])?>" class="<?php echo (SETS_MODE == $mode ? "selected" : '')?>"><?php echo ($type == TYPE_TEXT ? 'Vocab ' : '')?>Sets</a>
        <?php
        if ($type == TYPE_TEXT) {
            ?>
            <a href="<?php echo get_page_url(PAGE_PLAY, ['type' => $type, 'mode' => GRAMMAR_SETS_MODE])?>" class="<?php echo (GRAMMAR_SETS_MODE == $mode ? "selected" : '')?>">Grammar Sets</a>
            <?php
        }
        ?>	<div style="clear:both;"></div>
    </div>
    <?php
}

if ($mode == GRAMMAR_SETS_MODE) {

    $query = 'SELECT set_id, name, description FROM grammar_sets WHERE public = 1 ORDER BY njlpt_from DESC';
    try {
        $stmt = DB::getConnection()->prepare($query);
        $stmt->execute();

        $selectedSet = $params['set_id'];
        while ($row = $stmt->fetchObject()) {
            $array[$row->set_id] = $row->name . ' (demo)';
            if (!$selectedSet) {
                $selectedSet = $row->set_id;
            }
        }

        $stmt = null;
    } catch (PDOException $e) {
        log_db_error($query, $e->getMessage(), false, true);
    }

    $currentSetMenu = 'Current grammar set: ' . get_select_menu($array, '', $selectedSet,
            'if(this.value) location.href=\'' . get_page_url(PAGE_PLAY, ['type' => $type, 'mode' => GRAMMAR_SETS_MODE]) . "set_id/' + this.value + '/'");

    echo '<div id="sets_settings">' . $currentSetMenu . '</div>';
} elseif ($mode == SETS_MODE) {
    $set_type = ($type == TYPE_KANJI ? TYPE_KANJI : TYPE_VOCAB);
    ?>
    <div id="sets_settings">
        <?php
        if (!empty($_POST['new_set_name'])) {
            $selectedSet = LearningSet::createNew($_POST['new_set_name'], $set_type, isset($_POST['public']),
                    isset($_POST['editable']));
            if (!$selectedSet) {
                die('<div class="error_msg">Error creating set: please contact KB\'s admin if the problem persists.</div>');
            }
        } else {
            $selectedSet = (isset($params['set_id']) ? $params['set_id'] : null);
        }

        if (!empty($_POST['unsubscribe_set_id'])) {
            $res = DB::delete('DELETE FROM learning_set_subs WHERE user_id = :userid AND set_id = :set_id',
                    [':userid' => $_SESSION['user']->getID(), ':set_id' => $_POST['unsubscribe_set_id']]);
            if ($res) {
                echo '<div class="success_msg">Unsubscribed from set.</div>';
            }
        }

        if (!empty($_POST['delete_set_id'])) {
            $set = new LearningSet($_REQUEST['delete_set_id']);

            if (!$set->isValid()) {
                die('<div class="error_msg">Error deleting set: please contact KB\'s admin if the problem persists.</div>');
            }

            if ($err = $set->deleteSet()) {
                echo '<div class="error_msg">' . $err . '</div>';
            } else {
                echo '<div class="success_msg">Deleted set.</div>';
            }
        } elseif (!empty($_POST['public_domain_set_id'])) {
            $set = new LearningSet($_REQUEST['public_domain_set_id']);

            if (!$set->isValid()) {
                die('<div class="error_msg">Error updating set: please contact KB\'s admin if the problem persists.</div>');
            }

            $selectedSet = $set->id;

            if ($err = $set->makePublicDomain()) {
                echo '<div class="error_msg">' . $err . '</div>';
            } else {
                echo '<div class="success_msg">Set moved to public domain.</div>';
            }
        }

        $query = 'SELECT ls.*, subs.set_id AS sub, (SELECT COUNT(*) FROM learning_set_' . ($set_type == TYPE_KANJI ? TYPE_KANJI : TYPE_VOCAB) . ' WHERE set_id = ls.set_id) AS size FROM learning_sets ls LEFT JOIN learning_set_subs subs ON subs.set_id = ls.set_id AND subs.user_id = :userid WHERE ls.deleted = 0 AND (ls.user_id = :userid OR subs.set_id IS NOT NULL) AND set_type = :settype ORDER BY date_modified';
        try {
            $stmt = DB::getConnection()->prepare($query);
            $stmt->bindValue(':userid', $_SESSION['user']->getID(), PDO::PARAM_INT);
            $stmt->bindValue(':settype', $set_type, PDO::PARAM_STR);
            $stmt->execute();

            $array = [];
            if ($stmt->rowCount() > 0) {

                while ($row = $stmt->fetchObject()) {
                    if (!empty($params['editor']) && $params['editor'] != 'open' && $row->size < 4) {
                        if ($selectedSet == $row->set_id) {
                            if ($keys = array_keys($array)) {
                                $selectedSet = $keys[0];
                            } else {
                                $selectedSet = 0;
                            }
                            echo '<div class="error_msg">The selected set does not contain enough entries to be usable in drill mode.</div>';
                        }
                    } elseif (!$selectedSet) {
                        $selectedSet = $row->set_id;
                    }
                    $array[$row->set_id] = ($row->sub ? '' : '• ') . $row->name . " ($row->size)";
                }

                $currentSetMenu = 'Current learning set: ' . get_select_menu($array, '', $selectedSet,
                        'if(this.value) location.href=\'' . get_page_url(PAGE_PLAY,
                            ['type' => $type, 'mode' => SETS_MODE]) . "set_id/' + this.value + '/'");
            } else {
                $currentSetMenu = '';
            }
        } catch (PDOException $e) {
            log_db_error($query, $e->getMessage(), false, true);
        }

        if (!count($array)) {
            echo '<em>No sets available</em> ';
        }

        if ((!empty($params['editor']) && $params['editor'] != 'open') && !$params['view_set_id']) {
            $currentSetMenu .= '<span id="nosets-msg"> | <a href="#" onclick="$(\'#sets_editor\').show(\'slide\'); $(\'#nosets-msg\').hide(); return false;">create or edit sets &raquo;</a></span>';
        }
        ?>
        <div id="sets_editor" style="<?php if ($params['editor'] != 'open' && !$params['view_set_id']) echo 'display:none;'?>">
            <button onclick="location.href = '<?php
            echo get_page_url(PAGE_PLAY, ['type' => $type, 'mode' => SETS_MODE])
            ?>'">Resume Drill</button>
            <br/>

            <fieldset class="closed"><legend onclick="$(this).parent().toggleClass('closed', 200);">Create new set</legend>
                <form action="<?php
                echo get_page_url(PAGE_PLAY, ['mode' => SETS_MODE, 'type' => $set_type, 'editor' => 'open']);
                ?>" method="post">
                    Name: <input type="text" size="40" name="new_set_name"/><input type="submit" name="Create" value="create"/>
                    <p style="font-style:italic;font-size:80%;"><strong>Tip:</strong> Before creating a new set for a common textbook or method, you should first check if it is not already available (use the search tool below).</p>
                </form>
            </fieldset>

            <fieldset class="closed"><legend onclick="$(this).parent().toggleClass('closed', 200);">Search public sets</legend>
                <form action="" method="post" onsubmit="$('#subscribe_results').html('Searching...');
                            $.post('<?php echo SERVER_URL?>ajax/search_learning_sets/', $(this).serialize(), function(data, textStatus, jqXHR) {
                                $('#subscribe_results').html(data);
                            });
                            return false;">
                    <p style="margin-bottom:5px;"><a href="/sets/">Use Set Browser &raquo;</a></p>
                    <p style="margin-bottom:10px;"><?php
                        echo LearningSet::getAllTagCheckboxes();
                        ?></p><div style="clear:both;"></div>
                    <input type="hidden" name="filter_set_type" value="<?php echo $set_type?>"/>
                    <input type="text" size="40" name="filter_str"/> <input type="submit" name="Search" value="search"/>
                </form>
                <div id="subscribe_results"></div>
            </fieldset>

            <fieldset><legend>Edit set <?php
                    echo get_select_menu($array, 'edit_set_select', 0,
                        "if(this.value > 0) do_load('" . SERVER_URL . "ajax/edit_learning_set/?set_id=' + this.value, 'set_details'); return false;",
                        'Select a set...');
                    ?></legend>
                <div id="set_details"></div><?php
                if ($selectedSet && !empty($params['editor']) && $params['editor'] == 'open') {
                    ?><script type="text/javascript">
                        $(document).ready(function()
                        {
                            $('#edit_set_select').val(<?php echo $selectedSet?>).change();
                        });
                    </script><?php
                } elseif (!empty($params['view_set_id']) && $params['view_set_id'] > 0) {
                    ?><script type="text/javascript">
                        $(document).ready(function()
                        {
                            do_load('<?php echo SERVER_URL . "ajax/edit_learning_set/?set_id=" . (int) $params['view_set_id'];?>', 'set_details');
                        });
                    </script><?php
                }
                ?></fieldset>

            <?php
            if ($_SESSION['user']->isAdministrator()) {
                ?>	<fieldset><legend>Recent Sets</legend>
                    <?php
                    $query = 'SELECT ls.*, COUNT(*) AS size FROM learning_sets ls LEFT JOIN learning_set_' . $set_type . ' lse ON lse.set_id = ls.set_id WHERE ls.deleted = 0 AND ls.set_type = :set_type GROUP BY ls.set_id ORDER BY date_modified DESC LIMIT 10';
                    try {
                        $stmt = DB::getConnection()->prepare($query);
                        $stmt->bindValue(':set_type', $set_type);
                        $stmt->execute();
                        while ($row = $stmt->fetchObject()) {
                            $rows_subs = DB::count('SELECT COUNT(*) FROM learning_set_subs WHERE set_id = :setid',
                                    [':setid' => $row->set_id]);
                            echo '<div class="set_line"><button onclick="do_load(\'' . SERVER_URL . 'ajax/edit_learning_set/?set_id=' . $rows_subs . '\', \'set_details\'); return false;">edit</button><span class="name">' . $row->name . '</span> <span class="size">' . $row->size . '</span>' . ($row->public ? '<span class="prop">public</span>' : '') . ($row->editable ? '<span class="prop">editable</span>' : '') . ($rows_subs ? ' <span class="set_prop">' . $row_subs . ' subscriber' . ($row_subs > 1 ? 's' : '') . '</span>' : '') . '</div>';
                        }
                    } catch (PDOException $e) {
                        log_db_error($query, $e->getMessage(), false, true);
                    }
                    ?>
                </fieldset>
                <?php
            }
            ?></div>
        <?php
        if ((!empty($params['editor']) && $params['editor'] == 'open') || (!empty($params['view_set_id']) && $params['view_set_id'])) {
            echo '</div>';
            return;
        } else {
            echo $currentSetMenu . '</div>';
        }
    }

    if (!$_SESSION['cur_session']) {
        if ($mode == DRILL_MODE && isset($grade)) {
            $_SESSION['cur_session'] = new Session($type, $level, DRILL_MODE, $grade);
        } elseif ($mode == SETS_MODE) {
            if ($selectedSet) {
                $_SESSION['cur_session'] = new Session($type, $level, $mode, $selectedSet);
            } else {
                return;
            }
        } elseif ($mode == GRAMMAR_SETS_MODE) {
            if ($selectedSet) {
                $_SESSION['cur_session'] = new Session($type, $level, $mode, $selectedSet);
            } else {
                return;
            }
        } else {
            $_SESSION['cur_session'] = new Session($type, $level, $mode);
        }
    }
    ?>
    <div id="session_frame">
        <?php
        $_SESSION['cur_session']->displayWave();
        ?>
    </div>

    <div id="modal_dialog" title="Kanji Details" style="display:none;" lang="ja" xml:lang="ja">
        <div id="modal_dialog_content">
            <div style="text-align:center;"><img alt="load icon" src="<?php echo SERVER_URL?>img/ajax-loader.gif"/></div>
        </div>
    </div>

    <div id="translate_dialog" lang="ja" xml:lang="ja">
        <div id="translate_content"></div>
    </div>


    <?php
    if (!$_SESSION['cur_session']->isQuiz()) {
        ?>
        <hr/><div id="footer">
            <?php
            if ($_SESSION['cur_session']->isDrill()) {

                $options = $_SESSION['cur_session']->getGradeOptions();

                if ($options) {
                    $params = $_SESSION['cur_session']->getParams();
                    ?>
                    <div id="control-options">
                        Level:
                        <select id="options" name="options" onchange="if (this.value != '')
                                                window.location = this.value;">
                                <?php
                                foreach ($options as $array) {
                                    echo '<option value="' . get_page_url(PAGE_PLAY, $params,
                                        '?grade=' . $array['grade']) . '"' . ($array['selected'] ? ' selected="selected"' : '') . '>' . $array['label'] . '</option>';
                                }
                                ?>
                        </select>
                    </div>
                    <?php
                }
                echo '<div id="footer-stats">';

                if ($_SESSION['user']->isLoggedIn()) {
                    if ($_SESSION['user']->getPreference('drill', 'show_learning_stats')) {
                        ?>
                        <script type="text/javascript">

                            $(document).ready(function()
                            {
                                do_load('<?php echo SERVER_URL . 'ajax/footer_stats_bar/type/' . $_SESSION['cur_session']->getType();?>/', 'footer-stats');

                            });
                        </script>
                        <?php
                    }
                } else {
                    echo "<div class=\"pleaselog\">You need <a href=\"" . get_page_url(PAGE_PLAY, $params) . "\" requirelogin=\"1\">to login</a> in order to view your live stats.</div>";
                }
            } elseif ($_SESSION['cur_session']->isLearningSet()) {
                echo '<div id="footer-stats">';
                ?>
                <script type="text/javascript">

                    $(document).ready(function()
                    {
                        do_load('<?php echo SERVER_URL . 'ajax/footer_stats_bar/type/' . $_SESSION['cur_session']->getType() . '/mode/' . SETS_MODE . '/';?>', 'footer-stats');

                    });
                </script>
                <?php
            } elseif ($_SESSION['cur_session']->isGrammarSet()) {
                echo '<div id="footer-stats">';
                ?>
                <script type="text/javascript">

                    $(document).ready(function()
                    {
                        // do_load('<?php echo SERVER_URL . 'ajax/footer_stats_bar/type/' . $_SESSION['cur_session']->getType() . '/mode/' . GRAMMAR_SETS_MODE . '/';?>', 'footer-stats');

                    });
                </script>
                <?php
            }

            echo '</div><div style="clear:both"></div></div>';
        }
        ?>
        <script type="text/javascript">
            $(document).ready(function()
            {
                soundManager.setup({
                    url: '/js/swf/soundmanager2.swf',
                    flashVersion: 8, // optional: shiny features (default = 8)
                    // optional: ignore Flash where possible, use 100% HTML5 mode
                    preferFlash: false,
                });
            })
        </script>
