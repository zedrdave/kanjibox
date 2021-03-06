<?php
if (!$_SESSION['user'] || !$_SESSION['user']->isEditor()) {
    die('editors only');
}

if (!empty($_REQUEST['submit']) || $_REQUEST['id']) {
    $query = 'SELECT k.*, kx.* FROM kanjis k LEFT JOIN kanjis_ext kx ON kx.kanji_id = k.id WHERE 1 ';
    if (!empty($_REQUEST['id'])) {
        $query .= 'AND k.id IN (0';
        $query .= implode(',', explode(',', $_REQUEST['id']));
        $query .= ') ';
    }
    if (!empty($_REQUEST['prons'])) {
        $query .= 'AND prons LIKE ' . DB::getConnection()->quote('%' . $_REQUEST['prons'] . '%');
    }

    foreach (['kanji', 'njlpt', 'grade', 'strokes'] as $field) {
        if (!empty($_REQUEST[$field])) {
            $query .= 'AND ' . $field . ' = ' . DB::getConnection()->quote($_REQUEST[$field]);
        }
    }

    $pref_lang_kanji = Kanji::$langStrings[$_SESSION['user']->getPreference('lang', 'kanji_lang')];

    if (!empty($_REQUEST['translator'])) {
        $query .= ' AND (kx.meaning_' . $pref_lang_kanji . ' IS NULL OR kx.meaning_' . $pref_lang_kanji . " = '' OR kx.meaning_" . $pref_lang_kanji . " LIKE '(~)%')";
    }

    $query .= ' GROUP BY k.id ORDER BY k.id, k.njlpt, kx.prons';
}
?>
<div id="ajax-result" class="message" style="display:none;"></div>
<form>
    Id(s): <input name="id" size="30" value="<?php echo $_REQUEST['id']?>" /> <br/>
    Kanji: <input name="kanji" size="10" value="<?php echo $_REQUEST['kanji']?>" /> -	Reading: <input name="prons" size="10" value="<?php echo $_REQUEST['prons']?>" /><br/>
    JLPT: N<input name="njlpt" size="1" value="<?php echo $_REQUEST['njlpt']?>" /> - Grade: <input name="grade" size="1" value="<?php echo $_REQUEST['grade']?>" /> - Strokes: <input name="strokes" size="2" value="<?php echo $_REQUEST['strokes']?>" /><br/>
    <input type="checkbox" name="translator" value="1" <?php echo ($_REQUEST['translator'] ? 'checked' : '')?> /> Translator mode<br/>
    <input type="submit" name="submit" value="Find" />
</form>
<?php
if (!empty($query)) {

    try {
        $stmt = DB::getConnection()->prepare($query);
        $stmt->execute();

        if ($stmt->rowCount() == 0) {
            echo '<p><em>No entries matching these criteria</em></p>';
        } else {
            echo '<p><strong>' . $stmt->rowCount() . ' entries matching these criteria</strong></p>';
        }

        while ($kanji = $stmt->fetchObject()) {
            echo "<div class=\"word-block\">$kanji->id<br/><span class=\"kanji\" style=\"font-size:150%;font-weight:bold;\">$kanji->kanji</span> 【" . $kanji->prons . "】 <a href=\"#\" onclick=\"$(this).hide();$('#sels_$kanji->id').show();return false;\" id=\"jltp_$kanji->id\">(N$kanji->njlpt, Grade: $kanji->grade)</a><span id=\"sels_$kanji->id\" style=\"display:none;\">";
            echo get_jlpt_menu('njltp_sel_' . $kanji->id, $kanji->njlpt, "update_kanji_jlpt($kanji->id,this.value);");
            echo "<select id=\"grade_sel_$kanji->id\" onchange=\"update_kanji_grade($kanji->id,this.value); return false;\">";
            for ($i = 9; $i >= 0; $i--) {
                echo '<option value="' . $i . '"' . ($kanji->grade == $i ? ' selected' : '') . '>' . $i . '</option>';
            }
            echo '</select></span><br/>';

            foreach (Kanji::$langStrings as $lang => $fullLang) {
                if (!empty($_REQUEST['translator']) && $fullLang != 'english' && $fullLang != $pref_lang_kanji) {
                    continue;
                }

                $meaningCol = 'meaning_' . $fullLang;
                echo "<p style=\"padding:0; margin:2px;\"><a href=\"#\" onclick=\"$('#meaning_$kanji->id" . "_$lang" . "_edit').toggle(); $('#meaning_$kanji->id" . "_$lang').toggle(); return false;\"><img src=\"" . SERVER_URL . "/img/flags/$lang.png\" alt=\"$lang-flag\" style=\"vertical-align:bottom; margin:0 3px 0 0;\" /> " . ucwords($fullLang) . ": <span id=\"meaning_$kanji->id" . "_$lang\" style=\"font-style:italic;" . ($fullLang == $pref_lang_kanji ? 'display:none;' : '') . "\">" . $kanji->$meaningCol . "</span></a>";
                echo "<input type=\"text\" name=\"new_gloss\" id=\"meaning_$kanji->id" . "_$lang" . "_edit\" value=\"" . str_replace('"',
                    '\"', $kanji->$meaningCol) . "\" style=\"width:600px;" . ($fullLang == $pref_lang_kanji ? '' : 'display:none;') . "\" onchange=\"update_kanji_lang($kanji->id, '$lang', this.value);\" /><br/>";

                echo "<input type=\"hidden\" name=\"traditional\" id=\"traditional\" value=\"$kanji->traditional\" /><input type=\"hidden\" name=\"lang\" id=\"lang\" value=\"$lang\" /><input type=\"hidden\" name=\"kanji_id\" id=\"kanji_id\" value=\"$kanji->id\" /></p>";
            }
            echo '</div>';
        }
        $stmt = null;
    } catch (PDOException $e) {
        log_db_error($query, $e->getMessage(), false, true);
    }
}
?>
<script type="text/javascript">

    function update_kanji_jlpt(id, jlpt)
    {
        $.get('<?php echo SERVER_URL?>ajax/edit_kanji/id/' + id + '/?njlpt=' + jlpt, function(data) {
            $('#njltp_sel_' + id).css('border', '2px solid green')
            $('#ajax-result').show().html(data);
            setTimeout(function() {
                $('#ajax-result').hide().html('')
            }, 2000);
        });
    }

    function update_kanji_grade(id, grade)
    {
        $.get('<?php echo SERVER_URL?>ajax/edit_kanji/id/' + id + '/?grade=' + grade, function(data) {
            $('#grade_sel_' + id).css('border', '2px solid green')
            $('#ajax-result').show().html(data);
            setTimeout(function() {
                $('#ajax-result').hide().html('')
            }, 2000);
        });
    }

    function update_kanji_lang(id, lang, meaning)
    {
        $.get('<?php echo SERVER_URL?>ajax/kanji_translation/?update=1&kanji_id=' + id + '&lang=' + lang + '&new_gloss=' + encodeURIComponent(meaning), function(data) {
            $('#meaning_' + id + '_' + lang + '_edit').css('border', '2px solid green')
            $('#ajax-result').show().html(data);
            setTimeout(function() {
                $('#ajax-result').hide().html('');
                $('#meaning_' + id + '_' + lang + '_edit').hide();
                $('#meaning_' + id + '_' + lang).html($('#meaning_' + id + '_' + lang + '_edit').val()).show();
            }, 2000);
        });
    }

</script>