<?php
if(@$_SESSION['user']  && !empty($_REQUEST['deviceid']))
{
	register_kb_code($_REQUEST['deviceid'], @$_REQUEST['kbcode']);
}

function register_kb_code($device_id, $kb_code_array) {
	$secret_salt = 'kangetsukyo-massive';
	
	$device_id = strtoupper(trim($device_id));
	if(! preg_match('/^[0-9A-F\-]{32,40}$/', $device_id))
	{
		display_user_msg("Invalid Device ID.<br/>Device ID needs to be a string of 32 characters containing only digits '0' to '9' and letters 'A' to 'F'.", MSG_ERROR);
		return -1;
	}
	
	$build = array_shift($kb_code_array);
	$kb_code = strtoupper(implode('', $kb_code_array));
	if(! preg_match('/^[0-9]{3,4}$/', $build) || ! preg_match('/^[0-9A-F]{12,12}$/', $kb_code))
	{
		display_user_msg("Invalid KB Code.<br/>KB Code needs to be in the format: 'XXX-XXXX-XXXX-XXXX', containing only digits '0' to '9' and letters 'A' to 'F'.", MSG_ERROR);
		return -1;
	}
	
	$key = $device_id . '-' . $build . '-' . $secret_salt;
	$hash = substr(strtoupper(sha1($key)), 4, 12);
	// if($_SESSION['user']->is_admin())
	// 	echo $key . ' | ' . $hash . ' | ' . $kb_code;
	
	if($hash != $kb_code)
	{
		display_user_msg("Invalid Device ID/KB Code pair. Make sure you entered both correctly.", MSG_ERROR);
		return 0;
	}

	$res = mysql_query('SELECT * FROM users WHERE device_id = \'' . DB::getConnection()->quote($device_id) . "'") or die("SQL Error.");
	if($row = mysql_fetch_assoc($res))
	{
		display_user_msg("This KB Code has already been claimed by another account. Please contact us if you think this is a mistake.", MSG_ERROR);
		return -2;
	}
	
	$_SESSION['user']->upgradeAccount($device_id, $build, $kb_code);
	display_user_msg("Your account has been successfully upgraded.<br/>Welcome to the Elite!", MSG_SUCCESS);
	return 1;

}

if(@$_SESSION['user'] && $_SESSION['user']->isElite())
{
?>
<h2>Special Pages:</h2>

<ul>
	<li><a href="https://kanjibox.net/kb/page/play/type/text/mode/grammar_sets/">Grammar Sets</a>! <strong>(New)</strong></li>
	<li><a href="http://kanjibox.net/kb/page/play/mode/drill/type/text/">Text Drill</a></li>
	<li><a href="<?php echo get_page_url(PAGE_RANKS) ?>">KanjiBox ranking breakdown</a></li>
	<li><a href="<?php echo get_page_url('vocab_levels') ?>">Vocab Levels</a></li>
</ul>

<p>Also don't forget that "Elite" status users can use <a href="http://kanjibox.net/kb/page/faq/#hotkeys">keyboard shortcuts during drill/quiz</a>.</p>

<h2>Check this page regularly for new additions...</h2>
<?php
}
else
{
	
	if(@$params['special'] == 'ios')
	{
		echo '<div id="iphone-instructions">';
	}
	else
	{
	?>
		<p>You need to have "Elite" status to access this section of KanjiBox.</p>
        
        <p>If you own a copy of <a href="http://kanjibox.net/ios/">KanjiBox for iOS</a>, please <a href="#" onclick="$('#iphone-instructions').fadeIn(); return false;">click here</a> for more info on your free upgrade.</p>
		
        <p>If you do not use KanjiBox for iOS, you can still get an upgrade to Elite status by <a href="https://kanjibox.net/kb/page/faq/#elite">making a small donation</a>.</p>
	
        <div id="iphone-instructions" style="display:none;">
	<?php
	}
	
	?>
			<fieldset style="margin-top: 10px;"><legend style="font-size: 120%; font-weight: bold;">Free Status Upgrade for iDevice KanjiBox Users</legend>
				<p><big>Owning a copy of <a href="http://kanjibox.net/ios/">KanjiBox for iOS</a> entitles you to a free status upgrade on KanjiBox Online.</p> 
					<p><big><strong>The easy way</strong>:<br/>simply use the 'Sync' function (under the 'Settings' menu of KanjiBox on your device). Make sure to use the 'Create with Facebook' button when creating your Sync account... and you are done! (KanjiBox will automatically tie your device account to this account and give you Elite status).<br/>If you used an email login instead of Facebook to log into KB Online, use the same email login and password with the 'Create account' in the Sync panel on your device.</big></p>
					<br/>
                    <hr/>
                    <br/>
					<p>If for any reason you do not want to sync your accounts, please follow the simple instructions below to upgrade your account.</p>
				
				<img src="http://kanjibox.net/kb/img/kbcode_instructions.png" style="float: left; margin-right: 5px; width: 320px; height: 400px;"></img>
				
				<p>With the <em>latest</em> version of KanjiBox for iOS:
					<ol style="font-size:80%;">
						<li>Start the application on your iOS device.</li>
						<li>Go to <strong>Credits</strong>, under the main menu.</li>
						<li>At the bottom of the screen, you should see two lines entitled: "<strong>Device ID</strong>" and "<strong>KB Code</strong>" (see illustration).</li>
						<li>Copy the two strings, <em>exactly as they appear</em>, into the form below.</li>
						<li>Press Submit.</li>
						<li>You are done!</li>
					</ol>
				</p>
				<p>
					<form action="http://kanjibox.net/kb/page/elite/" method="post" style="margin: 5px 2px 10px 2px;">
						Device ID: <input type="text" size="40"  maxlength="40" name="deviceid" value="<?php echo @$_REQUEST['deviceid']; ?>"></input><br/>
						KB Code: <input type="text" size="3" maxlength="3" name="kbcode[0]" value="<?php echo @$_REQUEST['kbcode'][0]; ?>"></input>-<input type="text" size="4"  maxlength="4" name="kbcode[1]" value="<?php echo @$_REQUEST['kbcode'][1]; ?>"></input>-<input type="text" size="4" maxlength="4" name="kbcode[2]" value="<?php echo @$_REQUEST['kbcode'][2]; ?>"></input>-<input type="text" size="4" maxlength="4" name="kbcode[3]" value="<?php echo @$_REQUEST['kbcode'][3]; ?>"></input><br/>
						<div style="text-align: center; margin: 5px;"><input type="submit" name="Submit" value="Submit" style="font-size: 120%;"></div>
					</form>
					
				</p>
				
				<p><strong>Note 1: </strong> You can copy-paste the codes on your iOS by "double-tapping" on the text. You would then need to paste it into a note or email, in order to recover it on your computer. If you enter them by hand, be aware that they should solely consist of digits between '0' and '9', and letters from 'A' to 'F' (no letter 'O'!).
					</p>
				<p><strong>Note 2: </strong> Each iOS application has a unique KB Code, which can only be used once. Account upgrades are permanent and cannot, in principle, be transferred. If you run into any trouble, do not hesitate to <a href="mailto:support@kanjibox.net">contact me directly</a>.
				</p>
				<div style="clear:both;"></div>
				
			</fieldset>
		</div>
	<?php
}
?>