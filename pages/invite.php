<?php

force_logged_in_app();

//  Get list of friends who have this app installed...
		$user = $_SESSION['user']->get_fb_id();
		$rs = $facebook->api_client->fql_query("SELECT uid FROM user WHERE has_added_app=1 and uid IN (SELECT uid2 FROM friend WHERE uid1 = $user)");
		$arFriends = "";
		
		//  Build an delimited list of users...
		if ($rs)
		{
			for ( $i = 0; $i < count($rs); $i++ )
			{
				if ( $arFriends != "" )
					$arFriends .= ",";
			
				$arFriends .= $rs[$i]["uid"];
			}
		}
		
		//  Construct a next url for referrals
		$sNextUrl = APP_URL . urlencode("?refuid=".$user);
		
		//  Build your invite text
		$invfbml = <<<FBML
		You've been invited to join Kanji Box!<br/>
		<fb:name uid="$user" firstnameonly="true" shownetwork="false"/> wants to invite you over to <a href="http://www.facebook.com/apps/application.php?id=5132078849">Kanji Box</a>, where you will be able to train in the ancient secret art of Kanji, hone your Japanese skills and measure yourself up to <fb:pronoun objective="true" uid="$user"/> in a Kanji quiz fight to the death!
		<fb:req-choice url="http://www.facebook.com/apps/application.php?id=5132078849" label="Gambare!" />
FBML;
		
		?>
		
		<fb:request-form type="Kanji Box" action="<?php echo APP_URL ?>" content="<?php echo htmlentities($invfbml); ?>" invite="true">
			<fb:multi-friend-selector max="20" actiontext="Here are your friends who don't have Kanji Box installed. Invite them to play with you!" showborder="true" rows="5" exclude_ids="<?php echo $arFriends; ?>">
		</fb:request-form>		
