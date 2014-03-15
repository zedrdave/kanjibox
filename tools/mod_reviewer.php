<?php

if(!@$_SESSION['user'] || !$_SESSION['user']->is_editor())
	die("editors only");


if(isset($params['reviewed']))
	$_REQUEST['reviewed'] = $params['reviewed'];
	
if(@($params['details']))
	$_REQUEST['details'] = true;

// if(@($params['mode']) == 'review') {
	if(! isset($_REQUEST['details']))
		$_REQUEST['details'] = true;
	if(! isset($_REQUEST['reviewed']))
		$_REQUEST['reviewed'] = '0';
	if(! isset($_REQUEST['userlinks']))
		$_REQUEST['userlinks'] = true;
// }
	

$query = "SELECT du.*, u.fb_id, ux.first_name, ux.last_name, u.privileges AS user_privs FROM data_updates du LEFT JOIN data_updates du2 ON du.table_name = du2.table_name AND du.id_name = du2.id_name AND du.col_name = du2.col_name AND du.id_value = du2.id_value AND du2.reviewed = 0 LEFT JOIN users u ON u.id = du.user_id LEFT JOIN users_ext ux ON ux.user_id = du.user_id WHERE 1";

// if(isset($_REQUEST['submit'])) {
	
	if(!empty($_REQUEST['user_id'])) {
		$query .= ' AND du.user_id IN (0';
		$ids = explode(',', $_REQUEST['user_id']);
		foreach($ids as $id)
			$query .= ',' . (int) $id;
		
		$query .= ') ';
	}
	
	if(!empty($_REQUEST['table_name']))
		$query .= " AND du.table_name = '" . mysql_real_escape_string($_REQUEST['table_name']) . "' ";

	if(@$_REQUEST['reviewed'] === '1')
		$query .= " AND du.reviewed = 1 ";
	elseif(@$_REQUEST['reviewed'] === '0')
		$query .= " AND du.reviewed = 0 ";

	if(@$_REQUEST['need_work'] === '1')
		$query .= " AND du.need_work = 1 ";

	if(@$params['lang'])
		$query .= " AND (du.col_name = 'meaning_" . @Kanji::$lang_strings[$params['lang']] . "' OR du.col_name = 'gloss_" . @Vocab::$lang_strings[$params['lang']] . "')";
	
$query .= " GROUP BY du.update_id ORDER BY du.table_name, du.col_name, du.id_name, du.id_value, du.id_name_2, du.id_val_2, du.ts ASC";
	
  // echo $query;
?>
<div id="ajax-result" class="message" style="position:fixed;top:10px;display:none;"></div>
<?php echo display_editors_board(); ?>
<form action="<?php echo SERVER_URL ?>tools/mod_reviewer/" class="search_form">
	<h3>Filter options:</h3>
	User Id(s): <input name="user_id" size="30" value="<?php echo @$_REQUEST['user_id'] ?>" /> (your ID: <?php echo $_SESSION['user']->get_id(); ?>)<br/>
	Table: <?php
	echo get_select_menu(array('' => 'all', 'examples' => 'examples', 'examples_str' => 'examples_str', 'example_parts' => 'example_parts', 'jmdict' => 'jmdict', 'jmdict_ext' => 'jmdict_ext', 'kanjis_ext' => 'Kanji Translations'), 'table_name', (@$_REQUEST['table_name'] ? $_REQUEST['table_name'] : ''));

	echo "<br/>";
	$select_id = 'reviewed';
	echo '<select id="'. $select_id . '" name="' . $select_id . '">';
	foreach(array('' => 'all', '1' => 'Reviewed', '0' => 'Not reviewed') as $val => $name)
		echo '<option value="' . $val . '" ' . (($_REQUEST['reviewed'] === '' && $val === '') || ($_REQUEST['reviewed'] !== '' && $_REQUEST['reviewed'] == $val) ? 'selected="1" ' : '') . '>' . $name . '</option>';
	
	echo  '</select><br/>';
	
	echo 'Details: <input type="checkbox" name="details" ' . (@$_REQUEST['details'] ? 'checked' : '') . ' /><br/>';
	echo 'Include user links: <input type="checkbox" name="userlinks" ' . (@$_REQUEST['userlinks'] ? 'checked' : '') . ' /><br/>';
	?>
	<br/><input type="submit" name="submit" value="Find" />
</form>
<?php

	if(@$query) {
		$last_col = '';
		$last_table_name = '';
		$last_col_name = '';
		$last_val = '';
		$res = mysql_query($query) or die(mysql_error());
		
		$update = mysql_fetch_object($res);
		$i = 0;
		echo '<p>';
		
		while($update) {
			if($next_update = mysql_fetch_object($res))
				$next_col = "$next_update->table_name $next_update->id_name $next_update->id_value";
			else
				$next_col = '';
			if($last_table_name != $update->table_name)
				echo "</p><h2>$update->table_name</h2><p>";
			if($last_col_name != $update->col_name)
				echo "</p><h3>$update->col_name</h3><p>";
				
			$this_col = "$update->table_name $update->id_name $update->id_value";
			if($last_col != $this_col) {
				echo "</p><p class=\"update_chain\"><strong>$update->id_value: ";
				if(@$_REQUEST['details']) {
					switch($update->table_name) {
						case 'examples':
							$res_d = mysql_query("SELECT * FROM examples WHERE example_id = " . (int) $update->id_value) or die(mysql_error());
							$row_d = mysql_fetch_object($res_d);
							if($update->col_name == 'example_str')
								echo $row_d->english;
							else
								echo $row_d->example_str;
							echo '<br/>';
						break;
						case 'jmdict':
							$res_d = mysql_query("SELECT * FROM jmdict WHERE id = " . (int) $update->id_value) or die(mysql_error());
							$row_d = mysql_fetch_object($res_d);
							echo $row_d->word . '【' . $row_d->reading . '】: ';
						break;
						case 'example_parts':
							$res_d = mysql_query("SELECT * FROM jmdict WHERE id = " . (int) $update->old_value) or die(mysql_error());
							
                            if($row_d = mysql_fetch_object($res_d)) {
    							echo $row_d->word . '【' . $row_d->reading . '】';
                            }
                            else
                                echo ' [Deleted entry] ';
                            
							$res_d = mysql_query("SELECT * FROM jmdict WHERE id = " . (int) $update->new_value) or die(mysql_error());
                            if($row_d = mysql_fetch_object($res_d)) {
    							echo ' → ' . $row_d->word . '【' . $row_d->reading . '】: ';
                            }
                            else
                                echo ' → [Deleted entry] ';
                            
						break;
						case 'jmdict_ext':
							$res_d = mysql_query("SELECT * FROM jmdict_ext jx LEFT JOIN jmdict j ON jx.jmdict_id = j.id  WHERE jx.jmdict_id = " . (int) $update->id_value) or die(mysql_error());
							$row_d = mysql_fetch_object($res_d);
							echo $row_d->word . '【' . $row_d->reading . '】: ';
							if($update->col_name != 'gloss_english')
							 echo "<i>$row_d->gloss_english</i><br/>";
						break;
						case 'kanjis_ext':
							$res_d = mysql_query("SELECT * FROM kanjis_ext kx LEFT JOIN kanjis k ON kx.kanji_id = k.id  WHERE kx.kanji_id = " . (int) $update->id_value) or die(mysql_error());
							$row_d = mysql_fetch_object($res_d);
							echo $row_d->kanji . '【' . $row_d->prons . '】: ';
							if($update->col_name != 'meaning_english')
							 echo "<i>$row_d->meaning_english</i><br/>";
						break;
					}
				}
					
				echo "</strong>";
					
				echo "<span class=\"update update_replaced" . ($update->applied ? ($update->reviewed ? '' : '_not_reviewed') : '_not_applied') . "\">" . (empty($update->old_value)  && $update->old_value !== '0' ? '∅' : $update->old_value) . "</span>";
				
				if($update->applied)
					echo "<a href=\"#\" class=\"revert_update\" id=\"rev_$update->update_id\" onclick=\"revert('$update->update_id', '$update->table_name', '$update->id_name', '$update->id_value', '$update->ts'); return false;\"> <span class=\"arrow\">→</span><span class=\"delete\">←</span> </a>";
				else
					echo "<a href=\"#\" class=\"revert_update\" id=\"rev_$update->update_id\" onclick=\"discard('$update->update_id', '$update->table_name', '$update->id_name', '$update->id_value', '$update->ts'); return false;\"> <span class=\"arrow\">→</span><span class=\"delete\">×</span> </a>";
			}
			else {
				if($update->applied)
					echo "<a href=\"#\" class=\"revert_update\" id=\"rev_$update->update_id\" onclick=\"revert('$update->update_id', '$update->table_name', '$update->id_name', '$update->id_value', '$update->ts'); return false;\"> <span class=\"arrow\">→</span><span class=\"delete\">←</span> </a>";
				else
					echo "<a href=\"#\" class=\"revert_update\" id=\"rev_$update->update_id\" onclick=\"discard('$update->update_id', '$update->table_name', '$update->id_name', '$update->id_value', '$update->ts'); return false;\"> <span class=\"arrow\">→</span><span class=\"delete\">×</span> </a>";
			}
			
			echo "<a id=\"update_to_$update->update_id\" class=\"update update_replac" . ($next_update && $next_update->applied && $this_col == $next_col ? 'ed' : 'ing') . ($update->need_work ? '_need_work' : ''). ($update->applied ? ($update->reviewed ? '' : '_not_reviewed') : '_not_applied') . "\" href=\"#\" onclick=\"" . ($update->applied &&  $update->reviewed ? 'invalidate' : 'validate') . "($update->update_id, 0); return false;\">" . (empty($update->new_value) && $update->new_value !== '0' ? '∅' : $update->new_value ) . "</a>";
			
			if($update->need_work)
				echo " <a href=\"#\" onclick=\"validate($update->update_id, 0); return false;\">(✓)</a>";			
			else
				echo " <a href=\"#\" onclick=\"validate($update->update_id, 1); return false;\">(~)</a>";			
			
			if($update->usr_cmt)
				echo " <span class=\"usr-cmt\"><span class=\"cmt-icon\">&nbsp;...&nbsp;</span> <span class=\"txt\">$update->usr_cmt</span></span>";
				
			if(@$_REQUEST['userlinks']) {
				if(@$update->first_name[0] || @$update->last_name[0])
					echo " " . mb_substr(@$update->first_name, 0, 1, $encoding = "UTF-8") . '.'. mb_substr(@$update->last_name, 0, 1, $encoding = "UTF-8") .'.';
				else
					echo " id:$update->user_id ";
					
				if($_SESSION['user']->is_admin())
					echo "<a href=\"" . SERVER_URL . "admin/send_msg/?prefill_user_id_to=" . $update->user_id . "&prefill_msg_class=&prefill_msg_code=user_update&prefill_msg_title=Contribution&prefill_msg_text=" . urlencode("Hi " . $update->first_name . "\n\nAbout your suggested update: <blockquote>" . $update->new_value . "</blockquote>\n\n\nCheers!") . "\" title=\"$update->first_name $update->last_name\">" . ($update->user_privs > 0 ? ($update->user_privs > 1 ? '☆' : '〒') : '〒') . "</a>";
				else
					echo "<a href=\"https://www.facebook.com/?compose&sk=messages&id=$update->fb_id\" title=\"$update->first_name $update->last_name\">" . ($update->user_privs > 0 ? ($update->user_privs > 1 ? '☆' : '〒') : '〒') . "</a>";
				
			}
			
			if($this_col != $next_col) {
				switch($update->table_name) {
					case 'kanjis':
					case 'kanjis_ext':
						$url = "http://kanjibox.net/kb/tools/kanji_editor/?submit=1&id=$update->id_value";
					break;
					case 'examples':
						$url = "http://kanjibox.net/kb/tools/sentence_editor/?submit=1&example_id=$update->id_value";
					break;
					case 'jmdict':
						$url = "http://kanjibox.net/kb/tools/vocab_editor/?submit=1&id=$update->id_value";
					break;
					case 'example_parts':
						$url = '';
					break;
					case 'jmdict_ext':
						$url = "http://kanjibox.net/kb/tools/vocab_editor/?submit=1&id=$update->id_value";
					break;
					default:
						$url = '';
					break;
				}
				if($url)
					echo '&nbsp;&nbsp;&nbsp;<a href="' . $url . '">→</a>';
			}
			
			$last_col = "$update->table_name $update->id_name $update->id_value";
			$last_val = $update->new_value;
			$last_table_name = $update->table_name;
			$last_col_name = $update->col_name;
			$update = $next_update;
		}
		echo '</p>';
	}

?>
<script type="text/javascript">

	function validate(revid, need_work)  
	{		
		$('#ajax-result').html('Loading...').show();
		$.get('<?php echo SERVER_URL ?>ajax/data_updates/?validate=1&need_work=' + need_work + '&update_id=' + revid, function (data) {
			$('#ajax-result').html(data).show();
			setTimeout(function() { $('#ajax-result').html('').hide(); }, 2500);
	
			if($('#data_update_success', data).length > 0) {
				$('#update_to_' + revid).removeAttr('onclick').click(function() {invalidate(revid); return false;});
				$('#update_to_' + revid).prevAll('.update_replaced_not_reviewed').removeClass('update_replaced_not_reviewed').addClass('update_replaced').css('border', '1px solid black');
				$('#update_to_' + revid).removeClass('update_replaced_not_applied').removeClass('update_replaced_not_reviewed').removeClass('update_replacing_not_applied').removeClass('update_replacing_not_reviewed').removeClass('update_replacing_need_work').addClass(need_work ? 'update_replacing_need_work' : 'update_replacing');
			}
		});
	}

	function invalidate(revid, need_work)  
	{		
		alert("Invalidate not supported yet");
		// $('#ajax-result').html('Loading...');
		// $.get('<?php echo SERVER_URL ?>ajax/data_updates/?invalidate=1&update_id=' + revid, function (data) {
		// 	$('#ajax-result').html(data);
		// 	$('#ajax-result').show();
		// 	// setTimeout(function() { $('#ajax-result').html('') }, 2000);
		// 	
		// 	if($('#data_update_success', data).length > 0) {
		// 		$('#update_to_' + revid).removeAttr('onclick').click(function() {validate(revid); return false;});
		// 		$('#update_to_' + revid).removeClass('update_replaced').removeClass('update_replacing').addClass('update_replacing_not_applied');
		// 	}
		// });
	}

	function revert(revid)  
	{		
		$('#ajax-result').html('Loading...').show();
		$.get('<?php echo SERVER_URL ?>ajax/data_updates/?revert=1&update_id=' + revid, function (data) {
			$('#ajax-result').html(data).show();
			setTimeout(function() { $('#ajax-result').hide() }, 2000);
		
			if($('#data_update_success', data).length > 0) {
				$('#rev_' + revid + ' .arrow').html('▶');
				$('#rev_' + revid + ' .delete').html('×');
				$('#rev_' + revid).removeAttr('onclick').click(function() {discard(revid); return false;});
				$('#rev_' + revid + ' ~ a').addClass('reverted');
				$('#rev_' + revid).prev().removeClass('update_replaced').addClass('update_restored');
			}
		});
	}

	function discard(revid)  
	{		
		if(confirm("Do you REALLY want to delete these updates?")) {
			$('#ajax-result').html('Loading...').show();
			$.get('<?php echo SERVER_URL ?>ajax/data_updates/?discard=1&update_id=' + revid, function (data) {
				$('#ajax-result').html(data).show();
				setTimeout(function() { $('#ajax-result').hide() }, 2000);
		
				if($('#data_update_success', data).length > 0) {
					$('#rev_' + revid).removeAttr('onclick').click(function() { return false; });
					$('#rev_' + revid).addClass('revert_update_clicked');
					$('#rev_' + revid).unbind('click');
					$('#rev_' + revid + ' ~ a').addClass('discarded');
					$('#rev_' + revid).prev().removeClass('update_replaced').removeClass('update_replaced_not_applied').addClass('update_restored');
				}
			});
		}
	}

</script>