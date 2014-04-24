<?php
	
require_once('libs/lib.php');
require_once get_mode() .'.config.php';


if(! init_app())
{	
	header('Cache-Control: private, no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
	header('Pragma: no-cache');
	
	require_once('libs/connect_lib.php');
	display_login_page(SERVER_URL . 'decks.php?' .$_SERVER['QUERY_STRING'], 'To use <a href="https://www.facebook.com/kanjibox/">KanjiBox Decks</a>, please: ');
	exit();
}

?><!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="viewport" content="width=device-width,user-scalable=no">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black">
<title>KanjiBox Cards</title>
<script src="//ajax.googleapis.com/ajax/libs/jquery/2.0.3/jquery.min.js"></script>
<script src="<?php echo SERVER_URL ?>js/doubletap.js"></script>
<script src="<?php echo SERVER_URL ?>js/iscroll.js"></script>
<link rel="stylesheet" href="<?php echo SERVER_URL ?>css/decks.css" />
<link rel="apple-touch-icon-precomposed" sizes="57x57" href="<?php echo SERVER_URL ?>img/icons/apple-icon-57x57-precomposed.png" />
<link rel="apple-touch-icon-precomposed" sizes="72x72" href="<?php echo SERVER_URL ?>img/icons/apple-icon-72x72-precomposed.png" />
<link rel="apple-touch-icon-precomposed" sizes="114x114" href="<?php echo SERVER_URL ?>img/icons/apple-icon-114x114-precomposed.png" />
<link rel="apple-touch-icon-precomposed" sizes="144x144" href="<?php echo SERVER_URL ?>img/icons/apple-icon-144x144-precomposed.png" />
<script type="text/javascript">
var myScroll;

function set_sizes() {
   $('#scroller li').height(Math.min(screen.height, window.innerHeight) - $('#header').height())
   document.body.style.fontSize = (Math.ceil(window.innerHeight / 10)) + 'px';
}

$(function() {
 
	$(window).resize(set_sizes);
 	set_sizes();
	
	myScroll = new IScroll('#wrapper', {
		snap: 'li',
		momentum: false,
		preventDefault: false,
		// bounceEasing: false
		// eventPassthrough: true
	 });
	 
	 var DELAY = 300, timer = null;
	 clicks = 0;
	 
	 var wasDragging = false, cancelNextClick = false;
	 
	 
	 myScroll.on("flick", function(e) {
	 		cancelNextClick = true
			setTimeout(function(evt){ cancelNextClick = false }, 500)
 // 	 		 wasDragging = true
	 })
	 
	 $('#scroller li').mousedown(function() {
	     $(window).mousemove(function() {
	         cancelNextClick = true;
	         $(window).unbind("mousemove");
				setTimeout(function(evt){ cancelNextClick = false }, 500)
	     });
	 })
	 	  
	  $('#scroller li').doubletap(
		  function() {
		 	 $('#thelist').toggleClass('front back');  //perform double-click action
		  },
  			function() {
				// alert('click: ' + wasDragging + ' ' + cancelNextClick + ' ' + myScroll.currentPage.pageY + '/' + myScroll.pages[0].length)
				
				if(wasDragging)
					return;
				if(cancelNextClick) {
					cancelNextClick = false;
					return
				}
				 if(myScroll.currentPage.pageY+1 >= myScroll.pages[0].length)
					myScroll.goToPage(0, 0, 600)
				else
					myScroll.goToPage(0, myScroll.currentPage.pageY+1, 500)
  			}, 300);
	 
  	 	 $('#scroller a').on("click", function(e){
  			 cancelNextClick = true;
  	 	 		 // e.stopImmediatePropagation();
  	 	 })
		 $('#scroller li form *').on("click", function(e){
		 		 // e.stopImmediatePropagation();
		 			cancelNextClick = true;
		 })
		 // $('#scroller li form select').on("mouseup", function(e){
		 // 			 cancelNextClick = true;
		 // 		  e.stopImmediatePropagation();
		 // })
		 
		 $('#header').on("click", function(e) { myScroll.goToPage(0, 0, 400); })
})

</script>


</head>
<body>
<div id="header">KanjiBox Flashcards</div>
	<?php
	$type = (@$_REQUEST['type'] == 'vocab' ? 'vocab' : 'kanji');
	if(@$_REQUEST['njlpt'])
		$njlpt = min(5, max(1, @$_REQUEST['njlpt']));
	else
		$njlpt = max(1, $_SESSION['user']->get_level());
	$include_below = (@$_REQUEST['include_below'] == 1);
	
	$curve_values = array(1500 => 'Very Bad', 1050 => 'Bad', 950 => 'OK', 500 => 'Good', 0 => 'Very Good');
	
	if(isset($_REQUEST['curve']))
		$curve = (int) $_REQUEST['curve'];
	else
		$curve = 1050;
	if(! @$curve_values[$curve])
		$curve = 1050;
	
	if(isset($_REQUEST['num_examples']))
		$num_examples = (int) $_REQUEST['num_examples'];
	else
		$num_examples = 3;
	
	$user_id = mysql_real_escape_string($_SESSION['user']->getID());

	$newtoo = (@$_REQUEST['newtoo'] ? true : false);
	$include_below = (@$_REQUEST['include_below'] ? true : false);

	if($newtoo)
		$extra = ' OR l.curve IS NULL';
	else
		$extra = '';
	
	$lang_kanji = Vocab::$lang_strings[$_SESSION['user']->get_pref('lang', 'vocab_lang')];				
	$lang_vocab = Kanji::$lang_strings[$_SESSION['user']->get_pref('lang', 'kanji_lang')];				
	
	$extra_jlpt = $include_below ? '>' : '';
	mysql_query('SET SESSION group_concat_max_len = 10000;') or die(mysql_error());
	
	$query = "SELECT k.kanji, k.curve, prons, IFNULL(kx.meaning_$lang_kanji, CONCAT('<span class=\"missing-translation\">', kx.meaning_english, '</span>')) AS meaning, GROUP_CONCAT(DISTINCT CONCAT('<p><span class=\"main\">', ex.word, '</span><span class=\"pron\">【', ex.reading, '】</span>: ', IFNULL(jx.gloss_$lang_vocab, CONCAT('<span class=\"missing-translation\">', jx.gloss_english, '</span>')), '</p>') SEPARATOR '\n') AS examples
FROM (
	SELECT k.id, k.kanji, k2w.word_id, l.curve
	FROM kanjis k
	LEFT JOIN learning l ON l.kanji_id = k.id AND l.user_id = '$user_id'
	LEFT JOIN kanji2word k2w ON k2w.kanji_id = k.id
	JOIN kanji2word k2w2 ON k2w2.kanji_id = k.id AND k2w2.pri > k2w.pri
	WHERE k.njlpt $extra_jlpt= $njlpt AND l.curve > $curve $extra
	GROUP BY k.id, k2w.kanji_id, k2w.word_id
	HAVING COUNT( k2w2.kanji_id ) <= $num_examples
	) AS k
	LEFT JOIN kanjis_ext kx ON kx.kanji_id = k.id
	LEFT JOIN jmdict ex ON ex.id = k.word_id
	LEFT JOIN jmdict_ext jx ON jx.jmdict_id = ex.id
GROUP BY k.id
ORDER BY k.curve DESC LIMIT 100";

// die ('<p>' . $query . '</p>');

$res = mysql_query($query) or die(mysql_error());//log_db_error($query, false, true);
$tot = mysql_num_rows($res);

	?>
<div id="wrapper">
	<div id="scroller">
		<ul id="thelist" class="<?php echo ($tot > 0 ? 'front' : 'back') ?>">
			<li class="intro">
				<p class="intro">Deck of Flashcards selected based on your personal <a href="http://kanjibox.net/kb/">KanjiBox</a> statistics.</p>
				<p class="intro showfront"><em><strong>Tap</strong></em>: go to next card • <em><strong>Double-tap</strong></em>: flip card<br/><em><strong>Tap on header</strong></em>: come back to this screen.</p>
				<p class="intro"><?php if($tot)
					echo 'This deck contains <span class="parambold">' .  $tot . '</span> ' . $type . ' cards, level <span class="parambold">N' . $njlpt . '</span>' . ($include_below ? ' and below' : '') . ', where your current learning level is <span class="parambold">' . $curve_values[$curve] . '</span> or worse.';
					else
						echo 'This deck does not contain any cards. Please select some settings and click refresh.'; ?> <span class="showfront">[<a href="#" onclick="$('#thelist').toggleClass('front back'); return false;">change deck settings</a>]</span></p>
				<form method="get" action="<?php echo SERVER_URL ?>decks.php">
				<p class="intro showback">
					Generate deck of kanji (include up to <select name="num_examples" id="num_examples"><?php
						for($i = 0; $i <= 5; $i++)
							echo "<option value=\"$i\"" . ($i == $num_examples ? ' selected' : '') . ">$i</option>";
						
					?></select> examples), level <select id="njlpt" name="njlpt">
						<?php
						for($i = 5; $i >= 1; $i--)
							echo "<option value=\"$i\"" . ($i == $njlpt ? ' selected' : '') . ">N$i</option>";
						?>
					</select> <input type="checkbox" name="include_below" id="include_below" value="1" <?php echo ($include_below ? 'checked' : '') ?> /><label for="include_below">and below</label>,
					where your current learning level is at, or worse than, <select id="curve" name="curve">
						<?php
						foreach($curve_values as $key => $val)
							echo "<option value=\"$key\"" . ($key == $curve ? ' selected' : '') . ">$val</option>";
						?>
					</select> (<input type="checkbox" name="newtoo" id="newtoo" value="1" <?php echo ($newtoo ? 'checked' : '') ?> /><label for="newtoo">include those you have never been asked</label>).
					<input type="hidden" name="type" id="type" value="kanji" /> <input type="submit" name="Refresh" value="Refresh ☞"></input>
				</p>
				<p class="intro showfront">
					Use the <em>Add to Home Screen</em> option to save that deck.
				</p>
			</form>
			</li>
			<?php
				
			while($entry = mysql_fetch_object($res)) {
				
				if(is_null($entry->curve))
					$label = 'unknown';
				else {
					$label = '';

					foreach($curve_values as $val => $label)
						if($entry->curve >= $val) {
							$stat_level = str_replace(' ', '', strtolower($label));
							break;
						}
				}
					
				?><li>
				<div class="showback statlevel"><div class="dot <?php echo $stat_level; ?>"></div></div>
				<p class="kanji"><?php echo $entry->kanji ?></p>
				<p class="showback pron"><?php echo $entry->prons ?></p>
				<p class="showback translation"><?php echo $entry->meaning ?></p>
				<div class="showback examples">
					<?php echo $entry->examples ?>
				</div>
			</li><?php
			}
				
			?>
		</ul>
	</div>
</div>

</body>
</html>
