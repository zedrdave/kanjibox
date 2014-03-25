<?php
require_once 'libs/lib.php';
require_once get_mode() . '.config.php';

if ($hard_hat_db_down) {
    ?>
    <html>
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
            <title>Temporarily Down</title>
        </head>
        <body>
            <div class="error_msg">
                <img src="<?php echo SERVER_URL . 'img/construction_jp.gif' ?>" class="justified" style="height: 150px; width: 150px;" />
                <div class="title" title="Update Work in Progress">工事中</div>
                <p>I am in the midst of modifying some application files. You might experience transient errors while I do so. Please wait until this sign disappears before reporting any issues.<br/><br/>
                    Thanks for your patience!</p>
                <div style="clear:both"></div>
            </div>
        </body>
    </html>
    <?php
    die();
}

if (isset($_REQUEST['pwd_reset'])) {
    require_once 'libs/connect_lib.php';
    display_pwd_reset_page();
    exit();
}

if (isset($_REQUEST['new_account'])) {
    require_once 'libs/account_lib.php';
    display_new_account_page();
    exit();
}

stopwatch(); // Start bench timer

global $api_key, $page;

$page = isset($_REQUEST['page']) ? $_REQUEST['page'] : null;
$pages = array('main' => 'main.php', PAGE_PLAY => 'play.php', PAGE_SCORES => 'scores.php', PAGE_STATS => 'stats.php', PAGE_FAQ => 'faq.php', PAGE_RANKS => 'ranks.php', PAGE_DONATE => 'donate.php', PAGE_ELITE => 'elite.php', PAGE_INTERNATIONAL => 'international.php');
$titles = array('main' => 'KanjiBox: Japanese Study Tools for the Web');
$secret_pages = array('jlpt1' => 'jlpt1.php', 'test' => 'test.php', 'badges' => 'badges.php', 'vocab_levels' => 'vocab_levels.php', 'privacy' => 'privacy.php');

if (!isset($pages[$page]) && !isset($secret_pages[$page])) {
    $page = 'main';
}
$logged_in = init_app();

if (isset($_SESSION) && isset($_SESSION['crawler'])) {
    $page = 'faq';
}
$public_page = ($page == 'faq');

if (!$public_page && !$logged_in) {
    header('Cache-Control: private, no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
    header('Pragma: no-cache');

    require_once('libs/connect_lib.php');
    display_login_page();
    exit();
}

if (!$public_page && !isset($_SESSION['user'])) {
    $file_exists = file_exists('/tmp/kb/sess_' . session_id());
    $file_size = filesize('/tmp/kb/sess_' . session_id());
    log_error('Session disappeared!' . "\n session_id: " . session_id() . "\n Exists: $file_exists \n Size: $file_size", true, true);
}

if ((!empty($params['redirect']) && $params['redirect'] === 'back_to_forum') || (!empty($_REQUEST['redirect']) && $_REQUEST['redirect'] === 'back_to_forum')) {
    header('Location: http://kanjibox.net/forum/');
    exit();
}
if ((!empty($params['redirect']) && $params['redirect'] === 'set_subscribe') || (!empty($_REQUEST['redirect']) && $_REQUEST['redirect'] === 'set_subscribe')) {
    $set_id = (int) (isset($params['set_id']) ? $params['set_id'] : $_REQUEST['set_id']);
    header('Location: http://kanjibox.net/kb/set/' . $set_id . '/?redirected_subscribe=1');
    exit();
}
if ($redirect_to_url = (isset($_REQUEST['redirect_to_url']) ? $_REQUEST['redirect_to_url'] : null)) {
    if (md5($redirect_to_url . ' my secret sauce ' . date('D, d M Y')) === $_REQUEST['redirect_token']) {
        header('Location: ' . $redirect_to_url);
        exit();
    }
}
?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en"  xmlns:fb="http://www.facebook.com/2008/fbml">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <title><?php
            if (!empty($titles[$page])) {
                echo $titles[$page];
            } else {
                echo 'KanjiBox: ' . ucfirst($page);
            }
            ?></title>
        <script src="//ajax.googleapis.com/ajax/libs/jquery/1.8.3/jquery.min.js"></script>
        <script src="//ajax.googleapis.com/ajax/libs/jqueryui/1.9.1/jquery-ui.min.js"></script>
        <link rel="stylesheet" href="//code.jquery.com/ui/1.9.1/themes/base/jquery-ui.css" />
        <?php
        include_jquery('corner');
        include_jquery('form');

        include_css('general.css');
        include_css('drill_quiz.css');
        include_css('security.css');
        include_css('stats.css');
        include_css('scores.css');
        include_css('faq.css');
        include_css('messages.css');
        include_css('tools.css');

        if (!empty($params['mode']) && ($params['mode'] == SETS_MODE || $params['mode'] == GRAMMAR_SETS_MODE)) {
            include_css('sets.css');
        }

        include_js('soundmanager2-nodebug-jsmin.js');
        include_js('general.js');
        include_js('ajax.js');
        ?>

    </head>
    <body>
        <div id="msg-box"></div>
        <?php
        $two_columns = false;
        if (!empty($_SESSION['user']) && $_SESSION['user']->get_id() > 0) {
            if ($_SESSION['user']->is_name_hidden() && $_SESSION['user']->get_load_count() < 2) {
                echo '<div class="info-bar"><p>Due to your Facebook privacy settings, your name cannot be displayed to other users of this application and will therefore not appear in Global Highscores.</p><p>If you wish to appear in the global highscores: please <a href="//www.facebook.com/privacy/?view=profile">change your privacy settings</a>.</p></div>';
            }
            // if(! defined('ADDING') && $_SESSION['user']->get_load_count() == 0)
            elseif (!defined('ADDING') && (!$_SESSION['user']->is_elite() || $_SESSION['user']->is_admin())) {
                include('pandering.php');
            }
        }
        ?>

        <?php
        echo '<div style="float:left; width:' . ($two_columns ? '80%' : '100%') . ';">'; // <!-- full frame -->
// die('BLAHBLAHBLAH </body></html>');


        echo '<div id="kb-header">KanjiBox 5.0α' . (($_SESSION['user'] && $_SESSION['user']->is_elite()) ? '<a class="elite" href="' . get_page_url(PAGE_ELITE) . '">エリート</a>' : '') . '</div>';


// if($_SESSION['user']->is_admin())
// {
// }

        switch ($page) {
            case 'main':
                $tab = 'home';
                break;
            case 'play':
                $tab = ($params['type'] ? $params['type'] : 'home');
                break;
            case 'highscores':
            case 'stats':
            case 'faq':
            case 'international':
                $tab = $page;
                break;
            default:
                if (isset($tabs1[$page]) || isset($tabs2[$page])) {
                    $tab = $page;
                } else {
                    $tab = 'home';
                }
                break;
        }

        if (isset($_REQUEST['save_prefs']) && isset($_SESSION['user'])) {
            $_SESSION['user']->update_prefs($_POST);
        }
        if (isset($_SESSION['user'])) {
            $lang = $_SESSION['user']->get_pref('lang', 'vocab_lang');
        } else {
            $lang = 'en';
        }
        $tabs1 = array('home' => 'Home', 'highscores' => 'Highscores', 'stats' => 'Stats', 'faq' => 'FAQ');
// if($lang != 'en')

        $tabs1['international'] = '<img src="' . SERVER_URL . 'img/flags/' . $lang . '.png" alt="' . $lang . '" style="padding:0; margin: -4px 0 -7px 0;" />';

        $tabs2 = array('kana' => 'Kana', 'kanji' => 'Kanji', 'vocab' => 'Vocab', 'reading' => 'Reading', 'text' => 'Text');

        $tab_urls = array('home' => '', 'highscores' => 'highscores', 'stats' => 'stats', 'faq' => 'faq', 'international' => 'international', 'kana' => 'play/type/kana', 'kanji' => 'play/type/kanji', 'reading' => 'play/type/reading', 'vocab' => 'play/type/vocab', 'text' => 'play/type/text');

//echo '<div class="fb:title">' . ($tabs1[$tab] ? $tabs1[$tab] : ($tabs2[$tab] ? $tabs2[$tab] : $tab)) . '</div>';



        if ($hard_hat_zone) {
            ?>
            <div class="error_msg">
                <img src="<?php echo SERVER_URL . 'img/construction_jp.gif' ?>" class="justified" style="height: 150px; width: 150px;" />  <div class="title" title="Update Work in Progress">工事中</div>
                <p>I am in the midst of modifying some application files. You might experience transient errors while I do so. Please wait until this sign disappears before reporting any issues.<br/><br/>
                    Thanks for your patience!</p>
                <div style="clear:both" ></div>
            </div>
            <?php
        }
        ?>
        <div id="kb">
            <div class="tabs-frame">
                <ul class="tabs">
                    <?php
                    foreach ($tabs1 as $a_tab => $label) {
                        echo "<li><a class=\"tab-item" . ($tab == $a_tab ? " selected" : "") . "\" href='" . get_page_url($tab_urls[$a_tab]) . "'>$label</a></li>";
                    }
                    foreach (array_reverse($tabs2) as $a_tab => $label) {
                        echo "<li><a class=\"tab-item tab-item-right" . ($tab == $a_tab ? " selected" : "") . "\" href='" . get_page_url($tab_urls[$a_tab]) . "' title='$label'>$label</a></li>";
                    }
                    ?>
                </ul>
            </div>

            <div id="new-kb-msgs"></div>

            <div class="content" id="frame-<?php echo $page; ?>">
                <?php
                if (!empty($pages[$page])) {
                    require_once ABS_PATH . 'pages/' . $pages[$page];
                } elseif ($secret_pages[$page]) {
                    require_once ABS_PATH . 'pages/' . $secret_pages[$page];
                } else {
                    die('Unknown page: ' . $page);
                }
                ?>
                <p id="signout"><a href="<?php echo SERVER_URL ?>?sign_out=1">[sign out]</a></p>
                <?php
// $params = array( 'next' => SERVER_URL . '?sign_out=1' );
// $url = $facebook->getLogoutUrl($params);
// echo "<a href=\"$url\">[FB]</a>";
                ?>
            </div>
        </div>
        <div id="kbbottom"></div>
        </div> <!-- full frame -->

        <div id="user_feedback_dialog">
            <div id="user_feedback_content"></div>
        </div>
        <div id="fb-root"></div>
        <script type="text/javascript">

            if (top != self) {
                top.onbeforeunload = function() {
                };
                top.location.replace(self.location.href);
            }

            function facebook_onload() {

                window.fbAsyncInit = function() {

                    FB.init({
                        appId: '<?php echo $api_key ?>',
                        cookie: true,
                        xfbml: true,
                        oauth: true
                    });
                    FB.Event.subscribe('auth.login', function(response) {
                        window.location.reload();
                    });
                    FB.Event.subscribe('auth.logout', function(response) {
                        window.location.reload();
                    });
                };

                (function() {
                    var js, fjs = document.getElementsByTagName('script')[0];
                    if (document.getElementById('facebook-jssdk'))
                        return;

                    var e = document.createElement('script');
                    e.async = true;
                    e.src = document.location.protocol + '//connect.facebook.net/en_US/all.js';
                    document.getElementById('fb-root').appendChild(e);
                }());

            }



            $(document).ready(function()
            {
                $('#kb').corner('top');
                $('#kbbottom').corner('bottom');
                $('#kb .tabs .selected').corner('bevel tr 5px');

                $('.ui-state-default').hover(
                        function() {
                            $(this).addClass("ui-state-hover");
                        },
                        function() {
                            $(this).removeClass("ui-state-hover");
                        }
                );

<?php
if ($_SESSION['user'] && ($_SESSION['user']->get_load_count() % 20 == 0))
    echo "do_load('" . SERVER_URL . "ajax/get_messages/', 'new-kb-msgs');";


// echo ' //' . $_SESSION['user']->get_load_count() . "\n";
?>
            })


            // window.google_analytics_uacct = "UA-52899-7";
            //
            // var gaJsHost = (("https:" == document.location.protocol) ? "https://ssl." : "http://www.");
            // document.write(unescape("%3Cscript src='" + gaJsHost + "google-analytics.com/ga.js' type='text/javascript'%3E%3C/script%3E"));
            //
            // try {
            // var pageTracker = _gat._getTracker("UA-52899-7");
            // pageTracker._trackPageview();
            // } catch(err) {}

        </script>

    </body>
</html>
