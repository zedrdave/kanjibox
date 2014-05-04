<?php
require_once 'libs/lib.php';
require_once get_mode() . '.config.php';

try {
    init_app();
} catch (Exception $e) {
    log_error('Uncaught Exception in ' . __FILE__ . ': ' . $e->getMessage(), true, true);
}
?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en"  xmlns:fb="http://www.facebook.com/2008/fbml">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <title>KB Admin Tools</title>
        <script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.4/jquery.min.js"></script>
        <script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.7.2/jquery-ui.min.js"></script>
        <?php
        include_jquery('corner');
        include_jquery('form');

        include_css('general.css');
        include_css('tools.css');
        include_css('stats.css');
        include_css('messages.css');
        include_js('general.js');
        include_js('ajax.js');
        include_js('soundmanager2-nodebug-jsmin.js');
        ?>
    </head>
    <body>
        <?php
        echo $userAgent = $_SERVER['REMOTE_ADDR'];
        ?>
        <div style="background-color:white; text-align: left; width: 800px; margin: auto; padding: 20px;">

            <?php
            try {
                ini_set('display_errors', true);

                if (emty($_SESSION['user']) || !$_SESSION['user']->isAdministrator()) {
                    die('admins only');
                }

                define('ADMIN_MODE', true);

                if (empty($_REQUEST['script'])) {
                    if ($handle = opendir('admin')) {
                        echo '<ul>';
                        while (($file = readdir($handle)) !== false) {
                            if ($file[0] != "." && $file != ".." && filetype('admin/' . $file) !== 'dir') {
                                echo '<li><a href="' . APP_URL . 'admin/' . substr($file, 0, -4) . '/">' . substr($file,
                                    0, -4) . '</a></li>' . '<br/>';
                            }
                        }
                        echo '</ul>';
                        closedir($handle);
                    }
                } else {
                    echo 'loading: ' . $_REQUEST['script'] . '<br/><br/>';
                    require('admin/' . str_replace(array('.', '/'), '', $_REQUEST['script']) . '.php');
                }
            } catch (Exception $e) {
                log_error('Uncaught Exception in ' . __FILE__ . ': ' . $e->getMessage(), true, true);
            }
            ?>
        </div>
    </body>
</html>