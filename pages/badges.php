<?php

function get_badge($rank_array, $caption_type) {
    $kb_type = $rank_array->type;
    $rank = $rank_array->name_array[0];
    $rank_nice = $rank_array->name_array[1];
    $levels = array(LEVEL_N5 => '5級', LEVEL_N4 => '4級', LEVEL_N3 => '3級', LEVEL_N2 => '2級', LEVEL_N1 => '1級', LEVEL_SENSEI => '先生');
    if (!isset($rank_array->level)) {
        $rank_array->level = $_SESSION['user']->get_level();
    }
    $level_jp = $levels[$rank_array->level];
    $level = Session::$level_names[$rank_array->level];

    switch ($caption_type) {
        case 0:
        default:
            $caption = 'I am ' . ($rank == 'shougun' ? 'the' : 'a') . ' <a href="http://kanjibox.net" style="color:#EEE;text-decoration:none;" title="Kanji Box: ' . $level . '">KanjiBox ' . $rank_nice . ' (' . $level_jp . ')</a>';
            break;
        case 1:
            $caption = '<a href="http://kanjibox.net" style="color:#EEE;text-decoration:none;">' . $level . ' ' . ucwords($kb_type) . ' ' . $rank_nice . '</a>';
            break;
        case 2:
            $caption = 'I rank #' . $rank_array->rank . ' on <a href="http://kanjibox.net" style="color:#EEE;text-decoration:none;" title="Kanji Box - ' . ucwords($kb_type) . ' - ' . $level . '">KanjiBox (' . $level_jp . ')</a>';
            break;
        case 3:
            $caption = 'I am ' . ($rank == 'shougun' ? 'the' : 'a') . ' <a href="http://kanjibox.net" style="color:#EEE;text-decoration:none;" title="Kanji Box: ' . ucwords($kb_type) . '">' . ucwords($kb_type) . ' ' . $rank_nice . ' (' . $level_jp . ')</a>';
            break;
    }
    return '<div style="border:1px solid black;width:200px;padding:0;background:#5265E4 url(\'http://kanjibox.net/kb/img/badges/kb_badge_bground.png\');"><p style="height:16px;padding:1px;font-size:18px;font-weight:bold;text-align:center;margin:0;"><a href="http://kanjibox.net" style="color:#DDD;text-decoration:none;" title="Kanji Box">KanjiBox</a></p><div style="height:100px;"><a href="http://kanjibox.net" style="border:0;"><img src="http://kanjibox.net/kb/img/badges/' . $rank . '.png" alt="KanjiBox ' . $rank . '" style="border:0;" /></a></div><p style="height:16px;font-weight:bold;padding:3px 1px 3px 1px;color:#FFF;font-size:13px;text-align:center;margin:0;">' . $caption . '</p></div>';
}
?>
<h1>KanjiBox Badges</h1>
<em>To tell the world about your glorious KanjiBox ranking, simply copy the code from one of the boxes on the right and paste it into any HTML page (blog/newboard/online profile etc).<br/>Don't hesitate to post a link on <a href="http://www.facebook.com/kanjibox?v=wall">KanjiBox's wall</a> if you find an interesting way to display your badge somewhere...</em>

<?php
if ($_SESSION['user']->is_elite()) {
    $kb_types = array('kanji', 'vocab', 'reading', 'text');
} else {
    $kb_types = array('kanji', 'vocab', 'reading');
}
foreach ($kb_types as $kb_type) {
    $rank_array = $_SESSION['user']->get_rank($kb_type, false, 3600);
    if (!$rank_array) {
        continue;
    }
    if (!isset($rank_array->type)) {
        $rank_array->type = $kb_type;
    }
    ?>
    <h2>Mode: <?php echo ucwords($kb_type) ?></h2>

    <?php
    for ($type = 0; $type <= 3; $type++) {
        ?>
        <div style="margin: 10px 10px 10px 50px;">
            <div style="float:left; margin-right: 20px">
                <?php echo get_badge($rank_array, $type); ?>
            </div>
            <textarea style="float:left; width: 350px; height: 140px;"><?php echo htmlentities(get_badge($rank_array, $type), ENT_COMPAT, 'UTF-8'); ?></textarea>
            <div style="clear:both"></div>
        </div>
        <?php
    }
}
?>
<script type="text/javascript">

    $(document).ready(function()
    {
        $('textarea').click(function() {
            this.select();
            return false;
        })
    });
</script>
