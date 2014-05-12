<?php
require_once 'libs/lib.php';
require_once get_mode() . '.config.php';

$logged_in = init_app();
if (!$logged_in) {
    $_SESSION['user'] = new GuestUser();
}

$pop_types = ['week' => 'this week', 'month' => 'this month', 'all' => 'all time'];

$mode = null;
$search_str = '';
global $params;

if (!empty($params['mode']) && $params['mode'] == 'search') {
    $mode = 'search';
    if (!empty($_REQUEST['filter_str'])) {
        $search_str = substr($_REQUEST['filter_str'], 0, 50);
    }
} elseif (!empty($params['mode']) && $params['mode'] == 'faq') {
    $mode = 'faq';
} elseif (!empty($params['tags'])) {
    $mode = 'tags';
    $tags = explode('|', $params['tags']);
} elseif (isset($params['popular'])) {
    $mode = 'popular';
    $pop_type = $params['popular'];
    if (empty($pop_type) || !isset($pop_types[$pop_type])) {
        $pop_type = 'week';
    }
}

$all_tags = LearningSet::getAllTags(true);

if (!empty($_REQUEST['set_id']) && is_numeric($_REQUEST['set_id'])) {
    $set_id = (int) $_REQUEST['set_id'];
    $set = new LearningSet($set_id);
    $title = 'KanjiBox Study Set &raquo; ' . $set->getName();
} else {
    $set_id = null;
    $set = null;
    $title = 'KanjiBox Japanese Study Sets';
}
?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en"  xmlns:fb="http://www.facebook.com/2008/fbml">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <title><?php echo $title;?></title>
        <link type="text/css" href="/js/jquery/themes/custom-theme/jquery-ui-1.7.1.custom.css" rel="stylesheet" media="screen" />
        <script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/1.9.0/jquery.min.js"></script>
        <script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jqueryui/1.10.0/jquery-ui.min.js"></script>
        <?php
        include_jquery('corner');
        include_jquery('form');

        include_css('general.css');
        include_css('sets.css');
        include_css('faq.css');
        ?>
        <meta property="og:title" content="<?php
        echo ($set ? 'Study Set: ' . str_replace('"', '\"', mb_substr($set->getName(), 0, 50)) : 'KanjiBox Japanese Study Sets')
        ?>"/>
        <meta property="og:url" content="<?php echo (SERVER_URL . ($set ? 'set/' . $set->setID . '/' : 'sets/'))?>"/>
        <meta property="og:type" content="article"/>
        <meta property="og:image" content="http://kanjibox.net/kb/img/kb_large.png"/>
        <meta property="og:site_name" content="KanjiBox"/>
        <meta property="og:description" content="<?php
        if ($set && $set->isValid()) {
            echo str_replace('"', '\"', strip_tags($set->getDescription()));
        } else {
            echo "Japanese study sets compiled by users of KanjiBox to help them study a particular aspect (textbook, exam, film, manga...) of Japanese.";
        }
        ?>"/>
    </head>
    <body>
        <div id="fb-root"></div>
        <script type="text/javascript">(function(d, s, id) {
                var js, fjs = d.getElementsByTagName(s)[0];
                if (d.getElementById(id))
                    return;
                js = d.createElement(s);
                js.id = id;
                js.src = "//connect.facebook.net/en_US/all.js#xfbml=1&appId=5132078849";
                fjs.parentNode.insertBefore(js, fjs);
            }(document, 'script', 'facebook-jssdk'));
        </script>
        <div id="kb-header"><a href="<?php echo SERVER_URL;?>">KanjiBox Study Sets</a></div>
        <div id="kb">
            <div class="tabs-frame">
                <ul class="tabs">
                    <li><a class="tab-item<?php echo ($mode == 'faq') ? '' : ' selected'?>" href="<?php echo SERVER_URL;?>sets/">Study Sets</a></li>
                    <li><a class="tab-item<?php echo ($mode == 'faq') ? ' selected' : ''?>" href="<?php echo SERVER_URL;?>sets/mode/faq/">What is this?</a></li>
                </ul>
            </div>
            <?php

            function make_url_name($str)
            {
                return preg_replace(['/[[:punct:]]+/', '/[\<\>&\/]+/', '/\s+/'], ['', '', '_'],
                    strtolower(mb_substr($str, 0, 50)));
            }
            if ($mode == 'faq') {
                ?>
                <div class="content" id="frame-faq">
                    <ul class="content">
                        <li><h4>What are these lists?</h4>
                            These Japanese study sets are compiled by users of <a href="<?php echo SERVER_URL;?>">KanjiBox</a> to help them study a particular aspect (textbook, exam, film, manga...) of Japanese.<br/><br/>You can use them directly from within <a href="<?php echo SERVER_URL;?>">KanjiBox</a> or the <a href="itms://itunes.apple.com/WebObjects/MZStore.woa/wa/viewSoftware?id=322311303&amp;mt=8&amp;s=143441">KanjiBox app for iOS</a>.
                        </li>
                        <li><h4>How can I use the study sets with KanjiBox?</h4>
                            Just use the <em>'subscribe'</em> button to add it to your collection (sets are shared between all versions of KanjiBox, when you use the 'Sync' feature). Of course you can also browse and add sets directly from within the application.
                        </li>
                        <li><h4>What do the level colours mean?</h4>
                            The 'levels' bar shows how the items in the list are spread across JLPT levels: <span style="background-color:#A6B8FF;">N5</span>, <span style="background-color:#87E887;">N4</span>, <span style="background-color:#FACF89;">N3</span>, <span style="background-color:#FA8989;">N2</span>, <span style="background-color:#CCC;">N1</span>. The white bar indicates entries that are not in known JLPT lists (usually indicating advanced level, but not always).
                        </li>
                        <li><h4>Can I recommend sets to other users?</h4>
                            Subscribing to a set is the main way to let other users that a set is worth using. You can also use the Facebook 'like' button on a set's page (some future update will display a list of most-liked sets).
                        </li>
                        <li><h4>Can I create my own sets?</h4>
                            Yes, simply log into <a href="<?php echo SERVER_URL;?>">KanjiBox online</a> or start the <a href="itms://itunes.apple.com/WebObjects/MZStore.woa/wa/viewSoftware?id=322311303&amp;mt=8&amp;s=143441">KanjiBox app</a> and use the 'Create Set' option.
                        </li>
                        <li><h4>Do my sets have to be public?</h4>
                            No. In fact it is recommended you do not make sets public unless they can be of use to other users (textbook, news articles, method support etc). There is an option to make a set public or keep it private.
                        </li>
                        <li><h4>Can I collaborate with other users to make a set?</h4>
                            Yes. You simply have to make your set 'Publically editable' and other (logged-in) users will be able to add or remove entries from the set. Whenever possible, you should consider making your sets publically editable, so that people can contribute corrections or improvements to it.
                        </li>
                    </ul>
                    <p><em>More details to come...</em></p>
                </div>
                <?php
            } else {
                ?>
                <div class="content" id="frame-public-sets">
                    <fieldset><legend>Search public sets</legend>
                        <form action="<?php echo SERVER_URL;?>sets/mode/search/" method="get">
                            <p style="margin-bottom:10px;">
                                <input type="text" size="40" name="filter_str" value="<?php
                                echo htmlentities($search_str, ENT_COMPAT, 'UTF-8');
                                ?>"></input> <?php
                                       echo get_select_menu(['kanji' => 'Kanji', 'vocab' => 'Vocab'], 'set_type',
                                           (isset($_REQUEST['set_type']) ? $_REQUEST['set_type'] : null), '', 'All')
                                       ?> <input type="submit" name="Search" value="search"></input>
                            </p>
                            <?php
                            if ($mode == 'search' && $search_str == '') {
                                echo '<div class="error_msg">Empty search string.</div>';
                                $mode = 'list';
                            }
                            ?>
                            <p>Browse: <?php
                                foreach ($all_tags as $id => $tag) {
                                    if (!isset($tags) || array_search($tag, $tags) === false) {
                                        echo ' [<a href="' . SERVER_URL . 'sets/tags/' . $tag . '">' . $tag . '</a>]';
                                    } else {
                                        echo " [$tag] ";
                                    }
                                }
                                ?></p>
                            <p>Popular: <?php
                                foreach ($pop_types as $link => $name) {
                                    if ($mode == 'popular' && $pop_type == $link) {
                                        echo '[' . $name . '] ';
                                    } else {
                                        echo '[<a href="' . SERVER_URL . 'sets/popular/' . $link . '">' . $name . '</a>] ';
                                    }
                                }
                                ?>
                        </form>
                    </fieldset>
                    <fieldset class="set-detail-frame">
                        <div id="ajax-results"></div>
                        <?php
                        if ($set_id) {

                            if (!$set->isValid()) {
                                echo '<div class="error_msg">This set is private or has been deleted.</div>';
                            } else {
                                echo "<legend>" . $set->getName() . "</legend>";
                                ?>
                                <p style="margin-bottom:10px;"><?php
                                    if ($set->isOwner() || $set->isSubscribed()) {
                                        if ($set->isOwner()) {
                                            echo '<span class="subscribed-status">Author</span>';
                                        } else {
                                            echo '<span class="subscribed-status">Subscribed</span>';
                                        }

                                        echo "<button onclick=\"location.href ='" . SERVER_URL . "page/play/type/" . $set->getType() . "/mode/sets/set_id/" . $set->setID . "/'\">Play</button> ";
                                        if ($set->canEdit()) {
                                            echo "<button onclick=\"location.href ='" . SERVER_URL . "page/play/type/" . $set->getType() . "/mode/sets/view_set_id/" . $set->setID . "/'\">Edit</button> ";
                                        }
                                    } elseif ($_SESSION['user']->isLoggedIn()) {
                                        echo" <button id=\"subscribe-to-set\" onclick=\"subscribe_to_set($set->setID, this); return false;\">Subscribe</button> ";
                                    } else {
                                        echo "<button onclick=\"location.href ='" . SERVER_URL . "?redirect=set_subscribe&set_id=" . $set->setID . "'\">Log in & Subscribe</button>";
                                    }

                                    echo "<button onclick=\"location.href ='" . SERVER_URL . "export/set_export.php?set_id=" . $set->setID . "'\">Export as Text</button>";

                                    if ($set->canAdmin()) {
                                        echo " (public: <input type=\"checkbox\" name=\"set_public\" id=\"set_public\" value=\"1\" onclick=\"update_set_public(" . $set->setID . ", this.checked)\"" . ($set->isPublic() ? ' checked' : '') . (!$set->canAdmin() ? ' disabled' : '') . "></input>" . (($set->isPublic() && $set->canAdmin() && $set->getSubsCount()) ? '<span style="color:red;">access</span>' : 'access') . ", <input type=\"checkbox\" name=\"set_editable\" id=\"set_editable\" value=\"1\" onclick=\"update_set_editable(" . $set->setID . ", this.checked)\"" . ($set->isEditable() ? ' checked' : '') . (!$set->canAdmin() ? ' disabled' : '') . "></input>edit)";
                                    }
                                    ?> <span style="margin-left:10px;" class="fb-like" data-href="<?php echo SERVER_URL . 'set/' . $set->setID . '/'?>" data-send="false" data-layout="button_count" data-width="100" data-show-faces="false"></span>
                                </p>
                                <?php
                                echo "<div class=\"description\">";
                                if ($set->canEdit()) {
                                    echo "<textarea id=\"set_description\" onchange=\"update_set_desc(" . $set->setID . ", this.value);\" style=\"display:none;\">" . $set->getDescription() . "</textarea><p id=\"set_description_static\" onclick=\"$(this).hide(); $('#set_description').show(); return false;\">" . ($set->getDescription() ? nl2br($set->getDescription()) : '...') . "</p>";
                                } else {
                                    echo '<p>' . nl2br($set->getDescription()) . '</p>';
                                }

                                echo '<div class="tags">' . $set->showTagCheckboxes() . '</div><div style="clear: both;"></div>';

                                echo "</div>";
                                $rows = $set->getEntryData();

                                $set_author = $set->getAuthorName();

                                if (count($rows) > 5 && count($rows) < 1000) {
                                    echo '<div style="margin-bottom: 10px;"><p style="float: left; margin-right: 4px;">Levels: </p><div class="set-difficulty-bar">';
                                    $levels = [0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
                                    foreach ($rows as $row) {
                                        $levels[($row->njlpt == 0 && $row->katakana) ? 5 : $row->njlpt] ++;
                                    }
                                    $tot = 0;
                                    foreach ($levels as $i => $level) {
                                        $tot += $level;
                                    }
                                    $tot_width = 700;
                                    $temptot = 0;
                                    for ($i = 5; $i >= 0; $i--) {
                                        if ($levels[$i]) {
                                            $temptot += $levels[$i];
                                            $size = $tot_width * $levels[$i] / $tot;
                                            echo '<div class="njlpt-' . $i . '" style="width:' . $size . 'px;' . ($temptot == $tot ? 'border-right:1px solid black;' : '') . '">' . ($size > (5 * ceil(log($levels[$i] + 0.1,
                                                    10))) ? $levels[$i] : '') . '</div>';
                                        }
                                    }
                                    echo '</div><div style="clear: both;"></div></div>';
                                }

                                if (!$params['show'] && count($rows) > 1000) {
                                    $rows = array_slice($rows, 0, 500);
                                    echo "<div style=\"font-weight:bold;\">First " . count($rows) . ' <span class="minor">entries</span> [<a href="' . SERVER_URL . 'set/' . $set->setID . '/name/' . make_url_name($set->getName()) . '/show/all/' . '">show all</a>]';
                                } else {
                                    echo "<div style=\"font-weight:bold;\">" . count($rows) . ' <span class="minor">entries</span>';
                                }
                                echo ($set_author ? ' &mdash; <span class="minor">Created by</span> ' . $set_author : '') . ' &mdash; <span class="minor">Last modified:</span> ' . $set->getDateModified() . "</div>";

                                $is_kanji = ($set->getType() == TYPE_KANJI);
                                $jlpt2char = LearningSet::$jlpt2Char;

                                if (!count($rows)) {
                                    echo '<em>Empty set</em>';
                                } else {
                                    foreach ($rows as $row) {
                                        echo '<div class="set_content_line">';
                                        if ($is_kanji) {
                                            echo '<span class="njlpt">' . $jlpt2char[$row->njlpt] . "</span> <span class=\"kanji\">$row->kanji</span> • <span class=\"prons\">$row->prons</span> • <span class=\"english\">$row->meaning_english</span></div>\n";
                                        } else {
                                            echo '<span class="njlpt">' . $jlpt2char[$row->njlpt] . "</span> <span class=\"japanese\">" . ($row->usually_kana ? $row->reading : $row->word . ($row->word != $row->reading && !$row->katakana ? " 【" . $row->reading . "】" : '')) . "</span> • <span class=\"english\">$row->gloss_english</span></div>\n";
                                        }
                                    }
                                }
                            }
                        } else {
                            switch ($mode) {
                                case 'list':
                                default:
                                    echo '<legend>Recent Sets</legend>';
                                    $row = DB::count('SELECT COUNT(*) FROM learning_sets ls WHERE ls.deleted = 0 AND ls.public = 1',
                                            []);
                                    $entry_per_page = 50;
                                    if (!empty($params['page']) && $params['page'] > 0) {
                                        $page = (int) $params['page'];
                                    } else {
                                        $page = 1;
                                    }
                                    for ($i = 1; $i * $entry_per_page < $row; $i++) {
                                        if ($i == $page) {
                                            echo "[$i]";
                                        } else {
                                            echo "<a href=\"" . SERVER_URL . "sets/page/$i/\">[$i]</a>";
                                        }
                                    }
                                    $query = 'SELECT ls.*, GROUP_CONCAT(DISTINCT lst.tag_id SEPARATOR \',\') AS tags FROM learning_sets ls LEFT JOIN learning_set_tags lst ON lst.set_id = ls.set_id WHERE ls.deleted = 0 AND ls.public = 1 GROUP BY ls.set_id ORDER BY date_modified DESC LIMIT ' . (($page - 1) * $entry_per_page) . ', ' . ($page) * $entry_per_page;
                                    break;

                                case 'search':
                                    echo '<legend>' . ($_REQUEST['set_type'] != '' ? ucwords($_REQUEST['set_type']) . ' ' : '') . "Sets matching <em>" . htmlentities($search_str,
                                        ENT_COMPAT, 'UTF-8') . "</em></legend>";

                                    $query = "SELECT ls.*, GROUP_CONCAT(DISTINCT lst.tag_id SEPARATOR ',') AS tags FROM learning_sets ls LEFT JOIN learning_set_tags lst ON lst.set_id = ls.set_id WHERE ls.deleted = 0 AND ls.public = 1 AND (ls.name LIKE '%" . mysql_real_escape_string($search_str) . "%' OR ls.description LIKE '%" . mysql_real_escape_string($search_str) . "%')";

                                    if ($_REQUEST['set_type'] == 'kanji' || $_REQUEST['set_type'] == 'vocab') {
                                        $query .= " AND ls.set_type = '" . $_REQUEST['set_type'] . "' ";
                                    }

                                    $query .= " AND ls.deleted = 0 AND ls.public = 1 GROUP BY ls.set_id ORDER BY ls.date_modified DESC LIMIT 100";
                                    break;

                                case 'tags':
                                    echo "<legend>For tag" . (count($tags) > 1 ? 's: <em>' : ': <em>') . implode(' or ',
                                        $tags) . "</em></legend>";

                                    $query = 'SELECT ls.*, lst.tag_id AS tags FROM learning_sets ls ';

                                    if ($tags && count($tags)) {
                                        $query .= 'JOIN learning_set_tags lst ON lst.set_id = ls.set_id AND (0 ';
                                        foreach ($tags as $tag) {
                                            if ($tag_id = array_search($tag, $all_tags)) {
                                                $query .= "OR lst.tag_id = $tag_id ";
                                            }
                                        }
                                        $query .= ') WHERE ls.deleted = 0 AND ls.public = 1 GROUP BY lst.set_id';
                                    }
                                    break;

                                case 'popular':
                                    echo '<legend>Popular sets ' . $pop_types[$pop_type] . '</legend>';
                                    $date_filter = '';
                                    if ($pop_type == 'week') {
                                        $date_filter = 'AND lss.date_subscribed > DATE_SUB(NOW(),INTERVAL 7 DAY)';
                                    } elseif ($pop_type == 'month') {
                                        $date_filter = 'AND lss.date_subscribed > DATE_SUB(NOW(),INTERVAL 31 DAY)';
                                    }
                                    $query = 'SELECT ls.*, GROUP_CONCAT(DISTINCT lst.tag_id SEPARATOR \',\') AS tags FROM (SELECT l.*, COUNT(*) as subs_count' . ($pop_type != 'all' ? '_recent' : '') . ' FROM learning_sets l LEFT JOIN learning_set_subs lss ON lss.set_id = l.set_id WHERE lss.set_id IS NOT NULL ' . $date_filter . ' GROUP BY l.set_id) ls LEFT JOIN learning_set_tags lst ON lst.set_id = ls.set_id WHERE ls.deleted = 0 AND ls.public = 1 GROUP BY ls.set_id ORDER BY subs_count' . ($pop_type != 'all' ? '_recent' : '') . ' DESC';

                                    break;
                            }

                            try {
                                $stmt = DB::getConnection()->prepare($query);
                                $stmt->execute();

                                while ($row = $stmt->fetchObject()) {
                                    if (isset($row->subs_count)) {
                                        $count = $row->subs_count;
                                    } else {
                                        $count = DB::count('SELECT COUNT(*) FROM learning_set_subs WHERE set_id = ?',
                                                [$row->set_id]);
                                    }
                                    $row_num_entries = DB::count('SELECT COUNT(*) AS c FROM learning_set_' . $row->set_type . ' WHERE set_id = ?',
                                            [$row->set_id]);

                                    echo '<div class="set_line"><span class="set_type_' . $row->set_type . '">' . ($row->set_type == 'kanji' ? '漢字' : '単語') . '</span><a class="name" href="' . SERVER_URL . 'set/' . $row->set_id . '/name/' . make_url_name($row->name) . '">' . $row->name . '</a> <span class="size">' . $row_num_entries . '</span>' . ($row->editable ? '<span class="prop">editable</span>' : '') . ($count ? ' <span class="set_prop">' . $count . ' subscriber' . ($count > 1 ? 's' : '') . '</span>' : '');
                                    if (!empty($row->tags)) {
                                        foreach (explode(',', $row->tags) as $tag_id) {
                                            echo '<span class="tag_box">' . $all_tags[$tag_id] . '</span>';
                                        }
                                    }
                                    echo '</div>';
                                }
                            } catch (PDOException $e) {
                                log_db_error($query, $e->getMessage(), true, true);
                            }
                        }
                        ?>
                    </fieldset>
                </div>
                <script type="text/javascript">
    <?php
    if ($set && $set->canEdit()) {
        ?>
                        function update_tag(set_id, tag_id, val)
                        {
                            $.get('<?php echo SERVER_URL;?>ajax/edit_learning_set/?set_id=' + set_id + '&set_tag_id=' + tag_id + '&val=' + (val ? 1 : 0), function(data) {

                                $('#tag_' + tag_id).css('border', '1px solid green');
                                setTimeout(function() {
                                    $('#tag_' + tag_id).css('border', '1px solid #CCC');
                                }, 2000);
                            });
                        }

                        function update_set_public(id, val)
                        {
                            $.get('<?php echo SERVER_URL;?>ajax/edit_learning_set/?set_id=' + id + '&set_public=' + (val ? 1 : 0), function(data) {
                                $('#set_public').css('outline', '2px ridge green');
                                setTimeout(function() {
                                    $('#set_public').css('outline', 'none');
                                }, 2000);
                            });
                        }

                        function update_set_editable(id, val)
                        {
                            $.get('<?php echo SERVER_URL?>ajax/edit_learning_set/?set_id=' + id + '&set_editable=' + (val ? 1 : 0), function(data) {
                                $('#set_editable').css('outline', '2px ridge green');
                                setTimeout(function() {
                                    $('#set_editable').css('outline', 'none');
                                }, 2000);
                            });
                        }

                        function update_set_desc(id, desc)
                        {
                            $.get('<?php echo SERVER_URL?>ajax/edit_learning_set/?set_id=' + id + '&new_desc=' + desc, function(data) {
                                $('#set_description').css('border', '2px solid green');

                                setTimeout(function() {
                                    $('#set_description').css('border', '1px solid black');
                                    $('#set_description').hide();
                                    $('#set_description_static').show();
                                    $('#set_description_static').html($('#set_description').val());
                                }, 2000);
                            });
                        }
        <?php
    }

    if (isset($_REQUEST['redirected_subscribe'])) {
        echo "\nsubscribe_to_set(" . $set->setID . ", '#subscribe-to-set');\n";
    }
    ?>

                    function subscribe_to_set(set_id, btn) {
                        $(btn).prop('disabled', 'disabled');
                        $.post('<?php echo SERVER_URL?>ajax/edit_learning_set/', {'set_id': set_id, 'subscribe_to_set': true, 'return_play_btn': true}, function(data, textStatus, jqXHR) {
                            $(btn).hide();
                            $('#ajax-results').html(data);
                        });
                    }
                </script>

                <?php
            }
            ?>
        </div>
        <div id="kbbottom"></div>

        <script type="text/javascript">

            if (top !== self) {
                top.onbeforeunload = function() {
                };
                top.location.replace(self.location.href);
            }

            function gaSSDSLoad(acct) {
                var gaJsHost = (("https:" === document.location.protocol) ? "https://ssl." : "http://www."),
                        pageTracker,
                        s;
                s = document.createElement('script');
                s.src = gaJsHost + 'google-analytics.com/ga.js';
                s.type = 'text/javascript';
                s.onloadDone = false;
                function init() {
                    pageTracker = _gat._getTracker(acct);
                    pageTracker._trackPageview();
                }
                s.onload = function() {
                    s.onloadDone = true;
                    init();
                };
                s.onreadystatechange = function() {
                    if (('loaded' === s.readyState || 'complete' === s.readyState) && !s.onloadDone) {
                        s.onloadDone = true;
                        init();
                    }
                };
                document.getElementsByTagName('head')[0].appendChild(s);
            }

            $(document).ready(function()
            {
                $('#kb').corner('top');
                $('#kbbottom').corner('bottom');
                $('#kb .tabs .selected').corner('bevel tr 5px');
                $('.ui-state-default').hover(
                        function() {
                            $(this).addClass("ui-state-hover");
                        },
                        function() {
                            $(this).removeClass("ui-state-hover");
                        }
                );
            });

            window.onload = function() {
                gaSSDSLoad("UA-52899-7");
            };
        </script>

    </body>
</html>
