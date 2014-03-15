<?php
if(! @$_SESSION['user'])
{
	log_error('Empty SESSION[user] in load_next_wave.php', true);
	log_error('You need to be logged to access this function.', false, true);
}

if(! @$_SESSION['cur_session'])
{
//	log_error("###DEBUG - load_next_wave: Session time out. ", true, false);
	force_reload('Game session has timed out.');
}

$_SESSION['cur_session']->load_next_wave();

if(!$_SESSION['cur_session']->is_quiz() && $_SESSION['user']->is_logged_in() && $_SESSION['user']->get_pref('drill', 'show_learning_stats'))
{
?>
	<script type="text/javascript">
		do_load('<?php echo SERVER_URL . 'ajax/footer_stats_bar/type/' . $_SESSION['cur_session']->get_type() . ($_SESSION['cur_session']->is_learning_set() ? '/mode/' . SETS_MODE : ''); ?>/', 'footer-stats'); 
	</script>
<?php
}

if (! $_SESSION['cur_session']->display_wave())
{
//	log_error('###DEBUG: load_next_wave.php: display_wave() returned false, emptying sessions');
	$_SESSION['cur_session']->cleanup_before_destroy();
	$_SESSION['cur_session'] = NULL;
}
?>