<?php
define('MAX_UPLOAD_SIZE', 1024 * 150);

if(! @$_REQUEST['set_id'])
	die('<div class="error_msg">No set id</div>');
	
$set = new LearningSet($_REQUEST['set_id']);
if(! $set->is_valid())
	die('<div class="error_msg">Invalid set</div>');
	
if(@$_REQUEST['new_name']) {
	$set->update_name($_REQUEST['new_name']);
	exit();
}

if(@$_REQUEST['set_tag_id']) {
	echo $set->set_tag((int) $_REQUEST['set_tag_id'], @$_REQUEST['val']);
	exit();
}

if(isset($_REQUEST['set_public'])) {
	echo $set->set_public($_REQUEST['set_public']);
	exit();
}

if(isset($_REQUEST['set_editable'])) {
	echo $set->set_editable($_REQUEST['set_editable']);
	exit();
}


if(isset($_REQUEST['new_desc'])) {
	echo $set->update_desc($_REQUEST['new_desc']);
	exit();
}


if(isset($_POST['adding_to_set'])) {	
	if(@$_POST['add_to_set'])
		if($err = $set->add_to_set(@$_POST['add_to_set']))
			echo '<div class="error_msg">' . $err . '</div>';
	
	echo $set->get_formatted_list();
	exit();
}
elseif(isset($_POST['remove_entry_id'])) {	
	if($err = $set->remove_from_set(@$_POST['remove_entry_id']))
		echo '<div class="error_msg">' . $err . '</div>';
	
	exit();
}
elseif(isset($_POST['remove_level'])) {	
	$before = $set->get_entry_count();
	
	if($err = $set->remove_level_from_set(@$_POST['remove_level']))
		echo '<div class="error_msg">' . $err . '</div>';
	
	echo '<div class="success_msg">Removed ' . ($before - $set->get_entry_count()) . ' entries.</div>';
	
	echo $set->get_formatted_list();
	exit();
}
elseif(isset($_POST['remove_other_set_id'])) {	
	$before = $set->get_entry_count();

	if($err = $set->remove_other_set_from_set(@$_POST['remove_other_set_id']))
		echo '<div class="error_msg">' . $err . '</div>';
	
	echo '<div class="success_msg">Removed ' . ($before - $set->get_entry_count()) . ' entries.</div>';
	
	echo $set->get_formatted_list();
	exit();
}


if(isset($_POST['subscribe_to_set'])) {	
	if($err = $set->subscribe_to_set())
		echo '<div class="error_msg">' . $err . '</div>';
	else {
		if($_REQUEST['return_play_btn']) {
			echo "<span class=\"subscribed-status\">Subscribed</span> <button onclick=\"location.href ='" . SERVER_URL . "page/play/type/" . $set->get_type() . "/mode/sets/set_id/" . $set->set_id . "/'\">Play</button>";
		}
		else
			echo '<div class="success_msg">Suscribed to set</div>';
	}
	exit();
}

if(isset($_POST['search']) || isset($_FILES['add_to_set_file']['name'])) {
	
	if(isset($_POST['search']))
		$search_str = trim($_POST['search']);
	elseif ( isset($_FILES['add_to_set_file']['name'])
			&& @$_FILES['add_to_set_file']['error'] == UPLOAD_ERR_OK //checks for errors
			&& is_uploaded_file(@$_FILES['add_to_set_file']['tmp_name'])) {
			
		$search_str = file_get_contents($_FILES['add_to_set_file']['tmp_name']);
	}
	else
		die ('<div class="error_msg">File upload error' . (@$_FILES['add_to_set_file']['error'] == UPLOAD_ERR_FORM_SIZE ? ': File exceed maximum size (' . (int) (MAX_UPLOAD_SIZE / 1024) . '&nbsp;kb).' : '...') . '</div>');
		
	
	if(empty($search_str))
		echo '<div class="error_msg">Empty search string...</div>';
	else {
		$arr = $set->search_new_entries($search_str);
		
		if(count($arr) == 0)
			echo '<div class="error_msg">Nothing usable in search string...</div>';
		else {
			echo '<hr/>';
			$import_line = '<h3><input type="checkbox" class="check_select_all" onclick="$(\'#set_results .set_content_line input:enabled\').prop(\'checked\', this.checked); $(\'.check_select_all\').prop(\'checked\', this.checked);"></input> <button class="import_button" onclick="add_new_entries();">Import entries &raquo;</button></h3><form id="set_results" name="set_results" method="post" action=""><input type="hidden" id="set_id" name="set_id" value="' . $set->set_id . '"></input><input type="hidden" id="adding_to_set" name="adding_to_set" value="' . $set->set_id . '"></input>';
			
			echo $import_line;
			
			foreach($arr as $id => $row) {
				echo "<div class=\"set_content_line";
				if(@$row->set_id)
					echo " added";
				echo "\">";
				
				if(isset($row->kanji_id)) {
					echo "<input type=\"checkbox\" name=\"add_to_set[$row->kanji_id]\" id=\"add_to_set[$row->kanji_id]\" value=\"$row->kanji_id\"></input> ";
						
					echo "<label for=\"add_to_set[$row->kanji_id]\">".  LearningSet::$jlpt2char[$row->njlpt] . " <span class=\"kanji\">$row->kanji</span> • <span class=\"prons\">$row->prons</span> • <span class=\"english\">$row->meaning_english</span></label></div>";
				}
				elseif(isset($row->jmdict_id)) {
					echo "<input type=\"checkbox\" name=\"add_to_set[$row->jmdict_id]\" id=\"add_to_set[$row->jmdict_id]\" value=\"$row->jmdict_id\"></input> ";
						
					echo "<label for=\"add_to_set[$row->jmdict_id]\">".  LearningSet::$jlpt2char[$row->njlpt] . " <span class=\"japanese\">" . ($row->usually_kana ? $row->reading : ("$row->word" . ($row->word != $row->reading && !$row->katakana ? " 【" . $row->reading . "】" : ''))) . "</span> • <span class=\"english\">";
					
					$pos = mb_strpos($row->gloss_english, '④');
					if($pos !== FALSE)
						echo mb_substr($row->gloss_english, 0, $pos) . ' [...]';
					else
						echo $row->gloss_english;
					
					echo "</span></label></div>";
				}
			}
			
			echo "</form>";
			
			echo $import_line;
		}
	}
	exit();
}



echo '<em>Set id: <a href="' . get_page_url(PAGE_PLAY, array('type' => $set->get_type(), 'mode' => SETS_MODE, 'view_set_id' => $set->set_id)) . '">' . $set->set_id . '</a></em>';
if(! $set->is_owner()) {
	echo ' (created by: ';

	if($info = $set->get_owner_info()) {
    	if($info->fb_id)
    		echo "<a href=\"https://www.facebook.com/profile.php?id=$info->fb_id\">";
    	if($info->first_name || $info->last_name) {
    		echo $info->first_name . ' ';
    		if($arr = explode(' ', $info->last_name))
    			foreach($arr as $i => $word)
    				echo $word[0] . '.' . ($i == count($arr)-1 ? '' : ' ');
    	}
    	else
    		echo "John Doe";
    	if($info->fb_id)
    		echo '</a>';
    	echo ')';
    }
    else
		echo "Public Domain";
	
	if($set->is_subscribed())
		echo " <form style=\"display:inline;\" action=\"". get_page_url(PAGE_PLAY, array('mode' => SETS_MODE, 'type' => $set->get_type(), 'editor' => 'open')) ."\" method=\"post\"><input type=\"hidden\" name=\"unsubscribe_set_id\" value=\"$set->set_id\"></input><input type=\"submit\" value=\"Unsubscribe\"></submit></form> ";	
	elseif($set->is_public())
		echo " <button onclick=\"subscribe_to_set($set->set_id, this, '". str_replace("'", "\'", $set->get_name()) . "'); return false;\">Subscribe</button> ";
	
}

if($set->can_admin()) {
	echo " <form style=\"display:inline;\" action=\"". get_page_url(PAGE_PLAY, array('mode' => SETS_MODE, 'type' => $set->get_type(), 'editor' => 'open')) ."\" onsubmit=\"return confirm('Are you sure you want to delete this set?')\" method=\"post\"><input type=\"hidden\" name=\"delete_set_id\" value=\"$set->set_id\"></input><input class=\"delete\" type=\"submit\" value=\"Delete set\"></submit></form> ";
}

if(@$_SESSION['user']->isAdministrator() && !$set->is_public_domain()) {
	echo " <form style=\"display:inline;\" action=\"". get_page_url(PAGE_PLAY, array('mode' => SETS_MODE, 'type' => $set->get_type(), 'editor' => 'open')) ."\" method=\"post\"><input type=\"hidden\" name=\"public_domain_set_id\" value=\"$set->set_id\"></input><input type=\"submit\" value=\"Make public domain\"></submit></form> ";
}

echo '<a href="' . APP_URL . "export/set_export.php?set_id=" . $set->set_id . '" target="_blank"><button id="set_export_btn">Export</button></a> ';

$subs_count = $set->get_subs_count();
if($subs_count)
	echo " <span class=\"set_prop\">$subs_count subscriber" . ($subs_count > 1 ? 's' : ''). "</span>";

echo '<br/>';

echo "<p>";
if($set->can_admin())
	echo "Name: <input type=\"text\" id=\"edit_set_name\" value=\"" . htmlspecialchars($set->get_name(),  ENT_QUOTES, 'UTF-8') . "\" size=\"40\" onchange=\"update_set_name(". $set->set_id . ", this.value)\"></input>";
else
	echo "<hr/><span class=\"name\">" . $set->get_name() . "</span>";
	
echo " (public: <input type=\"checkbox\" name=\"set_public\" id=\"set_public\" value=\"1\" onclick=\"update_set_public(" . $set->set_id . ", this.checked)\"" . ($set->is_public() ? ' checked' : '') . (!$set->can_admin() || ($set->is_public() && $subs_count) ? ' disabled' : '') . "></input>access, <input type=\"checkbox\" name=\"set_editable\" id=\"set_editable\" value=\"1\" onclick=\"update_set_editable(" . $set->set_id . ", this.checked)\"" . ($set->is_editable() ? ' checked' : '') . (!$set->can_admin() ? ' disabled' : '') . "></input>edit)</p>";


if($set->can_edit())
	echo "<p>Description:<br/><textarea id=\"tag_description\" onchange=\"update_set_desc(". $set->set_id . ", this.value)\">" . $set->get_description() . "</textarea></p>";
else
	echo '<p class="description">' . $set->get_description() . '</p>';
	
echo '<p><div style="float:left;">Tags: </div>' . $set->show_tag_checkboxes() . '</p><div style="clear: both;"></div>';

?>

<?php
if($set->can_edit()) {
?>
<div id="add_to_set"><?php
	if($set->is_set_too_big()) {
		echo '<div style="color:red;font-weight:bold;">This set contains the maximum possible of entries (' . MAX_VOCAB_ENTRIES . '). Please remove some entries if you want to add more.</div>';
	}
	
?><button id="set_search_button" onclick="search_entries(<?php echo $set->set_id ?>); return false;">Add new entries from text field:</button> <br/><textarea id="add_to_set_search" name="add_to_set_search" onchange="search_entries(<?php echo $set->set_id ?>);"></textarea>
<form id="add_to_set_file_upload" enctype="multipart/form-data" action="<?php echo SERVER_URL ?>ajax/edit_learning_set/" method="post">
	<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo MAX_UPLOAD_SIZE ?>" />
	<input type="hidden" name="set_id" value="<?php echo $set->set_id ?>" />
<div style="font-style:italic; text-align: center;">- Or -</div> <input name="add_to_set_file" type="file" /> <input type="submit" value="Upload File" />

</form>

	<div id="add_to_set_results"></div>
</div>
<div id="set_content">
<?php 
}
else
	echo '<div id="set_content_wide">';
	
echo $set->get_formatted_list();
 ?></div>
<div style="clear:both"></div>

<script type="text/javascript">

    $(document).ready(function() { 
        // bind 'myForm' and provide a simple callback function 
       $('#add_to_set_file_upload').ajaxForm({
			beforeSubmit: function() {
				$('#add_to_set_file_upload > input').prop("disabled", true);
				$('#add_to_set_results').html("<em>Uploading and analysing...</em>");
			},
			success: function(responseText, statusText, xhr, $form) { 
				$('#add_to_set_file_upload > input').prop("disabled", false);
				$('#add_to_set_results').html(responseText);
			}
	  }); 
    }); 

	function subscribe_to_set(set_id, btn, set_name) {
		$(btn).prop('disabled', 'disabled');
		$.post('<?php echo SERVER_URL ?>ajax/edit_learning_set/', {'set_id': set_id, 'subscribe_to_set': true}, function (data, textStatus, jqXHR) {
			$(btn).hide();
		
			$('#edit_set_select').append($('<option>', { value : set_id }).text(set_name));
			$('#edit_set_select').val(set_id).change();
		});
	}

	function update_set_name(id, name)  
	{
		$.get('<?php echo SERVER_URL ?>ajax/edit_learning_set/?set_id=' + id + '&new_name=' + name, function (data) {
			$('#edit_set_name').css('border', '2px solid green')
			// alert(data);
			setTimeout(function() { $('#edit_set_name').css('border', '1px solid black') }, 2000);
		});
	}

	function update_tag(set_id, tag_id, val)  
	{
		$.get('<?php echo SERVER_URL ?>ajax/edit_learning_set/?set_id=' + set_id + '&set_tag_id=' + tag_id + '&val=' + (val ? 1 : 0), function (data) {
			$('#tag_' + tag_id).css('border', '1px solid green');
			setTimeout(function() { $('#tag_' + tag_id).css('border', 'none') }, 2000);
		});
	}


	function update_set_public(id, val)  
	{
		if($('#tag_description').val() == '') {
			alert('Please provide a helpful description before making a set public.');
			$('#set_public').css('outline', '2px ridge red');
			$('#set_public').attr('checked', false);
			setTimeout(function() { $('#set_public').css('outline', 'none') }, 2000);
			
			return;
		}
			
		$.get('<?php echo SERVER_URL ?>ajax/edit_learning_set/?set_id=' + id + '&set_public=' + (val ? 1 : 0), function (data) {
			$('#set_public').css('outline', '2px ridge green')
			 // alert(data);
			setTimeout(function() { $('#set_public').css('outline', 'none') }, 2000);
		});
	}

	function update_set_editable(id, val)  
	{
		$.get('<?php echo SERVER_URL ?>ajax/edit_learning_set/?set_id=' + id + '&set_editable=' + (val ? 1 : 0), function (data) {
			$('#set_editable').css('outline', '2px ridge green')
			// alert(data);
			setTimeout(function() { $('#set_editable').css('outline', 'none') }, 2000);
		});
	}

	function update_set_desc(id, desc)
	{
		$.post('<?php echo SERVER_URL ?>ajax/edit_learning_set/?set_id=' + id, {'new_desc': desc}, function (data) {
			if(data == '') {
				setTimeout(function() { $('#tag_description').css('border', '1px solid black') }, 2000);
				$('#tag_description').css('border', '2px solid green')
			}
			else {
				alert(data);
				$('#tag_description').css('border', '2px solid red');
			}
		});
	}

	var prev_search = '';
	
	function search_entries(id)  
	{
		var new_search = $('#add_to_set_search').val();
		if(prev_search == new_search)
			return;
		
		prev_search = new_search;
		
		if($('#add_to_set_search').prop('disabled') == 'disabled')
			return;
			
		$('#add_to_set_search').prop('disabled', 'disabled');;
		$('#set_search_button').prop('disabled', 'disabled');
		
		$.post('<?php echo SERVER_URL ?>ajax/edit_learning_set/', {'set_id': id, 'search': new_search}, function (data, textStatus, jqXHR) {
			$('#add_to_set_results').html(data);
			$('#add_to_set_search').prop('disabled', '');;
			$('#set_search_button').prop('disabled', '');
		});
	}
	
	function add_new_entries() {
		$('.check_select_all').prop('disabled', 'disabled');;
		$('.import_button').prop('disabled', 'disabled');
		
		$.post('<?php echo SERVER_URL ?>ajax/edit_learning_set/', $('#set_results').serialize(), function (data, textStatus, jqXHR) {
			$('#set_content').html(data);
			$('.check_select_all').prop('disabled', '');;
			$('.import_button').prop('disabled', '');
			$('#set_results input:checked').parent().addClass('added');
			$('#set_results input:checked').prop('checked', false).prop('disabled', 'disabled');
		});
	}
	
	function bulk_remove_other_set_from_set(id, other_set_id) {
		$.post('<?php echo SERVER_URL ?>ajax/edit_learning_set/', {'set_id': id, 'remove_other_set_id': other_set_id}, function (data, textStatus, jqXHR) {
			$('#set_content').html(data);
		});
	}

	function bulk_remove_level_from_set(id, level) {
		$.post('<?php echo SERVER_URL ?>ajax/edit_learning_set/', {'set_id': id, 'remove_level': level}, function (data, textStatus, jqXHR) {
			$('#set_content').html(data);
		});
	}

</script>