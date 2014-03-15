var seconds = 0;
var total_seconds = 0;
var timer_active = false;
var countdown_timeout = null;
var sol_displayed = 0;
var mod_dialog = null;

function show_kanji_details(kanji, url)
{
	if(mod_dialog == null)
        mod_dialog = $('#modal_dialog').dialog({ draggable: false, modal: true, resizable: false, width: 440 });
    mod_dialog.dialog('option', 'title', 'Kanji details: ' + kanji);
    mod_dialog.dialog('open');
    $('#modal_dialog_content').html('<img src="http://kanjibox.net/kb/img/ajax-loader.gif"></img>');
    do_load(url, 'modal_dialog_content');    
}

function show_vocab_details(vocab, url)
{
	if(mod_dialog == null)
        mod_dialog = $('#modal_dialog').dialog({ draggable: false, modal: true, resizable: false, width: 440 });
    mod_dialog.dialog('option', 'title', vocab);
    mod_dialog.dialog('open');
    $('#modal_dialog_content').html('<img src="http://kanjibox.net/kb/img/ajax-loader.gif"></img>');
    do_load(url, 'modal_dialog_content');    
}

function show_vocab_translate_dialog(base_url, jmdict_id, sid)
{
	feedback_dialog = $('#translate_dialog').dialog({ 
		draggable: true, modal: false, resizable: false, width: 500, position: 'top', 
		buttons: {
    		'Cancel': function() { $(this).dialog("close"); }, 
    		'Submit': function() { $('#translation_form').submit(); },   
	    }
    });
	
	feedback_dialog.dialog('option', 'title', 'Translate to your language');
	$('#translate_content').html('<p style="text-align: center; margin: auto;"><img alt="load icon" src="' + base_url + 'img/ajax-loader.gif"/></p>');

    $('#translate_content').show();
    $('#translate_content').load(base_url + 'ajax/vocab_translation/jmdict_id/' + jmdict_id + '/',
                                    function() {
        	$('#translation_form').submit(function() {
        	    $(this).ajaxSubmit({ 
            		url: base_url + 'ajax/vocab_translation/?update=1&sid=' + sid,
            		beforeSubmit: function() {
            		   $('#translation_form').html('Submitting...'); feedback_dialog.dialog('close');  
            		  }, 
            		success:(function(this_sid) { return function(msg){
            		    display_status_msg(msg);
            		    new_translation = $('#newtranslation', msg);
            		    if(new_translation && new_translation.length > 0) {
            		        $('#' + this_sid + ' .hint').html(new_translation).removeClass('missing_lang');
            	        }
            	    }}(sid)), 
            	});
            	return false; 
        	});   	
	    }
	);
	feedback_dialog.dialog('open');	

}

function show_kanji_translate_dialog(base_url, kanji_id, sid)
{
	feedback_dialog = $('#translate_dialog').dialog({ 
		draggable: true, modal: false, resizable: false, width: 500, position: 'top',
		buttons: {
		    'Cancel': function() { $(this).dialog("close"); }, 
	        'Submit': function() { $('#translation_form').submit(); },
	    }
	});
	
		
	feedback_dialog.dialog('option', 'title', 'Translate to your language');
	$('#translate_content').html('<p style="text-align: center; margin: auto;"><img alt="load icon" src="' + base_url + 'img/ajax-loader.gif"/></p>');

    $('#translate_content').show();
    $('#translate_content').load(base_url + 'ajax/kanji_translation/kanji_id/' + kanji_id + '/',
                                    function() {
            $("#translation_form input:text:visible:first").focus();
        	$('#translation_form').submit(function() {
        	    $(this).ajaxSubmit({ 
            		url: base_url + 'ajax/kanji_translation/?update=1&sid=' + sid,
            		beforeSubmit: function() {
            		   $('#translation_form').html('Submitting...'); feedback_dialog.dialog('close');  
            		  }, 
            		success:(function(this_sid) { return function(msg){
            		    display_status_msg(msg);
            		    new_translation = $('#newtranslation', msg);
            		    if(new_translation && new_translation.length > 0) {
            		        $('#' + this_sid + ' .meaning').html(new_translation).removeClass('missing_lang');
            		        $('#sol_' + this_sid + ' .meaning').html(new_translation);
            	        }
            	    }}(sid)), 
            	});
            	return false; 
        	});
	});
	feedback_dialog.dialog('open');	
}

function submit_answer(cur_sid, next_sid, answer_url)
{
	$('#' + cur_sid).hide();
	
	if(next_sid != '')
		$('#' + next_sid).show();

	do_load_and_insert(answer_url + '&countdown=' + seconds, 'solutions');
	if(next_sid != 'end_of_wave_wait')
		reset_countdown(31);
	else
		stop_countdown();
}


function init_countdown(val)
{
    total_seconds = val;
	if(countdown_timeout == null)
		update_countdown();
	reset_countdown();
}

function reset_countdown()
{
	if($('#countdown'))
	{
		seconds = total_seconds;
		timer_active = true;
	}
}

function set_coutdown_to(val)
{
    seconds = val;
}

function stop_countdown()
{
	timer_active = false;
}

function update_countdown() 
{
   	countdown_timeout = setTimeout(update_countdown, 1000) 

	obj = $('#countdown');
	if(! obj)
		return;
		
	if (! timer_active)
	{
		obj.html('');
		return;
	}
	
	if (seconds > 0)
		seconds -=1

	colg = Math.round(seconds/(total_seconds-1) * 205)
	if (seconds < (total_seconds/3))
		colr = 255
	else
		colr = Math.round((total_seconds-1-seconds)/(total_seconds-1) * 382)
    obj.html(seconds + 's.');
    obj.css('color', 'rgb(' + colr + ', ' + colg + ', 0)');
}

function do_load_vocab(spanobj, url) {
    $(spanobj).html('[?]');
    $(spanobj).load(url);
}

function remove_entry_from_set(url, set_id, entry_id, result_selector) {
	if(! confirm("Are you sure you want to remove this entry from the current study set?"))
		return;
	$.post(url, {set_id: set_id, remove_entry_id: entry_id }, function (data) {
        if(result_selector) {
            if(data)
                $(result_selector).show().html(data);
            else
                $(result_selector).show().html('Entry removed from set');
                
    		setTimeout(function() { 
    			$(result_selector).hide().html('') 
    		}, 2000);
        }
	});   
}