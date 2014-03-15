<?php
if(! @$_SESSION['user'])
{
	log_error('You need to be logged to access this function.', false, true);
}

$kanji = @$params['kanji'];
if(empty($kanji))
	die('No kanji selected.');
$query = 'SELECT * FROM `kanjis` WHERE kanji = \'' . mysql_real_escape_string($kanji) . '\'';
$res = mysql_query_debug($query) or log_db_error($query, '', false, true);
$row = mysql_fetch_object($res);
if(! $row)
	die('Unknown kanji: ' . $kanji);

?>
<div class="zoom"><span class="japanese"><?php echo $row->kanji ?></span></div>
  <div class="details">
	  <strong><?php echo Kanji::get_meaning_str($row->id) ?></strong><br/>
	  <?php echo Kanji::get_pronunciations($row) ?><br/>
	  <strong>Level: </strong><?php echo $row->njlpt; ?>級
  </div>
  <div style="clear:both;" />