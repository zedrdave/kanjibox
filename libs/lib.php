<?php
define('ABS_PATH', dirname(dirname(__FILE__)) . '/');
require_once(ABS_PATH . 'libs/consts.php');
require_once(ABS_PATH . 'libs/util_lib.php');
require_once(ABS_PATH . 'libs/log_lib.php');
require_once(ABS_PATH . 'classes/User.php');

function get_mode()
{
	return trim(strrchr(trim(str_replace('/htdocs', '', ABS_PATH), '/'), '/'), '/');
}

function __autoload($class)
{
	if(! file_exists(ABS_PATH . 'classes/' . $class . '.php'))
		log_error("Unknown class: " . ABS_PATH  . "classes/$class.php", false, true);
	include_class($class);
}

function init_app($ajax = false)
{
	global $facebook, $fb_id, $api_key, $secret, $params, $ajax;

	if(@$_REQUEST['sign_out'])
	{
		require_once(ABS_PATH . 'libs/account_lib.php');
		display_log_out_page();
		die();
	}

	ini_set('session.gc_maxlifetime', 36000);
	ini_set('session.cookie_lifetime', 36000);
	session_save_path('/var/lib/php5/web_kb_sessions');
	setcookie("kanjibox", 'yatta', time()+36000, '/', '.kanjibox.net');
	session_start();

	if(@$_REQUEST['reset_session'])
	{
		$_SESSION = array();
		setcookie('Vanilla', ' ', time() - 3600, '/', '.kanjibox.net');
		unset($_COOKIE['Vanilla']);
		setcookie("kanjibox", ' ', time() - 3600, '/', '.kanjibox.net');
		unset($_COOKIE['kanjibox']);
		if(@$_REQUEST['redirect'] == 'back_to_forum') {
			header("Location: /forum/");
			die();
		}
		else
			die('session reset');
	}

	// header('P3P: CP="IDC DSP COR CURa ADMa OUR IND PHY ONL COM STA"');

	get_db_conn();
	$params = array();
	if (@$_REQUEST['params'])
	{
		$arr = explode('/', $_REQUEST['params']);
		for ($i = 0; $i < count($arr) - 1; $i += 2)
			$params[$arr[$i]] = $arr[$i+1];
	}

    if(@$params['device'] == 'tablet')
        $_SESSION['params']['device'] = 'tablet';

	if(@$_SESSION['user'] && $_SESSION['user']->is_logged_in())
	{
		//DEBUG: ensure user has new-style level
		// if(strlen($_SESSION['user']->get_level()) > 1) {
		// 	$_SESSION = array();
		//
		// 	die('session reset. please reload.');
		// }

		$_SESSION['user']->inc_load_count();
		$_SESSION['user']->set_logged_in(true);
		$levels = Session::$level_names;
		if(! in_array($_SESSION['user']->get_level(), $levels))
			$_SESSION['user']->update_level($_SESSION['user']->get_njlpt_level());

		$fb_id = $_SESSION['user']->get_fb_id();

		return true;
	}
	elseif(! $ajax)
	{

		if(@$_REQUEST['login']) {
			if(empty($_POST['login']) || empty($_POST['pwd']))
				return false;

			$res = mysql_query("SELECT COUNT(*) AS c FROM `users` u LEFT JOIN users_ext ux ON ux.user_id = u.id WHERE ux.login_email = '" . mysql_real_escape_string(@$_POST['login']) . "' AND ux.login_pwd = MD5('" . mysql_real_escape_string(@$_POST['pwd']) . "')") or die(mysql_error());
			$row = mysql_fetch_object($res);
			if($row->c == 0)
				return false;

			$_SESSION['user'] = new User(array('ux.login_email' => @$_POST['login'], 'ux.login_pwd' => md5(@$_POST['pwd'])), false);


			return true;
		}
		else {

			// if(($_SERVER['REMOTE_ADDR'] == '153.161.71.120')) {
			// 	if (! $fb_id = fb_connect_init(false))
			// 		return false;
			// 	$fb_info = NULL;
			// }
			// else {

			if(@$_SESSION['user'] && $_SESSION['user']->is_admin()) {
				echo '### calling fb_connect_init';
			}

			if (! $fb_info = fb_connect_init(true))
					return false;
				$fb_id = $fb_info['id'];
				if(! $fb_id) {
					die(print_r($fb_info, true));
					return false;
			}

			$_SESSION['user'] = new User(array('fb_id' => $fb_id), true, $fb_info);

			if(! @$_SESSION['user'])
				log_error("User class creation failed", true, true);

			// if($_SESSION['user']->is_admin()) {
			// 	$cookiename = 'fbsr_'. $facebook->getAppId();
			// 	echo $cookiename;
			// 	echo 'signed: ';
			// 	print_r($facebook->getSignedRequest(), true);
			// 	echo ' | getPersistentData(user_id): ' . $facebook->myGetPersistentData('user_id', $default = 0);
			// 	echo ' | getPersistentData(access_token): ' . $facebook->myGetPersistentData('access_token');
			// 	echo ' | access_token: ' . $facebook->myGetAccessToken();
			// 	echo ' | getApplicationAccessToken: ' . $facebook->myGetApplicationAccessToken();
			// }


			// $_SESSION['user']->store_fb_friends();

			return true;
		}
	}

}

function fb_connect_init($test_query = true)
{
	global $facebook, $api_key, $secret, $session;

    // xdebug_get_function_stack();

    //Fixing FB's API:
    if(isset($_SESSION["fb_". $api_key ."_code"]) && isset($_REQUEST['code']))
        unset($_REQUEST['code']);


	require_once(ABS_PATH . 'api/facebook.php');
	try {
		if(! is_object($facebook))
			$facebook = new Facebook(array('appId'  =>  $api_key, 'secret' => $secret,  'cookie' => true));

		if($fb_id = $facebook->getUser()) {
			if (!$test_query)
				return $fb_id;
		}
		else {
			return false;
        }
	}
	catch (Exception $e) {
			// print_r($session);
			if($_SESSION['user']->is_admin()) {
				echo "EXCEPTION ###";
				echo $fb_id;
				echo($e->getMessage());
			}
			// die();
		// PROBABLY AN EXPIRED SESSION
		return false;
	}

	// if($_SESSION['user']->is_admin()) {
	// 	echo "###";
	// 	print_r($facebook);
	// 	print_r($session);
	// 	echo $fb_id;
	// 	echo "###";
	// }

	// if (!$session || !$fb_id)

	try
	{
		$fql_result = $facebook->api('/' . $fb_id);

		// $fql_result = $facebook->api_client->fql_query('SELECT uid, first_name, last_name, email FROM user WHERE uid=' . $fb_id);
	}
	catch (Exception $e) {
		if(@$_SESSION['user'] && $_SESSION['user']->is_admin()) {
			echo "EXCEPTION ###";
			echo($e->getMessage());
			echo '[fb id: ' . $fb_id . ']';
		}
			// print_r($session);
			// echo $fb_id;
			// echo ' | getPersistentData(user_id): ' . $facebook->myGetPersistentData('user_id', $default = 0);
			// echo ' | getPersistentData(access_token): ' . $facebook->myGetPersistentData('access_token');
			// echo ' | access_token: ' . $facebook->myGetAccessToken();
			// echo ' | getApplicationAccessToken: ' . $facebook->myGetApplicationAccessToken();
			// echo " EXCEPTION ###";
			// echo($e->getMessage());
		// PROBABLY AN EXPIRED SESSION

		return false;
	}

	if (! is_array($fql_result))
		return false;

	return $fql_result;
}

function is_tablet() {
    global $params;

    return (@$params['device'] == 'tablet' || @$_SESSION['params']['device'] == 'tablet');
}

function force_reload($msg, $url = APP_URL, $alt_msg = '')
{
	if(! $alt_msg)
		$alt_msg = $msg;

	include_css('general.css');
	if(@$_REQUEST['reloading'])
		echo '<div class="error_msg">' . $alt_msg . '</div>';
	else
		echo '<div class="error_msg">' . $msg . '<br/>If you are logged into Facebook, <a href="' . $url . '">click here to continue</a>.</div>';

	die();
}

function force_logged_in_app($page = 'main')
{
	if(!@$_SESSION['user'] || !$_SESSION['user']->is_logged_in())
	{
		header("Location: login.php");
		die();
	}
}

function print_prefs($cat)
{
	foreach(User::$pref_labels[$cat] as $pref => $label)
	{
		if(is_array($label))
		{
			$cur_val = @$_SESSION['user']->get_pref($cat, $pref);
			echo '<p>' . $label['legend'];
			echo '<select id="prefs[' . $cat . '][' . $pref . ']" name="prefs[' . $cat . '][' . $pref . ']" onchange="show_and_blink(\'save_prefs\'); return true;">';
			foreach($label['choices'] as $value => $name)
				echo '<option value="' . $value . '"' . ($cur_val == $value ? ' selected' : '') . '>' . $name . '</option>';
			echo '</select></p>';
		}
		else
			echo "<input type=\"checkbox\" name=\"prefs[$cat][$pref]\" id=\"prefs[$cat][$pref]\" value=\"1\" " . (@$_SESSION['user']->get_pref($cat, $pref) ? 'checked' : '') . " onclick=\"show_and_blink('save_prefs'); return true;\" /> $label ";
	}
}


function is_admin()
{
	if(! isset($_SESSION['user']))
		return false;
	return $_SESSION['user']->is_admin();
}

function array2obj($data)
{
	return is_array($data) ? (object) array_map(__FUNCTION__,$data) : $data;
}

function mysql_query_debug($query)
{
	$time = microtime(true);
	$ret = mysql_query($query);
	$elapsed = microtime(true) - $time;
	if($elapsed > 2.0)
	{
		$file = '/srv/www/kanjibox.net/logs/mysql_slow_queries.txt';
		$file = fopen($file, 'a');
		if($file) {
			fwrite($file, (@$_SERVER['REMOTE_ADDR'] ? $_SERVER['REMOTE_ADDR'] : 'local') . ' - ' . date('m/d/Y H:i:s') . "\n" . $elapsed . " s.\n" . $query . "\n***********************************\n\n");
			fclose($file);
		}
		else {
			error_log("Can't open file $file to log slow SQL query.");
		}
	}

	// $file = fopen('/home/kanjistory/kb_logs/query_time.txt', 'a');
	// $parts = explode(' ', $query);
	// fwrite($file, (@$_SERVER['REMOTE_ADDR'] ? $_SERVER['REMOTE_ADDR'] : 'local') . "\t" . time() . "\t" . $parts[0] . "\t" . sprintf('%.6f', $elapsed) . "\n");
	// fclose($file);

	return $ret;
}

function cached_query_value($query)
{
	$file = fopen('/srv/www/kanjibox.net/logs/cached_queries.txt', 'a');
	fwrite($file, "\n" . @$_SERVER['REMOTE_ADDR'] . ' - ' . date('m/d/Y H:i:s') . "\n" . $elapsed . " s.\n" . $query . "\n***********************************\n");

	define('CACHE_EXPIRATION', 3600);
	if(isset($_SESSION['cache'][$query]))
		if($_SESSION['cache'][$query]['ts'] + CACHE_EXPIRATION > time())
			return $_SESSION['cache'][$query]['value'];

	$res = mysql_query($query) or log_db_error($query, '', false, true);
	$val = mysql_fetch_field($res);

	if(isset($_SESSION['cache'][$query]))
		mysql_query("UPDATE cache SET value = " . (int) $val . ", ts = NOW() WHERE cache_id = " . $_SESSION['cache'][$query]['cache_id']);
	else
		mysql_query("INSERT INTO cache SET query = '" . mysql_real_escape_string($query) . "', value = " . (int) $val . ", ts = NOW()");

	fwrite($file, "Queried and cached value: $val\n");

	$_SESSION['cache'][$query] = array('ts' => time(), 'value' => $val, 'query' => $query);

	return $val;
}


function require_elite_user()
{
	if(@$_SESSION['user'] && $_SESSION['user']->is_elite())
		return true;

	echo '<div class="error">This page is only available for "Elite" level users.</div></body></html>';
	exit();
}

function j2n($j) {
	if($j >= 3)
		return $j+1;
	else
		return $j;
}

function post_db_correction($table_name, $id_name, $id_value, $col_name, $new_value, $apply = false, $id_name_2 = '', $id_value_2 = '', $reviewed = false, $user_cmt = '', $need_work = false) {
	if(! @$_SESSION['user'])
		return 'no logged-in user';
	$table_name = mysql_real_escape_string($table_name);
	$col_name = mysql_real_escape_string($col_name);
	$id_name = mysql_real_escape_string($id_name);
	$_table_name = "`" . str_replace("`", "", $table_name) . "`";
	$_col_name = "`" . str_replace("`", "", $col_name) . "`";
	$_id_name = "`" . str_replace("`", "", $id_name) . "`";
	$id_value = (int) $id_value;
	$new_value = mysql_real_escape_string($new_value);
	$user_cmt = mysql_real_escape_string($user_cmt);
	$need_work = (int) $need_work;

	if($id_name_2) {
		$select_id_2 = ' AND `' . str_replace("`", "", $id_name_2) . "` = '" . mysql_real_escape_string($id_value_2) . "'";
		$insert_id_2 = ", id_name_2 = '" . mysql_real_escape_string($id_name_2) . "', id_val_2 = '" . mysql_real_escape_string($id_value_2) . "'";
	}
	else {
		$select_id_2 = '';
		$insert_id_2 = '';
	}
	$res = mysql_query("SELECT $col_name AS old_value FROM $_table_name WHERE $_id_name = $id_value" . $select_id_2);
	if(! $res)
		return 'Invalid params: ' . mysql_error();
	if(mysql_num_rows($res) == 0)
		return 'No matching db row';
	if(mysql_num_rows($res) > 1)
		return 'More than one matching db row';

	$row = mysql_fetch_object($res);
	$old_value = mysql_real_escape_string($row->old_value);
	if($old_value == $new_value)
		return 'Value unchanged';

	$user_id = (int) $_SESSION['user']->get_id();

	$res = mysql_query("INSERT INTO data_updates SET user_id = $user_id, table_name = '$table_name', id_name = '$id_name', id_value = $id_value, col_name = '$col_name', old_value = '$old_value', new_value = '$new_value', applied = 0, usr_cmt = '$user_cmt' $insert_id_2, need_work = $need_work");
	if(! $res)
		return 'Invalid params for data_updates insert: ' . mysql_error();

	if($apply) {
		$update_id = mysql_insert_id();
		$res = mysql_query("UPDATE $_table_name SET $col_name = '" . ($need_work ? '(~)' : '') . "$new_value' WHERE $_id_name = $id_value" . $select_id_2);
		if(! $res)
			return 'Can\'t apply: ' . mysql_error();

		$reviewed = (int) ($_SESSION['user'] && $_SESSION['user']->is_editor());
		mysql_query("UPDATE data_updates SET applied = 1, reviewed = $reviewed, need_work = $need_work WHERE update_id = $update_id") or die(mysql_error());

		return 'Update successfully logged and applied';

	}
	else
		return 'Update successfully logged';
}

function get_audio_hash($str) {
	return md5($str . 'hophophop');
}

function display_editors_board() {
	?>
<div class="search_form" style="background-color:#DDD;"><h3><a href="#"  onclick="$('textarea#editors_board').toggle(); return false;">Editors Bulletin Board:</a></h3>
	<?php
		if(file_exists(dirname(__FILE__) . '/../tools/notes.txt'))
			$notes = file_get_contents(dirname(__FILE__) . '/../tools/notes.txt');
		else
			$notes = '';
	?>
	<textarea id="editors_board" style="width:98%;height:<?php echo min(max(5, strlen($notes)/90) + count(explode("\n", $notes)), 15) ?>em" onchange="save_board(this);"><?php echo $notes; ?></textarea>
</div>
<script type="text/javascript">
	function save_board(txtobj) {
		$('#ajax-result').html('Saving...').show();
		$.get('<?php echo SERVER_URL ?>ajax/mod_board/action/save/?board_content=' + encodeURIComponent($(txtobj).val()), function (data) {
			$('#ajax-result').html(data).show();
			setTimeout(function() { $('#ajax-result').hide() }, 2000);
		});
	}
</script>
<?php
}

function get_query_hash($query) {
	return md5($query . "no-ch34ting!" . date('%Y%M%H'));
}

function execute_query_with_hash($query, $payload, $apply = true) {
	if(get_query_hash($query) != $payload)
		die('Nonono...');

	execute_query($query, $apply);
}

function execute_query($query, $apply = true, $force_run_again = false) {

	$res = mysql_query('SELECT * FROM data_update_queries WHERE query_str = \'' . mysql_real_escape_string($query) . "'") or die(mysql_error());
	if(mysql_num_rows($res) > 0) {
		$row = mysql_fetch_object($res);
		if(!$force_run_again && ($row->applied || !$apply))
			echo "Query already ran";
		else {
			echo "Query already ran but not applied. Applying.<br/>";
			mysql_query("UPDATE data_update_queries SET applied = 1 WHERE update_query_id = $row->update_query_id") or die(mysql_error());
		}
	}
	else {
		mysql_query("INSERT INTO data_update_queries SET user_id = " . $_SESSION['user']->get_id() . ", query_str = '" . mysql_real_escape_string($query) . "', applied = " . (int) $apply)  or die(mysql_error());
	}
	if($apply) {
		mysql_query($query) or die(mysql_error());
		echo "Ran query: $query";
	}
	else {
		echo "Wrote query";
	}
}

function get_progress_bar($ratio, $tot_size = 400, $alt = '', $second_bar_ratio = 0) {
	// if($ratio == 0)
	// 	return '';
	// F2826C

	return "<div style=\"width:" . ($ratio * $tot_size / 100) . "px; height:15px; background-color:#" . ($ratio == 100 ? '1C6' : 'ABF') . "; border:1px solid black; float:left; color:#FFF; font-size:13px; font-weight:bold; text-align:center; margin-top:3px; padding-top:0; overflow:visible;\" alt=\"$alt\" title=\"$alt\">"  . ($ratio > 5 ? "$ratio%" : '') .  "</div>" .
	($second_bar_ratio > 0 ? "<div style=\"width:" . ($tot_size * $second_bar_ratio / 100) . "px;height:15px;background-color:#F2826C;border-top:1px solid black;border-right:1px solid black;border-bottom:1px solid black;float:left;margin-top:3px; font-size:13px;\">"  . ($second_bar_ratio < 5 ? '' : " $second_bar_ratio%") .  "</div>" : '') .
	($ratio + $second_bar_ratio < 100 ? "<div style=\"width:" . ($tot_size * (100 - $ratio - $second_bar_ratio) / 100) . "px;height:15px;background-color:#DDD;border-top:1px solid black;border-right:1px solid black;border-bottom:1px solid black;float:left;margin-top:3px; font-size:13px;\" alt=\"$alt\" title=\"$alt\">"  .
	(($ratio > 5 || $second_bar_ratio > 0) ? '' : "$ratio%") .  "</div>" : ''). "<div style=\"clear:both;\"></div>";
}


function old_to_new_jlpt($level) {
	switch($level) {
		case LEVEL_1:
		case LEVEL_J4:
			return LEVEL_N5;
		break;
		case LEVEL_2:
		case LEVEL_J3:
			return LEVEL_N4;
		break;
		case LEVEL_3:
		case LEVEL_J2:
			return LEVEL_N2;
		break;
		case LEVEL_SENSEI:
		case LEVEL_J1:
			return LEVEL_N1;
		break;
		case LEVEL_N5:
		case LEVEL_N4:
		case LEVEL_N3:
		case LEVEL_N2:
		case LEVEL_N1:
		default:
			return $level;
		break;
	}
}

?>