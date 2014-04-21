<?php

mb_internal_encoding('UTF-8');

function mb_ord($char) {
    return (strlen($char) < 2) ?
            ord($char) : 256 * mb_ord(substr($char, 0, -1)) + ord(substr($char, -1));
}

function mb_chr($string) {
    return html_entity_decode('&#' . intval($string) . ';');
}

$i = 0;
define('V_ICHIDAN', $i++);
define('V_GODAN', $i++);
define('V_KAGYOU', $i++);
define('V_SAGYOU', $i++);
define('V_UNKNOWN', $i++);

$i = 0;
define('VF_PRESENT', $i++);
define('VM_PRESENT', $i++);
define('VF_PAST', $i++);
define('VM_PAST', $i++);
define('V_CONDITIONAL', $i++);
define('VF_POTENTIAL', $i++);
define('VM_POTENTIAL', $i++);
define('VF_PASSIVE', $i++);
define('VM_PASSIVE', $i++);
define('VF_IMPERATIVE', $i++);
define('VF_VOLITIONAL', $i++);

define('VF_PRESENT_NEG', $i++);
define('VM_PRESENT_NEG', $i++);

define('I_STEM', $i++);

class Verb {

    function __construct($jmdict_id) {

    }

    function printAllForms($verb, $reading) {
        $vc = $this->vClassForVerb($verb, $reading);
        echo 'Form: ' . $this->vClassToString($vc) . '<br/>';

        echo 'Masu: ', $this->getForm(VM_PRESENT, $verb, $reading) . '<br/>';
        echo 'Past: ', $this->getForm(VF_PAST, $verb, $reading) . '<br/>';
        echo 'Passive: ', $this->getForm(VF_PASSIVE, $verb, $reading) . '<br/>';
        echo 'Conditional: ', $this->getForm(V_CONDITIONAL, $verb, $reading) . '<br/>';
        echo 'Volitional: ', $this->getForm(VF_VOLITIONAL, $verb, $reading) . '<br/>';
        echo 'Imperative: ', $this->getForm(VF_IMPERATIVE, $verb, $reading) . '<br/>';

        echo 'Dict Negative: ', $this->getForm(VF_PRESENT_NEG, $verb, $reading) . '<br/>';
        echo 'Masu Negative: ', $this->getForm(VM_PRESENT_NEG, $verb, $reading) . '<br/>';

        echo "'I' Stem: ", $this->getForm(I_STEM, $verb, $reading) . '<br/>';
    }

    function getForm($form, $verb, $reading) {
        $vc = $this->vClassForVerb($verb, $reading);
        if ($vc == V_UNKNOWN) {
            return 'n/a';
        }
        switch ($form) {
            case VF_PRESENT:
                return $verb;
            case VF_PRESENT_NEG:
                $suffix = 'あない';
                break;
            case VM_PRESENT:
                $suffix = 'います';
                break;
            case VM_PRESENT_NEG:
                $form = VM_PRESENT;
                $suffix = 'いません';
                break;
            case VM_WISH:
                $form = VM_PRESENT;
                $suffix = 'いたい';
                break;
            case VF_PAST:
                $suffix = 'た';
                break;
            case VM_PAST:
                $suffix = 'いました';
                break;
            case V_CONDITIONAL:
                $suffix = 'れば';
                break;
            case VF_POTENTIAL:
                $suffix = 'れる';
                break;
            case VM_POTENTIAL:
                $suffix = 'れます';
                break;
            case VF_IMPERATIVE:
                $suffix = 'えよ';
                break;
            case VF_PASSIVE:
                $suffix = 'られる';
                break;
            case VF_VOLITIONAL:
                $suffix = 'よう';
                break;
            case I_STEM:
                $form = VM_PRESENT;
                $suffix = 'い';
                break;
            default:
                break;
        }

        if ($form == VF_PAST) {
            $ending = mb_substr($verb, -2);
            if ($ending == '行く') {
                return (mb_substr($verb, 0, -1) . 'っ' . $suffix);
            }
            if ($ending == '請う' || $ending == '問う') {
                return (mb_substr($verb, 0, -1) . 'お' . $suffix);
            }
        }

        if ($form == VM_PRESENT || $form == VM_PAST) {
            if ($verb == 'いらっしゃる' || $reading == 'おっしゃる' || $verb == '下さる' || $reading == 'ござる' || mb_substr($reading, -3) == 'なさる') {
                return (mb_substr($verb, 0, -1) . $suffix);
            }
        }

        if ($vc == V_GODAN) {
            return mb_substr($verb, 0, -1) . $this->mixRecessiveKanaWithKana(mb_substr($verb, -1), mb_substr($suffix, 0, 1)) . mb_substr($suffix, 1);
        }

        if ($vc == V_ICHIDAN) {
            return mb_substr($verb, 0, -2) . $this->mixDominantKanaWithKana(mb_substr($verb, -2, 1), mb_substr($suffix, 0, 1)) . mb_substr($suffix, 1);
        }

        return 'n/a';
    }

    function mixDominantKanaWithKana($k1, $k2) {
        switch (mb_ord(mb_convert_encoding($k2, 'UTF-16', 'UTF-8'))) {
            case 0x3042: // あ
            case 0x3046: // う
            case 0x3044: // い
            case 0x304a: // お
            case 0x3048: // え
                return $k1;
            default:
                return $k1 . $k2;
        }
    }

    function mixRecessiveKanaWithKana($k1_char, $k2_char) {
        $k1 = mb_ord(mb_convert_encoding($k1_char, 'UTF-16', 'UTF-8'));
        $k2 = mb_ord(mb_convert_encoding($k2_char, 'UTF-16', 'UTF-8'));

        if ($k2 == 0x305f) { // た
            switch ($k1) {
                case 0x304f: // く
                    return 'いた';
                case 0x3050: // ぐ
                    return 'いだ';
                case 0x3059: // す
                    return 'した';
                case 0x3064: // つ
                case 0x3046: // う
                case 0x308b: // る
                    return 'った';
                case 0x306c: // ぬ
                case 0x3080: // む
                case 0x3076: // ぶ
                    return 'んだ';
            }
        }

        if ($k2 == 0x3066) { // て
            switch ($k1) {
                case 0x304f: // く
                    return 'いて';
                case 0x3050: // ぐ
                    return 'いで';
                case 0x3059: // す
                    return 'して';
                case 0x3064: // つ
                case 0x3046: // う
                case 0x308b: // る
                    return 'って';
                case 0x306c: // ぬ
                case 0x3080: // む
                case 0x3076: // ぶ
                    return 'んで';
            }
        }

        switch ($k1) {
            case 0x3046: // う
                switch ($k2) {
                    case 0x3042: // あ
                        return 'わ';
                    case 0x3044: // い
                    case 0x304a: // お
                    case 0x3048: // え
                        return $k2_char;
                    case 0x3088: // よ
                        return 'お';
                    case 0x3089: // ら
                    case 0x308c: // れ
                    case 0x308b: // る
                    case 0x3055: // さ
                        return "わ$k2_char";
                    default:
                        break;
                }
                break;

            case 0x304f: // く
            case 0x3050: // ぐ
            case 0x3059: // す
            case 0x3064: // つ
            case 0x306c: // ぬ
            case 0x3080: // む
            case 0x3076: // ぶ
            case 0x308b: // る
                switch ($k2) {
                    case 0x3042: // あ
                    case 0x3044: // い
                    case 0x304a: // お
                    case 0x3048: // え
                        return mb_convert_encoding(mb_chr($this->mixKana($k1, $k2)), 'UTF-8');

                    case 0x3088: // よ
                        //                    return @'お';
                        return mb_convert_encoding(mb_chr($this->mixKana($k1, 0x304a)), 'UTF-8');

                    case 0x3089: // ら
                    case 0x308c: // れ
                    case 0x308b: // る
                    case 0x3055: // さ
                        return mb_convert_encoding(mb_chr($this->mixKana($k1, $this->getVowelForKana($k2))), 'UTF-8');
                    default:
                        break;
                }
                break;
        }

        echo("Unknown combination: $k1_char + $k2_char");
        return '?';
    }

    function mixKana($k1, $k2) {
        switch ($k1) {
            case 0x304f: // く
                switch ($k2) {
                    case 0x3042: // あ
                        return 0x304b; // か
                    case 0x3044: // い
                        return 0x304d; // き
                    case 0x304a: // お
                        return 0x3053; // こ
                    case 0x3048: // え
                        return 0x3051; // け
                    default:
                        return $k1;
                }
                break;

            case 0x3050: // ぐ
                return $this->mixKana(0x304f, $k2) + 1; // く

            case 0x3059: // す
                switch ($k2) {
                    case 0x3042: // あ
                        return 0x3055; // さ
                    case 0x3044: // い
                        return 0x3057; // し
                    case 0x304a: // お
                        return 0x305d; // そ
                    case 0x3048: // え
                        return 0x305b; // せ
                    default:
                        return $k1;
                }
                break;

            case 0x3064: // つ
            case 0x306c: // ぬ
                switch ($k2) {
                    case 0x3042: // あ
                        return 0x306a; // な
                    case 0x3044: // い
                        return 0x306b; // に
                    case 0x304a: // お
                        return 0x306e; // の
                    case 0x3048: // え
                        return 0x306d; // ね
                    default:
                        return $k1;
                }
                break;
            case 0x3080: // む
                switch ($k2) {
                    case 0x3042: // あ
                        return 0x307e; // ま
                    case 0x3044: // い
                        return 0x307f; // み
                    case 0x304a: // お
                        return 0x3082; // も
                    case 0x3048: // え
                        return 0x3081; // め
                    default:
                        return $k1;
                }
                break;

            case 0x3076: // ぶ
                switch ($k2) {
                    case 0x3042: // あ
                        return 0x3070; // ば
                    case 0x3044: // い
                        return 0x3073; // び
                    case 0x304a: // お
                        return 0x307c; // ぼ
                    case 0x3048: // え
                        return 0x3079; // べ
                    default:
                        return $k1;
                }
                break;

            case 0x308b: // る
                switch ($k2) {
                    case 0x3042: // あ
                        return 0x3089; // ら
                    case 0x3044: // い
                        return 0x308a; // り
                    case 0x304a: // お
                        return 0x308d; // ろ
                    case 0x3048: // え
                        return 0x308c; // れ
                    default:
                        return $k1;
                }
                break;

            default:
                return '?';
        }
    }

    function vClassForVerb($verb, $reading) {
        if (mb_strlen($verb) <= 1) {
            return V_UNKNOWN;
        }
        if (mb_strlen($reading) <= 1) {
            return V_UNKNOWN;
        }


        if ($verb == '来る') {
            return V_KAGYOU;
        }
        if ($verb == 'する') {
            return V_KAGYOU;
        }

        $c_last = mb_ord(mb_convert_encoding(mb_substr($reading, -1), 'UTF-16'));
        //    NSLog(@"%C: %d | %x", c_last, c_last, c_last);

        $c_penult = mb_ord(mb_convert_encoding(mb_substr($reading, -2, 1), 'UTF-16'));
        $k_penult = mb_ord(mb_convert_encoding(mb_substr($verb, -2, -1), 'UTF-16', 'UTF-8'));

        switch ($c_last) {
            case 0x3046: // う
            case 0x304f: // く
            case 0x3050: // ぐ
            case 0x3059: // す
            case 0x305a: // ず
            case 0x3064: // つ
            case 0x306c: // ぬ
            case 0x3080: // む
            case 0x3075: // ふ
            case 0x3076: // ぶ
                return V_GODAN;

            case 0x308b: // る
                switch ($this->getVowelForKana($c_penult)) {
                    case 0x3042: // あ
                    case 0x3046: // う
                    case 0x304a: // お
                        return V_GODAN;
                    case 0x3048: // え
                        switch ($k_penult) {
                            case 0x5e30: // 帰
                            case 0x53cd: // 反
                            case 0x8986: // 覆
                            case 0x7ffb: // 翻
                            case 0x98dc: // 飜
                            case 0x7526: // 甦
                            case 0x8e74: // 蹴
                            case 0x5632: // 嘲
                            case 0x7af6: // 競
                            case 0x7cf6: // 糶
                            case 0x7126: // 焦
                            case 0x7167: // 照
                            case 0x7df4: // 練
                            case 0x755d: // 畝
                            case 0x6293: // 抓
                            case 0x637b: // 捻
                            case 0x6e1b: // 減
                            case 0x6e7f: // 湿
                            case 0x9670: // 陰
                            case 0x8302: // 茂
                            case 0x7ff3: // 翳
                            case 0x7e41: // 繁
                            case 0x558b: // 喋
                            case 0x6ed1: // 滑
                            case 0x8fb7: // 辷
                            case 0x4f8d: // 侍
                                return V_GODAN;

                            default:
                                $ending = mb_substr($verb, -3);
                                if ($ending == '湿気る' || $ending == '嘲ける' || $ending == '畝ねる' || $ending == 'くねる') {
                                    return V_GODAN;
                                }
                                break;
                        }

                        return V_ICHIDAN;

                    case 0x3044: // い
                        // @"入る", @"煎る", @"参る", @"切る", @"走る", @"毟る", @"罵る", @"散る", @"嗇る", @"限る", @"握る", @"遮る", @"弄る", @"齧る", @"詰る", @"捻る", @"交る", @"捩る"
                        switch ($k_penult) {
                            case 0x5165: // 入
                            case 0x714e: // 煎
                            case 0x53c2: // 参
                            case 0x5207: // 切
                            case 0x8d70: // 走
                            case 0x6bdf: // 毟
                            case 0x7f75: // 罵
                            case 0x6563: // 散
                            case 0x55c7: // 嗇
                            case 0x9650: // 限
                            case 0x63e1: // 握
                            case 0x906e: // 遮
                            case 0x5f04: // 弄
                            case 0x9f67: // 齧
                            case 0x8a70: // 詰
                            case 0x637b: // 捻
                            case 0x4ea4: // 交
                            case 0x6369: // 捩
                            case 0x8e99: // 躙
                                return V_GODAN;

                            case 0x3058: // じ
                                if (mb_substr($verb, -3) == 'まじる') {
                                    return V_GODAN;
                                }

                                break;
                            default:
                                break;
                        }

                        return V_ICHIDAN;
                }
                break;

            default:
                break;
        }


        return V_UNKNOWN;
    }

    function vClassToString($vc) {
        switch ($vc) {
            case V_GODAN:
                return 'godan (う-dropping)';
            case V_ICHIDAN:
                return 'ichidan (る-dropping)';
            case V_KAGYOU:
                return 'kagyou (irregular: する)';
            case V_SAGYOU:
                return 'sagyou (irregular: 来る)';
            case V_UNKNOWN:
                return 'n/a';
            default:
                return 'unknown vClass';
        }
    }

    function getVowelForKana($c) {
        switch ($c) {
            case 0x3042: // あ
            case 0x304b: // か
            case 0x304c: // が
            case 0x3055: // さ
            case 0x3056: // ざ
            case 0x305f: // た
            case 0x3060: // だ
            case 0x306a: // な
            case 0x306f: // は
            case 0x3070: // ば
            case 0x3071: // ぱ
            case 0x307e: // ま
            case 0x3083: // ゃ
            case 0x3084: // や
            case 0x3089: // ら
            case 0x308e: // ゎ
            case 0x308f: // わ
                return 0x3042;
            case 0x3044: // い
            case 0x304d: // き
            case 0x304e: // ぎ
            case 0x3057: // し
            case 0x3058: // じ
            case 0x3061: // ち
            case 0x3062: // ぢ
            case 0x306b: // に
            case 0x3072: // ひ
            case 0x3073: // び
            case 0x3074: // ぴ
            case 0x307f: // み
            case 0x308a: // り
            case 0x3090: // ゐ
                return 0x3044;
            case 0x3046: // う
            case 0x304f: // く
            case 0x3050: // ぐ
            case 0x3059: // す
            case 0x305a: // ず
            case 0x3064: // つ
            case 0x3065: // づ
            case 0x306c: // ぬ
            case 0x3075: // ふ
            case 0x3076: // ぶ
            case 0x3077: // ぷ
            case 0x3080: // む
            case 0x3085: // ゅ
            case 0x3086: // ゆ
            case 0x308b: // る
                return 0x3046;
            case 0x304a: // お
            case 0x3053: // こ
            case 0x3054: // ご
            case 0x305d: // そ
            case 0x305e: // ぞ
            case 0x3068: // と
            case 0x3069: // ど
            case 0x306e: // の
            case 0x307b: // ほ
            case 0x307c: // ぼ
            case 0x307d: // ぽ
            case 0x3082: // も
            case 0x3087: // ょ
            case 0x3088: // よ
            case 0x308d: // ろ
                return 0x304a;
            case 0x3048: // え
            case 0x3051: // け
            case 0x3052: // げ
            case 0x305b: // せ
            case 0x305c: // ぜ
            case 0x3066: // て
            case 0x3067: // で
            case 0x306d: // ね
            case 0x3078: // へ
            case 0x3079: // べ
            case 0x307a: // ぺ
            case 0x3081: // め
            case 0x308c: // れ
                return 0x3048; // え

            default:
                return 0;
        }
    }

    function getConsonnant($kana) {
        if ($kana <= 0x3040) {
            return '-';
        }

        if ($kana <= 0x304a) {
            return ' ';
        }

        if ($kana <= 0x3052) {
            return 'k';
        }

        if ($kana <= 0x305e) {
            return 's';
        }

        if ($kana <= 0x3065) {
            return 't';
        }

        if ($kana <= 0x306e) {
            return 'n';
        }

        if ($kana <= 0x307e) {
            return 'h';
        }

        if ($kana <= 0x3082) {
            return 'm';
        }

        if ($kana <= 0x3088) {
            return 'y';
        }

        if ($kana <= 0x308d) {
            return 'r';
        }

        if ($kana <= 0x3092) {
            return 'w';
        }

        return '-';
    }

}
