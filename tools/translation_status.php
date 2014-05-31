<?php

if (!$_SESSION['user']) {
    die('need to be logged');
}

if (!empty($params['type']) && $params['type'] == 'kanji') {
    $table = 'kanjis';
    $table_ext = 'kanjis_ext';
    $table_ext_idx = 'kanji_id';
    $content_col = 'meaning_';
} else {
    $table = 'jmdict';
    $table_ext = 'jmdict_ext';
    $table_ext_idx = 'jmdict_id';
    $content_col = 'gloss_';
}

$tot = DB::count('SELECT COUNT(*) FROM ' . $table . ' j LEFT JOIN ' . $table_ext . ' jx ON jx.' . $table_ext_idx . ' = j.id');
$jtotals[0] = DB::count('SELECT COUNT(*) FROM ' . $table . ' j LEFT JOIN ' . $table_ext . ' jx ON jx.' . $table_ext_idx . ' = j.id WHERE j.njlpt > 0');

for ($i = 5; $i > 0; $i--) {
    $jtotals[$i] = DB::count('SELECT COUNT(*) FROM ' . $table . ' j LEFT JOIN ' . $table_ext . ' jx ON jx.' . $table_ext_idx . ' = j.id WHERE j.njlpt = ' . $i);
}

echo '<ul>';
foreach (Vocab::$langStrings as $lang => $lang_full) {
    if ($lang == 'en') {
        continue;
    }

    $count = DB::count('SELECT COUNT(*) FROM ' . $table . ' j LEFT JOIN ' . $table_ext . ' jx ON jx.' . $table_ext_idx . ' = j.id WHERE jx.' . $content_col . $lang_full . ' != \'\'');
    $ratio = round(100 * $count / $tot, 2);
    echo '<li style="margin:20px 0 0 0; list-style-type: none;"><img src="' . SERVER_URL . '/img/flags/' . $lang . '.png" style="float:left;margin-right:10px;" alt="flag"/><div style="float:left;margin:4px 6px 0 0;">' . $lang_full . '</div>' . get_progress_bar($ratio,
        600, $count . '/' . $tot) . PHP_EOL;

    echo '<ul>' . PHP_EOL;
    $count = DB::count('SELECT COUNT(*) FROM ' . $table . ' j LEFT JOIN ' . $table_ext . ' jx ON jx.' . $table_ext_idx . ' = j.id WHERE j.njlpt > 0 AND jx.' . $content_col . $lang_full . ' != \'\'');
    $ratio = round(100 * $count / $jtotals[0], 2);
    echo '<li style="margin:1px; list-style-type: none;"> <div style="float:left;margin:4px 6px 0 0;">JLPT</div>' . get_progress_bar($ratio,
        550, $count . '/' . $tot) . '</li>' . PHP_EOL;

    for ($i = 5; $i > 0; $i--) {
        $count = DB::count('SELECT COUNT(*) FROM ' . $table . ' j LEFT JOIN ' . $table_ext . ' jx ON jx.' . $table_ext_idx . ' = j.id WHERE j.njlpt = ' . $i . ' AND jx.' . $content_col . $lang_full . ' != \'\'');
        $ratio = round(100 * $count / $jtotals[$i], 2);
        echo '<li style="margin:1px; list-style-type: none;"><div style="float:left;margin:4px 6px 0 0;">N' . $i . '</div>' . get_progress_bar($ratio,
            500, $count . '/' . $tot) . '</li>' . PHP_EOL;
    }

    echo '</ul></li>' . PHP_EOL;
}

echo '</ul>';
