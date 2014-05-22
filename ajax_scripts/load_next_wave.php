<?php
if (empty($_SESSION['user'])) {
    log_error('Empty SESSION[user] in load_next_wave.php', true);
    log_error('You need to be logged to access this function.', false, true);
}

if (empty($_SESSION['cur_session'])) {
    force_reload('Game session has timed out.');
}

$_SESSION['cur_session']->loadNextWave();

if (!$_SESSION['cur_session']->isQuiz() && $_SESSION['user']->isLoggedIn() && $_SESSION['user']->getPreference('drill',
        'show_learning_stats')) {
    ?>
    <script type="text/javascript">
        do_load('<?php echo SERVER_URL . 'ajax/footer_stats_bar/type/' . $_SESSION['cur_session']->getType() . ($_SESSION['cur_session']->isLearningSet() ? '/mode/' . SETS_MODE : '');?>/', 'footer-stats');
    </script>
    <?php
}

if (!$_SESSION['cur_session']->displayWave()) {
    $_SESSION['cur_session']->cleanupBeforeDestroy();
    $_SESSION['cur_session'] = NULL;
}