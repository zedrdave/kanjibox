<?php
if (!$logged_in) {
    force_reload('You cannot access this page directly.', get_page_url('home'), 'You need to be logged into Facebook in order to use this feature.');
    die();
}

include_jquery('pulse');

//TEMP: Winter:
// include_jquery('snowfall');
?>

<iframe src="//www.facebook.com/plugins/like.php?href=http%3A%2F%2Fwww.facebook.com%2Fkanjibox&amp;layout=box_count&amp;show_faces=false&amp;width=250&amp;action=like&amp;colorscheme=light&amp;height=65" scrolling="no" frameborder="0" style="border:none; overflow:hidden; width:60px; height:65px;float:right;" allowTransparency="true"></iframe>
<?php
if ($_SESSION['user']->get_pwd_hash() == 'need_to_reset') {
    echo '<div class="error_msg">Your password needs to be reset. Please use the <em>Change password</em> button below to set your access password.</div>';
}

if ($_SESSION['user']->get_pref('notif', 'post_news') && $_SESSION['user']->getFbID() > 0) {
    global $facebook;
    try {
        if (fb_connect_init()) {
            $perms = $facebook->api('/me/permissions/publish_stream');
            if (!$perms['data'] || !array_key_exists('publish_stream', $perms['data'][0])) {
                $loginUrl = $facebook->getLoginUrl(array('scope' => 'email,publish_stream'));

                echo '<div class="error_msg">You checked the <i>Post highscore updates in news feed</i> option in the settings, however, this app does not have permission to post your result in your Facebook feed.<br/>Please uncheck this option or <a href="' . $loginUrl . '">click here</a> to grant KanjiBox permissions to post to your feed.</div>';
            }
        }
    } catch (Exception $e) {
        echo '<div class="error_msg">Facebook error: please contact the developer if this error persists.</div>';
        if ($_SESSION['user']->isAdministrator()) {
            echo($e->getMessage());
            print_r($perms);
            print_r($_SESSION);
            print_r($_REQUEST);
        }
    }
}
?>
<h2 style="margin-top: 10px;">Hello <?php echo $_SESSION['user']->geFirstName() ?>-san, what a fine day to train in Kanji...</h2>
<?php
if (defined('ADDING') || !$_SESSION['user'] || !$_SESSION['user']->isLoggedIn()) {
    ?>
    <p>Welcome to Kanji Box !</p>
    <p>To start <strong>playing</strong> immediately, just set your <strong>level</strong> below and choose one of the <strong>tabs on the right</strong> ('kana', 'kanji', 'vocab' etc.).</p>
    <p>Feel free to invite your friends to join you on Kanji Box by using <a href="<?php echo get_page_url('invite') ?>">this invite form</a>, but please play nice, use it sparingly and only invite friends who you think may have an interest in learning Japanese.</p>
    <hr/>
    <?php
}
?>
<p>News, updates, community and other cool stuff: head out to <a href="//www.facebook.com/kanjibox">KanjiBox's application page on Facebook</a> ('Become a fan' if you want automatic notifications in your feed).</p>
<p>Got questions? <a href="<?php echo get_page_url('faq') ?>">We got answers</a>....</p>
<p>For more questions (and random banter), head out to <a href="http://kanjibox.net/forum/">KanjiBox's Discussion Board</a>. Use the <a href="//kanjibox.net/forum/categories/kanjibox-online">KB Online section</a> for questions/bug-reports specifically about this part of the site, but feel free to use other sections as well.</p>
<p>In addition to the in-game custom Study Set sections for <a href="/page/play/type/vocab/mode/sets/">Kanji</a> or <a href="//kanjibox.net/kb/page/play/type/kanji/mode/sets/">Vocab</a>, there is now a publicly accessible <a href="//kanjibox.net/kb/sets/">page for browsing and exploring the Study Sets</a> that other users have shared. If you find a set you like, one click is all it takes to bookmark it and start drilling it in KanjiBox.</p>

<?php
$lang_vocab = $_SESSION['user']->get_pref('lang', 'vocab_lang');
$lang_kanji = $_SESSION['user']->get_pref('lang', 'kanji_lang');

if ($_SESSION['user']->is_on_translator_probation()) {
    ?>
    <div style="padding: 5px; border: 1px solid #D11B00; background-color:#FF8803; margin-bottom:20px;"><h3 style="margin:0 0 5px 0">Translator Probation</h3>
        Sorry, but due to repeated flagging of your translations, the translation features on your account have automatically been restricted...<br/>Please use <i>Translator Mode</i> to go over past translations and fix them according to the <a href="/page/international/">translation guidelines</a> before you keep on translating new entries.
    </div>
    <?php
}
?>

<div style="padding: 5px; border: 1px solid #00A; background-color: #DDF;"><h3 style="margin:0 0 5px 0"><i>New:</i> <a href="/page/play/type/text/mode/grammar_sets/">Grammar Sets</a></h3>
    A demo of all Grammar Sets available as add-ons in the <a href="//kanjibox.net/ios/">iOS version of KanjiBox</a> is now available online, <a href="/page/play/type/text/mode/grammar_sets/">in the Text section</a> of the site.<br/>Note: To access this section, you first need to upgrade your account to <a href="//kanjibox.net/kb/page/faq/#elite">Elite</a> status (reminder: all users of KanjiBox on iOS <a href="//kanjibox.net/kb/page/elite/special/ios/">are eligible for a free upgrade</a>).
</div>

<?php
if ($lang_vocab != 'en') {
    $tot = DB::count('SELECT COUNT(*) FROM jmdict j WHERE j.njlpt = ?', [$_SESSION['user']->getLevel()]);
    $translated = DB::count('SELECT COUNT(*) FROM jmdict j LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id WHERE j.njlpt = $level AND jx.gloss_' . Vocab::$lang_strings[$lang_vocab] . ' IS NOT NULL AND jx.gloss_' . Vocab::$lang_strings[$lang_vocab] . ' != \'\'', []);
    $need_work = DB::count('SELECT COUNT(*) AS c FROM jmdict j LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id WHERE j.njlpt = $level AND jx.gloss_' . Vocab::$lang_strings[$lang_vocab] . ' LIKE \'(~)%\'', []);

    $translated -= $need_work;
    $ratio_good = round(100 * $translated / $tot, 1);
    $ratio_need_work = round(100 * $need_work / $tot, 1);

    echo '<div style="margin:5px;">';
    echo "<img src=\"" . SERVER_URL . "/img/flags/$lang_vocab.png\" style=\"float:left;margin-right:10px;\" alt=\"flag\" /> <div style=\"float:left;margin:4px 6px 0 0; width:210px;\">N$level Vocab Translation progress: </div>";

    echo get_progress_bar($ratio_good, 500, "$translated/$tot", $ratio_need_work);
    echo '</div>';
}

if ($lang_kanji != 'en') {
    $tot = DB::count('SELECT COUNT(*) FROM kanjis k WHERE k.njlpt = ?', [$_SESSION['user']->getLevel()]);
    $translated = DB::count('SELECT COUNT(*) FROM kanjis k LEFT JOIN kanjis_ext kx ON kx.kanji_id = k.id WHERE k.njlpt = $level AND kx.meaning_' . Kanji::$langStrings[$lang_kanji] . ' IS NOT NULL AND kx.meaning_' . Kanji::$langStrings[$lang_kanji] . ' != \'\'', []);
    $need_work = DB::count('SELECT COUNT(*) FROM kanjis k LEFT JOIN kanjis_ext kx ON kx.kanji_id = k.id WHERE k.njlpt = $level AND kx.meaning_' . Kanji::$langStrings[$lang_kanji] . ' LIKE \'(~)%\'', []);

    $translated -= $need_work;
    $ratio_good = round(100 * $translated / $tot, 1);
    $ratio_need_work = round(100 * $need_work / $tot, 1);

    echo '<div style="margin:5px;">';
    $ratio = round(100 * $translated / $tot, 1);
    echo "<img src=\"" . SERVER_URL . "/img/flags/$lang_kanji.png\" style=\"float:left;margin-right:10px;\" alt=\"flag\" /> <div style=\"float:left;margin:4px 6px 0 0; width:210px;\">N$level Kanji Translation progress: </div>";

    echo get_progress_bar($ratio_good, 500, "$translated/$tot", $ratio_need_work);
    echo '</div>';
}
?>
<hr/>
<h2>Settings</h2>
<fieldset><legend>Login Info</legend>
    <div id="ajax-result" style="display:none;"></div>
    <div id="login-mail">Email:  <span class="login"><?php echo $_SESSION['user']->get_email(); ?></span> <a href="#" style="font-size:90%" onclick="$('#login-mail').hide();
            $('#set-login-mail').show();
            return false;">[change]</a></div>
    <form id="set-login-mail" class="update-login" action="<?php echo SERVER_URL ?>ajax/update_login_info/" method="post">
        Login (your email): <input type="text" size="40" name="set_login" id="set_login" value="<?php echo $_SESSION['user']->get_email(); ?>" />
        <input type="submit" name="submit-new-login" id="submit-new-login" value="Set Login" />
    </form>
    <p id="set-password-link"><a href="#" onclick="$('#set-password').show();
            $('#set-password-link').hide();
            return false;">[change password]</a></p>
    <form id="set-password" class="update-login" action="<?php echo SERVER_URL ?>ajax/update_login_info/" method="post">
        Password: <input type="password" name="set_password" id="set_password" size="10" /> |	Password again: <input type="password" name="set_password_repeat" id="set_password_repeat" size="10" /> <input type="submit" name="submit-new-password" value="Set Password" />
    </form>
    <?php
    if ($_SESSION['user']->getFbID() == 0 || $_SESSION['user']->geFirstName() == '') {
        ?>
        <p id="set-name-link">Name (<em>optional</em>): <span class="name_info" id="first_name"><?php echo $_SESSION['user']->geFirstName() ?></span> <span class="name_info" id="last_name"><?php echo $_SESSION['user']->get_last_name() ?></span> <a href="#" onclick="$('#set-name').show();
                $('#set-name-link').hide();
                return false;">[change name]</a></p>
        <form id="set-name" class="update-login" action="<?php echo SERVER_URL ?>ajax/update_login_info/" method="post">
            <input type="text" name="set_first_name" id="set_first_name" size="16" placeholder="First Name"/> <input type="text" name="set_last_name" id="set_last_name" size="16" placeholder="Last Name"/> <input type="submit" name="change-name" value="Save" />
        </form>
        <?php
    }
    ?>
    <script type="text/javascript">
        $(document).ready(function() {
<?php
if ($_SESSION['user']->is_pwd_empty()) {
    ?>
                $('#ajax-result').show().html("<em>You don't have any password set. Use the button below to set a password.</em>");
    <?php
}
?>

            $('#set-password').ajaxForm({
                beforeSubmit: function(msg, jqForm) {

                    if ($('input#set_password').val() == '') {
                        $('#ajax-result').show().html('<div class="error_msg">Empty password...</div>')
                        setTimeout(function() {
                            $('#ajax-result').hide()
                        }, 2000);
                        $('#set-password').hide();
                        $('#set-password-link').show();
                        return false;
                    }

                    if ($('input#set_password').val() != $('input#set_password_repeat').val()) {
                        $('#ajax-result').show().html('<div class="error_msg">Your two password fields do not match...</div>')
                        setTimeout(function() {
                            $('#ajax-result').hide()
                        }, 2000);
                        return false;
                    }

                    $('#set-password input').prop('disabled', 'disabled');
                    return true;
                },
                success: function(msg, statusText, jqForm) {
                    $('#set-password').hide();
                    $('#set-password input').prop('disabled', '');
                    $('#set-password-link').show();
                    $('#ajax-result').show().html(msg)
                    setTimeout(function() {
                        $('#ajax-result').hide()
                    }, 2000);
                    // alert(msg);

                },
            });

            $('#set-login-mail').ajaxForm({
                beforeSubmit: function(msg, jqForm) {
                    if ($('span.login').html() == $('input#set_login').val()) {
                        $('#set-login-mail').hide();
                        $('#login-mail').show();
                        return false;
                    }

                    if ($('input#set_login').val() == '') {

                        $('#ajax-result').show().html('<div class="error_msg">Please enter a valid email as your login...</div>')
                        setTimeout(function() {
                            $('#ajax-result').hide()
                        }, 2000);
                        $('#set-login-mail').hide();
                        $('#login-mail').show();

                        return false;
                    }

                    $('#set-login-mail input').prop('disabled', 'disabled');

                    return true;
                },
                success: function(msg, statusText, jqForm) {
                    $('#set-login-mail').hide();
                    $('#set-login-mail input').prop('disabled', '');
                    $('#login-mail').show();
                    $('#ajax-result').show().html(msg)

                    success = $('.success_msg', msg);
                    if (success && success.length > 0)
                        $('span.login').html($('input#set_login').val());
                    else
                        $('input#set_login').val($('span.login').html());

                    setTimeout(function() {
                        $('#ajax-result').hide()
                    }, 2000);

                    // alert(msg);
                    //     jqForm.$('a').html(new_translation);
                },
            });

            $('#set-name').ajaxForm({
                beforeSubmit: function(msg, jqForm) {
                    if ($('input#first_name').val() == '') {
                        $('#ajax-result').show().html('<div class="error_msg">Please enter a first name...</div>')
                        setTimeout(function() {
                            $('#ajax-result').hide()
                        }, 2000);
                        $('#set-name').hide();
                        $('#set-name-link').show();
                        return false;
                    }
                    $('#set-name input').prop('disabled', 'disabled');
                    return true;
                },
                success: function(msg, statusText, jqForm) {
                    $('#set-name').hide();
                    $('#set-name input').prop('disabled', '');
                    $('#set-name-link').show();
                    $('#ajax-result').show().html(msg)

                    success = $('.success_msg', msg);
                    if (success && success.length > 0) {
                        $('span#first_name').html($('input#set_first_name').val());
                        $('span#last_name').html($('input#set_last_name').val());
                    }
                    else {
                        $('input#set_first_name').val($('span#first_name').html());
                        $('input#set_last_name').val($('span#last_name').html());
                    }
                    setTimeout(function() {
                        $('#ajax-result').hide()
                    }, 2000);

                    // alert(msg);
                    //     jqForm.$('a').html(new_translation);
                },
            });


        })
    </script>
</fieldset>
<form  class="settings" method="post" action="<?php echo APP_URL; ?>">
    <fieldset><legend>Level</legend>
        You are training for level: <select name="level" id="level" onchange="show_and_blink('save_prefs');
                        return true;" clickthrough="true" ><?php
                                                $levels = Session::$level_names;
                                                foreach ($levels as $level => $label)
                                                    echo "<option value=\"$level\"" . ($_SESSION['user']->getLevel() == $level ? ' selected' : ' ') . ">$label</option>"
                                                    ?></select>
    </fieldset>
    <p style="text-align:center;"><a href="#" id="more-options-link" onclick="$('#more-options').show('slow');
            $(this).hide();
            return false;">Show all settings...</a></p>
    <div id="more-options" style="display:none;">
        <fieldset><legend>General Options</legend>
            <?php print_prefs('general'); ?>
        </fieldset>
        <fieldset><legend>Drill Mode</legend>
            <?php print_prefs('drill'); ?>
        </fieldset>
        <fieldset><legend>Quiz Mode</legend>
            <?php print_prefs('quiz'); ?>
        </fieldset>
        <fieldset><legend>Notifications</legend>
            <?php print_prefs('notif'); ?>
        </fieldset>
        <fieldset><legend>Language <i>(experimental)</i></legend>
            <?php print_prefs('lang'); ?>
        </fieldset>
    </div>
    <center><input type="submit"  id="save_prefs" name="save_prefs" value="Save" style="display:none;"/></center>
</form>
<hr/>
<h2><a href="#" onclick="$('#exportprintouts').slideToggle();
        return false;">Export printouts</a></h2>
<div id="exportprintouts" style="display:none;">
    <fieldset><legend>Kanjis</legend>
        <form method="post" action="<?php echo SERVER_URL; ?>export/printout.php" target="printout">
            <input type="submit" name="printout" value="Generate list" />
            of kanjis (include up to <select name="examples" id="examples"><option value="0" selected>no</option><option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option></select> examples), level <select id="njlpt" name="njlpt">
                <option value="5">N5</option>
                <option value="4">N4</option>
                <option value="3">N3</option>
                <option value="2">N2</option>
                <option value="1">N1</option>
            </select> and below,
            where your current score is <select id="curve" name="curve">
                <option value="1500">Very Bad</option>
                <option value="1050" selected>Bad</option>
                <option value="950">OK</option>
                <option value="500">Good</option>
                <option value="0">Very Good</option>
            </select> (or worse)
            <br/>(<input type="checkbox" name="newtoo" id="newtoo" value="1"/> include those you have never been asked).
            <input type="hidden" name="type" id="type" value="kanji" />
        </form>
    </fieldset>
    <fieldset><legend>Vocab</legend>
        <form method="post" action="<?php echo $server_url; ?>export/printout.php" target="printout">
            <input type="submit" name="printout" value="Generate list" />
            of vocabulary, level <select id="njlpt" name="njlpt">
                <option value="5">N5</option>
                <option value="4">N4</option>
                <option value="3">N3</option>
                <option value="2">N2</option>
                <option value="1">N1</option>
            </select> and below,
            where your current score is <select id="curve" name="curve">
                <option value="1500">Very Bad</option>
                <option value="1050" selected>Bad</option>
                <option value="950">OK</option>
                <option value="500">Good</option>
                <option value="0">Very Good</option>
            </select> (or worse)
            <input type="hidden" name="type" id="type" value="vocab" />
            <br/>(<input type="checkbox" name="newtoo" id="newtoo" value="1"/> include those you have never been asked).
        </form>
    </fieldset>
</div>
<hr/>
<?php
if (!empty($_SESSION['user'])) {
    $messagesCount = DB::count('SELECT COUNT(*) FROM `messages` WHERE msg_read = 1 AND user_id_to = ?', [$_SESSION['user']->getID()]);
    if ($messagesCount > 0) {
        ?>
        <h2><a href="#" onclick="do_load('<?php echo SERVER_URL ?>ajax/get_messages/show/read/', 'old-kb-msgs');
                return false;">Past Messages</a></h2>
        <div id="old-kb-msgs"></div>
        <hr/>
        <?php
    }
}
?>
<p>This app wouldn't be possible without Jim Breen's awesome work and Monash University's <a href="http://www.csse.monash.edu.au/~jwb/cgi-bin/wwwjdic.cgi?1C">WWWJDIC</a>.</p>
<div class="fb-logout">
    <fb:login-button length="long" background="light" autologoutlink="true" size="medium" onlogin="window.location.reload();"></fb:login-button>
</div>
<br/>