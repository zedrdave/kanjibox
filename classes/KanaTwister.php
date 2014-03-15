<?php

require_once(ABS_PATH . 'libs/utf8_lib.php');

class KanaTwister
{
	private $pattern = NULL;
	private $replace_pattern_fn = NULL;
	
	public function __construct($pattern, $replace_function, $prob_coef = 1)
	{
		$this->replace_pattern_fn = create_function('$match,$sub_match', $replace_function);
		$this->pattern = $pattern;
		$this->prob_coef = $prob_coef;
	}
	
	public function twist($array, $append_to_array = true)
	{
		if($append_to_array)
			$ret_array = $array;
		else
			$ret_array = array();
		
		
		foreach($array as $kana => $prob)
		{
			$count = preg_match_all($this->pattern, $kana, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
		
			if ($count)
			{
				$fn = $this->replace_pattern_fn;
				shuffle($matches);
				foreach($matches as $match)
				{
					$twisted = substr_replace($kana, $fn($match[1][0], @$match[2][0]), $match[1][1], strlen($match[1][0]));
					if(! isset($ret_array[$twisted]))
					{
						$ret_array[$twisted] =  $prob * $this->prob_coef;
						break;
					}
				}
			}
		}
		return $ret_array;
	}


}

function add_tenten($char)
{
	// static $convmap = array(0x3040, 0x309F, 1, 0xFFFF);
	// return mb_encode_numericentity($char, $convmap, 'UTF-8');
	$codes = utf8ToUnicode($char);
	$codes[0]++;
	return unicodeToUtf8($codes);
}

function remove_tenten($char)
{
//	static $convmap = array(0x3040, 0x309F, -1, 0xFFFF);
//	return mb_convert_encoding(mb_encode_numericentity($char, $convmap, 'UTF-8'), 'ASCII', 'UTF-8');
	$codes = utf8ToUnicode($char);
	$codes[0]--;
	return unicodeToUtf8($codes);
}

function add_maru($char)
{
	// static $convmap = array(0x3040, 0x309F, 2, 0xFFFF);
	// return mb_encode_numericentity($char, $convmap, 'UTF-8');
	$codes = utf8ToUnicode($char);
	$codes[0]+=2;
	return unicodeToUtf8($codes);
}

function remove_maru($char)
{
// 	static $convmap = array(0x3040, 0x309F, -2, 0xFFFF);
// 	return mb_encode_numericentity($char, $convmap, 'UTF-8');
	$codes = utf8ToUnicode($char);
	$codes[0]-=2;
	return unicodeToUtf8($codes);
 }

function make_small($char)
{
	$codes = utf8ToUnicode($char);
	$codes[0]--;
	return unicodeToUtf8($codes);
 }

function make_big($char)
{
	$codes = utf8ToUnicode($char);
	$codes[0]++;
	return unicodeToUtf8($codes);
 }

?>