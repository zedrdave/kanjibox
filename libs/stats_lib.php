<?php

global $facebook;

function print_friendsboard($user, $level, $type, $title, $otherlevels = false) {
    global $facebook, $levels;

    $friends = $_SESSION['user']->get_friends();
    $friends[] = $user->get_fb_id();
    $friends_id = implode($friends, ',');

    // Backup:
    $query = "SELECT u.id, u.fb_id AS fb_id, g.id, g.score AS score, g.date_started AS date_played, TIMEDIFF(g.date_ended, g.date_started) AS duration, (u.level != g.level) AS otherlevel, u.level AS level, u.privileges
	FROM `users` u
	JOIN `games` g ON g.`user_id` = u.id AND g.`level` = " . ($otherlevels ? "u.level" : "'$level'") . " AND g.type = '$type'
	LEFT JOIN games g2 ON g.user_id = g2.user_id AND g2.`level` = g.level AND g2.type = g.type AND (g.score < g2.score OR (g.score = g2.score AND g.date_ended > g2.date_ended))
	WHERE u.active = 1 AND g2.score IS NULL AND u.fb_id IN ($friends_id)" . ($otherlevels ? " AND u.level != '$level'" : '') . "
	ORDER BY score DESC, duration ASC LIMIT 30";


    // $query = "SELECT u.id, u.fb_id AS fb_id, g.id, g.score AS score, g.date_started AS date_played, TIMEDIFF(g.date_ended, g.date_started) AS duration, (u.level != g.level) AS otherlevel, u.level AS level, u.privileges
    // FROM `users` u
    // JOIN `games` g ON g.`user_id` = u.id AND g.`level` = ". ($otherlevels ? "u.level" : "'$level'") . " AND g.type = '$type'
    // WHERE u.active = 1 AND u.fb_id IN ($friends_id)" . ($otherlevels ? " AND u.level != '$level'" : ''). "
    // ORDER BY score DESC, duration ASC LIMIT 30";
// echo $query;

    $res = mysql_query_debug($query) or die(mysql_error());
    if (mysql_num_rows($res) <= 0) {
        echo "<div class=\"scoreboard\"><h2>No Friends' High Scores Yet</h2>
		<br/>
		<p><a href=\"" . get_page_url(PAGE_INVITE) . "\">Invite</a> your friends to Kanji Box if you want to compare your score with theirs.</p></div>";
        return;
    }

    echo "<div class=\"scoreboard\"><h2>$title</h2>";

    $i = 1;
    while ($row = mysql_fetch_assoc($res)) {
        echo "<div class=\"user" . ($user->get_fb_id() == $row['fb_id'] ? " self" : "") . ($row['otherlevel'] ? " otherlevel" : "") . "\"><fb:profile-pic uid=\"" . $row['fb_id'] . "\" size=\"square\" linked=\"true\"></fb:profile-pic>";
        if (!$otherlevels && !$row['otherlevel'])
            echo "<div class=\"score_rank\">" . $i++ . "</div>";
        echo "<p>" . ($row['privileges'] > 0 ? '<a href="http://kanjibox.net/kb/page/faq/#elite" title="カッコいい人！">★</a> ' : '') . "<fb:name uid=\"" . $row['fb_id'] . "\" capitalize=\"true\" reflexive=\"true\" ></fb:name></p>";
        if ($otherlevels)
            echo "<p>Level: <strong>" . $levels[$row['level']] . "</strong></p>";

        echo "<p><strong>" . $row['score'] . " Pts</strong></p>";
        //	echo "<p>On: " . date('M jS, Y - g:ia',
        //						strtotime($row['date_played']) + ((8+$timezone) * 3600)) . "</p>";

        echo '<p>' . nice_time($row['date_played']) . '</p>';
        echo "<p>Time: " . $row['duration'] . "</p>";

        if ($row['otherlevel'])
            echo "<p><strong>Now training at level: " . $levels[$row['level']] . "</strong></p>";
        echo "<div style=\"clear: both;\"></div>";
        echo "</div>";
    }
    echo "</div><div style=\"clear: both;\"></div>";
}

function print_globalboard($user, $level, $type, $title, $limit = 5) {
    global $facebook, $levels;

    $query = "SELECT u.id, u.fb_id AS fb_id, g.id, g.score AS score, g.date_started AS date_played, TIMEDIFF(g.date_ended, g.date_started) AS duration, u.privileges, ux.first_name, ux.last_name FROM `users` u LEFT JOIN users_ext ux ON u.id = ux.user_id JOIN `games` g ON g.id = u.${type}_highscore_id  WHERE u.active = 1 AND name_hidden = 0 AND u.level = '$level' AND (u.fb_id != 0 OR ux.first_name != '' OR ux.last_name != '') ORDER BY g.score DESC, duration ASC LIMIT " . (int) $limit;

    // $query = "SELECT u.id, u.fb_id AS fb_id, g.id, g.score AS score, g.date_started AS date_played, TIMEDIFF(g.date_ended, g.date_started) AS duration FROM `users` u INNER JOIN `games` g ON g.`user_id` = u.id AND g.`level` = '$level' AND g.type = '$type' LEFT JOIN games g2 ON g.user_id = g2.user_id AND g2.level = '$level' AND g2.type = '$type'  AND (g.score < g2.score OR (g.score = g2.score AND g.date_ended > g2.date_ended))  WHERE u.active = 1 AND u.level = '$level' AND g2.score IS NULL ORDER BY score DESC " . (int) $limit;

    $res = mysql_query_debug($query) or die(mysql_error());
    if (mysql_num_rows($res) <= 0) {
        echo "<div class=\"scoreboard\"><h2>No Global High Scores For This Level Yet</h2></div>";
        return;
    }

    echo "<div class=\"scoreboard\"><h2>$title</h2>";
    $i = 1;
    $self_included = false;

    while ($row = mysql_fetch_assoc($res)) {
        if ($user->get_fb_id() == $row['fb_id']) {
            echo "<div class=\"user self\"><fb:profile-pic uid=\"" . $row['fb_id'] . "\" size=\"square\" linked=\"true\"></fb:profile-pic>";
            $self_included = true;
        } else {
            echo "<div class=\"user\">";
            if ($row['fb_id'])
                echo "<fb:profile-pic uid=\"" . $row['fb_id'] . "\" size=\"square\" linked=\"true\"></fb:profile-pic>";
        }
        echo "<div class=\"score_rank\">" . $i++ . "</div>";
        echo "<p>" . ($row['privileges'] > 0 ? '<a href="http://kanjibox.net/kb/page/faq/#elite" title="カッコいい人！">★</a> ' : '');
        if ($row['fb_id'])
            echo "<fb:name uid=\"" . $row['fb_id'] . "\" capitalize=\"true\" reflexive=\"true\"></fb:name>";
        else
            echo $row['first_name'] . ' ' . $row['last_name'];

        echo "</p>";
        echo "<p><strong>" . $row['score'] . " Pts</strong></p>";
//		echo "<p>On: " . date('M jS, Y - g:ia',
//							strtotime($row['date_played']) + ((7+$timezone) * 3600)) . "</p>";
        echo '<p>' . nice_time($row['date_played']) . '</p>';
        echo "<p>Time: " . $row['duration'] . "</p>";
        echo "<div style=\"clear: both;\"></div>";
        echo "</div>\n";
    }

    if (!$self_included) {
        if ($rank = $user->get_best_game($type)) {
            echo '<p class="ellipse">...</p>';
            echo "<div class=\"user self\"><fb:profile-pic uid=\"" . $user->get_fb_id() . "\" size=\"square\" linked=\"true\"></fb:profile-pic>";
            $font_size = ((int) $rank->rank ? min(100, (int) (150 / (floor(log10($rank->rank)) + 1))) : 100);
            echo "<div class=\"score_rank\"><span style=\"font-size:" . $font_size . "%\">" . $rank->rank . "</span></div>";
            echo "<p><fb:name uid=\"" . $user->get_fb_id() . "\" capitalize=\"true\" reflexive=\"true\" /></p>";
            echo "<p><strong>" . $rank->score . " Pts</strong></p>";
            //		echo "<p>On: " . date('M jS, Y - g:ia',
            //							strtotime($row['date_played']) + ((7+$timezone) * 3600)) . "</p>";
            echo '<p>' . nice_time($rank->date_played) . '</p>';
            echo "<p>Time: " . $rank->duration . "</p>";
            echo "<div style=\"clear: both;\"></div>";
            echo "</div>";
        }
    }

    echo "</div><div style=\"clear: both;\"></div>";
}

function print_grades_levels($user_id, $grade, $tot_size = 330, $title = '') {
    $res = mysql_query_debug("select count(*) as count from kanjis k left join learning l on k.id = l.kanji_id and user_id = '" . (int) $user_id . "' where k.grade = " . (int) $grade . " and curve < 500") or die(mysql_error());
    $row = mysql_fetch_array($res);
    $verygood = $row[0];

    $res = mysql_query_debug("select count(*) as count from kanjis k left join learning l on k.id = l.kanji_id and user_id = '" . (int) $user_id . "' where k.grade = " . (int) $grade . " and (curve >= 500 and curve < 950)") or die(mysql_error());
    $row = mysql_fetch_array($res);
    $good = $row[0];

    $res = mysql_query_debug("select count(*) as count from kanjis k left join learning l on k.id = l.kanji_id and user_id = '" . (int) $user_id . "' where k.grade = " . (int) $grade . " and (curve >= 950 and curve <= 1050)") or die(mysql_error());
    $row = mysql_fetch_array($res);
    $neutral = $row[0];

    $res = mysql_query_debug("select count(*) as count from kanjis k left join learning l on k.id = l.kanji_id and user_id = '" . (int) $user_id . "' where k.grade = " . (int) $grade . " and curve IS NULL") or die(mysql_error());
    $row = mysql_fetch_array($res);
    $unknown = $row[0];

    $res = mysql_query_debug("select count(*) as count from kanjis k left join learning l on k.id = l.kanji_id and user_id = '" . (int) $user_id . "' where k.grade = " . (int) $grade . " and (curve >= 1050 and curve < 1500)") or die(mysql_error());
    $row = mysql_fetch_array($res);
    $bad = $row[0];

    $res = mysql_query_debug("select count(*) as count from kanjis k left join learning l on k.id = l.kanji_id and user_id = '" . (int) $user_id . "' where k.grade = " . (int) $grade . " and curve >= 1500") or die(mysql_error());
    $row = mysql_fetch_array($res);
    $verybad = $row[0];

    $total = ($verybad + $bad + $unknown + $neutral + $good + $verygood);
    if (!$total)
        return '';

    if (empty($title))
        $title = 'Grade ' . $grade . ' (' . $total . '): ';

    return make_charts($verybad, $bad, $unknown, $neutral, $good, $verygood, $tot_size, $title);
}

function printJLPTLevels($user_id, $jlpt, $tot_size = 335, $title = '') {

    $verygood = DB::count('select count(*) from kanjis k left join learning l on k.id = l.kanji_id and user_id = ? where k.njlpt = ? and curve < 500', [$user_id, $jlpt]);
    $good = DB::count('select count(*) from kanjis k left join learning l on k.id = l.kanji_id and user_id = ? where k.njlpt = ? and (curve >= 500 and curve < 950)', [$user_id, $jlpt]);
    $unknown = DB::count('select count(*) from kanjis k left join learning l on k.id = l.kanji_id and user_id = ? where k.njlpt = ? and curve IS NULL', [$user_id, $jlpt]);
    $neutral = DB::count('select count(*) from kanjis k left join learning l on k.id = l.kanji_id and user_id = ? where k.njlpt = ? and (curve >= 950 and curve < 1050)', [$user_id, $jlpt]);
    $bad = DB::count('select count(*) from kanjis k left join learning l on k.id = l.kanji_id and user_id = ? where k.njlpt = ? and (curve >= 1050 and curve < 1500)', [$user_id, $jlpt]);
    $verybad = DB::count('select count(*) from kanjis k left join learning l on k.id = l.kanji_id and user_id = ? where k.njlpt = ? and curve >= 1500', [$user_id, $jlpt]);

    $total = ($verybad + $bad + $unknown + $neutral + $good + $verygood);
    if (!$total) {
        return '';
    }
    if (empty($title)) {
        $title = 'JLPT N' . $jlpt . ' (' . $total . '): ';
    }

    return make_charts($verybad, $bad, $unknown, $neutral, $good, $verygood, $tot_size, $title);
}

function print_vocab_jlpt_levels($user_id, $jlpt, $tot_size = 710, $title = '') {
    $query_params = array('verygood' => array(-1, 500), 'good' => array(500, 950), 'neutral' => array(950, 1050), 'bad' => array(1050, 1500), 'verybad' => array(1500, 10000));

    foreach ($query_params as $var => $val) {
        list($min, $max) = $val;
        $row = DB::count('select count(*) from jmdict j left join jmdict_learning l on j.id = l.jmdict_id and user_id = ? where j.njlpt = ? and curve >= ? and curve < ?', [$user_id, $jlpt, $min, $max]);
        $$var = $row[0];
    }

    $unknown = DB::count('select count(*) from jmdict j left join jmdict_learning l on j.id = l.jmdict_id and user_id = ? where j.njlpt = ? and curve IS NULL', [$user_id, $jlpt]);

    $total = ($verybad + $bad + $unknown + $neutral + $good + $verygood);
    if (!$total) {
        return '';
    }

    if (empty($title)) {
        $title = 'JLPT N' . $jlpt . ' (' . $total . '): ';
    }

    return make_charts($verybad, $bad, $unknown, $neutral, $good, $verygood, $tot_size, $title);
}

function print_kanji_set_stats($user_id, $set_id, $tot_size = 710, $title = '') {
    $query_params = array('verygood' => array(-1, 500), 'good' => array(500, 950), 'neutral' => array(950, 1050), 'bad' => array(1050, 1500), 'verybad' => array(1500, 10000));

    foreach ($query_params as $var => $val) {
        list($min, $max) = $val;
        $res = mysql_query_debug("SELECT COUNT(*) as count FROM learning_set_kanji ls LEFT JOIN learning l on l.kanji_id = ls.kanji_id and user_id = '" . (int) $user_id . "' WHERE ls.set_id = " . (int) $set_id . " and curve >= $min and curve < $max") or die(mysql_error());
        $row = mysql_fetch_array($res);
        $$var = $row[0];
    }

    $res = mysql_query_debug("select count(*) as count FROM learning_set_kanji ls LEFT JOIN learning l on l.kanji_id = ls.kanji_id and user_id = '" . (int) $user_id . "' WHERE ls.set_id = " . (int) $set_id . " and curve IS NULL") or die(mysql_error());
    $row = mysql_fetch_array($res);
    $unknown = $row[0];

    $total = ($verybad + $bad + $unknown + $neutral + $good + $verygood);
    if (!$total)
        return '';

    if (empty($title))
        $title = 'Set: ' . $jlpt . ' (' . $total . '): ';

    // if($_SESSION['user']->is_admin())
    // 	echo "<pre>$title: $total</pre>";

    return make_charts($verybad, $bad, $unknown, $neutral, $good, $verygood, $tot_size, $title);
}

function print_vocab_set_stats($user_id, $set_id, $tot_size = 710, $title = '') {
    $query_params = array('verygood' => array(-1, 500), 'good' => array(500, 950), 'neutral' => array(950, 1050), 'bad' => array(1050, 1500), 'verybad' => array(1500, 10000));

    foreach ($query_params as $var => $val) {
        list($min, $max) = $val;
        $res = mysql_query_debug("SELECT COUNT(*) as count FROM learning_set_vocab ls LEFT JOIN jmdict_learning l on l.jmdict_id = ls.jmdict_id and user_id = '" . (int) $user_id . "' WHERE ls.set_id = " . (int) $set_id . " and curve >= $min and curve < $max") or die(mysql_error());
        $row = mysql_fetch_array($res);
        $$var = $row[0];
    }

    $res = mysql_query_debug("select count(*) as count FROM learning_set_vocab ls LEFT JOIN jmdict_learning l on l.jmdict_id = ls.jmdict_id and user_id = '" . (int) $user_id . "' WHERE ls.set_id = " . (int) $set_id . " and curve IS NULL") or die(mysql_error());
    $row = mysql_fetch_array($res);
    $unknown = $row[0];

    $total = ($verybad + $bad + $unknown + $neutral + $good + $verygood);
    if (!$total)
        return '';

    if (empty($title))
        $title = 'Set: ' . $jlpt . ' (' . $total . '): ';

    // if($_SESSION['user']->is_admin())
    // 	echo "<pre>$title: $total</pre>";

    return make_charts($verybad, $bad, $unknown, $neutral, $good, $verygood, $tot_size, $title);
}

function print_reading_jlpt_levels($user_id, $jlpt, $tot_size = 710, $title = '') {
    $query_params = array('verygood' => array(-1, 500), 'good' => array(500, 950), 'neutral' => array(950, 1050), 'bad' => array(1050, 1500), 'verybad' => array(1500, 10000));
    $jlpt = (int) $jlpt;

    foreach ($query_params as $var => $val) {
        list($min, $max) = $val;

        $query = "select count(*) as count FROM jmdict j left join reading_learning l on j.id = l.jmdict_id and user_id = '" . (int) $user_id . "' where ((j.njlpt = $jlpt AND j.njlpt_r >= $jlpt) OR (j.njlpt > $jlpt AND j.njlpt_r = $jlpt)) AND j.usually_kana =  0 AND j.katakana = '0' AND j.word != j.reading AND curve >= $min and curve < $max";

        $res = mysql_query_debug($query) or die(mysql_error());
        $row = mysql_fetch_array($res);
        $$var = $row[0];
        // if($_SESSION['user']->is_admin())
        // 	echo "<pre>$query\n\nReturned: $row[0]</pre>";
    }

    $query = "select count(*) as count from jmdict j left join reading_learning l on j.id = l.jmdict_id and user_id = '" . (int) $user_id . "' WHERE ((j.njlpt = $jlpt AND j.njlpt_r >= $jlpt) OR (j.njlpt > $jlpt AND j.njlpt_r = $jlpt)) AND  j.usually_kana =  0 AND j.katakana = '0' AND j.word != j.reading AND curve IS NULL";
    $res = mysql_query_debug($query) or die(mysql_error());
    $row = mysql_fetch_array($res);
    $unknown = $row[0];

    // if($_SESSION['user']->is_admin())
    // 	echo "<pre>$query\n\nReturned: $row[0]</pre>";


    $total = ($verybad + $bad + $unknown + $neutral + $good + $verygood);
    if (!$total)
        return '';

    if (empty($title))
        $title = 'JLPT N' . $jlpt . ' (' . $total . '): ';

    // if($_SESSION['user']->is_admin())
    // 	echo "<pre>$title: $total</pre>";

    return make_charts($verybad, $bad, $unknown, $neutral, $good, $verygood, $tot_size, $title);
}

function print_reading_set_stats($user_id, $set_id, $tot_size = 710, $title = '') {
    $query_params = array('verygood' => array(-1, 500), 'good' => array(500, 950), 'neutral' => array(950, 1050), 'bad' => array(1050, 1500), 'verybad' => array(1500, 10000));

    foreach ($query_params as $var => $val) {
        list($min, $max) = $val;

        $query = "select COUNT(*) AS count FROM learning_set_vocab ls LEFT JOIN jmdict j ON j.id = ls.jmdict_id LEFT JOIN reading_learning l on ls.jmdict_id = l.jmdict_id and user_id = '" . (int) $user_id . "' WHERE ls.set_id = " . (int) $set_id . " AND j.usually_kana =  0 AND j.katakana = '0' AND j.word != j.reading AND curve >= $min and curve < $max";

        $res = mysql_query_debug($query) or die(mysql_error());
        $row = mysql_fetch_array($res);
        $$var = $row[0];
    }


    $query = "select COUNT(*) AS count FROM learning_set_vocab ls LEFT JOIN jmdict j ON j.id = ls.jmdict_id LEFT JOIN reading_learning l ON ls.jmdict_id = l.jmdict_id AND user_id = '" . (int) $user_id . "' WHERE ls.set_id = " . (int) $set_id . " AND  j.usually_kana =  0 AND j.katakana = '0' AND j.word != j.reading AND curve IS NULL";

    $res = mysql_query_debug($query) or die(mysql_error());

    $row = mysql_fetch_array($res);
    $unknown = $row[0];

    $total = ($verybad + $bad + $unknown + $neutral + $good + $verygood);
    if (!$total)
        return '';

    if (empty($title))
        $title = 'Set: ' . $jlpt . ' (' . $total . '): ';

    // if($_SESSION['user']->is_admin())
    // 	echo "<pre>$title: $total</pre>";

    return make_charts($verybad, $bad, $unknown, $neutral, $good, $verygood, $tot_size, $title);
}

function print_kana_levels($user_id, $tot_size = 710, $title = '') {
    $ret_str = '';

    $query_params = array('verygood' => array(-1, 500), 'good' => array(500, 950), 'neutral' => array(950, 1050), 'bad' => array(1050, 1500), 'verybad' => array(1500, 10000));

    foreach ($query_params as $var => $val) {
        list($min, $max) = $val;
        $res = mysql_query_debug("select count(*) as count from kanas k left join kana_learning l on k.id = l.kana_id and user_id = '" . (int) $user_id . "' where curve >= $min and curve < $max") or die(mysql_error());
        $row = mysql_fetch_array($res);
        $$var = $row[0];
    }

    $res = mysql_query_debug("select count(*) as count from kanas k left join kana_learning l on k.id = l.kana_id and user_id = '" . (int) $user_id . "' where curve IS NULL") or die(mysql_error());
    $row = mysql_fetch_array($res);
    $unknown = $row[0];


    $total = ($verybad + $bad + $unknown + $neutral + $good + $verygood);
    if (!$total)
        return '';

    return make_charts($verybad, $bad, $unknown, $neutral, $good, $verygood, $tot_size, $title);
}

function make_charts($verybad, $bad, $unknown, $neutral, $good, $verygood, $tot_size, $title = '') {
    $ret_str = '';

    $total = ($verybad + $bad + $unknown + $neutral + $good + $verygood);

    $ret_str .= '<div class="result_bar">';
    if (!empty($title))
        $ret_str .= '<p>' . $title . '</p>';
    $temptot = 0;
    foreach (array('verybad', 'bad', 'unknown', 'neutral', 'good', 'verygood') as $what) {
        if (!$total)
            echo "$title (empty)";
        $size = max(1, round(($tot_size * $$what) / $total) - 1);
        $temptot += $$what;
        if ($$what > 0 && $size > 0)
            $ret_str .= '<div class="' . $what . '" style="width:' . $size . 'px;' . ($temptot >= $total ? 'border-right:1px solid black;' : '') . '">' . ($size > (5 * ceil(log($$what + 0.1, 10))) ? $$what : '') . '</div>';
    }

    $ret_str .= '</div><div style="clear: both;"></div>';

    return $ret_str;
}

function resetRankings($level, $type) {
    $deleteQuery = 'DELETE FROM ranking WHERE level = :level AND type = :type';
    try {
        $stmt = DB::getConnection()->prepare($deleteQuery);
        $stmt->bindValue(':level', $level);
        $stmt->bindValue(':type', $type);
        $stmt->execute();
    } catch (PDOException $e) {
        log_db_error($deleteQuery, $e->getMessage(), true, true);
    }

    $query = 'INSERT INTO ranking (SELECT NULL, user_id, type, level, @rownum:=@rownum+1 as rank, game_id, NOW()
	FROM (SELECT @rownum:=0) r,
			(SELECT u.id as user_id, g.type as type, g.level as level, g.id as game_id
				FROM games g
				LEFT JOIN games g2 ON g.user_id = g2.user_id
					AND g2.`level` = g.level
					AND g2.type = g.type
					AND (
					g.score < g2.score
					OR (g.score = g2.score AND TIMEDIFF(g2.date_ended, g2.date_started) > TIMEDIFF(g.date_ended, g.date_started))
					)
				JOIN users u ON g.user_id = u.id AND g.level = u.level AND u.active = 1
	WHERE g2.user_id IS NULL AND g.type = :type AND g.level = :level
	GROUP BY g.user_id
	ORDER BY g.score DESC, TIMEDIFF(g.date_ended, g.date_started) ASC) temp)';

    try {
        $stmt = DB::getConnection()->prepare($query);
        $stmt->bindValue(':level', $level);
        $stmt->bindValue(':type', $type);
        $stmt->execute();
    } catch (PDOException $e) {
        log_db_error($query, $e->getMessage());
    }
}

function getTotalRankCounts($level, $type) {
    $query = 'SELECT COUNT(*) FROM users u JOIN ranking r ON u.id = r.user_id AND u.level = r.level WHERE u.active = 1 AND u.level = :level AND r.type = :type';
    try {
        $stmt = DB::getConnection()->prepare($query);
        $stmt->bindValue(':level', $level);
        $stmt->bindValue(':type', $type);
        $stmt->execute();

        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        log_db_error($query, $e->getMessage(), true, true);
    }
}
