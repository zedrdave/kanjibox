<?php

require_elite_user();

require_once ABS_PATH . 'libs/stats_lib.php';
include_css('stats.css');

$ranks = User::get_ranks();
$levels = Session::$level_names;

foreach ($levels as $level => $level_name) {
    echo get_rank_pop_table($level);
}

function get_rank_pop_table($level) {
    $is_admin = $_SESSION['user']->is_admin();


    $table = '<br/><table class="rankstats"><caption>' . Session::$level_names[$level] . '</caption>';
    $table .= '<tr class="header"><th style="border:none;"></th>';

    $types = array(TYPE_KANJI, TYPE_VOCAB, TYPE_READING, TYPE_TEXT);

    foreach ($types as $type) {
        $table .= '<th>' . $type . '</th>';
        $tot = get_tot_rank_counts($level, $type);
        $pops[$type] = get_rank_population($tot);
    }

    $table .= '</tr>';
    foreach ($pops[TYPE_KANJI] as $rank => $junk) {
        $table .= '<tr>';
        $table .= '<th>' . $rank . '</th>';
        foreach ($types as $type) {
            if ($rank != 'gokiburi' || $is_admin) {
                $table .= '<td>' . ($pops[$type][$rank] ? $pops[$type][$rank] : '') . '</td>';
            } else {
                $table .= '<td>...</td>';
            }
        }
        $table .= '</tr>';
    }
    $table .= '</table>';

    return $table;
}

function get_rank_population($total) {
    foreach (User::get_ranks() as $rank => $rank_long) {
        $pops[$rank] = 0;
    }

    $last_cutoff = 0;

    foreach (User::$ranks_abs as $cutoff => $rank) {
        if ($last_cutoff <= $total) {
            $pops[$rank[0]] = min($cutoff, $total) - $last_cutoff;
        }
        $last_cutoff = $cutoff;
    }

    foreach (User::$ranks_rel as $rank) {
        $cutoff = floor((float) $rank[2] * $total);

        if ($cutoff > $last_cutoff) {
            if ($last_cutoff <= $total) {
                $pops[$rank[0]] = min($cutoff, $total) - $last_cutoff;
            }
            $last_cutoff = $cutoff;
        }
    }

    if ($last_cutoff < $total) {
        $pops[User::$default_rank[0]] = $total - $last_cutoff;
    }

    return $pops;
}
