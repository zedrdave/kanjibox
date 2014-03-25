<?php

define('MAX_VOCAB_ENTRIES', 2000);

class LearningSet {

    public $set_id;
    private $data, $entry_data;
    static $jlpt2char = array(0 => '', 1 => '①', 2 => '②', 3 => '③', 4 => '④', 5 => '⑤');

    function __construct($set_id) {
        $this->valid = false;
        $this->entry_data = NULL;

        if (!(int) $set_id)
            return;

        $query = 'SELECT ls.*, IFNULL(subs.user_id, 0) AS sub_id FROM learning_sets ls LEFT JOIN learning_set_subs subs ON subs.set_id = ls.set_id AND subs.user_id = ' . $_SESSION['user']->get_id() . ' WHERE ls.set_id = ' . (int) $set_id;
        if (!$_SESSION['user']->is_admin())
            $query .= ' AND (ls.public = 1 OR ls.user_id = ' . $_SESSION['user']->get_id() . ')';
        $res = mysql_query($query) or log_db_error($query, '', false, true); //print_r(mysql_error());
        if (mysql_num_rows($res) == 0)
            return;

        $this->set_id = $set_id;
        $this->data = mysql_fetch_object($res);
        if ($this->data->set_type != TYPE_KANJI && $this->data->set_type != TYPE_VOCAB)
            $this->data->set_type = TYPE_VOCAB;

        $this->valid = true;
    }

    function is_valid() {
        return $this->valid;
    }

    static function create_new($name, $type, $public = true, $editable = false) {
        $public = $public || $editable;
        $query = 'INSERT INTO learning_sets SET user_id = ' . $_SESSION['user']->get_id() . ", set_type = '" . mysql_real_escape_string($type) . "', date_created = NOW(), date_modified = NOW(), public = " . (int) $public . ", editable = " . (int) $editable . ", name = '" . mysql_real_escape_string($name) . "'";
        $res = mysql_query($query) or log_db_error($query, '', false, true); //print_r(mysql_error());

        return mysql_insert_id();
    }

    function get_name() {
        if ($this->valid)
            return $this->data->name;
        else
            return "Invalid set";
    }

    function get_type() {
        if ($this->valid)
            return $this->data->set_type;
        else
            return "";
    }

    function get_entry_count() {
        return count($this->get_entry_data());
    }

    function get_set_entry_index() {
        return ($this->get_type() == TYPE_KANJI ? 'kanji_id' : 'jmdict_id');
    }

    function get_set_join_table() {
        return ($this->get_type() == TYPE_KANJI ? 'kanjis' : 'jmdict');
    }

    function get_description() {
        return $this->data->description;
    }

    function is_public() {
        return $this->data->public;
    }

    function is_editable() {
        return ($this->data && $this->data->editable);
    }

    function is_owner() {
        return (@$_SESSION['user'] && $this->data && $this->data->user_id == $_SESSION['user']->get_id());
    }

    function is_public_domain() {
        return ($this->data->user_id < 0);
    }

    function can_admin() {
        // return $this->is_owner();
        return $this->is_owner() || $_SESSION['user']->is_admin();
    }

    function can_edit() {
        return (($this->is_editable() && $this->is_subscribed()) || $this->can_admin());
    }

    function is_subscribed() {
        return ($this->data->sub_id > 0);
    }

    function update_name($new_name) {
        $new_name = trim(strip_tags($new_name));

        if (empty($new_name))
            return;
        if (!$this->can_admin())
            return 'Only owner can edit this.';

        $query = 'UPDATE learning_sets SET name = \'' . mysql_real_escape_string($new_name) . '\' WHERE set_id = ' . $this->set_id;
        $res = mysql_query($query) or log_db_error($query, '', false, true);

        $this->data->name = $new_name;
    }

    function set_public($val) {
        if ((int) $val == 0 && $this->get_subs_count() && !$_SESSION['user']->is_admin())
            return false;

        if ($this->can_admin())
            return $this->_update_prop('public', (int) $val);
    }

    function set_editable($val) {
        if ($this->can_admin())
            return $this->_update_prop('editable', (int) $val);
    }

    private function _update_prop($prop_name, $prop_value) {
        if (!$this->can_edit())
            die('This set is not publicly editable.');

        $query = "UPDATE learning_sets SET $prop_name = '" . mysql_real_escape_string($prop_value) . '\' WHERE set_id = ' . $this->set_id;
        $res = mysql_query($query);

        if (!$res)
            return mysql_error();

        $this->data->$prop_name = $prop_value;

        return "Updated $prop_name to $prop_value";
    }

    function update_desc($new_desc) {
        if (!$this->can_edit())
            die('This set is not publicly editable.');

        $new_desc = trim(strip_tags($new_desc, '<a><br><p><em><strong>'));

        $query = 'UPDATE learning_sets SET description = \'' . mysql_real_escape_string($new_desc) . '\' WHERE set_id = ' . $this->set_id;
        $res = mysql_query($query) or log_db_error($query, '', false, true);

        $this->data->description = $new_desc;
    }

    function show_tags() {
        $query = 'SELECT GROUP_CONCAT(tags.tag SEPARATOR \', \') AS tag_string FROM learning_set_tags lst LEFT JOIN tags ON tags.tag_id = lst.tag_id WHERE lst.set_id = ' . $this->set_id . ' GROUP BY lst.set_id ORDER BY tags.tag';
        $res = mysql_query($query) or log_db_error($query, '', false, true);
        $row = mysql_fetch_object($res);

        if ($row)
            return $row->tag_string;
        else
            return '';
    }

    function search_new_entries($search_str) {
        $new_entries = array();

        if ($this->get_type() == TYPE_KANJI) {
            preg_match_all('/[\\x{4E00}-\\x{9FA5}]/u', $search_str, $matches, PREG_PATTERN_ORDER);

            $kanjis = $matches[0];
            if (!$kanjis || count($kanjis) == 0)
                return $new_entries;

            $query = 'SELECT k.kanji, k.njlpt, kx.kanji_id, kx.prons, kx.meaning_english, ls.set_id FROM kanjis k LEFT JOIN kanjis_ext kx ON kx.kanji_id = k.id LEFT JOIN learning_set_kanji ls ON ls.set_id = ' . $this->set_id . ' AND ls.kanji_id = k.id WHERE k.kanji IN (\'' . implode($kanjis, "','") . "') ORDER BY k.njlpt DESC, k.strokes ASC";

            $res = mysql_query($query) or die(mysql_error());

            while ($row = mysql_fetch_object($res)) {
                $new_entries[$row->kanji_id] = $row;
            }
        } else {
            define('KB_VOCAB_SET', '# KB vocab');
            if (substr($search_str, 0, strlen(KB_VOCAB_SET)) == KB_VOCAB_SET) {
                $ids = array();
                $lines = explode("\n", $search_str);
                foreach ($lines as $line) {
                    if ($line[0] == '#')
                        continue;

                    $items = preg_split('/\s+/', $line);
                    $ids[] = (int) $items[0];
                }

                if (!count($ids))
                    $ids[] = 0;

                $query = 'SELECT jx.jmdict_id, j.word, j.njlpt, j.njlpt_r, j.reading, jx.gloss_english, j.katakana, j.usually_kana, ls.set_id FROM jmdict j LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id LEFT JOIN learning_set_vocab ls ON ls.set_id = ' . $this->set_id . ' AND ls.jmdict_id = j.id WHERE j.id IN (' . implode(',', $ids) . ')';
            }
            elseif (substr($search_str, 0, 6) == "JMDICT") {
                $words = array();

                $lines = explode("\n", $search_str);
                foreach ($lines as $line) {
                    $items = explode("\t", $line);
                    if (count($items) == 1) {
                        $words[] = $line;
                    } else if (count($items) >= 3) {
                        preg_match_all('/[\\x{3040}-\\x{30FF}\\x{4E00}-\\x{9FA5}]+/u', $items[2], $matches, PREG_PATTERN_ORDER);

                        if (count($matches[0]))
                            $words = array_merge($words, $matches[0]);
                    }
                }

                $words = array_unique($words);

                if (!$words || count($words) == 0)
                    return $new_entries;

                $query = 'SELECT jx.jmdict_id, j.word, j.njlpt, j.njlpt_r, j.reading, jx.gloss_english, j.katakana, j.usually_kana, ls.set_id FROM jmdict j LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id LEFT JOIN learning_set_vocab ls ON ls.set_id = ' . $this->set_id . ' AND ls.jmdict_id = j.id WHERE j.word IN (\'' . implode($words, "','") . '\') ORDER BY j.njlpt DESC, j.njlpt_r DESC';
            }
            elseif (substr($search_str, 0, 4) == "LIST") {

                $query = 'SELECT jx.jmdict_id, j.word, j.njlpt, j.njlpt_r, j.reading, jx.gloss_english, j.katakana, j.usually_kana, ls.set_id FROM jmdict j LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id LEFT JOIN learning_set_vocab ls ON ls.set_id = ' . $this->set_id . ' AND ls.jmdict_id = j.id WHERE 0';


                $lines = explode("\n", $search_str);
                array_shift($lines);
                foreach ($lines as $line) {
                    $items = explode("\t", $line);
                    if (count($items) == 1) {
                        $query .= ' OR j.word = \'' . mysql_real_escape_string($line) . "'";
                    } else if (count($items) == 2) {
                        preg_match_all('/[\\x{3040}-\\x{30FF}\\x{4E00}-\\x{9FA5}]+/u', $items[1], $matches, PREG_PATTERN_ORDER);
                        $query .= ' OR (j.word = \'' . mysql_real_escape_string($items[0]) . "'";

                        if (count($matches[0]) == 1)
                            $query .= ' AND j.reading = \'' . $matches[0][0] . '\'';
                        elseif (count($matches[0]) > 1)
                            $query .= ' AND j.reading IN (\'' . implode("','", $matches[0]) . '\')';
                        $query .= ')';
                    }
                }

                $query .= ' ORDER BY j.njlpt DESC, j.njlpt_r DESC';
            }
            elseif (extension_loaded('mecab') && mb_strlen($search_str) > max(10, 4 * preg_match_all('/\s+/u', $search_str, $matches))) {

                require_once(ABS_PATH . 'libs/mecab_lib.php');

                //Removing .srt junk:
                $search_str = preg_replace('/^\d+[\n\r]+\d\d:\d\d:\d\d,\d+ --> \d\d:\d\d:\d\d,\d+/m', '', $search_str);
                $search_str = preg_replace("/➡[\r\n]+/", '', $search_str);

                $new_entry_ids = parse_jp_sentence($search_str, false, false);
                // print_r($new_entries);
                if ($new_entry_ids)
                    $query = "SELECT jx.jmdict_id, j.word, j.njlpt, j.njlpt_r, j.reading, jx.gloss_english, j.katakana, j.usually_kana, ls.set_id FROM jmdict j LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id LEFT JOIN learning_set_vocab ls ON ls.set_id = $this->set_id AND ls.jmdict_id = j.id WHERE j.id IN (" . implode(',', $new_entry_ids) . ")";
                // echo @$query;
            }
            else {
                if (!extension_loaded('mecab'))
                    echo '<div class="error_msg">Japanese parser could not be loaded.</div>';

                preg_match_all('/[\\x{3040}-\\x{30FF}\\x{4E00}-\\x{9FA5}]+/u', $search_str, $matches, PREG_PATTERN_ORDER);
                $words = $matches[0];

                if (!$words || count($words) == 0)
                    return $new_entries;

                $query = 'SELECT jx.jmdict_id, j.word, j.njlpt, j.njlpt_r, j.reading, jx.gloss_english, j.katakana, j.usually_kana, ls.set_id FROM jmdict j LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id LEFT JOIN learning_set_vocab ls ON ls.set_id = ' . $this->set_id . ' AND ls.jmdict_id = j.id WHERE j.word IN (\'' . implode($words, "','") . '\') OR j.reading IN (\'' . implode($words, "','") . "') ORDER BY j.njlpt DESC, j.njlpt_r DESC";
            }

            if (@$query) {
                $res = mysql_query($query) or die(mysql_error());

                while ($row = mysql_fetch_object($res))
                    $new_entries[$row->jmdict_id] = $row;
            }
        }

        return $new_entries;
    }

    function mark_set_updated() {
        $query = "UPDATE learning_sets SET date_modified = NOW() WHERE set_id = " . $this->set_id;

        mysql_query($query) or die(mysql_error());
    }

    function get_date_modified() {
        $res = mysql_query("SELECT date_modified FROM learning_sets WHERE set_id = " . $this->set_id);
        $row = mysql_fetch_object($res);
        return $row->date_modified;
    }

    function is_set_too_big() {
        $tot_entries = count($this->get_entry_data());
        return($tot_entries >= MAX_VOCAB_ENTRIES);
    }

    function add_to_set($arr) {
        if (!$this->can_edit())
            return 'This set is not publicly editable.';

        if (!$arr || !count($arr))
            return "Nothing to add...";

        $tot_entries = count($this->get_entry_data());

        if (count($arr) + $tot_entries >= MAX_VOCAB_ENTRIES) {
            $error_msg = 'A set cannot contain more than ' . MAX_VOCAB_ENTRIES . ' entries.';
            if ($tot_entries >= MAX_VOCAB_ENTRIES)
                return ($error_msg . " Please remove some entries before trying to add more.");

            $arr = array_slice($arr, 0, MAX_VOCAB_ENTRIES - $tot_entries);

            $error_msg .= ' ' . (MAX_VOCAB_ENTRIES - $tot_entries) . 'entries were added.';
        } else
            $error_msg = '';

        $this->entry_data = NULL;

        $query = "INSERT IGNORE INTO learning_set_" . $this->get_type() . " (set_id, " . $this->get_set_entry_index() . ") VALUES ";
        foreach ($arr as $entry_id)
            $query .= "($this->set_id, " . (int) $entry_id . "), ";

        $query = substr($query, 0, -2);

        if (!mysql_query($query))
            return mysql_error();

        $this->mark_set_updated();

        if ($error_msg)
            return $error_msg;
    }

    function remove_from_set($id) {
        if (!$this->can_edit())
            return 'This set is not publicly editable.';

        $this->entry_data = NULL;

        $query = "DELETE FROM learning_set_" . $this->get_type() . " WHERE set_id = $this->set_id AND " . $this->get_set_entry_index() . " = $id LIMIT 1";
        mysql_query($query);

        $this->mark_set_updated();
        return mysql_error();
    }

    function remove_level_from_set($level) {
        if (!$this->can_edit())
            return 'This set is not publicly editable.';

        $this->entry_data = NULL;

        $query = "DELETE ls.* FROM learning_set_" . $this->get_type() . " ls LEFT JOIN " . $this->get_set_join_table() . " t ON t.id = ls." . $this->get_set_entry_index() . " WHERE ls.set_id = $this->set_id AND t.njlpt = " . (int) $level;
        mysql_query($query);

        $this->mark_set_updated();
        return mysql_error();
    }

    function remove_other_set_from_set($set_id) {
        if (!$this->can_edit())
            return 'This set is not publicly editable.';

        $this->entry_data = NULL;

        $query = "DELETE ls.* FROM learning_set_" . $this->get_type() . " ls LEFT JOIN learning_set_" . $this->get_type() . " ls2 ON ls." . $this->get_set_entry_index() . " = ls2." . $this->get_set_entry_index() . " WHERE ls.set_id = $this->set_id AND ls2.set_id = " . (int) $set_id . " AND ls2.set_id IS NOT NULL";
        mysql_query($query);

        $this->mark_set_updated();
        return mysql_error();
    }

    function subscribe_to_set() {
        mysql_query('INSERT IGNORE INTO learning_set_subs SET user_id = ' . $_SESSION['user']->get_id() . ", set_id = $this->set_id");

        return mysql_error();
    }

    private function load_entry_data() {
        if ($this->entry_data)
            return;

        if ($this->get_type() == TYPE_KANJI)
            $res = mysql_query("SELECT kx.kanji_id, kx.kanji_id AS id, k.kanji, kx.prons, kx.meaning_english, k.njlpt FROM learning_set_kanji ls LEFT JOIN kanjis k ON k.id = ls.kanji_id LEFT JOIN kanjis_ext kx ON kx.kanji_id = ls.kanji_id WHERE ls.set_id = $this->set_id ORDER BY k.njlpt DESC, k.strokes ASC");
        else
            $res = mysql_query("SELECT jx.jmdict_id, jx.jmdict_id AS id, j.word, j.njlpt, j.njlpt_r, j.reading, jx.gloss_english, j.usually_kana, j.katakana, ls.set_id FROM learning_set_vocab ls LEFT JOIN jmdict j ON j.id = ls.jmdict_id LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id WHERE ls.set_id = $this->set_id ORDER BY j.njlpt DESC, j.njlpt_r DESC");

        if (!$res)
            die(mysql_error());

        $this->entry_data = array();
        while ($row = mysql_fetch_object($res))
            $this->entry_data[] = $row;
    }

    function get_entry_data() {
        if ($this->entry_data == NULL)
            $this->load_entry_data();

        return $this->entry_data;
    }

    function get_formatted_list() {
        $ret = '<h3>Set content:</h3>';
        if ($this->entry_data == NULL)
            $this->load_entry_data();

        if (!count($this->entry_data))
            $ret .= "<em>Empty set</em>";
        else {
            $ret .= "<div>(" . count($this->entry_data) . " entries)";

            if ($this->can_edit()) {
                $ret .= ' <a href="#" onclick="$(this).hide(); $(\'#set-bulk-remove\').show(); return false;">| Bulk remove &raquo;</a>';
                $ret .= '<div id="set-bulk-remove" style="display: none;">';
                $ret .= '<p>Remove all entries at level: <select onchange="if(this.value != \'\')  bulk_remove_level_from_set(' . $this->set_id . ', this.value);">';
                $ret .= '<option value="">-</option>';

                for ($i = 5; $i >= 0; $i--)
                    $ret .= '<option value="' . $i . '">N' . $i . '</option>';
                $ret .= "</select></p>\n";
                $query = 'SELECT ls.*, subs.set_id AS sub FROM learning_sets ls LEFT JOIN learning_set_subs subs ON subs.set_id = ls.set_id AND subs.user_id = ' . $_SESSION['user']->get_id() . ' WHERE ls.deleted = 0 AND (ls.user_id = ' . $_SESSION['user']->get_id() . " OR subs.set_id IS NOT NULL) AND set_type = '" . $this->get_type() . "' AND ls.set_id != " . $this->set_id . " ORDER BY date_modified";
                $res = mysql_query($query) or log_db_error($query, '', false, true);

                $ret .= '<p>Remove all entries also in set: <select onchange="if(this.value != \'\') bulk_remove_other_set_from_set(' . $this->set_id . ', this.value);">';
                $ret .= '<option value="">-</option>';
                while ($row = mysql_fetch_object($res))
                    $ret .= '<option value="' . $row->set_id . '">' . ($row->sub ? '' : '• ') . $row->name . '</option>';
                $ret .= "</select></p>\n";
                $ret .= '</div>';
            }

            $ret .= " </div>";
            foreach ($this->entry_data as $row) {
                $ret .= "<div class=\"set_content_line\">";

                if ($this->can_edit())
                    $ret .= "<button class=\"remove_entry\" onclick=\"remove_entry_from_set('" . SERVER_URL . "ajax/edit_learning_set/', $this->set_id, $row->id, \$(this).parent());\">×</button> ";

                if ($this->get_type() == TYPE_KANJI)
                    $ret .= '<span class="njlpt">' . LearningSet::$jlpt2char[$row->njlpt] . "</span> <span class=\"kanji\">$row->kanji</span> • <span class=\"prons\">$row->prons</span> • <span class=\"english\">$row->meaning_english</span></div>\n";
                else
                    $ret .= '<span class="njlpt">' . LearningSet::$jlpt2char[$row->njlpt] . "</span> <span class=\"japanese\">" . ($row->usually_kana ? $row->reading : $row->word . ($row->word != $row->reading && !$row->katakana ? " 【" . $row->reading . "】" : '')) . "</span> • <span class=\"english\">$row->gloss_english</span></div>\n";
            }
        }


        return $ret;
    }

    function get_export() {

        if ($this->get_type() == TYPE_KANJI) {
            $ret = "# KB kanji\n";
            $res = mysql_query("SELECT kx.kanji_id, kx.kanji_id AS id, k.kanji, kx.prons, kx.meaning_english, k.njlpt FROM learning_set_kanji ls LEFT JOIN kanjis k ON k.id = ls.kanji_id LEFT JOIN kanjis_ext kx ON kx.kanji_id = ls.kanji_id WHERE ls.set_id = $this->set_id ORDER BY k.njlpt DESC, k.strokes ASC");
        } else {
            $ret = "# KB vocab\n";
            $res = mysql_query("SELECT jx.jmdict_id, jx.jmdict_id AS id, j.word, j.njlpt, j.njlpt_r, j.reading, jx.gloss_english, j.usually_kana, j.katakana, ls.set_id FROM learning_set_vocab ls LEFT JOIN jmdict j ON j.id = ls.jmdict_id LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id WHERE ls.set_id = $this->set_id ORDER BY j.njlpt DESC, j.njlpt_r DESC");
        }

        $ret .= "# KanjiBox Set export downloaded from: " . SERVER_URL . 'sets/' . $this->set_id . "/\n#\n";
        $ret .= "# " . $this->get_name() . "\n# " . $this->get_description() . "\n#\n";
        if (!$res)
            return mysql_error();
        if (!mysql_num_rows($res))
            $ret .= "Empty set";
        else {
            if ($this->get_type() == TYPE_KANJI) {
                while ($row = mysql_fetch_object($res))
                    $ret .= "$row->id\t$row->kanji\t$row->prons\n";
            } else {
                while ($row = mysql_fetch_object($res))
                    $ret .= "$row->id\t$row->word\t$row->reading\n";
            }
        }
        $ret .= "#\n# KanjiBox Set export downloaded from: " . SERVER_URL . 'sets/' . $this->set_id . "/\n";

        return $ret;
    }

    function set_tag($tag_id, $val) {
        if (!$this->can_edit())
            return "Set not editable";

        if (!$tag_id)
            return "invalid tag ID";

        if ($val) {
            $query = 'INSERT INTO learning_set_tags SET set_id = ' . $this->set_id . ', tag_id = ' . (int) $tag_id;
        } else {
            $query = 'DELETE FROM learning_set_tags WHERE set_id = ' . $this->set_id . ' AND tag_id = ' . (int) $tag_id . ' LIMIT 1';
        }

        mysql_query($query);
        return mysql_error();
    }

    function show_tag_checkboxes() {
        $query = 'SELECT tags.tag, tags.tag_id, IF(lst.set_id, 1, 0) AS checked FROM tags LEFT JOIN learning_set_tags lst ON tags.tag_id = lst.tag_id AND lst.set_id = ' . $this->set_id . ' ORDER BY tags.tag';
        $res = mysql_query($query) or print_r(mysql_error());

        $str = '';

        while ($row = mysql_fetch_object($res)) {
            if (!$this->can_edit() && !$row->checked)
                continue;

            $str .= '<span class="tag_box" id="tag_' . $row->tag_id . '">';
            if ($this->can_edit())
                $str .= '<input type="checkbox" ' . ($row->checked ? 'checked' : '') . ' id="check_tag_' . $row->tag_id . '" onclick="update_tag(' . $this->set_id . ', ' . $row->tag_id . ', this.checked)"></input>&nbsp;';

            $str .= '<label for="check_tag_' . $row->tag_id . '">' . $row->tag . '</label></span>';
        }

        return $str;
    }

    function get_owner_info() {
        $res = mysql_query('SELECT * FROM users u LEFT JOIN users_ext ux ON ux.user_id = u.id WHERE u.id = ' . $this->data->user_id);
        return mysql_fetch_object($res);
    }

    static function get_all_tag_checkboxes() {
        $query = 'SELECT tags.tag, tags.tag_id FROM tags ORDER BY tags.tag';
        $res = mysql_query($query) or print_r(mysql_error());

        $str = '';

        while ($row = mysql_fetch_object($res)) {
            $str .= '<span class="tag_box">';
            $str .= '<input type="checkbox" id="tags[' . $row->tag_id . ']" name="tags[' . $row->tag_id . ']" value="' . $row->tag_id . '"></input> ';
            $str .= '<label for="tags[' . $row->tag_id . ']">' . $row->tag . '</label></span>';
        }

        return $str;
    }

    function get_author_name() {
        $info = $this->get_owner_info();
        $set_author = '';
        if ($info && ($info->first_name || $info->last_name)) {
            $set_author = $info->first_name . ' ';
            if ($arr = explode(' ', $info->last_name))
                foreach ($arr as $i => $word)
                    $set_author .= mb_substr($word, 0, 1, 'UTF-8') . '.' . ($i == count($arr) - 1 ? '' : ' ');
        }
        elseif ($this->data->user_id < 0)
            $set_author = 'Public Domain';
        else
            $set_author = '#' . $this->data->user_id;
        return $set_author;
    }

    static function get_all_tags($has_public_sets = false) {
        $tags = array();
        $query = 'SELECT tags.tag, tags.tag_id FROM tags ';

        if ($has_public_sets) {
            $query .= ' JOIN learning_set_tags lst ON lst.tag_id = tags.tag_id JOIN learning_sets ls ON ls.set_id = lst.set_id AND ls.public = 1 AND deleted = 0 GROUP BY tags.tag_id';
        }
        $query .= ' ORDER BY tags.tag';

        $res = mysql_query($query) or print_r(mysql_error());
        while ($row = mysql_fetch_object($res)) {
            $tags[$row->tag_id] = $row->tag;
        }

        return $tags;
    }

    function get_subs_count() {
        $res = mysql_query('SELECT COUNT(*) AS c FROM learning_set_subs WHERE set_id = ' . $this->set_id) or die(mysql_error());
        $row = mysql_fetch_object($res);
        return $row->c;
    }

    function delete_set() {
        if (!$this->can_admin())
            return 'Not allowed to delete this set';

        $this->entry_data = NULL;

        mysql_query('DELETE FROM learning_set_' . $this->get_type() . ' WHERE set_id = ' . $this->set_id) or die(mysql_error());
        mysql_query('DELETE FROM learning_set_subs WHERE set_id = ' . $this->set_id) or die(mysql_error());
        mysql_query('UPDATE learning_sets SET deleted = 1 WHERE set_id = ' . $this->set_id . ' LIMIT 1') or die(mysql_error());
        $this->valid = false;
    }

    function make_public_domain() {
        if (!$this->can_admin())
            return 'Not allowed to modify this set';
        if (!$this->is_public())
            return 'Set must be public';

        mysql_query('UPDATE learning_sets SET user_id = -1, editable = 1, public = 1 WHERE set_id = ' . $this->set_id . ' LIMIT 1') or die(mysql_error());

        $this->data->user_id = -1;
        $this->data->editable = 1;

        $this->subscribe_to_set();
    }

}

?>