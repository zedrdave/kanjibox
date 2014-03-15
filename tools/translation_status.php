<?php

if(!@$_SESSION['user'])
	die("need to be logged");

if(@$params['type'] == 'kanji') {
	$table = 'kanjis';
	$table_ext = 'kanjis_ext';
	$table_ext_idx = 'kanji_id';
	$content_col = 'meaning_';
}
else {
	$table = 'jmdict';
	$table_ext = 'jmdict_ext';
	$table_ext_idx = 'jmdict_id';
	$content_col = 'gloss_';
}
	
$res = mysql_query("SELECT COUNT(*) AS c FROM $table j LEFT JOIN $table_ext jx ON jx.$table_ext_idx = j.id") or die(mysql_error());
$row = mysql_fetch_object($res);
// echo "<h2><strong>TOTAL:</strong> $row->c words</h2>";
$tot = $row->c;

$res = mysql_query("SELECT COUNT(*) AS c FROM $table j LEFT JOIN $table_ext jx ON jx.$table_ext_idx = j.id WHERE j.njlpt > 0") or die(mysql_error());
$row = mysql_fetch_object($res);
// echo "<h3> JLPT: $row->c words</h3>";
$jtotals[0] = $row->c;

for($i = 5; $i > 0; $i--) {
	$res = mysql_query("SELECT COUNT(*) AS c FROM $table j LEFT JOIN $table_ext jx ON jx.$table_ext_idx = j.id WHERE j.njlpt = $i") or die(mysql_error());
	$row = mysql_fetch_object($res);
	// echo "<h3> JLPT N$i: $row->c words</h3>";
	$jtotals[$i] = $row->c;
}

echo '<ul>';
foreach(Vocab::$lang_strings as $lang => $lang_full) {
	if($lang == 'en')
		continue;
		
	$res = mysql_query("SELECT COUNT(*) AS c FROM $table j LEFT JOIN $table_ext jx ON jx.$table_ext_idx = j.id WHERE jx.$content_col$lang_full != ''");
	$row = mysql_fetch_object($res);
	$ratio =  round(100 * $row->c / $tot, 2);


	echo "<li style=\"margin:20px 0 0 0; list-style-type: none;\"><img src=\"" . SERVER_URL . "/img/flags/$lang.png\" style=\"float:left;margin-right:10px;\" alt=\"flag\" /> <div style=\"float:left;margin:4px 6px 0 0;\">$lang_full</div> " . get_progress_bar($ratio, 600, "$row->c/$tot") . "\n";
	
	echo "<ul>\n";
	$res = mysql_query("SELECT COUNT(*) AS c FROM $table j LEFT JOIN $table_ext jx ON jx.$table_ext_idx = j.id WHERE j.njlpt > 0 AND jx.$content_col$lang_full != ''");
	$row = mysql_fetch_object($res);
	$ratio =  round(100 * $row->c / $jtotals[0], 2);
	echo "<li style=\"margin:1px; list-style-type: none;\"> <div style=\"float:left;margin:4px 6px 0 0;\">JLPT</div> " . get_progress_bar($ratio, 550, "$row->c/$tot") . "</li>\n";
	
	for($i = 5; $i > 0; $i--) {
		$res = mysql_query("SELECT COUNT(*) AS c FROM $table j LEFT JOIN $table_ext jx ON jx.$table_ext_idx = j.id WHERE j.njlpt = $i AND jx.$content_col$lang_full != ''");
		$row = mysql_fetch_object($res);
		
		$ratio =  round(100 * $row->c / $jtotals[$i], 2);
		echo "<li style=\"margin:1px; list-style-type: none;\"> <div style=\"float:left;margin:4px 6px 0 0;\">N$i</div> " . get_progress_bar($ratio, 500, "$row->c/$tot") . "</li>\n";
	}
	
	echo "</ul></li>\n";
	
}

echo '</ul>';

?>