<?php

if (!$_SESSION['user'] || !$_SESSION['user']->isElite()) {
    die('need to be at least elite');
}
mb_internal_encoding('UTF-8');

$apply = $_SESSION['user']->isEditor();

if (isset($_REQUEST['delete_id'])) {
    if (!$_SESSION['user']->isAdministrator()) {
        die('Not allowed to delete entries...');
    }
    if ($_REQUEST['delete_jmdict_id']) {
        $sentID = (int) $_REQUEST['delete_id'];
        $jmdictID = (int) $_REQUEST['jmdict_id'];
        DB::delete('DELETE FROM example_answers WHERE example_id = :example_id AND jmdict_id = :jmdict_id LIMIT 1',
            [':example_id' => $sentID, ':jmdict_id' => $jmdictID]);

        DB::insert('INSERT INTO data_update_queries SET user_id = :user_id, query_str = :query, applied = 1',
            [':user_id' => $_SESSION['user']->getID(), ':query' => $query]);
        echo '<div class="message">Deleted answer: (' . $sentID . ', ' . $jmdictID . ')</div>';
    } else {
        $sentID = (int) $_REQUEST['delete_id'];
        DB::insert('INSERT INTO data_update_queries SET user_id = :user_id, query_str = :query, applied = 1',
            [':user_id' => $_SESSION['user']->getID(), ':query' => $query]);
        DB::delete('DELETE FROM examples WHERE example_id = :sentID LIMIT 1', [':sentID' => $sentID]);
        echo '<div class="message">Deleted sentence ID: ' . $sentID . '</div>';
        DB::insert('INSERT INTO data_update_queries SET user_id = :user_id, query_str = :query, applied = 1',
            [':user_id' => $_SESSION['user']->getID(), ':query' => $query]
        );
        $rows = DB::delete('DELETE FROM example_parts WHERE example_id = :sentID', [':sentID' => $sentID]);
        echo '<div class="message">Deleted ' . $rows . ' sentence parts.</div>';
        return;
    }
}
if (isset($_REQUEST['create'])) {
    if (empty($_REQUEST['example_str'])) {
        echo '<div class="message">Error: please provide at least Japanese text.</div>';
        return;
    } elseif (mysql_query("INSERT INTO examples SET example_str = '" . DB::getConnection()->quote($_REQUEST['example_str']) . "', english = '" . DB::getConnection()->quote($_REQUEST['english']) . '\', status = \'kanjibox\'')) {
        $example_id = mysql_insert_id();

        echo '<div class="message">Created new sentence ID: ' . $example_id . '</div>';

        $sentence = $_REQUEST['example_str'];

        require_once ABS_PATH . 'libs/mecab_lib.php';

        $queries = parse_jp_sentence($sentence, true, true, $example_id);

        array_walk($queries, function(&$n) {
            $n = '(' . implode(',', $n) . ')';
        });

        $query = "INSERT INTO example_parts (example_id, jmdict_id, part_num, pos_start, pos_end, need_furi, proper_noun) VALUES " . implode(',',
                $queries);
        mysql_query($query) or die(mysql_error());
        echo '<div class="message">Inserted : ' . count($queries) . ' sentence parts <a href="#" onclick="$(\'.debug\').show(); return false;">[show debug]</a></div>';

        $query = "UPDATE examples e SET e.njlpt = (SELECT MIN(j.njlpt) FROM example_parts ep LEFT JOIN jmdict j ON j.id = ep.jmdict_id WHERE ep.example_id = $example_id), e.njlpt_r = (SELECT IFNULL(MIN(j.njlpt_r), 5) FROM example_parts ep LEFT JOIN jmdict j ON j.id = ep.jmdict_id  WHERE ep.example_id = $example_id AND need_furi = 1) WHERE e.example_id = $example_id LIMIT 1";
        mysql_query($query) or die(mysql_error());

        $_REQUEST['id'] = $example_id;
        $params['edit'] = true;
        $params['show_vocab'] = true;
        require_once('get_sentence.php');
        return;
    } else {
        echo '<div class="message">DB Error: ' . mysql_error() . '</div>';
    }
}

if (isset($_REQUEST['id'])) {
    $changes = 0;
    echo '<div class="message">';
    foreach (['example_str', 'example_var', 'english', 'annotation', 'njlpt', 'njlpt_r', 'status'] as $field) {
        if (isset($_REQUEST[$field])) {
            if ($field == 'status')
                $ret = post_db_correction('examples', 'example_id', (int) $_REQUEST['id'], $field, $_REQUEST[$field],
                    true, '', '', true);
            else
                $ret = post_db_correction('examples', 'example_id', (int) $_REQUEST['id'], $field, $_REQUEST[$field],
                    $apply);
            if ($ret != 'Value unchanged') {
                echo $ret . '<br/>';
                $changes++;
            }
        }
    }

    if (isset($_REQUEST['pos_end']) && isset($_REQUEST['merge_until_pos_end'])) {

        DB::delete('DELETE FROM example_parts WHERE example_id = :example_id AND pos_start >= :pos_start AND pos_start < :merge_until_pos_end LIMIT 1',
            [
            ':example_id' => $_REQUEST['id'],
            'pos_start' => $_REQUEST['pos_end'],
            'merge_until_pos_end' => $_REQUEST['merge_until_pos_end']
            ]
        );

        mysql_query("UPDATE example_parts SET pos_end = " . (int) $_REQUEST['merge_until_pos_end'] . " WHERE example_id = " . (int) $_REQUEST['id'] . " AND pos_end = " . (int) $_REQUEST['pos_end']);

        echo 'Fragments merged';
    }
    if (isset($_REQUEST['pos_start']) && isset($_REQUEST['new_pos_start']) && isset($_REQUEST['new_pos_end'])) {

        mysql_query("UPDATE example_parts SET pos_start = " . (int) $_REQUEST['new_pos_start'] . ", pos_end = " . (int) $_REQUEST['new_pos_end'] . " WHERE example_id = " . (int) $_REQUEST['id'] . " AND pos_start = " . (int) $_REQUEST['pos_start']) or die(mysql_error());
        echo "Changed position to: (" . (int) $_REQUEST['new_pos_start'] . ", " . (int) $_REQUEST['new_pos_end'] . ")";
    }
    if (isset($_REQUEST['pos_start']) && isset($_REQUEST['delete_jmdict_id'])) {

        mysql_query("DELETE FROM example_parts WHERE example_id = " . (int) $_REQUEST['id'] . " AND pos_start = " . (int) $_REQUEST['pos_start'] . " LIMIT 1") or die(mysql_error());
        echo "Deleted fragment at position: " . (int) $_REQUEST['pos_start'];
    } elseif (isset($_REQUEST['pos_start']) && isset($_REQUEST['split_at_pos'])) {

        $res = mysql_query("SELECT ep.*, SUBSTRING(e.example_str, ep.pos_start+1, ep.pos_end-ep.pos_start) AS fragment FROM example_parts ep LEFT JOIN examples e ON e.example_id = ep.example_id WHERE ep.example_id = " . (int) $_REQUEST['id'] . " AND ep.pos_start = " . (int) $_REQUEST['pos_start']) or die(mysql_error());

        $row = mysql_fetch_object($res);
        if ($row) {
            $word = $row->fragment;
            $need_furi_1 = (int) !(preg_match('/[^\p{Hiragana}\p{Katakana}ー・〜？！。０-９0-9a-zA-Z＝「」]/u',
                    mb_substr($word, 0, $_REQUEST['split_at_pos']), $matches) == 0);
            $need_furi_2 = (int) !(preg_match('/[^\p{Hiragana}\p{Katakana}ー・〜？！。０-９0-9a-zA-Z＝「」]/u',
                    mb_substr($word, $_REQUEST['split_at_pos']), $matches) == 0);

            $query = "UPDATE example_parts SET part_num = part_num+1 WHERE example_id = $row->example_id AND part_num > $row->part_num";
            mysql_query($query) or die(mysql_error());

            $query = "UPDATE example_parts SET pos_end = " . (int) ($row->pos_start + $_REQUEST['split_at_pos']) . ", need_furi = $need_furi_1 WHERE example_id = $row->example_id AND pos_start = $row->pos_start";
            mysql_query($query) or die(mysql_error());

            DB::insert('INSERT INTO example_parts SET example_id = :example_id, jmdict_id = :jmdict_id, part_num = :part_num, pos_start = :pos_start, pos_end = :pos_end, need_furi = :need_furi_2',
                [
                'example_id' => $row->example_id,
                ':jmdict_id' => $row->jmdict_id,
                'part_num' => ($row->part_num + 1),
                'pos_start' => ($row->pos_start + $_REQUEST['split_at_pos']),
                'pos_end' => $row->pos_end,
                'need_furi_2' => $need_furi_2
                ]
            );

            echo 'Fragment split.';
        } else {
            echo 'Can\'t find this fragment.';
        }
    } elseif (isset($_REQUEST['pos_start']) && isset($_REQUEST['new_jmdict_id'])) {

        $ret = post_db_correction('example_parts', 'example_id', (int) $_REQUEST['id'], 'jmdict_id',
            (int) $_REQUEST['new_jmdict_id'], $apply, 'pos_start', (int) $_REQUEST['pos_start']);
        if ($ret != 'Value unchanged') {
            echo $ret . '<br/>';
            $changes++;
        }

        if ($changes == 0) {
            echo 'No changes';
        }
    }

    echo '</div>';

    if (!isset($_REQUEST['short_reply'])) {
        $params['edit'] = true;
        $params['show_vocab'] = true;

        require_once 'get_sentence.php';
    }
} else {
    echo 'No id provided';
}