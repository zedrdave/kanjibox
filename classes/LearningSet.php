<?php

define('MAX_VOCAB_ENTRIES', 2000);

class LearningSet
{

    public $id;
    private $data;
    private $entryData;
    public static $jlpt2Char = [0 => '', 1 => '①', 2 => '②', 3 => '③', 4 => '④', 5 => '⑤'];

    public function __construct($id)
    {
        $this->valid = false;
        $this->entryData = null;

        if (!(int) $id) {
            return;
        }

        $query = 'SELECT ls.*, IFNULL(subs.user_id, 0) AS sub_id FROM learning_sets ls LEFT JOIN learning_set_subs subs ON subs.set_id = ls.set_id AND subs.user_id = :userid WHERE ls.set_id = :setid';
        if (!$_SESSION['user']->isAdministrator()) {
            $query .= ' AND (ls.public = 1 OR ls.user_id = :userid)';
        }

        try {
            $stmt = DB::getConnection()->prepare($query);
            $stmt->bindValue(':userid', $_SESSION['user']->getID(), PDO::PARAM_INT);
            $stmt->bindValue(':setid', $id, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() == 0) {
                return;
            }

            $this->id = $id;
            $this->data = $stmt->fetchObject();
            if ($this->data->set_type != TYPE_KANJI && $this->data->set_type != TYPE_VOCAB) {
                $this->data->set_type = TYPE_VOCAB;
            }
            $this->valid = true;
        } catch (PDOException $e) {
            log_db_error($query, $e->getMessage(), false, true);
            return false;
        }
    }

    public function isValid()
    {
        return $this->valid;
    }

    public static function createNew($name, $type, $public = true, $editable = false)
    {
        return DB::insert('INSERT INTO learning_sets SET user_id = :userid, set_type = :settype, date_created = NOW(), date_modified = NOW(), public = :public, editable = :editable, name = :name',
                [
                ':userid' => $_SESSION['user']->getID(),
                ':settype' => $type,
                ':public' => $public || $editable,
                ':editable' => $editable,
                ':name' => $name,
                ]
        );
    }

    public function getName()
    {
        if ($this->valid) {
            return $this->data->name;
        } else {
            return 'Invalid set';
        }
    }

    public function getType()
    {
        if ($this->valid) {
            return $this->data->set_type;
        } else {
            return '';
        }
    }

    public function getEntryCount()
    {
        return count($this->getEntryData());
    }

    public function getSetEntryIndex()
    {
        return ($this->getType() == TYPE_KANJI ? 'kanji_id' : 'jmdict_id');
    }

    public function getSetJoinTable()
    {
        return ($this->getType() == TYPE_KANJI ? 'kanjis' : 'jmdict');
    }

    public function getDescription()
    {
        return $this->data->description;
    }

    public function isPublic()
    {
        return $this->data->public;
    }

    public function isEditable()
    {
        return ($this->data && $this->data->editable);
    }

    public function isOwner()
    {
        return (!empty($_SESSION['user']) && $this->data && $this->data->user_id == $_SESSION['user']->getID());
    }

    public function isPublicDomain()
    {
        return ($this->data->user_id < 0);
    }

    public function canAdmin()
    {
        return $this->isOwner() || $_SESSION['user']->isAdministrator();
    }

    public function canEdit()
    {
        return (($this->isEditable() && $this->isSubscribed()) || $this->canAdmin());
    }

    public function isSubscribed()
    {
        return ($this->data->sub_id > 0);
    }

    public function updateName($newName)
    {
        $newName = trim(strip_tags($newName));

        if (empty($newName)) {
            return;
        }
        if (!$this->canAdmin()) {
            return 'Only owner can edit this.';
        }

        $query = 'UPDATE learning_sets SET name = \'' . DB::getConnection()->quote($newName) . '\' WHERE set_id = ' . $this->id;
        $res = mysql_query($query) or log_db_error($query, '', false, true);

        $this->data->name = $newName;
    }

    public function setPublic($val)
    {
        if ((int) $val == 0 && $this->getSubsCount() && !$_SESSION['user']->isAdministrator()) {
            return false;
        }

        if ($this->canAdmin()) {
            return $this->updateProp('public', (int) $val);
        }
    }

    public function setEditable($val)
    {
        if ($this->canAdmin()) {
            return $this->updateProp('editable', (int) $val);
        }
    }

    private function updateProp($name, $value)
    {
        if (!$this->canEdit()) {
            die('This set is not publicly editable.');
        }

        $query = 'UPDATE learning_sets SET ' . $name . ' = :propvalue WHERE set_id = :setid';
        try {
            $stmt = DB::getConnection()->prepare($query);
            $stmt->bindValue(':propvalue', $value, PDO::PARAM_STR);
            $stmt->bindValue(':setid', $this->id, PDO::PARAM_INT);
            $stmt->execute();
            $stmt = null;

            $this->data->$name = $value;
            return 'Updated ' . $name . ' to .' . $value;
        } catch (PDOException $e) {
            log_db_error($query, $e->getMessage(), false, true);
        }
    }

    public function updateDesc($newDesc)
    {
        if (!$this->canEdit()) {
            die('This set is not publicly editable.');
        }

        $newDesc = trim(strip_tags($newDesc, '<a><br><p><em><strong>'));

        $query = 'UPDATE learning_sets SET description = :newdescription WHERE set_id = :setid';
        try {
            $stmt = DB::getConnection()->prepare($query);
            $stmt->bindValue(':newdescription', $newDesc, PDO::PARAM_STR);
            $stmt->bindValue(':setid', $this->id, PDO::PARAM_INT);
            $stmt->execute();

            $this->data->description = $newDesc;
            $stmt = null;
        } catch (PDOException $e) {
            log_db_error($query, $e->getMessage(), false, true);
        }
    }

    public function showTags()
    {
        $query = 'SELECT GROUP_CONCAT(tags.tag SEPARATOR \', \') AS tag_string FROM learning_set_tags lst LEFT JOIN tags ON tags.tag_id = lst.tag_id WHERE lst.set_id = ' . $this->id . ' GROUP BY lst.set_id ORDER BY tags.tag';
        $res = mysql_query($query) or log_db_error($query, '', false, true);
        $row = mysql_fetch_object($res);

        if ($row) {
            return $row->tag_string;
        } else {
            return '';
        }
    }

    public function searchNewEntries($searchStr)
    {
        $newEntries = [];

        if ($this->getType() == TYPE_KANJI) {
            preg_match_all('/[\\x{4E00}-\\x{9FA5}]/u', $searchStr, $matches, PREG_PATTERN_ORDER);

            $kanjis = $matches[0];
            if (!$kanjis || count($kanjis) == 0) {
                return $newEntries;
            }

            $query = 'SELECT k.kanji, k.njlpt, kx.kanji_id, kx.prons, kx.meaning_english, ls.set_id FROM kanjis k LEFT JOIN kanjis_ext kx ON kx.kanji_id = k.id LEFT JOIN learning_set_kanji ls ON ls.set_id = ' . $this->id . ' AND ls.kanji_id = k.id WHERE k.kanji IN (\'' . implode($kanjis,
                    "','") . "') ORDER BY k.njlpt DESC, k.strokes ASC";

            $res = mysql_query($query) or die(mysql_error());

            while ($row = mysql_fetch_object($res)) {
                $newEntries[$row->kanji_id] = $row;
            }
        } else {
            define('KB_VOCAB_SET', '# KB vocab');
            if (substr($searchStr, 0, strlen(KB_VOCAB_SET)) == KB_VOCAB_SET) {
                $ids = [];
                $lines = explode("\n", $searchStr);
                foreach ($lines as $line) {
                    if ($line[0] == '#')
                        continue;

                    $items = preg_split('/\s+/', $line);
                    $ids[] = (int) $items[0];
                }

                if (!count($ids))
                    $ids[] = 0;

                $query = 'SELECT jx.jmdict_id, j.word, j.njlpt, j.njlpt_r, j.reading, jx.gloss_english, j.katakana, j.usually_kana, ls.set_id FROM jmdict j LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id LEFT JOIN learning_set_vocab ls ON ls.set_id = ' . $this->id . ' AND ls.jmdict_id = j.id WHERE j.id IN (' . implode(',',
                        $ids) . ')';
            }
            elseif (substr($searchStr, 0, 6) == "JMDICT") {
                $words = [];

                $lines = explode("\n", $searchStr);
                foreach ($lines as $line) {
                    $items = explode("\t", $line);
                    if (count($items) == 1) {
                        $words[] = $line;
                    } else if (count($items) >= 3) {
                        preg_match_all('/[\\x{3040}-\\x{30FF}\\x{4E00}-\\x{9FA5}]+/u', $items[2], $matches,
                            PREG_PATTERN_ORDER);

                        if (count($matches[0]))
                            $words = array_merge($words, $matches[0]);
                    }
                }

                $words = array_unique($words);

                if (!$words || count($words) == 0)
                    return $newEntries;

                $query = 'SELECT jx.jmdict_id, j.word, j.njlpt, j.njlpt_r, j.reading, jx.gloss_english, j.katakana, j.usually_kana, ls.set_id FROM jmdict j LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id LEFT JOIN learning_set_vocab ls ON ls.set_id = ' . $this->id . ' AND ls.jmdict_id = j.id WHERE j.word IN (\'' . implode($words,
                        "','") . '\') ORDER BY j.njlpt DESC, j.njlpt_r DESC';
            }
            elseif (substr($searchStr, 0, 4) == "LIST") {

                $query = 'SELECT jx.jmdict_id, j.word, j.njlpt, j.njlpt_r, j.reading, jx.gloss_english, j.katakana, j.usually_kana, ls.set_id FROM jmdict j LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id LEFT JOIN learning_set_vocab ls ON ls.set_id = ' . $this->id . ' AND ls.jmdict_id = j.id WHERE 0';


                $lines = explode("\n", $searchStr);
                array_shift($lines);
                foreach ($lines as $line) {
                    $items = explode("\t", $line);
                    if (count($items) == 1) {
                        $query .= ' OR j.word = \'' . DB::getConnection()->quote($line) . "'";
                    } else if (count($items) == 2) {
                        preg_match_all('/[\\x{3040}-\\x{30FF}\\x{4E00}-\\x{9FA5}]+/u', $items[1], $matches,
                            PREG_PATTERN_ORDER);
                        $query .= ' OR (j.word = \'' . DB::getConnection()->quote($items[0]) . "'";

                        if (count($matches[0]) == 1)
                            $query .= ' AND j.reading = \'' . $matches[0][0] . '\'';
                        elseif (count($matches[0]) > 1)
                            $query .= ' AND j.reading IN (\'' . implode("','", $matches[0]) . '\')';
                        $query .= ')';
                    }
                }

                $query .= ' ORDER BY j.njlpt DESC, j.njlpt_r DESC';
            }
            elseif (extension_loaded('mecab') && mb_strlen($searchStr) > max(10,
                    4 * preg_match_all('/\s+/u', $searchStr, $matches))) {

                require_once ABS_PATH . 'libs/mecab_lib.php';

                //Removing .srt junk:
                $searchStr = preg_replace('/^\d+[\n\r]+\d\d:\d\d:\d\d,\d+ --> \d\d:\d\d:\d\d,\d+/m', '', $searchStr);
                $searchStr = preg_replace("/➡[\r\n]+/", '', $searchStr);

                $newEntryIDs = parse_jp_sentence($searchStr, false, false);

                if ($newEntryIDs) {
                    $query = "SELECT jx.jmdict_id, j.word, j.njlpt, j.njlpt_r, j.reading, jx.gloss_english, j.katakana, j.usually_kana, ls.set_id FROM jmdict j LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id LEFT JOIN learning_set_vocab ls ON ls.set_id = $this->id AND ls.jmdict_id = j.id WHERE j.id IN (" . implode(',',
                            $newEntryIDs) . ")";
                }
            } else {
                if (!extension_loaded('mecab')) {
                    echo '<div class="error_msg">Japanese parser could not be loaded.</div>';
                }

                preg_match_all('/[\\x{3040}-\\x{30FF}\\x{4E00}-\\x{9FA5}]+/u', $searchStr, $matches, PREG_PATTERN_ORDER);
                $words = $matches[0];

                if (!$words || count($words) == 0) {
                    return $newEntries;
                }

                $query = 'SELECT jx.jmdict_id, j.word, j.njlpt, j.njlpt_r, j.reading, jx.gloss_english, j.katakana, j.usually_kana, ls.set_id FROM jmdict j LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id LEFT JOIN learning_set_vocab ls ON ls.set_id = ' . $this->id . ' AND ls.jmdict_id = j.id WHERE j.word IN (\'' . implode($words,
                        "','") . '\') OR j.reading IN (\'' . implode($words, "','") . "') ORDER BY j.njlpt DESC, j.njlpt_r DESC";
            }

            if (@$query) {
                $res = mysql_query($query) or die(mysql_error());

                while ($row = mysql_fetch_object($res)) {
                    $newEntries[$row->jmdict_id] = $row;
                }
            }
        }

        return $newEntries;
    }

    public function markSetUpdated()
    {
        $query = 'UPDATE learning_sets SET date_modified = NOW() WHERE set_id = :setid';
        try {
            $stmt = DB::getConnection()->prepare($query);
            $stmt->bindValue(':setid', $this->id, PDO::PARAM_INT);
            $stmt->execute();
            $stmt = null;
        } catch (PDOException $e) {
            log_db_error($query, $e->getMessage(), true, true);
        }
    }

    public function getDateModified()
    {
        $query = 'SELECT date_modified FROM learning_sets WHERE set_id = :setid';
        try {
            $stmt = DB::getConnection()->prepare($query);
            $stmt->bindValue(':setid', $this->id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            log_db_error($query, $e->getMessage(), true, true);
        }
    }

    public function isSetTooBig()
    {
        $totEntries = count($this->getEntryData());
        return($totEntries >= MAX_VOCAB_ENTRIES);
    }

    function addToSet($arr)
    {
        if (!$this->canEdit()) {
            return 'This set is not publicly editable.';
        }

        if (!$arr || !count($arr)) {
            return 'Nothing to add...';
        }

        $tot_entries = count($this->getEntryData());

        if (count($arr) + $tot_entries >= MAX_VOCAB_ENTRIES) {
            $error_msg = 'A set cannot contain more than ' . MAX_VOCAB_ENTRIES . ' entries.';
            if ($tot_entries >= MAX_VOCAB_ENTRIES) {
                return ($error_msg . ' Please remove some entries before trying to add more.');
            }

            $arr = array_slice($arr, 0, MAX_VOCAB_ENTRIES - $tot_entries);

            $error_msg .= ' ' . (MAX_VOCAB_ENTRIES - $tot_entries) . 'entries were added.';
        } else {
            $error_msg = '';
        }

        $this->entryData = NULL;

        $query = 'INSERT IGNORE INTO learning_set_' . $this->getType() . ' (set_id, ' . $this->getSetEntryIndex() . ') VALUES ';
        foreach ($arr as $entry_id) {
            $query .= "($this->id, " . (int) $entry_id . "), ";
        }

        $query = substr($query, 0, -2);

        if (!mysql_query($query)) {
            return mysql_error();
        }

        $this->markSetUpdated();

        if ($error_msg) {
            return $error_msg;
        }
    }

    public function removeFromSet($id)
    {
        if (!$this->canEdit()) {
            return 'This set is not publicly editable.';
        }

        $this->entryData = null;

        DB::delete('DELETE FROM learning_set_' . $this->getType() . ' WHERE set_id = :setid AND ' . $this->getSetEntryIndex() . ' = :entryindex LIMIT 1',
            [
            ':setid' => $this->id,
            ':entryindex' => $id,
            ]
        );
        return '';
    }

    public function removeLevelFromSet($level)
    {
        if (!$this->canEdit()) {
            return 'This set is not publicly editable.';
        }

        $this->entryData = null;

        DB::delete('DELETE ls.* FROM learning_set_' . $this->getType() . ' ls LEFT JOIN ' . $this->getSetJoinTable() . ' t ON t.id = ls.' . $this->getSetEntryIndex() . ' WHERE ls.set_id = :setid AND t.njlpt = :level',
            [
            ':setid' => $this->id,
            ':level' => $level,
            ]
        );

        $this->markSetUpdated();
    }

    public function removeOtherSetFromSet($setID)
    {
        if (!$this->canEdit()) {
            return 'This set is not publicly editable.';
        }

        $this->entryData = null;

        DB::delete('DELETE ls.* FROM learning_set_' . $this->getType() . ' ls LEFT JOIN learning_set_' . $this->getType() . ' ls2 ON ls.' . $this->getSetEntryIndex() . ' = ls2.' . $this->getSetEntryIndex() . ' WHERE ls.set_id = :id AND ls2.set_id = :setid AND ls2.set_id IS NOT NULL',
            [
            ':id' => $this->id,
            ':setid' => $setID,
            ]
        );

        $this->markSetUpdated();
    }

    public function subscribeToSet()
    {
        DB::insert('INSERT IGNORE INTO learning_set_subs SET user_id = :userid, set_id = :setid',
            [
            ':setid' => $this->id,
            ':userid' => $_SESSION['user']->getID()
            ]
        );

        return '';
    }

    private function loadEntryData()
    {
        if ($this->entryData) {
            return;
        }

        if ($this->getType() == TYPE_KANJI) {
            $res = mysql_query("SELECT kx.kanji_id, kx.kanji_id AS id, k.kanji, kx.prons, kx.meaning_english, k.njlpt FROM learning_set_kanji ls LEFT JOIN kanjis k ON k.id = ls.kanji_id LEFT JOIN kanjis_ext kx ON kx.kanji_id = ls.kanji_id WHERE ls.set_id = $this->id ORDER BY k.njlpt DESC, k.strokes ASC");
        } else {
            $res = mysql_query("SELECT jx.jmdict_id, jx.jmdict_id AS id, j.word, j.njlpt, j.njlpt_r, j.reading, jx.gloss_english, j.usually_kana, j.katakana, ls.set_id FROM learning_set_vocab ls LEFT JOIN jmdict j ON j.id = ls.jmdict_id LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id WHERE ls.set_id = $this->id ORDER BY j.njlpt DESC, j.njlpt_r DESC");
        }

        if (!$res) {
            die(mysql_error());
        }

        $this->entryData = [];
        while ($row = mysql_fetch_object($res)) {
            $this->entryData[] = $row;
        }
    }

    public function getEntryData()
    {
        if ($this->entryData == null) {
            $this->loadEntryData();
        }

        return $this->entryData;
    }

    function getFormattedList()
    {
        $ret = '<h3>Set content:</h3>';
        if ($this->entryData == null) {
            $this->loadEntryData();
        }

        if (!count($this->entryData)) {
            $ret .= '<em>Empty set</em>';
        } else {
            $ret .= '<div>(' . count($this->entryData) . ' entries)';

            if ($this->canEdit()) {
                $ret .= ' <a href="#" onclick="$(this).hide(); $(\'#set-bulk-remove\').show(); return false;">| Bulk remove &raquo;</a>';
                $ret .= '<div id="set-bulk-remove" style="display: none;">';
                $ret .= '<p>Remove all entries at level: <select onchange="if(this.value != \'\')  bulk_remove_level_from_set(' . $this->id . ', this.value);">';
                $ret .= '<option value="">-</option>';

                for ($i = 5; $i >= 0; $i--) {
                    $ret .= '<option value="' . $i . '">N' . $i . '</option>';
                }
                $ret .= "</select></p>\n";
                $query = 'SELECT ls.*, subs.set_id AS sub FROM learning_sets ls LEFT JOIN learning_set_subs subs ON subs.set_id = ls.set_id AND subs.user_id = ' . $_SESSION['user']->getID() . ' WHERE ls.deleted = 0 AND (ls.user_id = ' . $_SESSION['user']->getID() . " OR subs.set_id IS NOT NULL) AND set_type = '" . $this->getType() . "' AND ls.set_id != " . $this->id . " ORDER BY date_modified";
                $res = mysql_query($query) or log_db_error($query, '', false, true);

                $ret .= '<p>Remove all entries also in set: <select onchange="if(this.value != \'\') bulk_remove_other_set_from_set(' . $this->id . ', this.value);">';
                $ret .= '<option value="">-</option>';
                while ($row = mysql_fetch_object($res)) {
                    $ret .= '<option value="' . $row->id . '">' . ($row->sub ? '' : '• ') . $row->name . '</option>';
                }
                $ret .= "</select></p>\n";
                $ret .= '</div>';
            }

            $ret .= ' </div>';
            foreach ($this->entryData as $row) {
                $ret .= "<div class=\"set_content_line\">";

                if ($this->canEdit()) {
                    $ret .= "<button class=\"remove_entry\" onclick=\"remove_entry_from_set('" . SERVER_URL . "ajax/edit_learning_set/', $this->id, $row->id, \$(this).parent());\">×</button> ";
                }

                if ($this->getType() == TYPE_KANJI) {
                    $ret .= '<span class="njlpt">' . LearningSet::$jlpt2Char[$row->njlpt] . "</span> <span class=\"kanji\">$row->kanji</span> • <span class=\"prons\">$row->prons</span> • <span class=\"english\">$row->meaning_english</span></div>\n";
                } else {
                    $ret .= '<span class="njlpt">' . LearningSet::$jlpt2Char[$row->njlpt] . "</span> <span class=\"japanese\">" . ($row->usually_kana ? $row->reading : $row->word . ($row->word != $row->reading && !$row->katakana ? " 【" . $row->reading . "】" : '')) . "</span> • <span class=\"english\">$row->gloss_english</span></div>\n";
                }
            }
        }


        return $ret;
    }

    public function getExport()
    {

        if ($this->getType() == TYPE_KANJI) {
            $ret = "# KB kanji\n";
            $res = mysql_query("SELECT kx.kanji_id, kx.kanji_id AS id, k.kanji, kx.prons, kx.meaning_english, k.njlpt FROM learning_set_kanji ls LEFT JOIN kanjis k ON k.id = ls.kanji_id LEFT JOIN kanjis_ext kx ON kx.kanji_id = ls.kanji_id WHERE ls.set_id = $this->id ORDER BY k.njlpt DESC, k.strokes ASC");
        } else {
            $ret = "# KB vocab\n";
            $res = mysql_query("SELECT jx.jmdict_id, jx.jmdict_id AS id, j.word, j.njlpt, j.njlpt_r, j.reading, jx.gloss_english, j.usually_kana, j.katakana, ls.set_id FROM learning_set_vocab ls LEFT JOIN jmdict j ON j.id = ls.jmdict_id LEFT JOIN jmdict_ext jx ON jx.jmdict_id = j.id WHERE ls.set_id = $this->id ORDER BY j.njlpt DESC, j.njlpt_r DESC");
        }

        $ret .= "# KanjiBox Set export downloaded from: " . SERVER_URL . 'sets/' . $this->id . "/\n#\n";
        $ret .= "# " . $this->getName() . "\n# " . $this->getDescription() . "\n#\n";
        if (!$res) {
            return mysql_error();
        }
        if (!mysql_num_rows($res)) {
            $ret .= "Empty set";
        } else {
            if ($this->getType() == TYPE_KANJI) {
                while ($row = mysql_fetch_object($res)) {
                    $ret .= "$row->id\t$row->kanji\t$row->prons\n";
                }
            } else {
                while ($row = mysql_fetch_object($res)) {
                    $ret .= "$row->id\t$row->word\t$row->reading\n";
                }
            }
        }
        $ret .= "#\n# KanjiBox Set export downloaded from: " . SERVER_URL . 'sets/' . $this->id . "/\n";

        return $ret;
    }

    public function setTag($tag_id, $val)
    {
        if (!$this->canEdit()) {
            return 'Set not editable';
        }

        if (!$tag_id) {
            return 'invalid tag ID';
        }

        if (!empty($val)) {
            DB::insert('INSERT INTO learning_set_tags SET set_id = :setid, tag_id = :tagid',
                [':setid' => $this->id, ':tagid' => $tag_id]);
        } else {
            DB::delete('DELETE FROM learning_set_tags WHERE set_id = :setid AND tag_id = :tagid LIMIT 1',
                [
                ':setid' => $this->id,
                ':tagid' => $tag_id
                ]
            );
        }
    }

    public function showTagCheckboxes()
    {
        $query = 'SELECT tags.tag, tags.tag_id, IF(lst.set_id, 1, 0) AS checked FROM tags LEFT JOIN learning_set_tags lst ON tags.tag_id = lst.tag_id AND lst.set_id = ' . $this->id . ' ORDER BY tags.tag';
        $res = mysql_query($query) or print_r(mysql_error());

        $str = '';

        while ($row = mysql_fetch_object($res)) {
            if (!$this->canEdit() && !$row->checked) {
                continue;
            }

            $str .= '<span class="tag_box" id="tag_' . $row->tag_id . '">';
            if ($this->canEdit()) {
                $str .= '<input type="checkbox" ' . ($row->checked ? 'checked' : '') . ' id="check_tag_' . $row->tag_id . '" onclick="update_tag(' . $this->id . ', ' . $row->tag_id . ', this.checked)"></input>&nbsp;';
            }

            $str .= '<label for="check_tag_' . $row->tag_id . '">' . $row->tag . '</label></span>';
        }

        return $str;
    }

    public function getOwnerInfo()
    {
        $res = mysql_query('SELECT * FROM users u LEFT JOIN users_ext ux ON ux.user_id = u.id WHERE u.id = ' . $this->data->user_id);
        return mysql_fetch_object($res);
    }

    public static function getAllTagCheckboxes()
    {
        $query = 'SELECT tags.tag, tags.tag_id FROM tags ORDER BY tags.tag';
        $str = '';
        try {
            $stmt = DB::getConnection()->prepare($query);
            $stmt->execute();
            while ($row = $stmt->fetchObject()) {
                $str .= '<span class="tag_box">';
                $str .= '<input type="checkbox" id="tags[' . $row->tag_id . ']" name="tags[' . $row->tag_id . ']" value="' . $row->tag_id . '"></input> ';
                $str .= '<label for="tags[' . $row->tag_id . ']">' . $row->tag . '</label></span>';
            }
            $stmt = null;
        } catch (PDOException $e) {
            log_db_error($query, $e->getMessage(), false, true);
        }
        return $str;
    }

    public function getAuthorName()
    {
        $info = $this->getOwnerInfo();
        $set_author = '';
        if ($info && ($info->first_name || $info->last_name)) {
            $set_author = $info->first_name . ' ';
            if ($arr = explode(' ', $info->last_name)) {
                foreach ($arr as $i => $word) {
                    $set_author .= mb_substr($word, 0, 1, 'UTF-8') . '.' . ($i == count($arr) - 1 ? '' : ' ');
                }
            }
        } elseif ($this->data->user_id < 0) {
            $set_author = 'Public Domain';
        } else {
            $set_author = '#' . $this->data->user_id;
        }
        return $set_author;
    }

    public static function getAllTags($has_public_sets = false)
    {
        global $dbh;
        $query = 'SELECT tags.tag_id, tags.tag FROM tags ';
        if ($has_public_sets) {
            $query .= ' JOIN learning_set_tags lst ON lst.tag_id = tags.tag_id JOIN learning_sets ls ON ls.set_id = lst.set_id AND ls.public = 1 AND deleted = 0 GROUP BY tags.tag_id';
        }
        $query .= ' ORDER BY tags.tag';

        $tags = $dbh->query($query)->fetchAll(PDO::FETCH_KEY_PAIR);

        return $tags;
    }

    public function getSubsCount()
    {
        return DB::count('SELECT COUNT(*) FROM learning_set_subs WHERE set_id = :setid', [':setid' => $this->id]);
    }

    function deleteSet()
    {
        if (!$this->canAdmin()) {
            return 'Not allowed to delete this set';
        }

        $this->entryData = null;

        try {
            DB::getConnection()->beginTransaction();
            DB::delete('DELETE FROM learning_set_' . $this->getType() . ' WHERE set_id = :setid',
                [':setid' => $this->id]);
            DB::delete('DELETE FROM learning_set_subs WHERE set_id = :setid', [':setid' => $this->id]);
            DB::update('UPDATE learning_sets SET deleted = 1 WHERE set_id = :setid LIMIT 1', [':setid' => $this->id]);
            DB::getConnection()->commit();
        } catch (PDOException $e) {
            DB::getConnection()->rollBack();
            log_error($e->getMessage(), false, true);
        }

        $this->valid = false;
    }

    public function makePublicDomain()
    {
        if (!$this->canAdmin()) {
            return 'Not allowed to modify this set';
        }
        if (!$this->isPublic()) {
            return 'Set must be public';
        }

        mysql_query('UPDATE learning_sets SET user_id = -1, editable = 1, public = 1 WHERE set_id = ' . $this->id . ' LIMIT 1') or die(mysql_error());

        $this->data->user_id = -1;
        $this->data->editable = 1;

        $this->subscribeToSet();
    }
}
