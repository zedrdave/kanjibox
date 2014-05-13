<?php
function parse_jp_sentence($sentence, $include_particles = true, $output_debug = false, $example_id = 0) {
	// $sentence = preg_replace('/^\d+[\n\r]+\d\d:\d\d:\d\d,\d+ --> \d\d:\d\d:\d\d,\d+/m', '', $sentence);
	// $sentence = preg_replace("/➡[\r\n]+/", '', $sentence);
	
	if(! extension_loaded('mecab'))
		return;

	ini_set('display_errors', true);

	$mecab = mecab_new();
	mb_internal_encoding('UTF-8');
	$parse = mecab_sparse_tostr($mecab, $sentence);
	mecab_destroy($mecab);
	$cur_pos = 0;
	$part_num = 0;
	$next_pos = 0;
	
	if($output_debug)
		echo "<pre style=\"display:none;\" class=\"debug\">$parse</pre>";

	$queries = [];

	foreach(explode("\n", $parse) as $line) {
		$items = explode("\t", $line);
		if(count($items) < 2)
			continue;
		$details = explode(',', $items[1]);
		
		if($example_id) {
			$cur_pos = $next_pos;
			$next_pos = $cur_pos + mb_strlen($items[0]);
		}
		// if(count($details) < 8) // unknown token
		// 	continue; 
		if($details[0] == '記号' || (!$include_particles && $details[0] == '助詞'))
			continue;
		if(($details[0] == '助動詞' && $items[0] != 'です' && $items[0] != 'だろ' && $items[0] != 'だ' && $items[0] != 'だっ') || (@$can_add_te_suffix && $items[0] == 'て')) {
			if($example_id) {
				$last_query = array_pop($queries);
				if($last_query[4] == $cur_pos)
					$last_query[4] = $next_pos;
				$queries[] = $last_query;
			}
			continue;
		}
	
		if($example_id) {
			while(mb_substr($sentence, $cur_pos, mb_strlen($items[0])) != $items[0]) {
				$cur_pos++;
				if($cur_pos + mb_strlen($items[0]) >= mb_strlen($items[0])) {
					$cur_pos = -1;
					break;
				}
			}
	
			if($cur_pos == -1)
				break;
		}
		
		if($details[6] != '*')
			$word = $details[6];
		else
			$word = $items[0];

		if(preg_match('/[^0-9a-zA-Z\->:,]/u', mb_convert_kana($word, 'a')) == 0)
			continue;
	
	
		$hiragana = mb_convert_kana(@$details[7], 'c');
		$is_katakana = (@$details[7] == $word);
		$is_kana = (preg_match('/[^\p{Hiragana}\p{Katakana}a-zA-Z]/u', $word, $matches) == 0);
		
		if(!$include_particles && $is_kana && mb_strlen($word) <= 1)
			continue;
		
		$word = DB::getConnection()->quote($word);
		$hiragana = DB::getConnection()->quote($hiragana);
		
		$can_add_te_suffix = false;
		
		
		if($word == 'の' && $hiragana == 'の')
			$word = '乃';
		elseif($word == 'ほど')
			$word = '程';
		
		if($is_katakana)
			$query_where = "j.word = '$word' AND j.katakana = 1";
		elseif('動詞' == $details[0] || '形容詞' == $details[0] || '助動詞' == $details[0]) {
			$can_add_te_suffix = true;
			$query_where = "(j.word = '$word'";
			if($is_kana)
				$query_where .= " OR (j.usually_kana = 1 AND j.reading = '$word'))";
			else {
				$query_where .= ") ORDER BY ";
				if($hiragana != '*')
					for($i = 1; $i <= mb_strlen($hiragana); $i++)
						$query_where .= "SUBSTR(j.reading, $i, 1) != SUBSTR('$hiragana', $i, 1),";
				$query_where .= ' j.njlpt_r DESC, j.njlpt_r DESC LIMIT 1';
			}
		}
		else
			$query_where = "((j.word = '$word'" . ($hiragana != '' ? "AND j.reading = '$hiragana'" : '') . ") OR (j.reading = '$word' AND j.usually_kana = 1)) ORDER BY j.njlpt DESC, j.njlpt_r DESC LIMIT 1";
		
		if($output_debug)
			echo "<pre style=\"display:none;\" class=\"debug\">SELECT j.id FROM jmdict j WHERE $query_where</pre>";
		
		$res = mysql_query('SELECT j.id FROM jmdict j WHERE ' . $query_where) or die(mysql_error());
		if($example_id && mysql_num_rows($res) == 0) {
			if($output_debug)
				echo "<pre style=\"display:none;\" class=\"debug\">SELECT j.id FROM jmdict_deleted j WHERE $query_where</pre>";
			$res = mysql_query('SELECT j.id FROM jmdict_deleted j WHERE ' . $query_where) or die(mysql_error());
		}
        
		if(mysql_num_rows($res) == 0 && $details[1] == '固有名詞' && $is_katakana) {
			if($example_id) {
				$query_array = array($example_id, -1, $part_num, (int) $cur_pos, $next_pos, '0', '1');				
				$queries[] = $query_array;
			}
			$part_num++;
			continue;
		}
        
		if(mysql_num_rows($res) == 0 && !$is_katakana && $hiragana != '') {
			if($output_debug)
				echo "<pre style=\"display:none;\" class=\"debug\">SELECT j.id FROM jmdict j WHERE j.reading = '$hiragana' ORDER BY j.njlpt DESC, j.njlpt_r DESC</pre>";
			
			$res = mysql_query("SELECT j.id FROM jmdict j WHERE j.reading = '$hiragana' ORDER BY j.njlpt DESC, j.njlpt_r DESC") or die(mysql_error());
		}
		if($example_id &&  mysql_num_rows($res) == 0 && !$is_katakana  && $hiragana != '') {
			if($output_debug)
				echo "<pre style=\"display:none;\" class=\"debug\">SELECT j.id FROM jmdict_deleted j WHERE j.reading = '$hiragana' ORDER BY j.njlpt_r DESC, j.njlpt DESC</pre>";
			
			$res = mysql_query("SELECT j.id FROM jmdict_deleted j WHERE j.reading = '$hiragana' ORDER BY j.njlpt_r DESC, j.njlpt DESC") or die(mysql_error());
		}

		if($row = mysql_fetch_object($res)) {
			if($example_id)
				$queries[] = array($example_id, $row->id, $part_num, (int) $cur_pos, $next_pos, ($is_kana ? '0' : '1'), '0');
			else
				$queries[] = $row->id;
		}
		
		$part_num++;
	}
	
	// if(!$example_id)
	// 	array_unique($queries);

	return $queries;
}			
?>