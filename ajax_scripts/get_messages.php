<?php
if(! @$_SESSION['user'])
{
	log_error('You need to be logged-in to access this function.', false, true);
}

$query = 'SELECT m.*, u_to.fb_id AS fb_id, ux_to.first_name AS user_to_first_name, ux_to.last_name AS user_to_last_name FROM `messages` m LEFT JOIN users u_to ON u_to.id = m.user_id_to JOIN users_ext ux_to on ux_to.user_id = u_to.id WHERE ';
if(@$params['type'] == 'from')
	$query .= "user_id_from = " . (int) $_SESSION['user']->get_id();
else
	$query .= "user_id_to = " . (int) $_SESSION['user']->get_id();

if(@$params['show'] == 'all')
	$query .= " ";
elseif(@$params['show'] == 'read')
	$query .= " AND msg_read = 1";
else
	$query .= " AND msg_read = 0";


if(@$params['limit'])
	$limit = $params['limit'];
else
	$limit = 20;
	
if(@$params['skip'])
	$skip = $params['skip'];
else
	$skip = 0;
	
$query .= ' ORDER BY date_created DESC LIMIT ' . (int) $skip . ', ' . (int) $limit;

$res = mysql_query_debug($query) or log_db_error($query, '', false, true);

if(mysql_num_rows($res))
	while ($msg = mysql_fetch_object($res))
	{
		?>
		<div id="kb-msg-<?php echo $msg->message_id ?>" class="kb-msg kb-msg-<?php echo $msg->msg_class ?>">
			<?php
			if($msg->user_id_from == $_SESSION['user']->get_id())
			{
				if($msg->user_to_first_name || $msg->user_to_last_name)
					$to = $msg->user_to_first_name . ' ' . $msg->user_to_last_name;
				else
					$to = 'KB ID: ' . $msg->user_id_to . ' - FB ID: <a href="http://www.facebook.com/profile.php?id=' . $msg->fb_id . '">' . $msg->fb_id . '</a>';
				echo '<div class="msg-to">to: ' . $to .  '</div>';
			}
			?>
			<div class="date"><?php echo $msg->date_created ?></div>
			<div style="clear:both;"></div>
			<div class="msg-title"><?php echo $msg->msg_title ?></div>
			<p><?php echo $msg->msg_text ?></p>
			<?php
			if($msg->user_id_from == 5)
			{
				echo '<div class="signature"><a href="mailto:dave@kanjibox.net">Dave</a></div>';
			}
			
			if(!$msg->msg_read && ($msg->user_id_to == $_SESSION['user']->get_id()))
			{
				?>
			<a class="dismiss" href="#" onclick="do_load('<?php echo SERVER_URL ?>ajax/mark_msg_read/id/<?php echo $msg->message_id ?>/', ''); $('#kb-msg-<?php echo $msg->message_id ?>').fadeOut(); return false;">[dismiss]</a>
			<?php
			}
			else
			{
				echo "<div class=\"dismiss\">" . ($msg->msg_read ? 'Read' : 'Unread') . "</div>";
			}	
			?>
		</div>
		<?php
	}
	// <div id="kb-msg-special" class="kb-msg kb-msg-thanks">
	// 	<div class="msg-title">New on KanjiBox: Badges!</div>
	// 	<p>
	// 		Easy-to-use <a href="http://kanjibox.net/kb/page/badges/">Customised HTML badges</a> to use on your own website/blog/newsboard/etc.<br/><br/>
	// 		Wear your KanjiBox colours proudly!
	// 	</p>
	// 	<a class="dismiss" href="#" onclick="$('#kb-msg-special').fadeOut(); return false;">[dismiss]</a>
	// </div>
?>