<?php

class User
{

    private $data = null;
    private $prefs = null;
    private $loggedIn;
    private $loadCount = 0;
    public static $ranksAbs = [1 => ['shougun', 'Shōgun'], 5 => ['daimyou', 'Daimyō'], 30 => ['samurai', 'Samurai'], 100 => ['ninja', 'Ninja']];
    public static $ranksRel = [['rikishi', 'Rikishi', 0.3], ['tanuki', 'Tanuki', 0.5], ['kappa', 'Kappa', 0.7]];
    public static $defaultRank = ['gokiburi', 'Gokiburi'];
    public static $prefLabels = [
        'general' => [
            'auto_vanish' => 'Hide answers automatically after a few seconds <strong><a href="http://kanjibox.net/kb/page/faq/#corrections">*</a></strong><br/>',
            'shortcuts' => 'Enable Hotkeys  (press keyboard key \'1\' to \'5\' to choose an answer) &mdash; <em>Currently only available to <a href="http://kanjibox.net/kb/page/faq/#elite">Elite</a> users</em><br/>',
            'hide_rare_kanji' => 'Hide rare kanji spellings'
        ],
        'drill' => [
            'show_english' => 'Show kanji meaning', 'show_reading_translation' => 'Show translation (Reading drill)',
            'show_examples' => 'Show examples', 'show_learning_stats' => 'Show learning stats',
            'show_reading' => ['legend' => 'Show reading: ', 'choices' => ['always' => 'Always', 'above_level' => 'If above level', 'never' => 'Never']]
        ],
        'quiz' => ['show_prog_bar' => 'Show Progress Bar'],
        'notif' => ['post_news' => 'Post highscore updates in news feed'],
        'lang' => [
            'kanji_lang' => ['legend' => 'Kanji meanings: ', 'choices' => ['en' => 'English', 'de' => 'Deutsch', 'fi' => 'Suomi', 'fr' => 'Français', 'it' => 'Italiano', 'pl' => 'Polski', 'ru' => 'Русский', 'sp' => 'Español', 'sv' => 'Svenska', 'tr' => 'Türkçe', 'th' => 'ภาษาไทย']],
            'vocab_lang' => ['legend' => 'Vocab definitions: ', 'choices' => ['en' => 'English', 'de' => 'Deutsch', 'fi' => 'Suomi', 'fr' => 'Français', 'it' => 'Italiano', 'pl' => 'Polski', 'ru' => 'Русский', 'sp' => 'Español', 'sv' => 'Svenska', 'tr' => 'Türkçe', 'th' => 'ภาษาไทย']],
            'translator_mode' => 'Translator mode &mdash; <em>Only show entries that need translating</em>'
        ]
    ];
    public static $defaultPrefs = ['general' => ['auto_vanish' => true, 'shortcuts' => true, 'hide_rare_kanji' => true], 'drill' => ['show_english' => true, 'show_pron' => true, 'show_examples' => true, 'show_learning_stats' => true, 'show_reading' => 'above_level', 'show_reading_translation' => true], 'quiz' => ['show_prog_bar' => true], 'notif' => ['profile_box' => true, 'post_news' => true], 'lang' => ['kanji_lang' => 'en', 'vocab_lang' => 'en', 'translator_mode' => false]];

    public function __construct($idParams, $loggedIn = false, $info = null)
    {
        if (empty($idParams)) {
            log_error('Can\'t instantiate User class without id_params', true, true);
        }

        $this->loggedIn = $loggedIn;
        $this->friends = false;

        if (is_array($idParams)) {
            $whereID = implode(' = ? AND ', array_keys($idParams)) . ' = ?';
        } elseif (is_int($idParams)) {
            $whereID = 'u.id = ' . (int) $idParams;
        } else {
            log_error("User::_construct(): Invalid id_param: '$idParams'", true, true);
        }

        $query = 'SELECT * FROM `users` u LEFT JOIN users_ext ux ON ux.user_id = u.id WHERE ' . $whereID;
        try {
            $stmt = DB::getConnection()->prepare($query);
            $stmt->execute(array_values($idParams));
            $this->data = $stmt->fetchObject();
        } catch (PDOException $e) {
            log_db_error($query, $e->getMessage(), false, true);
        }

        if (!empty($this->data)) {
            $query = 'UPDATE `users` SET `last_played` = NOW(), games_played = games_played + 1, `active` = :active WHERE `id` = :id';
            try {
                $stmt = DB::getConnection()->prepare($query);
                $stmt->bindValue(':active', ($loggedIn ? '1' : '0'));
                $stmt->bindValue(':id', $this->getID());
                $stmt->execute();
            } catch (PDOException $e) {
                log_db_error($query, $e->getMessage(), false, true);
            }

            // missing users_ext record
            if (!$this->data->user_id) {
                $query = 'INSERT INTO `users_ext` SET `user_id` = :id';
                try {
                    $stmt = DB::getConnection()->prepare($query);
                    $stmt->bindValue(':id', $this->getID(), PDO::PARAM_INT);
                    $stmt->execute();
                } catch (PDOException $e) {
                    log_db_error($query, $e->getMessage(), false, true);
                }
            }
        } elseif ($idParams['fb_id']) {
            $this->data = $this->create_account($idParams['fb_id'], $loggedIn);
        } else {
            die('incorrect login/pwd');
        }

        if (empty($this->data->inf_session_key) && !empty($_POST['fb_sig_session_key']) && ($_POST['fb_sig_expires'] == 0)) {
            $this->data->inf_session_key = $_POST['fb_sig_session_key'];
            $query = 'UPDATE `users_ext` SET `inf_session_key` = :sessionid WHERE `user_id` = :userid';
            try {
                $stmt = DB::getConnection()->prepare($query);
                $stmt->bindValue(':sessionid', $this->data->inf_session_key, PDO::PARAM_STR);
                $stmt->bindValue(':userid', $this->getID(), PDO::PARAM_INT);
                $stmt->execute();
            } catch (PDOException $e) {
                log_db_error($query, $e->getMessage());
            }
        }

        if ($info && isset($info['first_name']) && isset($info['last_name'])) {
            $nameHidden = (empty($info['first_name']) && empty($info['last_name']));

            if ($nameHidden != $this->isNameHidden()) {
                $this->data->name_hidden = $nameHidden;
                $query = 'UPDATE `users` SET  `name_hidden` = :name_hidden WHERE id = :userid';
                try {
                    $stmt = DB::getConnection()->prepare($query);
                    $stmt->bindValue(':name_hidden', $nameHidden, PDO::PARAM_INT);
                    $stmt->bindValue(':userid', $this->getID(), PDO::PARAM_INT);
                    $stmt->execute();
                } catch (PDOException $e) {
                    log_db_error($query, $e->getMessage());
                }
            }

            if ($info['first_name'] != $this->data->first_name || $info['last_name'] != $this->data->last_name) {
                if (!empty($info['first_name'])) {
                    $this->data->first_name = $info['first_name'];
                }
                if (!empty($info['last_name'])) {
                    $this->data->last_name = $info['last_name'];
                }

                $query = 'UPDATE `users_ext` SET `first_name` = :firstname, `last_name` = :lastname WHERE user_id = :userid';
                try {
                    $stmt = DB::getConnection()->prepare($query);
                    $stmt->bindValue(':firstname', $this->data->first_name, PDO::PARAM_STR);
                    $stmt->bindValue(':lastname', $this->data->last_name, PDO::PARAM_STR);
                    $stmt->bindValue(':userid', $this->getID(), PDO::PARAM_INT);
                    $stmt->execute();
                } catch (PDOException $e) {
                    log_db_error($query, $e->getMessage());
                }
            }
        }

        if (empty($this->data->login_email) && !empty($info['email']) && strpos($info['email'],
                '@proxymail.facebook.com') === false) {
            $email = $info['email'];

            $rowCount = DB::count('SELECT COUNT(*) FROM `users_ext` WHERE `login_email` = ? AND `user_id` != ?',
                    [$email, $this->getID()]);
            if ($rowCount == 0) {
                $this->data->login_email = $email;

                $query = 'UPDATE `users_ext` SET `login_email` = :email WHERE user_id = :userid';
                try {
                    $stmt = DB::getConnection()->prepare($query);
                    $stmt->bindValue(':email', $info['email'], PDO::PARAM_STR);
                    $stmt->bindValue(':userid', $this->getID(), PDO::PARAM_INT);
                    $stmt->execute();
                } catch (PDOException $e) {
                    log_db_error($query, $e->getMessage());
                }
            }
        }

        if (!empty($this->data->prefs)) {
            $this->prefs = unserialize($this->data->prefs);
        }
        if (!is_array($this->prefs)) {
            $this->prefs = [];
        }

        $curLevel = $this->getLevel();
        if ($curLevel == LEVEL_1 || $curLevel == LEVEL_2 || $curLevel == LEVEL_3 || $curLevel == LEVEL_J1 || $curLevel == LEVEL_J2 || $curLevel == LEVEL_J3 || $curLevel == LEVEL_J4) {
            $this->data->level = $this->getNJLPTLevel();
        }

        if ($this->data->purged) {
            $this->unarchiveDbrecords();
        }

        $this->loggedIn = true;
    }

    public static function createAccount($fbID, $loggedIn)
    {
        if (!(int) $fbID) {
            log_error("User::create_account: invalid fb_id: '$fbID'");
        }

        $query = 'INSERT INTO `users` SET `fb_id` = \'' . ((int) $fbID) . '\', `date_joined` = NOW(), `last_played` = NOW(), active = \'' . (int) $loggedIn . '\', level=\'' . LEVEL_N3 . '\'';
        mysql_query_debug($query) or log_db_error($query, false, true);
        $userID = mysql_insert_id();

        $query = 'SELECT * FROM `users` u LEFT JOIN users_ext ux ON ux.user_id = u.id  WHERE u.id = \'' . (int) $userID . '\'';
        $rec = mysql_query_debug($query) or log_db_error($query, false, true);
        if ($newUser = mysql_fetch_object($rec)) {
            $query = 'INSERT INTO `users_ext` SET `user_id` = ' . ((int) $newUser->id);
            mysql_query_debug($query) or log_db_error($query, false, true);
            return $newUser;
        } else {
            log_error('User::create_account creation failed', true, true);
        }
    }

    public function isAdministrator()
    {
        return ($this->getID() == '1');
    }

    public function isEditor()
    {
        return ($this->isAdministrator() || $this->data->privileges > 1 );
    }

    public function getPreference($pref, $subPref = null)
    {
        if ($subPref) {
            if (isset($this->prefs[$pref][$subPref])) {
                return $this->prefs[$pref][$subPref];
            } elseif (isset(User::$defaultPrefs[$pref][$subPref])) {
                return User::$defaultPrefs[$pref][$subPref];
            } else {
                log_error('No default for pref: ' . $pref . ' - ' . $subPref, false, false);
                return null;
            }
        } elseif (isset($this->prefs[$pref])) {
            return $this->prefs[$pref];
        } else {
            return User::$defaultPrefs[$pref];
        }
    }

    public function updateLevel($newLevel)
    {
        $query = 'UPDATE `users` SET `level` = :level WHERE `id` = :id';
        try {
            $stmt = DB::getConnection()->prepare($query);
            $stmt->bindValue(':level', $newLevel, PDO::PARAM_INT);
            $stmt->bindValue(':id', $this->getID(), PDO::PARAM_INT);
            $stmt->execute();
        } catch (PDOException $e) {
            log_db_error($query, true, true);
        }
    }

    public function updatePreferences($newPrefs)
    {
        foreach (User::$prefLabels as $key => $val) {
            foreach ($val as $key2 => $val2) {
                if (isset($newPrefs['prefs'][$key][$key2])) {
                    $this->prefs[$key][$key2] = $newPrefs['prefs'][$key][$key2];
                } else {
                    $this->prefs[$key][$key2] = false;
                }
            }
        }

        if (isset($newPrefs['level'])) {
            $this->data->level = $newPrefs['level'];
            $_SESSION['cur_session'] = null;
        }

        $this->savePreferences();
        $this->cacheHighscores();
    }

    public function savePreferences()
    {
        /** mysql_query_debug formerly used: check if slow queries can be monitored by DB * */
        // Update level and status
        $query = 'UPDATE `users` SET `level` = :level, `active` = :active WHERE `id` = :id';
        try {
            $stmt = DB::getConnection()->prepare($query);
            $stmt->bindValue(':level', $this->getLevel(), PDO::PARAM_INT);
            $stmt->bindValue(':active', (int) ($this->isLoggedIn()), PDO::PARAM_INT);
            $stmt->bindValue(':id', $this->getID(), PDO::PARAM_INT);
            $stmt->execute();
        } catch (PDOException $e) {
            log_db_error($query, true, true);
        }

        // Update preferences
        $query = 'UPDATE `users_ext` SET `prefs` = :preferences WHERE `user_id` = :id';
        try {
            $stmt = DB::getConnection()->prepare($query);
            $stmt->bindValue(':preferences', serialize($this->prefs), PDO::PARAM_STR);
            $stmt->bindValue(':id', $this->getID(), PDO::PARAM_INT);
            $stmt->execute();
        } catch (PDOException $e) {
            log_db_error($query, true, true);
        }
    }

    public function updateProfileBox($forceUpdate = false)
    {
        if (!$this->loggedIn) {
            return false;
        }

        if ($this->getFbID() <= 0) {
            return true;
        }

        fb_connect_init();

        $fbID = $this->getFbID();

        if (!$this->getPreference('notif', 'profile_box')) {
            if ($forceUpdate) {
                try {
                    // $facebook->api_client->profile_setFBML(NULL, $fb_id, ' ', '', '');
                } catch (Exception $e) {
                    // oh well...
                    log_exception($e, "update_profile_box() - Can't reset profile to blank.");
                }
            }
            return;
        }

        require_once ABS_PATH . 'libs/stats_lib.php';
        $levels = Session::$levelNames;

        $text = '<fb:ref handle="global_announcement" />';
        $text .= '<fb:ref handle="profile_css" />';
        $text .= "<p class=\"summary\"><fb:name firstnameonly=\"true\" uid=\"$fbID\" useyou=\"false\" capitalize=\"true\" /> is training at level: <strong>" . $levels[$this->getLevel()] . "</strong> on <a href=\"" . get_page_url() . "\">Kanji Box</a>.</p>";

        $query = 'SELECT SUM(c) as c FROM ((SELECT COUNT(*) as c FROM learning l WHERE l.user_id = ' . (int) $this->getID() . ' LIMIT 1) UNION (SELECT COUNT(*) as c FROM jmdict_learning jl WHERE jl.user_id = ' . (int) $this->getID() . ' LIMIT 1) UNION (SELECT COUNT(*) as c FROM reading_learning rl WHERE rl.user_id = ' . (int) $this->getID() . ' LIMIT 1)) as t';

        $res = mysql_query_debug($query) or log_db_error($query, true, true);
        $row = mysql_fetch_object($res);

        if ($row->c > 0) {
            foreach (['kanji' => 'Kanji', 'vocab' => 'Vocabulary', 'reading' => 'Reading'] as $type => $typeDesc) {
                $text .= '<fieldset class="profile-box"><legend><a href="' . get_page_url(PAGE_PLAY,
                        ['type' => $type, 'mode' => QUIZ_MODE]) . '">' . $typeDesc . '</a></legend>';

                $game = $this->getHighscore($this->getLevel(), $type);
                if ($game) {
                    $rank = $this->getRank($type);
                    $text .= '<div class="game">';
                    $text .= '<img class="rank-icon" src="' . SERVER_URL . 'img/ranks/rank_' . $rank->short_name . '.png" />';
                    $text .= '<div class="ranking">Rank: <strong>' . $rank->pretty_name . '</strong></div>';
                    $text .= '<div class="highscore">Highscore: <strong>' . $game->score . ' Pts</strong></div>';
                    $text .= '<div style="clear:both;" ></div>';
                    $text .= '</div>';
                }

                $jlptLevel = $this->getNJLPTLevel();

                $wideBar = 340;
                $narrowBar = 145;
                switch ($type) {
                    case 'kanji':
                        if ($this->getLevel() == $jlptLevel) {
                            $big = printJLPTLevels($this->getID(), $jlptLevel, $wideBar,
                                'Learning stats - ' . $jlptLevel);
                            $small = printJLPTLevels($this->getID(), $jlptLevel, $narrowBar,
                                'Learning stats - ' . $jlptLevel);
                        } else {
                            $num = (int) Question::levelToGrade($this->getLevel());
                            if ($num > 0) {
                                $big = printGradeLevels($this->getID(), $num, $wideBar, 'Learning stats - Grade ' . $num);
                                $small = printGradeLevels($this->getID(), $num, $narrowBar,
                                    'Learning stats - Grade ' . $num);
                            } else {
                                $big = printJLPTLevels($this->getID(), 1, $wideBar, 'Learning stats - 1-kyuu');
                                $small = printJLPTLevels($this->getID(), 1, $narrowBar, 'Learning stats - 1-kyuu');
                            }
                        }
                        break;

                    case 'vocab':
                        $num = Question::levelToGrade($jlptLevel);
                        $num = $num[1];
                        $big = print_vocab_jlpt_levels($this->getID(), $num, $wideBar,
                            'Learning stats - ' . $num . '-kyuu');
                        $small = print_vocab_jlpt_levels($this->getID(), $num, $narrowBar,
                            'Learning stats - ' . $num . '-kyuu');

                        break;

                    case 'reading':
                        $num = Question::levelToGrade($jlptLevel);
                        $num = $num[1];
                        $big = printReadingJLPTLevels($this->getID(), $num, $wideBar,
                            'Learning stats - ' . $num . '-kyuu');
                        $small = printReadingJLPTLevels($this->getID(), $num, $narrowBar,
                            'Learning stats - ' . $num . '-kyuu');
                        break;
                }
                $text .= '<fb:wide>' . $big . '</fb:wide><fb:narrow>' . $small . '</fb:narrow>';
                $text .= '</fieldset>';
            }
        } else {
            $text .= "<p class=\"details\"><fb:pronoun uid=\"$fbID\" useyou=\"false\" capitalize=\"true\" /> hasn't logged any scores or statistics yet.</p>";
        }

        try {
            // $facebook->api_client->profile_setFBML($text, $fb_id, $text, '', '');
        } catch (Exception $e) {
            //	$this->set_pref('notif', 'profile_box', false);

            try {
                $fields = ['last_name', 'first_name', 'is_app_user', 'sex', 'pic', 'profile_url'];
            } catch (Exception $e) {
                log_exception($e,
                    "update_profile_box() - Can't update profile and can't get info." . "\nFBML:\n" . $text);
            }
        }
    }

    public function setPreference($key, $key2, $newValue)
    {
        $this->prefs[$key][$key2] = $newValue;
        $this->savePreferences();
    }

    public function publishStory($type)
    {
        $rank = $this->getRank($type, true);
        if (!$rank) {
            die('Can\'t get rank...');
        }
        return $this->publishRankStory($type, $rank, false);
    }

    public function publishRankStory($type, $rank, $justNow = true)
    {
        global $facebook;

        if ($this->getFbID() <= 0) {
            return 'Not using Facebook';
        }

        if (!fb_connect_init()) {
            return 'Can\'t init Facebook';
        }

        if ($type != TYPE_KANJI && $type != TYPE_VOCAB && $type != TYPE_READING && $type != TYPE_TEXT) {
            die('unknown type');
        }

        $levels = Session::$levelNames;

        if ($this->data->first_name) {
            $description = $this->data->first_name . ($justNow ? ' just' : '') . ' reached the glorious rank of ' . $rank->pretty_name . ' (' . $levels[$this->getLevel()] . ' ' . ucfirst($type) . ' division) in KanjiBox!';
        } else {
            $description = 'I' . ($justNow ? ' just' : '') . ' reached the glorious rank of ' . $rank->pretty_name . ' (' . $levels[$this->getLevel()] . ' ' . ucfirst($type) . ' division) in KanjiBox!';
        }

        try {
            if ($facebook->api('/me/feed', 'post',
                    ['picture' => SERVER_URL . 'img/ranks/rank_' . $rank->short_name . '.png', 'name' => 'KanjiBox', 'link' => get_page_url(PAGE_PLAY,
                        ['type' => $type, 'mode' => QUIZ_MODE]), 'caption' => '', 'description' => $description, 'actions' => [['name' => 'Play', 'link' => get_page_url()]]])) {
                $str = "<div class=\"success_msg\">Posted story on Facebook</div>";

                return $str;
            }
        } catch (FacebookApiException $e) {
            return '<div class="error_msg">Facebook error. Make sure KanjiBox is allowed to post on your Facebook feed.</div>';
        }
    }

    public function getHighscore($level, $type)
    {
        $query = 'SELECT * FROM `games` g WHERE `user_id` = :id AND `level` = :level AND `type` = :type ORDER BY `score` DESC, TIMEDIFF(g.date_ended, g.date_started) ASC LIMIT 1';
        try {
            $stmt = DB::getConnection()->prepare($query);
            $stmt->bindValue(':id', $this->getID());
            $stmt->bindValue(':level', $level);
            $stmt->bindValue(':type', $type);
            $stmt->execute();

            return $stmt->fetchObject();
        } catch (PDOException $e) {
            log_db_error($query, true, true);

            return null;
        }
    }

    public function isHighscore($scoreID, $level, $type)
    {
        $score = $this->getHighscore($level, $type);

        return (!$score || ($score->id == $scoreID));
    }

    public function resetHighscores($level = '', $type = '')
    {
        $query = 'FROM `games` WHERE `user_id` = \'' . $this->getID() . '\'';
        if ($level) {
            $query .= ' AND `level` = \'' . DB::getConnection()->quote($level) . '\' ';
        }
        if ($type) {
            $query .= ' AND `type` = \'' . DB::getConnection()->quote($type) . '\'';
        }

        $query = 'SELECT COUNT(*) as c ' . $query;
        $res = mysql_query_debug($query) or log_db_error($query, false, true);
        $row = mysql_fetch_object($res);
        display_user_msg('Deleting ' . $row->c . ' records');

        $query = 'DELETE ' . $query;
        mysql_query_debug($query) or log_db_error($query, false, true);
    }

    public function getFriendsranking($score, $level, $type)
    {
        if ($this->getFbID() <= 0) {
            return 0;
        }

        fb_connect_init();

        $friends = $this->getFriends();
        $friendsID = implode($friends, ',');

        $query = "SELECT COUNT(u.id) as c FROM `users` u INNER JOIN `games` g ON g.`user_id` = u.id LEFT JOIN games g2 ON g.user_id = g2.user_id AND (g.score < g2.score OR (g.score = g2.score AND g.date_ended > g2.date_ended))  WHERE g.`level` = '$level'  AND g2.level = '$level' AND g2.score IS NULL AND g.score > $score AND g.type = '$type' AND u.fb_id IN ($friendsID) ORDER BY score DESC, duration ASC";
        $res = mysql_query_debug($query) or log_db_error($query);
        $row = mysql_fetch_object($res);
        return $row->c + 1;
    }

    public function getRank($type, $noRefresh = false, $expiredTime = 3600)
    {
        require_once ABS_PATH . 'libs/stats_lib.php';

        // *** Function? as used twice...
        $query = 'SELECT r.rank, r.type, r.level, TIMESTAMPDIFF(SECOND, r.last_updated, NOW()) AS age, r.last_updated FROM ranking r LEFT JOIN games g ON g.id = r.game_id WHERE r.user_id = :id AND r.level = :level AND r.type = :type';
        try {
            $stmt = DB::getConnection()->prepare($query);
            $stmt->bindValue(':id', $this->getID());
            $stmt->bindValue(':level', $this->getLevel());
            $stmt->bindValue(':type', $type);
            $stmt->execute();

            $rank = $stmt->fetchObject();
        } catch (PDOException $e) {
            log_db_error($query, $e->getMessage(), true, true);
        }

        if (!$rank || $rank->age > $expiredTime) {
            if ($noRefresh) {
                if (!$rank) {
                    $query = 'SELECT COUNT(*) AS rank, this_g.id as game_id, NOW() as last_updated, 0 as age, this_u.level as level, \'' . $type . '\' as type FROM
					users this_u LEFT JOIN games this_g ON this_u.' . $type . '_highscore_id = this_g.id
					LEFT JOIN users u ON u.level = this_u.level
					JOIN games g ON u.' . $type . '_highscore_id = g.id
					WHERE this_u.id = :userid AND (this_g.score IS NULL OR g.score > this_g.score OR (g.score = this_g.score && TIMEDIFF(g.date_ended, g.date_started) < TIMEDIFF(this_g.date_ended, this_g.date_started) ))';

                    try {
                        $stmt = DB::getConnection()->prepare($query);
                        $stmt->bindValue(':userid', $this->getID(), PDO::PARAM_INT);
                        $stmt->execute();
                        $rank = $stmt->fetchObject();
                        $stmt = null;
                    } catch (PDOException $e) {
                        log_db_error($query, $e->getMessage(), true, true);
                    }

                    if (!$rank) {
                        log_error('Can\'t get quick rank for user: ' . $this->getID() . ', level: ' . $this->getLevel() . ", type: $type",
                            true, true);
                    }

                    $query = 'INSERT INTO ranking SET last_updated = NOW(), user_id = :userid, game_id = :gameid, type = :type, level=:level, rank = :rank';
                    try {
                        $stmt = DB::getConnection()->prepare($query);
                        $stmt->bindValue(':userid', $this->getID(), PDO::PARAM_INT);
                        $stmt->bindValue(':gameid', (isset($rank->game_id) ? $rank->game_id : 0), PDO::PARAM_INT);
                        $stmt->bindValue(':type', $rank->type, PDO::PARAM_STR);
                        $stmt->bindValue(':level', (isset($rank->level) ? $rank->level : 0), PDO::PARAM_INT);
                        $stmt->bindValue(':rank', $rank->rank, PDO::PARAM_INT);
                        $stmt->execute();
                        $stmt = null;
                    } catch (PDOException $e) {
                        log_db_error($query, $e->getMessage(), true, true);
                    }
                }
            } else {
                resetRankings($this->getLevel(), $type);
                // *** Function? as twice used...
                $query = 'SELECT r.rank, r.type, r.level, TIMESTAMPDIFF(SECOND, r.last_updated, NOW()) AS age, r.last_updated FROM ranking r LEFT JOIN games g ON g.id = r.game_id WHERE r.user_id = :id AND r.level = :level AND r.type = :type';
                try {
                    $stmt = DB::getConnection()->prepare($query);
                    $stmt->bindValue(':id', $this->getID());
                    $stmt->bindValue(':level', $this->getLevel());
                    $stmt->bindValue(':type', $type);
                    $stmt->execute();

                    $rank = $stmt->fetchObject();
                } catch (PDOException $e) {
                    log_db_error($query, $e->getMessage(), true, true);
                }

                if ($rank && $expiredTime > 10 && $rank->age > $expiredTime) {
                    log_error('Can\'t update ranking for level: ' . $this->getLevel() . ", type: $type \n rank: " . print_r($rank,
                            true) . "\n expired_time: $expiredTime \n query: $query", true);
                }
            }
        }

        if ($rank) {
            $rank->tot_count = max(1, getTotalRankCounts($this->getLevel(), $type));
            $rank->name_array = $this->getRankNameArray($rank->rank, $rank->tot_count);
            $rank->pretty_name = $rank->name_array[1];
            $rank->short_name = $rank->name_array[0];
        } else { // default ranking
            $rank = new stdClass();
            $rank->tot_count = max(1, getTotalRankCounts($this->getLevel(), $type));
            $rank->rank = $rank->tot_count;
            $rank->name_array = $this->getRankNameArray($rank->rank, $rank->tot_count);
            $rank->pretty_name = $rank->name_array[1];
            $rank->short_name = $rank->name_array[0];
        }

        return $rank;
    }

    public function getRankNameArray($ranking, $totCount)
    {
        if ($ranking <= 0) {
            return ['Gokiburi', 'gokiburi'];
        }

        foreach (User::$ranksAbs as $rankNum => $rankArray) {
            if ($ranking <= $rankNum) {
                return $rankArray;
            }
        }

        foreach (User::$ranksRel as $rankArray) {
            if (((float) $ranking / $totCount) <= $rankArray[2]) {
                return $rankArray;
            }
        }
        return User::$defaultRank;
    }

    public function cacheHighscores()
    {
        foreach (['kanji', 'vocab', 'reading', 'text'] as $type) {
            $query = "UPDATE `users` u
			SET ${type}_highscore_id =
			IFNULL((
			SELECT g.id AS g_id
			FROM `games` g
			WHERE
			g.`user_id` = u.id
			AND g.`level` = :level
			AND g.type = :type
			ORDER BY g.score DESC, g.date_ended ASC
			LIMIT 1
			), 0) WHERE u.id = :id";

            try {
                $stmt = DB::getConnection()->prepare($query);
                $stmt->bindValue(':level', $this->getLevel());
                $stmt->bindValue(':type', $type);
                $stmt->bindValue(':id', $this->getID());
                $stmt->execute();
            } catch (PDOException $e) {
                log_db_error($query, false, true);
            }
        }
    }

    public function printHighscores($type, $title = '')
    {
        if ($this->getFbID() <= 0)
            return;

        fb_connect_init();

        $levels = Session::$levelNames;

        $friends = $this->getFriends();

        $friends[] = $this->getFbID();
        $friendsID = implode($friends, ',');

        $query = "SELECT u.id, u.fb_id AS fb_id, g.id, g.score AS score, g.date_started AS date_played, TIMEDIFF(g.date_ended, g.date_started) AS duration, (u.level != g.level) AS otherlevel, g.level AS level
		FROM `users` u
		JOIN `games` g ON g.`user_id` = u.id AND g.type = '$type'
		LEFT JOIN games g2 ON g.user_id = g2.user_id AND g2.`level` = g.level AND g2.type = g.type AND (g.score < g2.score OR (g.score = g2.score AND g.date_ended > g2.date_ended))
		WHERE g2.score IS NULL AND u.fb_id = " . $this->getFbID() . "
		ORDER BY g.date_started DESC";

        $res = mysql_query_debug($query) or log_db_error($query);
        if (mysql_num_rows($res) <= 0) {
            echo "<div class=\"scoreboard\"><h2>No Registered Scores Yet</h2>
			<br/><p>You need to <a href=\"" . get_page_url(PAGE_PLAY, ['type' => $type, 'mode' => QUIZ_MODE]) . "\">play in Quiz mode</a> in order to log some scores.</p></div>";
            return;
        }

        echo "<div class=\"scoreboard\"><h2>$title</h2>";

        while ($row = mysql_fetch_object($res)) {
            if (!@$levels[$row->level]) {
                continue;
            }
            echo "<div class=\"user" . ($row->otherlevel ? " otherlevel" : "") . "\">";
            echo "<fb:profile-pic uid=\"" . $row->fb_id . "\" size=\"square\" linked=\"true\"></fb:profile-pic>";
            echo "<p>Level: <strong>" . $levels[$row->level] . "</strong></p>";
            echo "<p><strong>" . $row->score . " Pts</strong></p>";
            echo '<p>' . nice_time($row->date_played) . '</p>';
            echo "<p>Time: " . $row->duration . "</p>";
            echo "<div style=\"clear: both;\"></div>";
            echo "</div>";
        }
        echo "</div><div style=\"clear: both;\"></div>";
    }

    public function getBestGame($type)
    {
        $query = 'SELECT r.*, g.*, g.date_started AS date_played, TIMEDIFF(g.date_ended, g.date_started) AS duration FROM ranking  r JOIN games g ON g.id = r.game_id WHERE r.user_id = ' . (int) $this->getID() . ' AND r.level = \'' . DB::getConnection()->quote($this->getLevel()) . '\' AND r.type = \'' . DB::getConnection()->quote($type) . '\' LIMIT 1';

        $res = mysql_query_debug($query) or log_db_error($query);

        if ($row = mysql_fetch_object($res)) {
            return $row;
        }

        $query = 'SELECT *, g.date_started AS date_played, TIMEDIFF(g.date_ended, g.date_started) AS duration FROM users u JOIN games g ON g.id = u.`' . $type . '_highscore_id` WHERE u.id = ' . (int) $this->getID() . ' LIMIT 1';
        $res = mysql_query_debug($query) or log_db_error($query);
        $row = mysql_fetch_object($res);
        if ($row) {
            $row->rank = '?';
        }
        return $row;
    }

    public function getNJLPTLevel()
    {
        return old_to_new_jlpt($this->getLevel());
    }

    public function isLoggedIn()
    {
        return $this->loggedIn;
    }

    public function setLoggedIn($loggedIn)
    {
        if ($this->loggedIn != $loggedIn) {
            $this->loggedIn = $loggedIn;
            $query = 'UPDATE `users` SET `last_played` = NOW(), `active` = ' . ($loggedIn ? '1' : '0') . ' WHERE `id` = ' . (int) $this->getID();
            mysql_query_debug($query) or log_db_error($query, false, true);
        }
    }

    public function upgradeAccount($deviceID, $build, $kbCode)
    {
        $query = 'UPDATE `users` SET `last_played` = NOW(), `active` = 1, build = ' . (int) $build . ', kb_code = \'' . DB::getConnection()->quote($kbCode) . '\', privileges = 1 WHERE `id` = ' . (int) $this->getID();
        mysql_query_debug($query) or log_db_error($query, false, true);

        $this->data->privileges = 1;
    }

    public static function getRanks()
    {
        $ranks = [];
        foreach (User::$ranksAbs as $rank) {
            $ranks[$rank[0]] = $rank[1];
        }
        foreach (User::$ranksRel as $rank) {
            $ranks[$rank[0]] = $rank[1];
        }
        $rank = User::$defaultRank;
        $ranks[$rank[0]] = $rank[1];

        return $ranks;
    }

    public function unarchiveDbrecords()
    {
        foreach (['kana_learning', 'learning', 'jmdict_learning', 'reading_learning'] as $tableName) {
            mysql_query_debug('INSERT IGNORE INTO ' . $tableName . ' (SELECT * FROM _purge_' . $tableName . ' pl WHERE pl.user_id = ' . $this->getID() . ')');
            mysql_query_debug('DELETE pl.* FROM _purge_' . $tableName . ' pl WHERE pl.user_id = ' . $this->getID());
        }

        mysql_query_debug('INSERT IGNORE INTO games (SELECT * FROM _purge_games pl WHERE pl.user_id = ' . $this->getID() . ')');
        mysql_query_debug('DELETE pl.* FROM _purge_games pl WHERE pl.user_id = ' . $this->getID());


        $this->data->purged = 0;
        mysql_query_debug("UPDATE users SET purged = '0' WHERE id = " . $this->getID());
    }

    public function storeFbfriends()
    {
        if (!fb_connect_init(false)) {
            return false;
        }

        try {
            $friends = $this->getFriends();
        } catch (Exception $e) {
            log_exception($e, 'User::store_fb_friends(): friends_get()', false, false);
            return false;
        }

        if (is_array($friends) && count($friends)) {
            $fbID = (int) $this->getFbID();
            mysql_query_debug('BEGIN');
            mysql_query_debug('DELETE FROM fb_friends WHERE fb_id_1 = ' . $fbID);
            foreach ($friends as $friend) {
                mysql_query_debug('INSERT INTO fb_friends SET fb_id_1 = ' . $fbID . ', fb_id_2 = ' . $friend);
            }
            mysql_query_debug('COMMIT');
        }

        return true;
    }

    public function getFriends()
    {
        if ($this->friends) {
            return $this->friends;
        }

        global $facebook;

        if (!fb_connect_init(false)) {
            return [];
        }

        $res = $facebook->api('me/friends');
        $this->friends = [];
        foreach ($res['data'] as $friend) {
            if ($friend['id']) {
                $this->friends[] = $friend['id'];
            }
        }

        return $this->friends;
    }

    public function updateLogin($login)
    {
        if (empty($login))
            return;

        $query = 'SELECT COUNT(*) AS c FROM `users_ext` WHERE `login_email` = \'' . DB::getConnection()->quote($login) . '\' AND `user_id` != ' . (int) $this->getID();
        $res = mysql_query_debug($query) or die(mysql_error());
        $row = mysql_fetch_object($res);
        if ($row->c > 0) {
            return '<div class="error_msg">This login email is already taken.</div>';
        }


        $query = 'UPDATE `users_ext` SET `login_email` = \'' . DB::getConnection()->quote($login) . '\' WHERE `user_id` = ' . (int) $this->getID();

        if (mysql_query_debug($query)) {
            $this->data->login_email = $login;
            return '<div class="success_msg">Your login was updated</div>';
        } else {
            return '<div class="error_msg">Update failed: database error.</div>';
        }
    }

    public function updateName($firstName, $lastName)
    {
        if (empty($firstName) && empty($lastName)) {
            return;
        }

        if ($this->isNameHidden()) {
            $this->data->name_hidden = false;
            $query = 'UPDATE `users` SET  `name_hidden` = \'0\' WHERE id = ' . $this->getID();
            mysql_query_debug($query) or log_db_error($query);
        }

        if ($firstName != $this->data->first_name || $lastName != $this->data->last_name) {
            if (!empty($firstName)) {
                $this->data->first_name = $firstName;
            }
            if (!empty($lastName)) {
                $this->data->last_name = $lastName;
            }

            $query = 'UPDATE `users_ext` SET  `first_name` = \'' . DB::getConnection()->quote($this->data->first_name) . '\',  `last_name` = \'' . DB::getConnection()->quote($this->data->last_name) . '\' WHERE user_id = ' . $this->getID();

            if (mysql_query_debug($query)) {
                return '<div class="success_msg">Your name was updated</div>';
            } else {
                return '<div class="error_msg">Update failed: database error.</div>';
            }
        }
    }

    public function updatePassword($pwd)
    {
        if (empty($pwd)) {
            return;
        }

        $query = 'UPDATE `users_ext` SET `login_pwd` = MD5(\'' . DB::getConnection()->quote($pwd) . '\') WHERE `user_id` = ' . (int) $this->getID();

        if (mysql_query_debug($query)) {

            $this->data->login_pwd = $pwd;
            return '<div class="success_msg">Your password has been updated</div>';
        } else {
            return '<div class="error_msg">Update failed: database error.</div>';
        }
    }

    public function getJLPTNumLevel()
    {
        return $this->data->level;
    }

    public function getFbID()
    {
        return $this->data->fb_id;
    }

    public function getID()
    {
        return (int) $this->data->id;
    }

    public function getEmail()
    {
        return $this->data->login_email;
    }

    public function getPwdHash()
    {
        return $this->data->login_pwd;
    }

    public function getFirstName()
    {
        return $this->data->first_name;
    }

    public function getLastName()
    {
        return $this->data->last_name;
    }

    public function isPwdEmpty()
    {
        return $this->data->login_pwd == '';
    }

    public function getLevel()
    {
        return $this->data->level;
    }

    public function isNameHidden()
    {
        return $this->data->name_hidden;
    }

    public function isElite()
    {
        return $this->data->privileges > 0;
    }

    public function isOnTranslatorProbation()
    {
        return $this->data->translator_probation;
    }

    public function incLoadCount()
    {
        $this->loadCount++;
    }

    public function getLoadCount()
    {
        return $this->loadCount;
    }

    public function isGuestUser()
    {
        return false;
    }
}
