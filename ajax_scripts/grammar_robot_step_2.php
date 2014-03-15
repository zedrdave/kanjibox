<?php
if(!@$_SESSION['user'] || !$_SESSION['user']->is_editor())
	die("only editors");

mb_internal_encoding("UTF-8");

if(@$_REQUEST['answer_ids']) {
	$set_id = (int) @$_REQUEST['set_id'];
	$answer_ids = array_keys(@$_REQUEST['answer_ids']);
	
	if(count($answer_ids) < 4) {
		die('<div class="error_msg">You need to select at least 4 different answers to generate a set of questions.</div>');
	}
	
	$preferences = array();
	$mismatched_wrong_answers = array();
	$problem_wrong_answers = array();

	foreach($answer_ids as $id)
		foreach($answer_ids as $id_2)
			if($id != $id_2)
				$preferences[$id][$id_2] = 0;
	
	$query = "SELECT sq.question_id, sq.jmdict_id AS answer_id, gar.jmdict_id, gar.status FROM grammar_questions sq LEFT JOIN grammar_answer_reviews gar ON gar.question_id = sq.question_id WHERE sq.set_id = $set_id AND sq.jmdict_id IN (" . implode(',', $answer_ids) . ")";
	$res = mysql_query($query) or die(mysql_error());
	while($row = mysql_fetch_object($res)) {
		@$reviews[$row->question_id][$row->jmdict_id] = $row->status;
		if($row->status == 'problem' && in_array($row->jmdict_id, $answer_ids)) {
			$preferences[$row->answer_id][$row->jmdict_id] += 10;
			$preferences[$row->jmdict_id][$row->answer_id] += 10;
		}
	}
	
	
	$query = "SELECT sq.question_id, sq.jmdict_id AS right_answer, GROUP_CONCAT(ga.jmdict_id) AS wrong_answers FROM grammar_questions sq LEFT JOIN grammar_answers ga ON ga.question_id = sq.question_id WHERE sq.set_id = $set_id AND sq.jmdict_id IN (" . implode(',', $answer_ids) . ") GROUP BY sq.question_id";
	
	$res = mysql_query($query) or die(mysql_error());
	
	$questions_wrong_answers = array();
	$questions_right_answers = array();
	
	$min_wrong_answers = 100;
	
	while($row = mysql_fetch_object($res)) {
		$questions_right_answers[$row->question_id] = $row->right_answer;
		
		if($row->wrong_answers == '') {
			$min_wrong_answers = 0;
			continue;
		}

		$wrong_answers = explode(',', $row->wrong_answers);
		$wrong_answers_tot = 0;
		foreach($wrong_answers as $wrong_answer) {
			if(! in_array($wrong_answer, $answer_ids)) {
				$mismatched_wrong_answers[$row->question_id][] = $wrong_answer;
				continue;
			}
			
			if(@$reviews[$row->question_id][$row->jmdict_id] == 'problem')
				$problem_wrong_answers[$row->question_id][] = $wrong_answer;
			else {
				$questions_wrong_answers[$row->question_id][] = $wrong_answer;
				@$preferences[$row->right_answer][$wrong_answer] -= 1;
				$wrong_answers_tot++;
			}
		}
		$min_wrong_answers = min($min_wrong_answers, $wrong_answers_tot);
	}
	// foreach($preferences as $id => &$prefs) {
	// 	arsort($prefs);
	// }
	
	if($min_wrong_answers >= 5) {
		echo "<p>This selection already has at least 5 Wrong Answers per question. You can see the set's details <a href=\"/kb/tools/grammar_list/set_id/". $set_id . "/\">over here</a>.</p>";
	}
	
   function shuffle_assoc(&$array) {
       $keys = array_keys($array);
       shuffle($keys);
       foreach($keys as $key)
           $new[$key] = $array[$key];
       $array = $new;
       return true;
   }
	
	if(count(@$mismatched_wrong_answers)) {
		echo "<h3>Some words in this selection only appear as Wrong Answer and never as Right Answer. You should either remove them or add new questions.</h3>";
		foreach($mismatched_wrong_answers as $question_id => $answer_ids) {
			$res = mysql_query("SELECT j.id, j.word, j.reading, j.usually_kana FROM jmdict j WHERE j.id IN (" . implode(',', $answer_ids) . ")") or die(mysql_error());
			
			while($row = mysql_fetch_object($res)) {
				
				echo "<p><a href=\"/kb/tools/grammar_editor/?edit_question_id=$question_id\">Q. Id: $question_id</a>: " . ($row->usually_kana || $row->word == $row->reading ? $row->reading : $row->word . '【' . $row->reading . '】') . "　[id: $row->id]</p>";
			}
		} 
	}
	
	// echo "Min: " . $min_wrong_answers . "\n\n";
	
	if($min_wrong_answers < 2)
		$add_total = 2;
	elseif($min_wrong_answers < 5)
		$add_total = min(5, count($answer_ids)-1);
	else
		$add_total = min($min_wrong_answers+1, count($answer_ids)-1);
	
	if($add_total <= $min_wrong_answers)
		die('<div class="error_msg">All questions already have at least ' . $min_wrong_answers . ' Wrong Answers. You need to select more question/answers to get more suggestions.</div>');
		
	$suggestions = array();
	
	foreach($questions_right_answers as $question_id => $answer_id) {
		$wrong_answers = $preferences[$answer_id];
		shuffle_assoc($wrong_answers);
		arsort($wrong_answers);
				
		$i = count(@$questions_wrong_answers[$question_id]);

		foreach($wrong_answers as $wrong_answer => $pref) {
			
			if(@$questions_wrong_answers[$question_id] && in_array($wrong_answer, $questions_wrong_answers[$question_id]))
				continue;
				
			if(@$reviews[$question_id][$wrong_answer] == 'problem')
				continue;

			if($i++ >= $add_total)
				break;
			
			@$suggestions[$question_id][] = $wrong_answer;
		}
	}
	
	$res = mysql_query("SELECT sq.*, e.*, j.njlpt AS word_jlpt FROM grammar_questions sq JOIN examples e ON sq.sentence_id = e.example_id JOIN jmdict j ON j.id = sq.jmdict_id JOIN jmdict_ext jg ON jg.jmdict_id = j.id WHERE sq.question_id IN (" . implode(',', array_keys($suggestions)) . ") ORDER BY sq.question_id") or die(mysql_error());
	?>
	<select name="review_add" id="review_add" onchange="review_menu_change(this.value);">
		<option value="review_and_add">Review and Add:</option>
		<option value="add">Add Now, Review Later:</option>
	</select>
	<?php

	while($question = mysql_fetch_object($res)) {
		echo '<div class="db-group" style="padding: 3px;">';
		
		$str = $question->example_str;		
      echo '<p>' . mb_substr($str, 0, $question->pos_start) . '<span class="highlighted-correct">' .  mb_substr($str, $question->pos_start, $question->pos_end - $question->pos_start) . '</span>' . mb_substr($str, $question->pos_end) . "<span style=\"font-size:90%;color:#777;\">#<a href=\"http://kanjibox.net/kb/tools/grammar_editor/?edit_question_id=$question->question_id\">$question->question_id</a></span>" . '</p>';
		
		$res_answers = mysql_query("SELECT j.id AS answer_jmdict_id, IF(j.usually_kana, j.reading, j.word) AS decoy, '' AS review_status FROM jmdict j JOIN jmdict_ext jg ON jg.jmdict_id = j.id WHERE j.id IN (" . implode(',', $suggestions[$question->question_id]) . ")") or die(mysql_error());
		
		while($answer = mysql_fetch_object($res_answers)) {
	      $wrong_str = mb_substr($str, 0, $question->pos_start) . '<span class="highlighted-wrong">' . $answer->decoy . '</span>' . mb_substr($str, $question->pos_end);
		
			echo "<p style=\"margin:5px; padding:0;\" class=\"reviewed-" . ($answer->review_status) . "\">";
			
			echo '<input id="' . "review[$question->question_id][$answer->answer_jmdict_id]" . '" type="checkbox" class="review-button" onchange="add_review_remove(' . $question->question_id . ', ' . $answer->answer_jmdict_id . ',  this.checked)" ' . ($answer->review_status == 'ok' ? 'checked="checked"' : '') . '></input><label for="' . "review[$question->question_id][$answer->answer_jmdict_id]" . '">Review</label> ';
			
			echo "<label id=\"answer_str_" . $question->question_id . "_$answer->answer_jmdict_id\" for=\"answer[$question->question_id][$answer->answer_jmdict_id]\" style=\"" . ($answer->review_status == 'ok' ? 'border: 1px solid black; color: #888;' : ($answer->review_status == 'problem' ? 'text-decoration:line-through; color: #888;' : '')) . "\">$wrong_str</label>";
			
			// echo "<input type=\"checkbox\" name=\"answer[$question->question_id][$answer->answer_jmdict_id]\" id=\"answer[$question->question_id][$answer->answer_jmdict_id]\" onchange=\"update_review_status($question->question_id, $answer->answer_jmdict_id, this.checked ? 'ok' : 'problem');\" " . ($answer->review_status == 'ok' ? ' checked' : '') . "></input><label id=\"answer_str_" . $question->question_id . "_$answer->answer_jmdict_id\" for=\"answer[$question->question_id][$answer->answer_jmdict_id]\" style=\"" . ($answer->review_status == 'ok' ? 'text-decoration: line-through; color: #888;' : ($answer->review_status == 'problem' ? 'border: 1px solid #A00' : '')) . "\">$str</label>";
			
	      echo " <span style=\"font-size:90%;color:#777;\">#<a href=\"http://kanjibox.net/kb/tools/grammar_editor/?edit_question_id=$question->question_id\">$question->question_id</a>-$answer->answer_jmdict_id</span>" . ' <input id="' . "flag[$question->question_id][$answer->answer_jmdict_id]" . '" type="checkbox" class="flag-button" onchange="update_review_status(' . $question->question_id . ', ' . $answer->answer_jmdict_id . ',  this.checked ? \'problem\' : \'\')" ' . ($answer->review_status == 'problem' ? 'checked="checked"' : '') . '></input><label for="' . "flag[$question->question_id][$answer->answer_jmdict_id]" . '">Flag</label>';
			
	      echo "</p>\n";
			
		}
		
		echo '</div>';
	}
	if($add_total < 5) {
		echo '<p><span style="color:red; font-weight:bold;">Note:</span> there aren\'t enough Wrong Answers yet.</p>';
	}
	echo '<p>Once all suggestions above have been reviewed, <button onclick="$(\'#make-wrong-answers-form\').submit();" name="more-suggestions">click here</button> to generate more suggestions.</p>';
	
	?>
	<script>

	    $(function () {
	          $(".flag-button").button({
	            icons: {
	              primary: "ui-icon-alert"
	            },
	            text: false
	          })
			 
				 $('#review_add').val('<?php echo ($_SESSION['user']->get_id() == 1 ? 'add' : 'review_and_add'); ?>');
				 review_menu_change($('#review_add').val());
	      })
			
			function add_review_remove(question_id, answer_jmdict_id, checked) {
 	        var selector = '#answer_str_' + question_id + '_' + answer_jmdict_id

				if(checked) {
					arg_name = 'add_wrong_answer';
					review = 'ok'
				}
				else {
					arg_name = 'delete_answer_id';
					review = ''
				}
				$(selector).css('border', '1px solid #AAA')
				
				$.ajax({
		            url: '<?php echo SERVER_URL ?>ajax/edit_question/question_id/' + question_id + "/?no_content=1&" + arg_name + "=" + answer_jmdict_id, 
		            type: 'POST',
		            success: function (data) {
							if(data != '')
			                $('#ajax-result').show().html(data);
							$(selector).css('border', '')
							if($('#review_add').val() == 'review_and_add')
								update_review_status(question_id, answer_jmdict_id, 'ok');
			        		setTimeout(function() {
			        			$('#ajax-result').hide().html('')
			        		}, 1000);
	        		  
						  },
		            timeout: 1000, 
		            error: function(x, t, m) {
		                alert("There was a connection error and your change was NOT saved.\nIf this problem persists, please reload the page.")
		            }
		        });					
			
			}
			
			function review_menu_change(val) {

				if(val == 'review_and_add')
					icon = 'ui-icon-check'; 
				else
					icon = 'ui-icon-plusthick';
				
	          $(".review-button").button({
	            icons: {
	              primary: icon
	            },
	            text: false
	          })
				
			}
		
			function delete_question(question_id, selector) {
				if(confirm("Are you sure you want to delete this entire question?")) {
					$.ajax({
			            url: '<?php echo SERVER_URL ?>ajax/edit_question/?delete_id=' + question_id, 
			            type: 'GET',
			            success: function (data) {
								if(data != '') {
									alert(data.replace(/<(?:.|\n)*?>/gm, ''));
				                $('#ajax-result').show().html(data);
				        		setTimeout(function() {
				        			$('#ajax-result').hide().html('')
				        		}, 10000);
							}
		           
	  		  		    $(selector).parent().css('text-decoration', 'line-through')
	  		  		    $(selector).parent().css('text-decoration-color', '#F00')
						
			        	},
			            timeout: 1000, 
			            error: function(x, t, m) {
			                alert("There was a connection error and your change was NOT saved.\nIf this problem persists, please reload the page.")
			            }
			        });
				}
			}

			function delete_answer(question_id, answer_id, selector) {
				$.ajax({
		            url: '<?php echo SERVER_URL ?>ajax/edit_question/question_id/' + question_id + '/?no_content=1&delete_answer_id=' + answer_id, 
		            type: 'GET',
		            success: function (data) {
							if(data != '') {
								// alert(data.replace(/<(?:.|\n)*?>/gm, ''));
			                $('#ajax-result').show().html(data);
			        		setTimeout(function() {
			        			$('#ajax-result').hide().html('')
			        		}, 10000);
						}
	           
		  		    $(selector).parent().css('text-decoration', 'line-through')
		  		    $(selector).parent().css('text-decoration-color', '#F00')
					
		        	},
		            timeout: 1000, 
		            error: function(x, t, m) {
		                alert("There was a connection error and your change was NOT saved.\nIf this problem persists, please reload the page.")
		            }
		        });
			}

		function update_review_status(question_id, jmdict_id, status)  
		{
	        var selector = '#answer_str_' + question_id + '_' + jmdict_id
        
			if(status != 'ok')
      		$(selector).css('color', '#AAA')
			else
  				$(selector).css('border', '1px solid #AAA')
				
			$.ajax({
	            url: '<?php echo SERVER_URL ?>ajax/edit_question/question_id/' + question_id + '/jmdict_id/' + jmdict_id + '/?update_reviewed=' + status, 
	            type: 'POST',
	            success: function (data) {
						if(data != '') {
							alert(data.replace(/<(?:.|\n)*?>/gm, ''));
		                $('#ajax-result').show().html(data);
		        		setTimeout(function() {
		        			$('#ajax-result').hide().html('')
		        		}, 10000);
					}
					else {						
	                if(status == 'problem')
	                  $(selector).css('text-decoration', 'line-through')
	                else 
	        				$(selector).css('color', '').css('border', '').css('text-decoration', '')
					}
	        	},
	            timeout: 1000, 
	            error: function(x, t, m) {
	                alert("There was a connection error and your change was NOT saved.\nIf this problem persists, please reload the page.")
	            }
	        });
		}

	</script>
	<?php
	
	exit;
}
?>