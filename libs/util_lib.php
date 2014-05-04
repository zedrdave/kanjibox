<?php
define('MSG_ERROR', 'error');
define('MSG_SUCCESS', 'success');
define('MSG_NOTICE', 'notice');

function get_db_conn()
{
    //mysql_connect($GLOBALS['db_ip'], $GLOBALS['db_user'], $GLOBALS['db_pass']) or log_error("Can't connect to DB: " . $GLOBALS['db_user'] . ':' . strlen($GLOBALS['db_pass']) . '@' . $GLOBALS['db_ip'], false, true) or log_db_error('mysql_connect()', '', true, true);
    //mysql_select_db($GLOBALS['db_name']) or log_db_error('mysql_select_db()', '', true, true);
    //mysql_query("SET NAMES 'utf8'") or log_db_error('SET NAMES \'utf8\'', '', true, true);

    try {
        global $dbh;
        $dbh = new PDO('mysql:host=' . $GLOBALS['db_ip'] . ';dbname=' . $GLOBALS['db_name'] . ';charset=utf8',
            $GLOBALS['db_user'], $GLOBALS['db_pass']);
    } catch (PDOException $e) {
        log_error("Can't connect to DB: " . $GLOBALS['db_user'] . ':' . strlen($GLOBALS['db_pass']) . '@' . $GLOBALS['db_ip'],
            false, true);
        log_db_error('mysql_connect()', '', true, true);
        die();
    }
}

function include_css($file)
{
    $ts = filemtime(ABS_PATH . 'css/' . $file);
    echo "<link rel=\"stylesheet\" type=\"text/css\" media=\"screen\" href=\"" . SERVER_URL . "css/$file?ts=$ts\" />";
}

function include_js($file)
{
    $ts = filemtime('js/' . $file);
    echo "<script type=\"text/javascript\" src=\"" . SERVER_URL . "js/$file?ts=$ts\"></script>";
}

function include_jquery($jquery_plugin)
{
    echo "<script type=\"text/javascript\" src=\"/js/jquery/jquery.$jquery_plugin.js\"></script>";
}

function insert_js_msg($msg)
{
    ?>
    <script type="text/javascript">
        $(document).ready(function()
        {
            display_message_text("<?php echo htmlspecialchars($msg);?>");
        }
    </script>
    <?php
}

function print_button($id, $img_off, $img_on, $extra_js, $link = "#")
{
    echo " <a href=\"$link\" $extra_js><img id=\"$id\" class=\"clickable\" src=\"" . SERVER_URL . "img/$img_off\" onMouseOver=\"document.getElementById('$id').setSrc('" . SERVER_URL . "img/$img_on');\" onMouseOut=\"document.getElementById('$id').setSrc('" . SERVER_URL . "img/$img_off');\" />";
    echo "<img class=\"preload\" src=\"" . SERVER_URL . "img/$img_on\" /> </a> ";
}

function safe_comma_list($array)
{
    if (!$array || !is_array($array)) {
        return "''";
    }
    return "'" . implode('\', \'', array_map('mysql_real_escape_string', $array)) . "'";
}

function is_assoc($array)
{
    if (!is_array($array)) {
        return false;
    }
    foreach (array_keys($array) as $k => $v) {
        if ($k !== $v) {
            return true;
        }
    }
    return false;
}

function pretty_print($array)
{
    if (is_array($array) || is_object($array)) {
        if (is_assoc($array) || is_object($array)) {
            echo '<ul>';
            foreach ($array as $key => $val) {
                echo '<li><strong>' . $key . '</strong>: ';
                pretty_print($val);
                echo '</li>';
            }
            echo '</ul>';
        } else {
            echo '<ol>';
            foreach ($array as $key => $val) {
                echo '<li>';
                pretty_print($val);
                echo '</li>';
            }
            echo '</ol>';
        }
    } else {
        echo htmlentities($array);
    }
}

function draw_table($title, $sql_results, $extras = array())
{
    $ret = '<table cellspacing="0" class="spreadsheet"><caption>' . $title . '</caption><tbody>';
    while ($row = mysql_fetch_assoc($sql_results)) {
        if (!$heading) {
            $ret .= '<tr>';
            $i = 0;
            foreach ($row as $key => $val) {
                $ret .= '<th class="heading' . ($i++ % 2 ? ' even_column' : '') . '"' . ($extras[$key] ? ' ' . $extras[$key] : '') . '>' . $key . '</th>';
            }
            $ret .= '</tr>';

            $heading = true;
        }
        $i = 0;
        $ret .= '<tr>';
        foreach ($row as $key => $val) {
            $ret .= '<td' . ($i++ % 2 ? ' class="even_column"' : '') . ($extras[$key] ? ' ' . $extras[$key] : '') . '>' . $val . '</td>';
        }
        $ret .= "</tr>\n";
    }

    $ret .= '</tbody></table>';

    return $ret;
}

function make_toggle_visibility($str, $delay = 0, $toggle_text = '&gt;&gt; ')
{
    $id = md5($str) . rand(1, 100);

    return ' <a href="#" class="more_link" id="cts_' . $id . '" onclick="$(\'#' . $id . '\').' . ($delay ? 'delay(' . (int) ($delay / 3) . ').fadeIn(' . 5 * $delay . ')' : 'show()') . '; $(\'#cts_' . $id . '\').hide(); return false;">' . $toggle_text . '</a><div id="' . $id . '" style="display:none">' . $str . '</div>';
}

function insert_js_snippet($js)
{
    echo "<script type=\"text/javascript\">\n";
    echo "\$(document).ready(function() {";
    echo $js;
    echo "});";
    echo "\n</script>";
}

function get_page_url($page = '', $params = NULL, $args = '')
{
    $param_str = '';
    if ($params) {
        foreach ($params as $param => $val) {
            $param_str .= $param . '/' . $val . '/';
        }
    }
    return APP_URL . ($page ? 'page/' . $page . '/' . $param_str : '') . $args;
}

function get_this_page_url($new_params = NULL, $args = '', $reset_params = false)
{
    global $params, $page;
    $param_str = '';
    if ($reset_params) {
        foreach ((array) $new_params as $param => $val) {
            $param_str .= $param . '/' . $val . '/';
        }
    } else {
        foreach (array_merge((array) $params, (array) $new_params) as $param => $val) {
            $param_str .= $param . '/' . $val . '/';
        }
    }
    return APP_URL . 'page/' . $page . '/' . $param_str . $args;
}

function display_user_msg($msg, $type = MSG_NOTICE)
{
    echo '<div class="' . $type . '_msg">' . $msg . '</div>';
}

function nice_time($time)
{
    return $time;
}

function get_select_menu($array, $select_id = '', $selected = '', $on_change = '', $first_default = '',
    $input_name = '', $class = '')
{
    if ($input_name == '') {
        $input_name = $select_id;
    }
    $str = '';
    $str .= '<select ' . ($select_id ? 'id="' . $select_id . '" name="' . $input_name . '" ' : '') . ($on_change ? 'onchange="' . $on_change . '" ' : '') . ($class ? 'class="' . $class . '" ' : '') . '>';
    if ($first_default) {
        $str .= '<option value="" selected="' . ($selected != '' ? '0' : '1') . '">' . $first_default . '</option>';
    }
    foreach ($array as $val => $name) {
        $str .= '<option value="' . $val . '" ' . ($selected == $val ? 'selected="1" ' : '') . '>' . $name . '</option>';
    }

    return ($str . '</select>');
}

function get_jlpt_menu($id, $selected, $onchange = '')
{
    // $levels = array(5 => 'N5', 4 => 'N4', 3 => 'N3', 2 => 'N2', 1 => 'N1', 0 => '先生');
    return get_select_menu(Session::$level_names, $id, $selected, $onchange);
}

function display_select_menu($array, $select_id = '', $selected = '', $on_change = '', $first_default = '')
{
    echo get_select_menu($array, $select_id, $selected, $on_change, $first_default);
}

function display_status_msg($msg, $type = 'status')
{
    insert_js_snippet("display_status_msg('" . strreplace("'", "\'", $msg) . "');");
}
