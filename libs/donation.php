<?php
	$donation_total = 250;
	$donation_current = 63.5; // real 52.3
//	$tot_width = 700;

// last: George - 5
?>
<div style="background-color:#FED; border: 2px solid #999; padding: 10px; margin: 10px 10px 30px 10px;">
	<h1>Keep KanjiBox Going: Donate!</h1>
	<div style="width: 580px; float: left; margin-left:5px;">
	<p style="font-size:14px; font-weight:bold;"><a style="color:#600; font-weight:bold; font-style:italic;" href="http://apps.facebook.com/kanjibox/page/donate/">Really...</a></p>
	</div>
	<form action="https://www.paypal.com/cgi-bin/webscr" method="post" style="text-align:center; padding: 15px; margin-top: 10px;">
		<input type="hidden" name="cmd" value="_s-xclick">
		<input type="hidden" name="hosted_button_id" value="6660774">
		<input type="image" src="https://www.paypal.com/en_US/i/btn/btn_donate_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
		<img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1">
	</form>
	<div style="clear:both;"/>

	<div style="border:1px solid #000; background-color: white; margin-top:10px; height:15px; "><div style="background-color:#9CF; font-weight: bold; padding: 2px; float:left; height:11px; border-right:1px solid #000; margin-right:4px; width:<?php echo round(100*$donation_current/$donation_total) ?>%;"></div><?php echo $donation_current ?> 千円</div>
	<div style="clear:both;"/>


</div>