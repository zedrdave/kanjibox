<?php
require_once 'libs/lib.php';
?>
<h1>KanjiBox Badges</h1>
<em>To tell the world about your glorious KanjiBox ranking, simply copy the code from one of the boxes on the right and paste it into any HTML page (blog/newboard/online profile etc).<br/>Don't hesitate to post a link on <a href="http://www.facebook.com/kanjibox?v=wall">KanjiBox's wall</a> if you find an interesting way to display your badge somewhere...</em>

<?php
if (!empty($_SESSION['user']) && $_SESSION['user']->isElite()) {
    $kb_types = array('kanji', 'vocab', 'reading', 'text');
} else {
    $kb_types = array('kanji', 'vocab', 'reading');
}
foreach ($kb_types as $kb_type) {
    $rank_array = $_SESSION['user']->getRank($kb_type, false, 3600);
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
