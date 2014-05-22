<?php
$userID = $_SESSION['user']->getID();

$type = @$_POST['filter_set_type'];

$filter_str = trim(@$_POST['filter_str']);
$tags = @$_POST['tags'];

if(! $filter_str && !$tags)
	die('<div class="error_msg">No search string</div>');

$query = "SELECT ls.*, subs.user_id AS sub_user_id, COUNT(*) AS set_size FROM learning_sets ls LEFT JOIN learning_set_subs subs ON subs.user_id = $userID AND subs.set_id = ls.set_id LEFT JOIN learning_set_$type lse ON lse.set_id = ls.set_id ";

if($tags && count($tags)) {
	$query .= "JOIN learning_set_tags lst ON lst.set_id = ls.set_id AND (0 ";
	foreach($tags as $tag_id)
		$query .= "OR lst.tag_id = " . (int) $tag_id . " ";
	$query .= ")";
}

$query .= "WHERE ls.deleted = 0 AND ls.set_type = '$type' ";

// $query .= "AND ls.user_id != $user_id ";
$query .= "AND ls.public = 1 ";

if($filter_str)
	$query .= "AND (ls.name LIKE '%" . DB::getConnection()->quote($filter_str) . "%' OR ls.description LIKE '%" . DB::getConnection()->quote($filter_str) . "%')";
	
$query .= " GROUP BY ls.set_id ORDER BY ls.date_modified DESC LIMIT 100";

$res = mysql_query($query) or die(mysql_error());

 // echo $query;

if(mysql_num_rows($res) == 0)
	echo "No match...";
else {
	while($row = mysql_fetch_object($res)) {
		echo "<div class=\"set_line\">";
		
		if(!$row->sub_user_id && $row->user_id != $userID)
			echo"<button onclick=\"subscribe_to_set($row->id, this); return false;\">subscribe</button> ";

		if($_SESSION['user']->isAdministrator())
			echo '<button onclick="do_load(\'' . SERVER_URL . 'ajax/edit_learning_set/?set_id=' . $row->id . '\', \'set_details\'); return false;">edit</button> ';
		else
			echo '<button onclick="do_load(\'' . SERVER_URL . 'ajax/edit_learning_set/?set_id=' . $row->id . '\', \'set_details\'); return false;">' . ($row->editable && $row->sub_user_id ? 'edit' : 'view')  . '</button> ';
			// echo"<button onclick=\"show_set_id($row->set_id); return false;\"". (!$row->sub_user_id ?  ' style="display:none;"' : '') . ">" . ($row->editable ? 'edit' : 'view') . "</button> ";
		
		echo "<span class=\"name\" id=\"set_name_$row->id\">$row->name</span> <span class=\"size\">$row->set_size";
		echo ($row->set_size == 1 ? ' entry' : ' entries');
			
		echo "</span>";
		if($row->editable)
			echo '<span class="prop">editable</span>';

		$res_tag = mysql_query("SELECT t.* FROM learning_set_tags lst LEFT JOIN tags t ON t.tag_id = lst.tag_id WHERE lst.set_id = $row->id");
		while($tag_row = mysql_fetch_object($res_tag))
			echo "<span class=\"tag_box\">$tag_row->tag</span>";

		echo "<div class=\"description\">". (strlen($row->description) > 400 ? substr($row->description, 0, 300) . '...' : $row->description) . "</div>";
		
		echo "</div>";
?>
		
<script type="text/javascript">

	function subscribe_to_set(set_id, btn) {
		$(btn).prop('disabled', 'disabled');
		$.post('<?php echo SERVER_URL ?>ajax/edit_learning_set/', {'set_id': set_id, 'subscribe_to_set': true}, function (data, textStatus, jqXHR) {
			$(btn).hide();
			$(btn).next().show();
			
			var set_name = $('span#set_name_' + set_id).html();	
			
				
			show_set_id(set_id);
			$('#edit_set_select').append($('<option>', { value : set_id }).text(set_name));
			$('#edit_set_select').val(set_id).change();
		});
	}
	
	function show_set_id(set_id) {
		// $('#edit_set_select').prop('disabled', 'disabled')
		// 
		// $.get('<?php echo SERVER_URL ?>ajax/edit_learning_set/?set_id=' + set_id, function (data, textStatus, jqXHR) {
		// $('#edit_set_select').prop('disabled', '')
		// $('#set_details').html(data);
		$('#edit_set_select').val(set_id).change();
		// });
	}
</script>

<?php
	}
}

?>