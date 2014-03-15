function show(obj, duration) 
{
    $(obj).fadeIn(duration);
}

function hide(obj, duration) 
{
    $(obj).fadeOut(duration);
}

function show_and_blink(name)
{
    $('#' + name).fadeIn(100).pulse({
        backgroundColors: ['#0072f3', '#ffffff'],
        speed: 600,
        runLength: 2
    });
}

function display_status_msg(msg)
{
   $('#msg-box').html(msg).show();
   setTimeout("$('#msg-box').fadeOut(10)", 2000);
   
}

function show_feedback_dialog(base_url, sid)
{
	feedback_dialog = $('#user_feedback_dialog').dialog({ 
		draggable: false, modal: true, resizable: false, width: 500, position: 'top', 
		buttons: {
		'Send Feedback': 
			function(){
				$('form.feedback_option_form:visible').ajaxSubmit({ 
					url: base_url + 'ajax/submit_feedback/',
					beforeSubmit: function() { 
					   if($('#feedback_form_select').value == '')
					      return false;
					
						  var valid = true;
							$('form.feedback_option_form:visible .form_required').each(function() {
								  if($(this).val() == 0 || $(this).val() == '') {
									  $(this).css('background-color', 'red');
									  valid = false;
								  }
							  });
							  if(! valid)
								  return false;
					  
					$('form.feedback_option_form:visible').html('Submitting...'); feedback_dialog.dialog('close');  }, 
					success: function(msg){  display_status_msg(msg); } 
				}); 
			}, 
		'Cancel': 
			function() { $(this).dialog("close"); }} 
		});
	feedback_dialog.dialog('option', 'title', 'Report a Problem');
	$('#user_feedback_content').html('<p style="text-align: center; margin: auto;"><img alt="load icon" src="' + base_url + 'img/ajax-loader.gif"/></p>');
	do_load(base_url + 'ajax/user_feedback/sid/' + sid + '/', 'user_feedback_content');
	feedback_dialog.dialog('open');	
}



function play_tts(str, hash) {
	var mySound = soundManager.createSound({
	 id:str,
	 url:'http://kanjibox.net/kb/audio/' + encodeURIComponent(str) + '/' + hash + '/'
	});
	mySound.play();
}