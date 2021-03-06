<?php

if(!@$_SESSION['user'] || !$_SESSION['user']->isEditor())
	die("editors only");

mb_internal_encoding('UTF-8');

if(isset($_REQUEST['submit'])) {
	$queryWhere = '';
	$query_from = " FROM examples e ";

	if(@$_REQUEST['example_id'])
		$queryWhere .= ' AND example_id = ' . (int) $_REQUEST['example_id'];
	if(@$_REQUEST['example_str'])
		$queryWhere .= " AND example_str LIKE '%" . DB::getConnection()->quote($_REQUEST['example_str']) . "%'";
	if(@$_REQUEST['english'])
		$queryWhere .= " AND english LIKE '%" . DB::getConnection()->quote($_REQUEST['english']) . "%'";

	if(@$_REQUEST['part_word'] || @$_REQUEST['part_jmdict_id']) {
		$query_from .= "LEFT JOIN example_parts ep ON ep.example_id = e.example_id ";
		if(@$_REQUEST['part_jmdict_id']) {
			$queryWhere .= ' AND ep.jmdict_id = ' . (int) $_REQUEST['part_jmdict_id'];
		}
		if(@$_REQUEST['part_word']) {
			$query_from .= "JOIN jmdict j ON ep.jmdict_id = j.id ";
			$queryWhere .= ' AND j.word = \'' . DB::getConnection()->quote($_REQUEST['part_word']) . '\'';
		}
	}

	if($queryWhere) {				
		if(@$_REQUEST['njlpt'])
			$queryWhere .= ' AND njlpt = ' . (int) $_REQUEST['njlpt'];
		if(@$_REQUEST['njlpt_r'])
			$queryWhere .= ' AND njlpt_r = ' . (int) $_REQUEST['njlpt_r'];

		if(@$_REQUEST['example_str_not'])
			foreach(explode(',', $_REQUEST['example_str_not']) as $word)
				$queryWhere .= " AND example_str NOT LIKE '%" . DB::getConnection()->quote(trim($word)) . "%'";
		
		$queryWhere .= " GROUP BY e.example_id ORDER BY e.njlpt DESC, e.njlpt_r DESC";
	
		$query = "SELECT * " . $query_from . "WHERE 1" . $queryWhere;
	}
	
	// echo $query . "<br/>";
}
?>
<div id="ajax-result"></div>
<form>
	Id: <input name="example_id" size="7" value="<?php echo @$_REQUEST['example_id'] ?>" /> <br/>
	Jp: <input name="example_str" size="50" value="<?php echo @$_REQUEST['example_str'] ?>" /> | Not: <input name="example_str_not" size="50" value="<?php echo @$_REQUEST['example_str_not'] ?>" /><br/>
	En: <input name="english" size="80" value="<?php echo @$_REQUEST['english'] ?>" /><br/>
	Contains word: <input name="part_word" size="25" value="<?php echo @$_REQUEST['part_word'] ?>" /> - ID: <input name="part_jmdict_id" size="25" value="<?php echo @$_REQUEST['part_jmdict_id'] ?>" /><br/>
	Level: <input name="njlpt" size="1" value="<?php echo @$_REQUEST['njlpt'] ?>" /> - R: N<input name="njlpt_r" size="1" value="<?php echo @$_REQUEST['njlpt_r'] ?>" /><br/>
	<input type="submit" name="submit" value="Find" />
</form>
<div id="sentences">
<?php

	if(@$query) {
		// echo $query;
		$res = mysql_query($query) or die(mysql_error());
		while($sentence = mysql_fetch_object($res)) {
			echo "<div id=\"sent_$sentence->example_id\"><div class=\"sentence-block\"><a href=\"#\" onclick=\"load_sentence($sentence->example_id); return false;\">$sentence->example_id</a><br/>";
			echo "<p style=\"font-weight:bold;\">$sentence->example_str</p>";
			echo "<p>[JLPT: N$sentence->njlpt | R: N$sentence->njlpt_r]</p>";
			echo "<p style=\"font-style:italic;\">$sentence->english</p>";
			echo "<p>Status: $sentence->status</p>";
			echo "</div></div>";
		}
	}

?>
</div>
<script type="text/javascript">
	function load_sentence(id)  
	{
		$.get('<?php echo SERVER_URL ?>ajax/get_sentence/edit/yes/?id=' + id + '&container_selector=' + encodeURIComponent('#sent_' + id), function (data) {
			$('#sent_' + id).css('background-color', '#EEE')
			$('#sent_' + id).html(data);
		});
	}
</script>