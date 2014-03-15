// function do_submit(formname, posturl, rewriteid) 
// {    
// 
//     $.ajax({
//         url: geturl,
//         type: 'POST',
//         success: function(html){
//             $('#' + insertid).prepend('<div class="wrapper">' + html + '</div>');
//         }
//     });
//  ajax.formdata = document.getElementById(formname).serialize();
// 
// }

function do_load_and_insert(geturl, insertid) 
{
    $.get(geturl,
            function(data, textStatus, jqXHR){
                $('#' + insertid).prepend('<div class="wrapper">' + data + '</div>');
            }
    );
}

function do_load_with_close_button(geturl, rewriteid) 
{
    $("#" + rewriteid).html('Loading...');
    $("#" + rewriteid).show();
    
    $.ajax({
        url: geturl,
        type: 'POST',
        success: function(data){
            $('#' + rewriteid).html(data);
            $('#' + rewriteid).append('<div><a href="#" onclick="$(\'#' + rewriteid + '\').hide(); return false;" class="icon-button ui-state-default ui-corner-all" title="Close" style="text-align:center;">x</a><div style="clear:both;"></div></div>');
        }
    });
}


function do_load(geturl, rewriteid) 
{
    do_load_with_waiting(geturl, rewriteid, 'Loading...');
}

function do_load_with_waiting(geturl, rewriteid, waiting_str) 
{
    if(rewriteid)
    {
        $("#" + rewriteid).html(waiting_str);
        $("#" + rewriteid).show();
        $("#" + rewriteid).load(geturl);
		//display_loading(ajax.result_div);
	}
	else
        $.ajax({url: geturl});
}