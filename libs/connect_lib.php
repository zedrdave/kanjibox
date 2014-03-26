<?php

function display_login_css() {
    ?>		<style type="text/css">
        body
        {
            background-color: #3b5998;
            font-size: 12px;
        }

        #loginbox
        {
            margin: 5% auto auto auto;
            padding: 20px;
            width: 400px;
            border: 2px solid black;
            background-color: #f7f7f7;
        }

        #connect-button
        {
            text-align: center;
            margin-top: 10px;
        }

        #connect-button
        {
            text-align: center;
            margin-top: 30px;
        }

        #fb-login-button
        {
            border: 1px solid black;
            background-color: #3b5998;
            font-weight: bold;
            padding: 2px 6px 2px 6px;
            color: #FFF;
            text-decoration: none;
        }

        .login-box {
            margin: auto;
            width: 300px;
            border: 1px solid black;
            padding: 10px;

        }

        .login-box .submit-btn {
            margin: 5px auto 5px auto;
            text-align: center;
        }

        .error_msg {
            font-weight: bold;
            color: #C00;
        }

    </style>
    <?php
}

function display_login_page($redirect_to_url = '', $header_msg = 'In order to use <a href="https://www.facebook.com/kanjibox/">KanjiBox Online</a>: ') {
    global $api_key, $secret, $facebook;
    ?>
    <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
    <html xmlns="http://www.w3.org/1999/xhtml" xmlns:fb="http://www.facebook.com/2008/fbml">
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
            <meta name="viewport" content="width=500">
                <title>KanjiBox</title>
                <?php
                fb_connect_init(false);

                if (!$redirect_to_url && $redirect_to_url = (isset($_REQUEST['redirect_to_url']) ? $_REQUEST['redirect_to_url'] : null)) {
                    if (md5($redirect_to_url . ' my secret sauce ' . date('D, d M Y')) != $_REQUEST['redirect_token']) {
                        $redirect_to_url = '';
                    }
                }

                $loginUrl = $facebook->getLoginUrl(array(
                    'redirect_uri' => $redirect_to_url ? $redirect_to_url : (SERVER_URL . (!empty($_REQUEST['redirect']) && $_REQUEST['redirect'] === 'back_to_forum' ? 'page/main/redirect/back_to_forum/' : (!empty($_REQUEST['redirect']) && $_REQUEST['redirect'] == 'set_subscribe' ? 'page/main/redirect/set_subscribe/set_id/' . $_REQUEST['set_id'] . '/' : ''))),
                    'cancel_url' => SERVER_URL . 'login/cancelled/',
                    'oauth' => true,
                    'cookie' => true,
                    'scope' => 'email,publish_stream',
                ));

                // if(@$_REQUEST['login_with'] == 'facebook')
                //	 echo "<script type='text/javascript'>top.location.href = '$loginUrl';</script>";
                // 	 		<script src="http://static.ak.connect.facebook.com/js/api_lib/v0.4/FeatureLoader.js.php/en_US" type="text/javascript"></script>

                echo display_login_css();
                ?>
        </head>
        <body>
            <div id="loginbox">
                <p><?php
                    echo $header_msg;
                    ?></p>
                <div id="connect-button">
                    <a href="<?php echo $loginUrl; ?>" id="fb-login-button">Log in with Facebook</a>
                </div>

                <h2 style="text-align:center; font-size:40px">OR</h2>
                <form class="login-box" method="post" action="<?php echo SERVER_URL; ?>">
                    <?php
                    if (!empty($params['login']) && $params['login'] === 'cancelled') {
                        echo '<div class="error_msg">Facebook seems to have cancelled the login. Please contact me if this error persists.</div>';
                    }

                    if (!empty($_POST['login'])) {
                        $res = mysql_query("SELECT login_pwd FROM users_ext ux WHERE ux.login_email = '" . mysql_real_escape_string($_POST['login']) . "'") or die(mysql_error());
                        $row = mysql_fetch_object($res);
                        if ($row && $row->login_pwd == 'need_to_reset') {
                            echo "<div class=\"error_msg\">Your password needs to be reset before you can log in. Please follow <a href=\"https://kanjibox.net/kb/?pwd_reset=1\">these instructions</a> to reset your password.</div>";
                        } else {
                            echo "<div class=\"error_msg\">Incorrect login/password. Please try again.</div>";
                        }
                    }
                    ?>
                    <h3>Log in directly:</h3>
                    <p>Login (email): <input type="text" name="login" size="30"></input></p>
                    <p>Password: <input type="password" name="pwd"></input></p><?php
                    // if($redirect_to_url = @$_REQUEST['redirect_to_url']) {
                    // 	if(md5($redirect_to_url . ' my secret sauce ' . date('D, d M Y')) != @$_REQUEST['redirect_token'])
                    // 		$redirect_to_url = '';
                    // }
                    if ($redirect_to_url) {
                        echo '<input type="hidden" name="redirect_to_url" value="' . $redirect_to_url . '"></input>';
                        echo '<input type="hidden" name="redirect_token" value="' . md5($redirect_to_url . ' my secret sauce ' . date('D, d M Y')) . '"></input>';
                    } else {
                        if ((!empty($_REQUEST['redirect']) && $_REQUEST['redirect'] == 'back_to_forum') || (!empty($_REQUEST['redirect']) && $_REQUEST['redirect']) == 'set_subscribe') {
                            echo '<input type="hidden" name="redirect" value="' . $_REQUEST['redirect'] . '"></input>';
                        }
                        if ((!empty($_REQUEST['redirect']) && $_REQUEST['redirect'] === 'set_subscribe') && $_REQUEST['set_id']) {
                            echo '<input type="hidden" name="set_id" value="' . (int) $_REQUEST['set_id'] . '"></input>';
                        }
                    }
                    ?>
                    <div class="submit-btn"><input type="submit" name="Log in" value="Log in"></input></div>
                    <p style="text-align:center; margin: 10px 0 0 0; padding: 0;"><a href="<?php echo SERVER_URL ?>?new_account=1">[Create new account]</a>&nbsp;&nbsp;&nbsp;<a href="<?php echo SERVER_URL ?>?pwd_reset=1">[Recover lost password]</a></p>
                </form>
                <p>You can also consult <a href="<?php echo get_page_url('faq'); ?>">KanjiBox's FAQ</a>, browse <a href="<?php echo SERVER_URL; ?>sets/">public Japanese study sets</a> or check out the <a href="http://kanjibox.net/ios/">KanjiBox app for iOS (iPhone/iPod/iPad)</a>.</p>
            </div>
            <script type="text/javascript">
                if (top != self) {
                    top.onbeforeunload = function() {
                    };
                    top.location.replace(self.location.href);
                }
            </script>
            <?php
            /*
              function refresh_page() {
              window.location.reload();
              }

              function facebook_onload() {
              FB.init('<?php echo $api_key ?>', "<?php echo SERVER_URL; ?>/xd_receiver.htm");

              FB.ensureInit(function() {
              FB.Facebook.get_sessionState().waitUntilReady(function(session) {
              var is_now_logged_into_facebook = session ? true : false;

              if(is_now_logged_into_facebook)
              refresh_page();
              });
              });
              }

              window.onload = function() { facebook_onload(); };

              </script>

              <script type="text/javascript">
              var gaJsHost = (("https:" == document.location.protocol) ? "https://ssl." : "http://www.");
              document.write(unescape("%3Cscript src='" + gaJsHost + "google-analytics.com/ga.js' type='text/javascript'%3E%3C/script%3E"));
              </script>
              <script type="text/javascript">
              try {
              var pageTracker = _gat._getTracker("UA-52899-7");
              pageTracker._trackPageview();
              } catch(err) {}</script>
             */
            ?>
        </body>
    </html>
    <?php
}

function display_pwd_reset_page() {
    ?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
    <html xmlns="http://www.w3.org/1999/xhtml" xmlns:fb="http://www.facebook.com/2008/fbml">
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
            <title>KanjiBox: Password Reset</title><?php
            echo display_login_css();

            $reset_salt = "adakh$#$23klasd42,0";
            ?>
            <script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.4/jquery.min.js"></script>
        </head>
        <body>
            <script type="text/javascript">
                if (top != self) {
                    top.onbeforeunload = function() {
                    };
                    top.location.replace(self.location.href);
                }
            </script>
            <div id="loginbox">
                <h2>Recover existing KanjiBox account</h2>
                <form method="post" action="<?php echo SERVER_URL ?>" style="text-align:center;">
                    <input type="hidden" name="pwd_reset" value="1"></input>
                    <?php
                    if ((!empty($_REQUEST['action']) && $_REQUEST['action'] == 'send_email') && (!empty($_REQUEST['first_name']) && !empty($_REQUEST['login_mail']))) {
                        get_db_conn();

                        if (strpos($_REQUEST['login_mail'], '@') < 1) {
                            log_pwd_reset('The email linked to this account does not seem valid and cannot be used to send you a recovery password. Please log in using Facebook and correct your email.');
                        }


                        $query = 'SELECT * FROM users u LEFT JOIN users_ext ux ON ux.user_id = u.id WHERE ux.login_email = \'' . mysql_real_escape_string($_REQUEST['login_mail']) . '\' AND ux.login_email != \'\' AND ux.login_email IS NOT NULL AND (ux.first_name = \'\' OR ux.first_name IS NULL OR ux.first_name = \'' . mysql_real_escape_string($_REQUEST['first_name']) . '\')';

                        $res = mysql_query($query) or die(mysql_error());

                        if ($row = mysql_fetch_object($res)) {

                            $date = time();
                            $hash = md5($reset_salt . $date . $row->login_email . $row->login_pwd . $row->user_id);

                            if (mail($row->login_email, 'KanjiBox Password Recovery', "Hello $row->first_name,\n\nYou recently requested a password reset on your KanjiBox account. Please click on the following link to set a new password for your account:\n" . SERVER_URL . "?pwd_reset=1&action=recover&mail=$row->login_email&uid=$row->user_id&t=$date&h=$hash\n\nGood luck with your kanji studying!\n-- \nKanjiBox")) {
                                echo '<div class="success_msg">A recovery email was sent to your account address: <strong>' . $row->login_email . '</strong>. Please check your inbox and follow the instructions in the email.</div>';
                                log_pwd_reset('Recovery email sent to: ' . $row->login_email . ' (account: ' . $row->user_id . ')', false, false);
                            } else {
                                log_pwd_reset('There was an error sending the recovery email to your address: <strong>' . htmlentities($row->login_email) . '</strong>. Please contact us directly if you think this is a bug.');
                            }
                        } else {
                            log_pwd_reset('No account with this email login and first name were found in the DB. Please consider <a href="' . SERVER_URL . '">logging using Facebook</a> to set your login/password directly or <a href="https://www.facebook.com/kanjibox">contact me</a> directly if the problem persists.', true, true, $query);
                        }
                    } elseif (!empty($_REQUEST['action']) && $_REQUEST['action'] === 'recover') {
                        $date = $_REQUEST['t'];
                        $hash = $_REQUEST['h'];
                        $user_id = $_REQUEST['uid'];
                        $login_email = $_REQUEST['mail'];

                        if (!$date || !$hash || !$user_id || !$login_email) {
                            log_pwd_reset('Invalid params');
                        }

                        if (abs($date - time()) > 3600 * 24) {
                            log_pwd_reset('This reset code has expired. Please request another one.</div>');
                        }

                        get_db_conn();

                        $res = mysql_query('SELECT * FROM users u LEFT JOIN users_ext ux ON ux.user_id = u.id WHERE ux.login_email = \'' . mysql_real_escape_string($login_email) . '\' AND ux.login_email != \'\' AND ux.login_email IS NOT NULL AND u.id = ' . (int) $user_id) or die('DB error');

                        if (!$row = mysql_fetch_object($res))
                            log_pwd_reset('Cannot find account for this email.');

                        if ($hash != md5($reset_salt . $date . $login_email . $row->login_pwd . $row->user_id))
                            log_pwd_reset('Invalid recovery code.');

                        if (!empty($_REQUEST['pwd']) && $_REQUEST['pwd']) {
                            if (mysql_query('UPDATE users_ext SET login_pwd = MD5(\'' . mysql_real_escape_string($_REQUEST['pwd']) . '\') WHERE user_id = ' . (int) $user_id)) {
                                echo "<h3>Your password has been successfully reset!</h3>";
                                echo '<a href="' . SERVER_URL . '">Play &raquo;</a>';
                                log_pwd_reset('Password reset for account: ' . $user_id . ' (email: ' . $login_email . ')', false, false);
                            } else
                                log_pwd_reset('DB Error.');
                        }
                        else {
                            ?>
                            <input type="hidden" name="mail" id="mail" value="<?php echo htmlentities($login_email); ?>"></input>
                            <input type="hidden" name="uid" id="uid" value="<?php echo (int) $user_id; ?>"></input>
                            <input type="hidden" name="h" id="hash" value="<?php echo $hash; ?>"></input>
                            <input type="hidden" name="t" id="date" value="<?php echo $date; ?>"></input>
                            <input type="hidden" name="action" id="action" value="recover"></input>
                            <p>Enter the new password for your account (login: <strong><?php echo $login_email ?></strong>):</p>
                            <label>Password: </label><input type="password" name="pwd" id="pwd"></input><br/>
                            <label>Password (again): </label><input type="password" name="pwd2" id="pwd2"></input><br/><br/>

                            <label>&nbsp;</label><input type="submit" name="send_mail" value="Change Password" style="margin:auto;" onclick="if ($('#pwd').val() !== $('#pwd2').val()) {
                                                    alert('Passwords don\'t match');
                                                    return false;
                                                }
                                                else
                                                    return true;"></input>
                                                        <?php
                                                    }
                                                } else {
                                                    ?><input type="hidden" name="action" value="send_email"></input>
                        <label>Email: </label><input type="edit" name="login_mail" id="login_mail"></input><br/>
                        <label><em>First</em> name: </label><input type="edit" name="first_name" id="first_name"></input><br/><br/>
                        <label>&nbsp;</label><input type="submit" name="send_mail" value="Send recovery mail" style="margin:auto;"></input>
                        <?php
                    }
                    ?>
                </form>
            </div>

        </body>
    </html>

    <?php
}
