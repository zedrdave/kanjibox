$(document).ready(function()  
{
	$(document).bind('keyup', '1', shortcutkey);
    $(document).bind('keyup', '2', shortcutkey);
    $(document).bind('keyup', '3', shortcutkey);
    $(document).bind('keyup', '4', shortcutkey);
    $(document).bind('keyup', '5', shortcutkey);
});

function shortcutkey(event)
 {
	num = event.keyCode - 48;
    // window.console.log('Debugging: ' + num);
	
	// $('.question .choice').css('background-color', 'green');
	// $('.question .choice:nth-child(' + (num) + ')').css('background-color', 'red');

	$('.question:visible .choice:nth-child(' + (num) + ')').pulse({
        backgroundColors: ['#03B', '#ffffff'],
        speed: 100,
        runLength: 2
    });

     setTimeout(function() {
		$('.question:visible .choice:nth-child(' + (num) + ')').triggerHandler('click');
     }, 300);
	
	return false;
 }
