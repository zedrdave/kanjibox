<?php
if (empty($_SESSION['user'])) {
    log_error('Empty SESSION[user] in submit_answer.php', true);
    log_error('You need to be logged to access this function.', false, true);
}

if (empty($_SESSION['cur_session'])) {
    force_reload('Game session has timed out.');
}

$sid = $_REQUEST['sid'];
$answer_id = $_REQUEST['answer_id'];
$time = (int) min(30, $_REQUEST['countdown']);

$div_id = 'sol_' . $sid;
$class = $_SESSION['cur_session']->display_solution($sid, $answer_id);


switch ($class) {
    case 'correct':
        $timeout = 3000;
        break;
    case 'skipped':
        $timeout = 5000;
        break;
    case 'wrong':
        $timeout = 7000;
        break;

    case '*duplicate*':
    case '*unknown*':
        die(' ');
        break;
}

if (!$_SESSION['user']->get_pref('general', 'auto_vanish'))
    $timeout = 1000 * 120;

$_SESSION['cur_session']->register_answer($sid, $answer_id, $time);
$load_next = $_SESSION['cur_session']->all_answered();

if ($load_next)
    $timeout = min(5000, (int) ($timeout / 2));

$keep_last_n_sols = 2;
?>
<script type="text/javascript">

<?php
if ($_SESSION['cur_session']->is_quiz()) {
    ?>
        $('#score').html("<?php echo $_SESSION['cur_session']->get_score_str() ?>");

    <?php
}
?>

    function hide_<?php echo $div_id ?>()
    {
        mydiv = $('#<?php echo $div_id ?>');
        if (!mydiv)
            return;

        if (mydiv.data('keep_onscreen'))
        {
            mydiv.data('expired', true);
<?php
if ($load_next)
    echo 'mydiv.prepend(\'<p class="dismiss-warning">- Click here to load next wave -</p>\');';
else
    echo 'mydiv.prepend(\'<p class="dismiss-warning">- Click to dismiss -</p>\');';
?>
            return;
        }

        mydiv.hide(500);

<?php
if ($load_next)
    echo "do_load('" . SERVER_URL . "ajax/load_next_wave/', 'session_frame');";
?>
    }


    $(document).ready(function()
    {
        mydiv = $('#<?php echo $div_id ?>');
        if (!mydiv)
            return;
        setTimeout(hide_<?php echo $div_id ?>, <?php echo (int) $timeout; ?>);

        //	mydiv.mouseover(mouseover_<?php echo $div_id ?>);
        //	mydiv.mouseout(mouseout_<?php echo $div_id ?>);
        mydiv.click(function() {
            if ($(this).data('keep_onscreen'))
            {
                $(this).removeClass('keep-on-screen');
                $(this).data('keep_onscreen', false);
                if ($(this).data('expired'))
                    hide_<?php echo $div_id ?>();
            }
            else
            {
                $(this).addClass('keep-on-screen');
                $(this).data('keep_onscreen', true);
            }
        })

        $('.more_link', mydiv).click(function(e) {
            e.stopPropagation(); // for stop the click action (event)
            return false;
        });
        $('.kanji_detail', mydiv).click(function(e) {
            e.stopPropagation(); // for stop the click action (event)
            return false;
        });
        $('.tts-link', mydiv).click(function(e) {
            e.stopPropagation(); // for stop the click action (event)
            return false;
        });

        $('.ui-state-default', mydiv).click(function(e) {
            e.stopPropagation(); // for stop the click action (event)
            return false;
        });

        $('a.remove-from-set', mydiv).click(function(e) {
            e.stopPropagation(); // for stop the click action (event)
            return false;
        });

    });



</script>
<?php
flush();

if ($load_next) {
    $_SESSION['cur_session']->load_next_wave();
}