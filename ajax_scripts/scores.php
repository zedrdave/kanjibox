<?php
if (!empty($_SESSION['user']) && !$_SESSION['user']->isLoggedIn()) {
    log_error('is_logged_in() == false, in scores.php', true);
    log_error('You need to be logged to access this function.', false, true);
}

require_once ABS_PATH . 'pages/scores.php';

global $api_key;
?>
<script type="text/javascript">
    $(document).ready(function()
    {
        FB.XFBML.parse(document.getElementById('frame-highscores'));
    });
</script>
