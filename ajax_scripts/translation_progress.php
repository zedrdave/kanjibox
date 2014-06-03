<?php

if (!$_SESSION['user']) {
    die('need to be logged');
}

if (!empty($params['type']) && $params['type'] == 'kanji') {
    $table = 'kanjis';
    $table_ext = 'kanjis_ext';
    $table_ext_idx = 'kanji_id';
    $content_col = 'meaning_';
    $langs = Kanji::$langStrings;
} else {
    $table = 'jmdict';
    $table_ext = 'jmdict_ext';
    $table_ext_idx = 'jmdict_id';
    $content_col = 'gloss_';
    $langs = Vocab::$langStrings;
}

function my_mysql_query($query)
{
    $start = microtime();
    $ret = mysql_query($query);
    $took = (microtime() - $start);
    // if($took > 0.001)
    //     echo 'took: ' . $took . ' - ' . $query . '<br/>';
    return $ret;
}
$tot = DB::count('SELECT COUNT(*) FROM ' . $table . ' j');
$jtotals[0] = DB::count('SELECT COUNT(*) FROM ' . $table . ' j WHERE j.njlpt > 0');

for ($i = 5; $i > 0; $i--) {
    $jtotals[$i] = DB::count('SELECT COUNT(*) FROM ' . $table . ' j WHERE j.njlpt = ' . $i);
}
?>
<p style="font-style:italic;">Red bars indicate items for which translations have been submitted that did not meet the translation guidelines (e.g. improper typography, missing senses etc) and need to be revised. <em>Please</em> <a style="font-weight:bold; color:#33A;" href="http://kanjibox.net/kb/page/international/#guidelines">read the guidelines</a> before you start translating.</p>
<?php

echo '<ul>';
foreach ($langs as $lang => $lang_full) {
    if ($lang == 'en') {
        continue;
    }

    $row_need_work = DB::count('SELECT COUNT(*) FROM ' . $table_ext . ' jx WHERE jx.' . $content_col . $lang_full . ' LIKE \'(~)%\'');

    $res = my_mysql_query('SELECT COUNT(*) FROM ' . $table_ext . ' jx WHERE jx.' . $content_col . $lang_full . ' != \'\'');
    $row = mysql_fetch_object($res);

    $row->c -= (int) $row_need_work;

    $ratio_good = round(100 * $row->c / $tot, 2);
    $ratio_need_work = round(100 * $row_need_work / $tot, 2);

    echo "<li style=\"margin:20px 0 0 0; list-style-type: none;\"><img src=\"" . SERVER_URL . "/img/flags/$lang.png\" style=\"float:left;margin-right:10px;\" alt=\"flag\" /> <div style=\"float:left;margin:4px 6px 0 0;\">$lang_full</div> " . get_progress_bar($ratio_good,
        600, "$row->c/$tot", $ratio_need_work) . "</div>";

    echo '<ul>';
    $res = my_mysql_query("SELECT COUNT(*) AS c FROM $table j LEFT JOIN $table_ext jx ON jx.$table_ext_idx = j.id WHERE j.njlpt > 0 AND jx.$content_col$lang_full LIKE '(~)%'");
    $row_need_work = mysql_fetch_object($res);

    $res = my_mysql_query("SELECT COUNT(*) AS c FROM $table j LEFT JOIN $table_ext jx ON jx.$table_ext_idx = j.id WHERE j.njlpt > 0 AND jx.$content_col$lang_full != ''");
    $row = mysql_fetch_object($res);
    $row->c -= (int) $row_need_work->c;

    $ratio_good = round(100 * $row->c / $jtotals[0], 2);
    $ratio_need_work = round(100 * $row_need_work->c / $jtotals[0], 2);

    echo "<li style=\"margin:1px; list-style-type: none;\"> <div style=\"float:left;margin:4px 6px 0 0;\">JLPT</div> " . get_progress_bar($ratio_good,
        550, "$row->c/$tot", $ratio_need_work) . "</div></li>";

    for ($i = 5; $i > 0; $i--) {
        $res = my_mysql_query("SELECT COUNT(*) AS c FROM $table j LEFT JOIN $table_ext jx ON jx.$table_ext_idx = j.id WHERE j.njlpt = $i AND jx.$content_col$lang_full LIKE '(~)%'");
        $row_need_work = mysql_fetch_object($res);
        $res = my_mysql_query("SELECT COUNT(*) AS c FROM $table j LEFT JOIN $table_ext jx ON jx.$table_ext_idx = j.id WHERE j.njlpt = $i AND jx.$content_col$lang_full != ''");
        $row = mysql_fetch_object($res);
        $row->c -= (int) $row_need_work->c;

        $ratio_good = round(100 * $row->c / $jtotals[$i], 2);
        $ratio_need_work = round(100 * $row_need_work->c / $jtotals[$i], 2);

        echo "<li style=\"margin:1px; list-style-type: none;\"> <div style=\"float:left;margin:4px 6px 0 0;\">N$i</div> " . get_progress_bar($ratio_good,
            500, "$row->c/" . $jtotals[$i], $ratio_need_work) . "</div></li>";
    }

    echo '</ul></li>';
}
echo '</ul>';
