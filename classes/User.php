<?php

class User {

    private $data = NULL;
    private $prefs = NULL;
    private $logged_in;
    private $load_count = 0;
    static $ranks_abs = array(1 => array('shougun', 'Shōgun'), 5 => array('daimyou', 'Daimyō'), 30 => array('samurai', 'Samurai'), 100 => array('ninja', 'Ninja'));
    static $ranks_rel = array(array('rikishi', 'Rikishi', 0.3), array('tanuki', 'Tanuki', 0.5), array('kappa', 'Kappa', 0.7));
    static $default_rank = array('gokiburi', 'Gokiburi');
    public static $pref_labels = array(
        'general' => array(
            'auto_vanish' => 'Hide answers automatically after a few seconds <strong><a href="http://kanjibox.net/kb/page/faq/#corrections">*</a></strong><br/>',
            'shortcuts' => 'Enable Hotkeys  (press keyboard key \'1\' to \'5\' to choose an answer) &mdash; <em>Currently only available to <a href="http://kanjibox.net/kb/page/faq/#elite">Elite</a> users</em><br/>',
            'hide_rare_kanji' => 'Hide rare kanji spellings'),
        'drill' => array('show_english' => 'Show kanji meaning', 'show_reading_translation' => 'Show translation (Reading drill)',
            'show_examples' => 'Show examples', 'show_learning_stats' => 'Show learning stats',
            'show_reading' => array('legend' => 'Show reading: ', 'choices' => array('always' => 'Always', 'above_level' => 'If above level', 'never' => 'Never'))),
        'quiz' => array('show_prog_bar' => 'Show Progress Bar'),
        'notif' => array('post_news' => 'Post highscore updates in news feed'),
        'lang' => array(
            'kanji_lang' => array('legend' => 'Kanji meanings: ', 'choices' => array('en' => 'English', 'de' => 'Deutsch', 'fi' => 'Suomi', 'fr' => 'Français', 'it' => 'Italiano', 'pl' => 'Polski', 'ru' => 'Русский', 'sp' => 'Español', 'sv' => 'Svenska', 'tr' => 'Türkçe', 'th' => 'ภาษาไทย')),
            'vocab_lang' => array('legend' => 'Vocab definitions: ', 'choices' => array('en' => 'English', 'de' => 'Deutsch', 'fi' => 'Suomi', 'fr' => 'Français', 'it' => 'Italiano', 'pl' => 'Polski', 'ru' => 'Русский', 'sp' => 'Español', 'sv' => 'Svenska', 'tr' => 'Türkçe', 'th' => 'ภาษาไทย')),
            'translator_mode' => 'Translator mode &mdash; <em>Only show entries that need translating</em>'
        )
    );
    public static $default_prefs = array('general' => array('auto_vanish' => true, 'shortcuts' => true, 'hide_rare_kanji' => true), 'drill' => array('show_english' => true, 'show_pron' => true, 'show_examples' => true, 'show_learning_stats' => true, 'show_reading' => 'above_level', 'show_reading_translation' => true), 'quiz' => array('show_prog_bar' => true), 'notif' => array('profile_box' => true, 'post_news' => true), 'lang' => array('kanji_lang' => 'en', 'vocab_lang' => 'en', 'translator_mode' => false));

    public function __construct($id_params, $_logged_in = false, $info = NULL) {
        global $facebook;

        if (empty($id_params)) {
            log_error('Can\'t instantiate User class without id_params', true, true);
        }

        $this->logged_in = $_logged_in;
        $this->friends = false;

        if (is_array($id_params)) {
            $where_id = implode(' = ? AND ', array_keys($id_params)) . ' = ?';
        } elseif (is_int($id_params)) {
            $where_id = 'u.id = ' . (int) $id_params;
        } else {
            log_error("User::_construct(): Invalid id_param: '$id_params'", true, true);
        }

        $query = 'SELECT * FROM `users` u LEFT JOIN users_ext ux ON ux.user_id = u.id WHERE ' . $where_id;
        try {
            $stmt = DB::getConnection()->prepare($query);
            $stmt->execute(array_values($id_params));
            $this->data = $stmt->fetchObject();
        } catch (PDOException $e) {
            log_db_error($query, $e->getMessage(), false, true);
        }

        if (!empty($this->data)) {
            $query = 'UPDATE `users` SET `last_played` = NOW(), games_played = games_played + 1, `active` = :active WHERE `id` = :id';
            try {
                $stmt = DB::getConnection()->prepare($query);
                $stmt->bindValue(':active', ($_logged_in ? '1' : '0'));
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
                    $stmt->bindValue(':id', $this->getID());
                    $stmt->execute();
                } catch (PDOException $e) {
                    log_db_error($query, $e->getMessage(), false, true);
                }
            }
        } elseif ($id_params['fb_id']) {
            $this->data = $this->create_account($id_params['fb_id'], $_logged_in);
        } else {
            die('incorrect login/pwd');
        }

        if (empty($this->data->inf_session_key) && !empty($_POST['fb_sig_session_key']) && (@$_POST['fb_sig_expires'] == 0)) {
            $this->data->inf_session_key = $_POST['fb_sig_session_key'];
            $query = 'UPDATE `users_ext` SET `inf_session_key` = \'' . mysql_real_escape_string($this->data->inf_session_key) . '\' WHERE `user_id` = \'' . $this->getID() . '\'';
            mysql_query_debug($query) or log_db_error($query);
        }

        if ($info && isset($info['first_name']) && isset($info['last_name'])) {
            $name_hidden = (empty($info['first_name']) && empty($info['last_name']));

            if ($name_hidden != $this->is_name_hidden()) {
                $this->data->name_hidden = $name_hidden;
                $query = 'UPDATE `users` SET  `name_hidden` = \'' . (int) $name_hidden . '\' WHERE id = ' . $this->getID();
                mysql_query_debug($query) or log_db_error($query);
            }

            if ($info['first_name'] != $this->data->first_name || $info['last_name'] != $this->data->last_name) {
                if (!empty($info['first_name'])) {
                    $this->data->first_name = $info['first_name'];
                }
                if (!empty($info['last_name'])) {
                    $this->data->last_name = $info['last_name'];
                }

                $query = 'UPDATE `users_ext` SET  `first_name` = \'' . mysql_real_escape_string($this->data->first_name) . '\',  `last_name` = \'' . mysql_real_escape_string($this->data->last_name) . '\' WHERE user_id = ' . $this->getID();
                mysql_query_debug($query) or log_db_error($query);
            }
        }

        if (empty($this->data->login_email) && !empty($info['email']) && strpos($info['email'], '@proxymail.facebook.com') === FALSE) {
            $email = $info['email'];
            $query = 'SELECT COUNT(*) AS c FROM `users_ext` WHERE `login_email` = \'' . mysql_real_escape_string($email) . '\' AND `user_id` != ' . (int) $this->getID();
            $res = mysql_query_debug($query) or die(mysql_error());
            $row = mysql_fetch_object($res);
            if ($row->c == 0) {
                $this->data->login_email = $email;
                $query = 'UPDATE `users_ext` SET `login_email` = \'' . mysql_real_escape_string($info['email']) . '\' WHERE user_id = ' . $this->getID();
                mysql_query_debug($query) or log_db_error($query);
            }
        }

        if (!empty($this->data->prefs)) {
            $this->prefs = unserialize($this->data->prefs);
        }
        if (!is_array($this->prefs)) {
            $this->prefs = array();
        }

        $cur_level = $this->get_level();
        if ($cur_level == LEVEL_1 || $cur_level == LEVEL_2 || $cur_level == LEVEL_3 || $cur_level == LEVEL_J1 || $cur_level == LEVEL_J2 || $cur_level == LEVEL_J3 || $cur_level == LEVEL_J4) {
            $this->data->level = $this->get_njlpt_level();
        }

        if ($this->data->purged) {
            $this->unarchive_db_records();
        }

        $this->logged_in = true;
    }

    static function create_account($fb_id, $_logged_in) {
        if (!(int) $fb_id) {
            log_error("User::create_account: invalid fb_id: '$fb_id'");
        }

        $query = 'INSERT INTO `users` SET `fb_id` = \'' . ((int) $fb_id) . '\', `date_joined` = NOW(), `last_played` = NOW(), active = \'' . (int) $_logged_in . '\', level=\'' . LEVEL_N3 . '\'';
        mysql_query_debug($query) or log_db_error($query, false, true);
        $user_id = mysql_insert_id();

        $query = 'SELECT * FROM `users` u LEFT JOIN users_ext ux ON ux.user_id = u.id  WHERE u.id = \'' . (int) $user_id . '\'';
        $rec = mysql_query_debug($query) or log_db_error($query, false, true);
        if ($new_user = mysql_fetch_object($rec)) {
            $query = 'INSERT INTO `users_ext` SET `user_id` = ' . ((int) $new_user->id);
            mysql_query_debug($query) or log_db_error($query, false, true);
            return $new_user;
        } else {
            log_error('User::create_account creation failed', true, true);
        }
    }

    function isAdministrator() {
        return ($this->getID() == '1');
    }

    function isEditor() {
        return ($this->isAdministrator() || $this->data->privileges > 1 );
    }

    function get_pref($pref, $sub_pref = NULL) {
        if ($sub_pref) {
            if (isset($this->prefs[$pref][$sub_pref]))
                return $this->prefs[$pref][$sub_pref];
            elseif (isset(User::$default_prefs[$pref][$sub_pref]))
                return User::$default_prefs[$pref][$sub_pref];
            else {
                log_error('No default for pref: ' . $pref . ' - ' . $sub_pref, false, false);
                return NULL;
            }
        } elseif (isset($this->prefs[$pref]))
            return $this->prefs[$pref];
        else
            return @User::$default_prefs[$pref];
    }

    function update_level($new_level) {
        $query = 'UPDATE `users` SET `level` = :level WHERE `id` = :id';
        try {
            $stmt = DB::getConnection()->prepare($query);
            $stmt->bindValue(':level', $new_level);
            $stmt->bindValue(':id', $this->getID());
            $stmt->execute();
        } catch (PDOException $e) {
            log_db_error($query, true, true);
        }
    }

    function update_prefs($new_prefs) {
        $old_profile_pref = $this->get_pref('notif', 'profile_box');

        foreach (User::$pref_labels as $key => $val) {
            foreach ($val as $key2 => $val2) {
                if (isset($new_prefs['prefs'][$key][$key2])) {
                    $this->prefs[$key][$key2] = $new_prefs['prefs'][$key][$key2];
                } else {
                    $this->prefs[$key][$key2] = false;
                }
            }
        }

        if (isset($new_prefs['level'])) {
            $this->data->level = $new_prefs['level'];
            $_SESSION['cur_session'] = NULL;
        }

        $this->save_prefs();
        $this->cacheHighscores();
        //$force_update = ($old_profile_pref != $this->get_pref('notif', 'profile_box'));
        //$this->update_profile_box($force_update);
    }

    function save_prefs() {
        /** mysql_query_debug formerly used: check if slow queries can be monitored by DB * */
        // Update level and status
        $query = 'UPDATE `users` SET `level` = :level, `active` = :active WHERE `id` = :id';
        try {
            $stmt = DB::getConnection()->prepare($query);
            $stmt->bindValue(':level', $this->get_level());
            $stmt->bindValue(':active', (int) ($this->is_logged_in()));
            $stmt->bindValue(':id', $this->getID());
            $stmt->execute();
        } catch (PDOException $e) {
            log_db_error($query, true, true);
        }

        // Update preferences
        $query = 'UPDATE `users_ext` SET `prefs` = :preferences WHERE `user_id` = :id';
        try {
            $stmt = DB::getConnection()->prepare($query);
            $stmt->bindValue(':preferences', serialize($this->prefs));
            $stmt->bindValue(':id', $this->getID());
            $stmt->execute();
        } catch (PDOException $e) {
            log_db_error($query, true, true);
        }
    }

    function update_profile_box($force_update = false) {
        global $facebook;

        if (!$this->logged_in) {
            return false;
        }

        if ($this->get_fb_id() <= 0) {
            return true;
        }

        fb_connect_init();

        $fb_id = $this->get_fb_id();

        if (!$this->get_pref('notif', 'profile_box')) {
            if ($force_update) {
                try {
                    // $facebook->api_client->profile_setFBML(NULL, $fb_id, ' ', '', '');
                } catch (Exception $e) {
                    // oh well...
                    log_exception($e, "update_profile_box() - Can't reset profile to blank.");
                }
            }
            return;
        }

        require_once(ABS_PATH . 'libs/stats_lib.php');
        $levels = Session::$level_names;


        $text = '<fb:ref handle="global_announcement" />';
        $text .= '<fb:ref handle="profile_css" />';

        $text .= "<p class=\"summary\"><fb:name firstnameonly=\"true\" uid=\"$fb_id\" useyou=\"false\" capitalize=\"true\" /> is training at level: <strong>" . $levels[$this->get_level()] . "</strong> on <a href=\"" . get_page_url() . "\">Kanji Box</a>.</p>";

        $query = 'SELECT SUM(c) as c FROM ((SELECT COUNT(*) as c FROM learning l WHERE l.user_id = ' . (int) $this->getID() . ' LIMIT 1) UNION (SELECT COUNT(*) as c FROM jmdict_learning jl WHERE jl.user_id = ' . (int) $this->getID() . ' LIMIT 1) UNION (SELECT COUNT(*) as c FROM reading_learning rl WHERE rl.user_id = ' . (int) $this->getID() . ' LIMIT 1)) as t';

        $res = mysql_query_debug($query) or log_db_error($query, true, true);
        $row = mysql_fetch_object($res);

        $info_fields = array();

        if ($row->c > 0) {
            foreach (array('kanji' => 'Kanji', 'vocab' => 'Vocabulary', 'reading' => 'Reading') as $type => $type_desc) {
                $text .= '<fieldset class="profile-box"><legend><a href="' . get_page_url(PAGE_PLAY, array('type' => $type, 'mode' => QUIZ_MODE)) . '">' . $type_desc . '</a></legend>';

                $game = $this->getHighscore($this->get_level(), $type);
                if ($game) {
                    $rank = $this->get_rank($type);
                    $text .= '<div class="game">';
                    $text .= '<img class="rank-icon" src="' . SERVER_URL . 'img/ranks/rank_' . $rank->short_name . '.png" />';
                    $text .= '<div class="ranking">Rank: <strong>' . $rank->pretty_name . '</strong></div>';
                    $text .= '<div class="highscore">Highscore: <strong>' . $game->score . ' Pts</strong></div>';
                    $text .= '<div style="clear:both;" ></div>';
                    $text .= '</div>';

                    /*
                      $info_fields[] = array('field' => $type_desc . ' Ranking',
                      'items' => array(array('label'=> $rank->pretty_name,
                      'image' => SERVER_URL . 'img/ranks/rank_' . $rank->short_name . '.png',
                      'description'=> 'Highscore: ' . $game->score . ' Pts.',
                      'link'=> get_page_url(PAGE_PLAY, array('type' => $type, 'mode' => QUIZ_MODE)))));
                     */
                }

                $jlpt_level = $this->get_njlpt_level();

                $wide_bar = 340;
                $narrow_bar = 145;
                switch ($type) {
                    case 'kanji':
                        if ($this->get_level() == $jlpt_level) {
                            $big = print_jlpt_levels($this->getID(), $jlpt_level, $wide_bar, 'Learning stats - ' . $jlpt_level);
                            $small = print_jlpt_levels($this->getID(), $jlpt_level, $narrow_bar, 'Learning stats - ' . $jlpt_level);
                        } else {
                            $num = (int) Question::level_to_grade($this->get_level());
                            if ($num > 0) {
                                $big = print_grades_levels($this->getID(), $num, $wide_bar, 'Learning stats - Grade ' . $num);
                                $small = print_grades_levels($this->getID(), $num, $narrow_bar, 'Learning stats - Grade ' . $num);
                            } else {
                                $big = print_jlpt_levels($this->getID(), 1, $wide_bar, 'Learning stats - 1-kyuu');
                                $small = print_jlpt_levels($this->getID(), 1, $narrow_bar, 'Learning stats - 1-kyuu');
                            }
                        }
                        break;

                    case 'vocab':
                        $num = Question::level_to_grade($jlpt_level);
                        $num = $num[1];
                        $big = print_vocab_jlpt_levels($this->getID(), $num, $wide_bar, 'Learning stats - ' . $num . '-kyuu');
                        $small = print_vocab_jlpt_levels($this->getID(), $num, $narrow_bar, 'Learning stats - ' . $num . '-kyuu');

                        break;

                    case 'reading':
                        $num = Question::level_to_grade($jlpt_level);
                        $num = $num[1];
                        $big = print_reading_jlpt_levels($this->getID(), $num, $wide_bar, 'Learning stats - ' . $num . '-kyuu');
                        $small = print_reading_jlpt_levels($this->getID(), $num, $narrow_bar, 'Learning stats - ' . $num . '-kyuu');
                        break;
                }
                $text .= '<fb:wide>' . $big . '</fb:wide><fb:narrow>' . $small . '</fb:narrow>';
                $text .= '</fieldset>';
            }
        } else {
            $text .= "<p class=\"details\"><fb:pronoun uid=\"$fb_id\" useyou=\"false\" capitalize=\"true\" /> hasn't logged any scores or statistics yet.</p>";
        }

        try {
            // $facebook->api_client->profile_setFBML($text, $fb_id, $text, '', '');
        } catch (Exception $e) {
            //	$this->set_pref('notif', 'profile_box', false);

            try {
                $fields = array('last_name', 'first_name', 'is_app_user', 'sex', 'pic', 'profile_url');
                // $info = $facebook->api_client->users_getInfo($fb_id, $fields);
                // log_exception($e, "update_profile_box() - Can't update profile.\nUser info: ". print_r($info, true) . "\nFBML:\n" . $text);
            } catch (Exception $e) {
                log_exception($e, "update_profile_box() - Can't update profile and can't get info." . "\nFBML:\n" . $text);
            }
        }
    }

    function set_pref($key, $key2, $new_value) {
        $this->prefs[$key][$key2] = $new_value;
        $this->save_prefs();
    }

    function publish_story($type) {
        $rank = $this->get_rank($type, true);
        if (!$rank)
            die("Can't get rank...");
        return $this->publish_rank_story($type, $rank, false);
    }

    function publish_rank_story($type, $rank, $just_now = true) {
        //
        //return false;

        global $facebook;

        if ($this->get_fb_id() <= 0)
            return 'Not using Facebook';

        if (!fb_connect_init())
            return "Can't init Facebook";

        if ($type != TYPE_KANJI && $type != TYPE_VOCAB && $type != TYPE_READING && $type != TYPE_TEXT)
            die('unknown type');

        $levels = Session::$level_names;

        if ($this->data->first_name)
            $description = $this->data->first_name . ($just_now ? ' just' : '') . ' reached the glorious rank of ' . $rank->pretty_name . ' (' . $levels[$this->get_level()] . ' ' . ucfirst($type) . ' division) in KanjiBox!';
        else
            $description = 'I' . ($just_now ? ' just' : '') . ' reached the glorious rank of ' . $rank->pretty_name . ' (' . $levels[$this->get_level()] . ' ' . ucfirst($type) . ' division) in KanjiBox!';


        try {

            // if($facebook->api('/me/feed', 'post', array('picture' => SERVER_URL . 'img/ranks/rank_' . $rank->short_name . '.png', 'name' => 'KanjiBox', 'link' => get_page_url(PAGE_PLAY, array('type' => $type, 'mode' => QUIZ_MODE)), 'caption' => '', 'description' => $description, 'actions' => array(array('name' => 'Play', 'link' => get_page_url())))))


            if ($facebook->api('/me/feed', 'post', array('picture' => SERVER_URL . 'img/ranks/rank_' . $rank->short_name . '.png', 'name' => 'KanjiBox', 'link' => get_page_url(PAGE_PLAY, array('type' => $type, 'mode' => QUIZ_MODE)), 'caption' => '', 'description' => $description, 'actions' => array(array('name' => 'Play', 'link' => get_page_url()))))) {
                $str = "<div class=\"success_msg\">Posted story on Facebook</div>";

                return $str;
            }
        } catch (FacebookApiException $e) {

            // log_error("publish_rank_story: FB Exception\n" . print_r($e, true), false, true);

            return "<div class=\"error_msg\">Facebook error. Make sure KanjiBox is allowed to post on your Facebook feed.</div>";
        }

        // echo '<script src="http://static.ak.connect.facebook.com/js/api_lib/v0.4/FeatureLoader.js.php/en_US" type="text/javascript"></script>';
        // insert_js_snippet("FB.Connect.streamPublish('やった！', " . json_encode($attachment) . ", ". json_encode($action_links) . ", 0, 'Your reaction?');");
    }

    function getHighscore($level, $type) {
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

    function is_highscore($score_id, $level, $type) {
        $score = $this->getHighscore($level, $type);

        return (!$score || ($score->id == $score_id));
    }

    function reset_highscores($level = '', $type = '') {
        $query = 'FROM `games` WHERE `user_id` = \'' . $this->getID() . '\'';
        if ($level)
            $query .= ' AND `level` = \'' . mysql_real_escape_string($level) . '\' ';
        if ($type)
            $query .= ' AND `type` = \'' . mysql_real_escape_string($type) . '\'';

        $query = 'SELECT COUNT(*) as c ' . $query;
        $res = mysql_query_debug($query) or log_db_error($select_query, false, true);
        $row = mysql_fetch_object($res);
        display_user_msg('Deleting ' . $row->c . ' records');

        $query = 'DELETE ' . $query;
        mysql_query_debug($query) or log_db_error($query, false, true);
    }

    function get_friendsranking($score, $level, $type) {
        global $facebook;

        if ($this->get_fb_id() <= 0)
            return 0;

        fb_connect_init();

        $friends = $this->get_friends();

        // $friends = array_diff($friends, array(""));
        $friends_id = implode($friends, ',');

        $query = "SELECT COUNT(u.id) as c FROM `users` u INNER JOIN `games` g ON g.`user_id` = u.id LEFT JOIN games g2 ON g.user_id = g2.user_id AND (g.score < g2.score OR (g.score = g2.score AND g.date_ended > g2.date_ended))  WHERE g.`level` = '$level'  AND g2.level = '$level' AND g2.score IS NULL AND g.score > $score AND g.type = '$type' AND u.fb_id IN ($friends_id) ORDER BY score DESC, duration ASC";
        $res = mysql_query_debug($query) or log_db_error($query);
        $row = mysql_fetch_object($res);
        return $row->c + 1;
    }

    function get_rank($type, $no_refresh = false, $expired_time = 3600) {
        require_once ABS_PATH . 'libs/stats_lib.php';

        // *** Function? as twice used...
        $query = 'SELECT r.rank, r.type, r.level, TIMESTAMPDIFF(SECOND, r.last_updated, NOW()) AS age, r.last_updated FROM ranking r LEFT JOIN games g ON g.id = r.game_id WHERE r.user_id = :id AND r.level = :level AND r.type = :type';
        try {
            $stmt = DB::getConnection()->prepare($query);
            $stmt->bindValue(':id', $this->getID());
            $stmt->bindValue(':level', $this->get_level());
            $stmt->bindValue(':type', $type);
            $stmt->execute();

            $rank = $stmt->fetchObject();
        } catch (PDOException $e) {
            log_db_error($query, $e->getMessage(), true, true);
        }

        if (!$rank || $rank->age > $expired_time) {
            if ($no_refresh) {
                if (!$rank) {
                    $query = 'SELECT COUNT(*) AS rank, this_g.id as game_id, NOW() as last_updated, 0 as age, this_u.level as level, \'' . $type . '\' as type FROM
					users this_u LEFT JOIN games this_g ON this_u.' . $type . '_highscore_id = this_g.id
					LEFT JOIN users u ON u.level = this_u.level
					JOIN games g ON u.' . $type . '_highscore_id = g.id
					WHERE this_u.id = ' . $this->getID() . ' AND (this_g.score IS NULL OR g.score > this_g.score OR (g.score = this_g.score && TIMEDIFF(g.date_ended, g.date_started) < TIMEDIFF(this_g.date_ended, this_g.date_started) ))';
                    $res = mysql_query_debug($query) or log_db_error($query, true, true);
                    $rank = mysql_fetch_object($res);

                    if (!$rank) {
                        log_error("Can't get quick rank for user: " . $this->getID() . ", level: " . $this->get_level() . ", type: $type", true, true);
                    }

                    $query = 'INSERT INTO ranking SET last_updated = NOW(), user_id = ' . $this->getID() . ', game_id = ' . (int) $rank->game_id . ', type = \'' . $rank->type . '\', level=\'' . $rank->level . '\', rank = ' . $rank->rank;
                    mysql_query_debug($query) or log_db_error($query, true, true);
                }
            } else {
                resetRankings($this->get_level(), $type);
                // *** Function? as twice used...
                $query = 'SELECT r.rank, r.type, r.level, TIMESTAMPDIFF(SECOND, r.last_updated, NOW()) AS age, r.last_updated FROM ranking r LEFT JOIN games g ON g.id = r.game_id WHERE r.user_id = :id AND r.level = :level AND r.type = :type';
                try {
                    $stmt = DB::getConnection()->prepare($query);
                    $stmt->bindValue(':id', $this->getID());
                    $stmt->bindValue(':level', $this->get_level());
                    $stmt->bindValue(':type', $type);
                    $stmt->execute();

                    $rank = $stmt->fetchObject();
                } catch (PDOException $e) {
                    log_db_error($query, $e->getMessage(), true, true);
                }

                if ($rank && $expired_time > 10 && $rank->age > $expired_time) {
                    log_error('Can\'t update ranking for level: ' . $this->get_level() . ", type: $type \n rank: " . print_r($rank, true) . "\n expired_time: $expired_time \n query: $query", true);
                }
            }
        }

        if ($rank) {
            $rank->tot_count = max(1, getTotalRankCounts($this->get_level(), $type));
            $rank->name_array = $this->get_rank_name_array($rank->rank, $rank->tot_count);
            $rank->pretty_name = $rank->name_array[1];
            $rank->short_name = $rank->name_array[0];
        } else { // default ranking
            $rank = new stdClass();
            $rank->tot_count = max(1, getTotalRankCounts($this->get_level(), $type));
            $rank->rank = $rank->tot_count;
            $rank->name_array = $this->get_rank_name_array($rank->rank, $rank->tot_count);
            $rank->pretty_name = $rank->name_array[1];
            $rank->short_name = $rank->name_array[0];
        }

        return $rank;
    }

    function get_rank_name_array($ranking, $tot_count) {
        if ($ranking <= 0) {
            return array('Gokiburi', 'gokiburi');
        }

        foreach (User::$ranks_abs as $rank_num => $rank_array) {
            if ($ranking <= $rank_num) {
                return $rank_array;
            }
        }

        foreach (User::$ranks_rel as $rank_array) {
            if (((float) $ranking / $tot_count) <= $rank_array[2]) {
                return $rank_array;
            }
        }
        return User::$default_rank;
    }

    function cacheHighscores() {
        foreach (array('kanji', 'vocab', 'reading', 'text') as $type) {
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
                $stmt->bindValue(':level', $this->get_level());
                $stmt->bindValue(':type', $type);
                $stmt->bindValue(':id', $this->getID());
                $stmt->execute();
            } catch (PDOException $e) {
                log_db_error($query, false, true);
            }
        }
    }

    function print_highscores($type, $title = '') {
        global $facebook;

        if ($this->get_fb_id() <= 0)
            return;

        fb_connect_init();

        $levels = Session::$level_names;

        $friends = $this->get_friends();

        // $friends = array_diff($friends, array(""));
        $friends[] = $this->get_fb_id();
        $friends_id = implode($friends, ',');

        $query = "SELECT u.id, u.fb_id AS fb_id, g.id, g.score AS score, g.date_started AS date_played, TIMEDIFF(g.date_ended, g.date_started) AS duration, (u.level != g.level) AS otherlevel, g.level AS level
		FROM `users` u
		JOIN `games` g ON g.`user_id` = u.id AND g.type = '$type'
		LEFT JOIN games g2 ON g.user_id = g2.user_id AND g2.`level` = g.level AND g2.type = g.type AND (g.score < g2.score OR (g.score = g2.score AND g.date_ended > g2.date_ended))
		WHERE g2.score IS NULL AND u.fb_id = " . $this->get_fb_id() . "
		ORDER BY g.date_started DESC";

        $res = mysql_query_debug($query) or log_db_error($query);
        if (mysql_num_rows($res) <= 0) {
            echo "<div class=\"scoreboard\"><h2>No Registered Scores Yet</h2>
			<br/><p>You need to <a href=\"" . get_page_url(PAGE_PLAY, array('type' => $type, 'mode' => QUIZ_MODE)) . "\">play in Quiz mode</a> in order to log some scores.</p></div>";
            return;
        }

        echo "<div class=\"scoreboard\"><h2>$title</h2>";

        $i = 1;
        while ($row = mysql_fetch_object($res)) {
            if (!@$levels[$row->level])
                continue;
            echo "<div class=\"user" . ($row->otherlevel ? " otherlevel" : "") . "\">";
            echo "<fb:profile-pic uid=\"" . $row->fb_id . "\" size=\"square\" linked=\"true\"></fb:profile-pic>";
//			if (!$otherlevels)
//				echo "<div class=\"score_rank\">" . $i++. "</div>";
            echo "<p>Level: <strong>" . $levels[$row->level] . "</strong></p>";
            echo "<p><strong>" . $row->score . " Pts</strong></p>";
            echo '<p>' . nice_time($row->date_played) . '</p>';
            echo "<p>Time: " . $row->duration . "</p>";
            echo "<div style=\"clear: both;\"></div>";
            echo "</div>";
        }
        echo "</div><div style=\"clear: both;\"></div>";
    }

    function get_best_game($type) {
        $query = 'SELECT r.*, g.*, g.date_started AS date_played, TIMEDIFF(g.date_ended, g.date_started) AS duration FROM ranking  r JOIN games g ON g.id = r.game_id WHERE r.user_id = ' . (int) $this->getID() . ' AND r.level = \'' . mysql_real_escape_string($this->get_level()) . '\' AND r.type = \'' . mysql_real_escape_string($type) . '\' LIMIT 1';

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

    function get_njlpt_level() {
        return old_to_new_jlpt($this->get_level());
    }

    function is_logged_in() {
        return $this->logged_in;
    }

    function set_logged_in($_logged_in) {
        if ($this->logged_in != $_logged_in) {
            $this->logged_in = $_logged_in;
            $query = 'UPDATE `users` SET `last_played` = NOW(), `active` = ' . ($_logged_in ? '1' : '0') . ' WHERE `id` = ' . (int) $this->getID();
            mysql_query_debug($query) or log_db_error($query, false, true);
        }
    }

    function upgrade_account($device_id, $build, $kb_code) {
        $query = 'UPDATE `users` SET `last_played` = NOW(), `active` = 1, build = ' . (int) $build . ', kb_code = \'' . mysql_real_escape_string($kb_code) . '\', privileges = 1 WHERE `id` = ' . (int) $this->getID();
        // $query = 'UPDATE `users` SET `last_played` = NOW(), `active` = 1, device_id = \''. mysql_real_escape_string($device_id) . '\', build = '. (int) $build . ', kb_code = \''. mysql_real_escape_string($kb_code) . '\', privileges = 1 WHERE `id` = ' . (int) $this->get_id();
        mysql_query_debug($query) or log_db_error($query, false, true);

        $this->data->privileges = 1;
    }

    static function get_ranks() {
        $ranks = array();
        foreach (User::$ranks_abs as $rank) {
            $ranks[$rank[0]] = $rank[1];
        }
        foreach (User::$ranks_rel as $rank) {
            $ranks[$rank[0]] = $rank[1];
        }
        $rank = User::$default_rank;
        $ranks[$rank[0]] = $rank[1];

        return $ranks;
    }

    function unarchive_db_records() {
        foreach (array('kana_learning', 'learning', 'jmdict_learning', 'reading_learning') as $table_name) {
            mysql_query_debug('INSERT IGNORE INTO ' . $table_name . ' (SELECT * FROM _purge_' . $table_name . ' pl WHERE pl.user_id = ' . $this->getID() . ')');
            mysql_query_debug('DELETE pl.* FROM _purge_' . $table_name . ' pl WHERE pl.user_id = ' . $this->getID());
        }

        mysql_query_debug('INSERT IGNORE INTO games (SELECT * FROM _purge_games pl WHERE pl.user_id = ' . $this->getID() . ')');
        mysql_query_debug('DELETE pl.* FROM _purge_games pl WHERE pl.user_id = ' . $this->getID());


        $this->data->purged = 0;
        mysql_query_debug("UPDATE users SET purged = '0' WHERE id = " . $this->getID());
    }

    function store_fb_friends() {
        global $facebook;

        if (!fb_connect_init(false)) {
            return false;
        }

        try {
            $friends = $this->get_friends();
        } catch (Exception $e) {
            log_exception($e, 'User::store_fb_friends(): friends_get()', false, false);
            return false;
        }

        if (is_array($friends) && count($friends)) {
            $fb_id = (int) $this->get_fb_id();
            mysql_query_debug('BEGIN');
            mysql_query_debug('DELETE FROM fb_friends WHERE fb_id_1 = ' . $fb_id);
            foreach ($friends as $friend) {
                mysql_query_debug('INSERT INTO fb_friends SET fb_id_1 = ' . $fb_id . ', fb_id_2 = ' . $friend);
            }
            mysql_query_debug('COMMIT');
        }

        return true;
    }

    function get_friends() {
        if ($this->friends)
            return $this->friends;

        global $facebook;

        if (!fb_connect_init(false))
            return array();

        $res = $facebook->api('me/friends');
        $this->friends = array();
        foreach ($res['data'] as $friend)
            if ($friend['id'])
                $this->friends[] = $friend['id'];

        return $this->friends;
    }

    function update_login($login) {
        if (empty($login))
            return;

        $query = 'SELECT COUNT(*) AS c FROM `users_ext` WHERE `login_email` = \'' . mysql_real_escape_string($login) . '\' AND `user_id` != ' . (int) $this->getID();
        $res = mysql_query_debug($query) or die(mysql_error());
        $row = mysql_fetch_object($res);
        if ($row->c > 0)
            return '<div class="error_msg">This login email is already taken.</div>';


        $query = 'UPDATE `users_ext` SET `login_email` = \'' . mysql_real_escape_string($login) . '\' WHERE `user_id` = ' . (int) $this->getID();

        if (mysql_query_debug($query)) {
            $this->data->login_email = $login;
            return '<div class="success_msg">Your login was updated</div>';
        } else
            return '<div class="error_msg">Update failed: database error.</div>';
    }

    function update_name($first_name, $last_name) {
        if (empty($first_name) && empty($last_name))
            return;

        if ($this->is_name_hidden()) {
            $this->data->name_hidden = false;
            $query = 'UPDATE `users` SET  `name_hidden` = \'0\' WHERE id = ' . $this->getID();
            mysql_query_debug($query) or log_db_error($query);
        }

        if ($first_name != $this->data->first_name || $last_name != $this->data->last_name) {
            if (!empty($first_name))
                $this->data->first_name = $first_name;
            if (!empty($last_name))
                $this->data->last_name = $last_name;

            $query = 'UPDATE `users_ext` SET  `first_name` = \'' . mysql_real_escape_string($this->data->first_name) . '\',  `last_name` = \'' . mysql_real_escape_string($this->data->last_name) . '\' WHERE user_id = ' . $this->getID();

            if (mysql_query_debug($query)) {
                return '<div class="success_msg">Your name was updated</div>';
            } else
                return '<div class="error_msg">Update failed: database error.</div>';
        }
    }

    function update_password($pwd) {
        if (empty($pwd))
            return;

        $query = 'UPDATE `users_ext` SET `login_pwd` = MD5(\'' . mysql_real_escape_string($pwd) . '\') WHERE `user_id` = ' . (int) $this->getID();

        if (mysql_query_debug($query)) {

            $this->data->login_pwd = $pwd;
            return '<div class="success_msg">Your password has been updated</div>';
        } else {
            return '<div class="error_msg">Update failed: database error.</div>';
        }
    }

    function getJLPTNumLevel() {
        return $this->data->level;
    }

    function get_fb_id() {
        return $this->data->fb_id;
    }

    function getID() {
        return (int) $this->data->id;
    }

    function get_email() {
        return $this->data->login_email;
    }

    function get_pwd_hash() {
        return $this->data->login_pwd;
    }

    function get_first_name() {
        /* <fb:name firstnameonly="true" uid="<?php echo $_SESSION['user']->get_fb_id() ?>" useyou="false" linked="false" ifcantsee="Anonymous Gaijin"></fb:name> */
        return $this->data->first_name;
    }

    function get_last_name() {
        return $this->data->last_name;
    }

    function is_pwd_empty() {
        return $this->data->login_pwd == '';
    }

    function get_level() {
        return $this->data->level;
    }

    function is_name_hidden() {
        return $this->data->name_hidden;
    }

    function is_elite() {
        return $this->data->privileges > 0;
    }

    function is_on_translator_probation() {
        return $this->data->translator_probation;
    }

    function inc_load_count() {
        $this->load_count++;
    }

    function get_load_count() {
        return $this->load_count;
    }

    function is_guest_user() {
        return false;
    }

}

?>