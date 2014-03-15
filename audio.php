<?php
header("Content-Type: audio/mpeg");

if(!@$_REQUEST['string'] || (md5($_REQUEST['string'] . 'hophophop') != rtrim(@$_REQUEST['hash'], '/')))
	die();

$filename = 'audio/' . urlencode($_REQUEST['string']) .'.mp3';

if(! file_exists($filename)) {
	$ch = curl_init("http://translate.google.com/translate_tts?ie=UTF-8&q=" . urlencode($_REQUEST['string']) ."&tl=ja");
	$fh = fopen($filename, 'w'); 
	curl_setopt($ch, CURLOPT_REFERER, '');
	curl_setopt($ch, CURLOPT_FILE, $fh); 
	curl_exec($ch);
	curl_close($ch);
	fclose($fh);
}

readfile($filename);


?>