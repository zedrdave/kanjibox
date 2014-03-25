<?php

function display_login_css() {
    ?>		
    <style type="text/css">
        body
        {
            background-color: #3b5998;
            font-size: 12px;
        }

        body a:visited {
            color: #284499;
        }

        #loginbox
        {
            margin: 100px auto auto auto;
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

        .success_msg {
            font-weight: bold;
            border: 2px solid #12B61E;
            padding: 4px;
            margin: 5px;
        }

        fieldset legend {
            font-size: 120%;
            font-weight: bold;
            text-align: left;
        }

        fieldset {
            text-align: right;
            padding-right: 60px;
            margin-bottom: 15px;
            margin-top: 10px;
        }
    </style>
    <?php
}

function print_edit_line($label, $id, $type = 'edit') {
    echo '<label>' . $label . ': </label><input type="' . $type . '" name="' . $id . '" id="' . $id . '" value="' . htmlentities(@$_REQUEST[$id]) . '"></input><br/>';
}

function display_new_account_page() {
    ?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
    <html xmlns="http://www.w3.org/1999/xhtml" xmlns:fb="http://www.facebook.com/2008/fbml">
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
            <title>KanjiBox: New Account</title><?php
            echo display_login_css();
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
                <h2>Create a new KanjiBox account</h2>
                <p style="font-style:italic;">Note: if you have a Facebook account or use the Sync feature on the <a href="https://kanjibox.net/iphone/">iOS version of KanjiBox</a>, it is recommended you log into your existing account (you can later set it up for direct access without Facebook login) rather than creating a new one.</p>
                <?php
                $no_error = true;

                if ($_REQUEST['action'] == 'new_account') {
                    get_db_conn();

                    $riddle = str_replace(' ', '', strtolower($_REQUEST['spam_question']));
                    if ($riddle != 'assur' && $riddle != 'dursarukin' && $riddle != 'nimrud' && $riddle != 'nineveh' && $riddle != 'caleh') {
                        log_public_error('Failed the basic Spam Protection / Survival IQ Test.<br/>Come on, <a href="http://lmgtfy.com/?q=What+is+the+capital+of+Assyria">it\'s not that hard</a> (a few different answers are acceptable).');
                        $no_error = false;
                    }

                    if ($no_error && @$_REQUEST['login_mail'] != @$_REQUEST['login_mail_2']) {
                        log_public_error('Your email and email confirmation fields do not match...');
                        $no_error = false;
                    }

                    if ($no_error && (strpos($_REQUEST['login_mail'], '@') < 1)) {
                        log_public_error('The email you provided does not appear to be valid. Please provide a valid email address (I promise I won\'t be using it to spam you).');
                        $no_error = false;
                    }

                    if ($_REQUEST['password'] != $_REQUEST['password_2']) {
                        log_public_error('Your password and password confirmation fields do not match...');
                        $no_error = false;
                    }

                    if ($no_error) {
                        $query = 'SELECT * FROM users_ext ux WHERE ux.login_email = \'' . mysql_real_escape_string($_REQUEST['login_mail']) . '\' AND ux.login_email != \'\' AND ux.login_email IS NOT NULL';
                        $res = mysql_query($query) or die(mysql_error());

                        if ($row = mysql_fetch_object($res)) {
                            log_public_error('This login email is already registered. Please use the <a href="' . SERVER_URL . '?pwd_reset=1">password recovery page</a> to access it.');
                            $no_error = false;
                        }
                    }

                    if ($no_error) {
                        mysql_query("INSERT INTO users SET privileges = 0, device_id = NULL, fb_id = NULL") or die(mysql_error());

                        $user_id = mysql_insert_id();
                        mysql_query("INSERT INTO users_ext SET login_email = '" . mysql_real_escape_string($_REQUEST['login_mail']) . "', login_pwd = MD5('" . mysql_real_escape_string($_POST['password']) . "'), user_id = $user_id, first_name = '" . mysql_real_escape_string($_REQUEST['first_name']) . "', last_name = '" . mysql_real_escape_string($_REQUEST['last_name']) . "'") or die(mysql_error());

                        echo '<div class="success_msg">Account successfully created for login <em>' . htmlentities($_REQUEST['login_mail']) . '</em>. You can now <a href="' . SERVER_URL . '">login from the main page</a>.</div>';
                    }
                }

                if (empty($_REQUEST['action']) || !$no_error) {
                    ?>
                    <form method="post" action="<?php echo SERVER_URL ?>" style="text-align:center;">

                        <input type="hidden" name="action" value="new_account"></input>
                        <fieldset>
                            <legend>Login Info</legend><?php
                            echo print_edit_line('Email (your login)', 'login_mail');
                            echo print_edit_line('Email confirmation', 'login_mail_2');
                            echo print_edit_line('Password', 'password', 'password');
                            echo print_edit_line('Password confirmation', 'password_2', 'password');
                            ?>
                        </fieldset>
                        <br/>
                        <fieldset>
                            <legend>Personal Info</legend><?php
                            echo print_edit_line('First name', 'first_name');
                            echo print_edit_line('Last name (optional)', 'last_name');
                            ?></fieldset>

                        <fieldset>
                            <legend>Spam Protection / Survival IQ Test</legend>
                            <label>What is the capital of Assyria: </label><input type="edit" name="spam_question" id="spam_question" value="<?php echo htmlentities(@$_REQUEST['spam_question']) ?>"></input> <small>[<a href="http://lmgtfy.com/?q=What+is+the+capital+of+Assyria">hint</a>]</small><br/>
                        </fieldset>

                        <label>&nbsp;</label><input type="submit" name="new_account" value="Create Account" style="margin:auto;" onclick="if ($('#password').val() != $('#password_2').val()) {
                                            alert('Passwords don\'t match');
                                            return false;
                                        }
                                        ;
                                        if ($('#login_mail').val() != $('#login_mail_2').val()) {
                                            alert('Logins don\'t match');
                                            return false;
                                        }
                                        ;"></input>
                    </form>

                    <?php
                }
                ?>
            </div>

        </body>
    </html>

    <?php
}

function display_log_out_page() {
    session_save_path('/var/lib/php5/web_kb_sessions');
    session_start();
    $_SESSION = array();
    setcookie('Vanilla', ' ', time() - 3600, '/', '.kanjibox.net');
    unset($_COOKIE['Vanilla']);
    setcookie("kanjibox", ' ', time() - 3600, '/', '.kanjibox.net');
    unset($_COOKIE['kanjibox']);
    ?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
    <html xmlns="http://www.w3.org/1999/xhtml" xmlns:fb="http://www.facebook.com/2008/fbml">
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
            <title>KanjiBox: Logged Out</title><?php
            echo display_login_css();
            ?>
        </head>
        <body>
            <div id="loginbox">
                <h2>Successfully logged-out!</h2>
                <p><a href="<?php echo SERVER_URL ?>">Go back to main page</a></p>
            </div>

        </body>
    </html>

    <?php
    exit(-1);
}
