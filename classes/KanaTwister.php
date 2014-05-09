<?php

require_once ABS_PATH . 'libs/utf8_lib.php';

class KanaTwister
{

    private $pattern = null;
    private $replacePatternFn = null;

    public function __construct($pattern, $replaceFunction, $probCoef = 1)
    {
        $this->replacePatternFn = create_function('$match,$sub_match', $replaceFunction);
        $this->pattern = $pattern;
        $this->probCoef = $probCoef;
    }

    public function twist($array, $appendToArray = true)
    {
        if ($appendToArray) {
            $retArray = $array;
        } else {
            $retArray = [];
        }

        foreach ($array as $kana => $prob) {
            $count = preg_match_all($this->pattern, $kana, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

            if ($count) {
                $fn = $this->replacePatternFn;
                shuffle($matches);
                foreach ($matches as $match) {
                    $twisted = substr_replace($kana, $fn($match[1][0], $match[2][0]), $match[1][1], strlen($match[1][0]));
                    if (!isset($retArray[$twisted])) {
                        $retArray[$twisted] = $prob * $this->probCoef;
                        break;
                    }
                }
            }
        }
        return $retArray;
    }
}

function addTenten($char)
{
    // static $convmap = [0x3040, 0x309F, 1, 0xFFFF];
    // return mb_encode_numericentity($char, $convmap, 'UTF-8');
    $codes = utf8ToUnicode($char);
    $codes[0] ++;
    return unicodeToUtf8($codes);
}

function removeTenten($char)
{
    //	static $convmap = [0x3040, 0x309F, -1, 0xFFFF];
    //	return mb_convert_encoding(mb_encode_numericentity($char, $convmap, 'UTF-8'), 'ASCII', 'UTF-8');
    $codes = utf8ToUnicode($char);
    $codes[0] --;
    return unicodeToUtf8($codes);
}

function addMaru($char)
{
    // static $convmap = [0x3040, 0x309F, 2, 0xFFFF];
    // return mb_encode_numericentity($char, $convmap, 'UTF-8');
    $codes = utf8ToUnicode($char);
    $codes[0]+=2;
    return unicodeToUtf8($codes);
}

function removeMaru($char)
{
    // 	static $convmap = [0x3040, 0x309F, -2, 0xFFFF];
    // 	return mb_encode_numericentity($char, $convmap, 'UTF-8');
    $codes = utf8ToUnicode($char);
    $codes[0]-=2;
    return unicodeToUtf8($codes);
}

function makeSmall($char)
{
    $codes = utf8ToUnicode($char);
    $codes[0] --;
    return unicodeToUtf8($codes);
}

function makeBig($char)
{
    $codes = utf8ToUnicode($char);
    $codes[0] ++;
    return unicodeToUtf8($codes);
}
