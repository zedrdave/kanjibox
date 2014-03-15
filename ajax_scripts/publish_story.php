<?php
if(!@$_SESSION['user'])
	die('Need valid session');

$type = @$params['type'];
if(!$type)
	die('Need type');
	
		
if(! fb_connect_init()) {
	global $facebook, $api_key, $secret;
	
	require_once(ABS_PATH . 'api/facebook.php');
	if(! is_object($facebook))
		$facebook = new Facebook(array('appId'  =>  $api_key, 'secret' => $secret,  'cookie' => true));
	
	$loginUrl = $facebook->getLoginUrl(array(
	'redirect_uri' => SERVER_URL . 'page/highscores/action/publish_story/publish_type/' . $type . '/',
	 'oauth' => true,
	'cookie' => true,
	'scope'=>'publish_stream',
	));
	
	?>
	<script type="text/javascript">
		window.location.href = '<?php echo $loginUrl; ?>';
	</script>
	<?php
	die();
}


echo $_SESSION['user']->publish_story($type);

?>