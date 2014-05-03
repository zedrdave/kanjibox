<h2>Internationalisation</h2>

<div id="tabs">
    <ul>
        <li><a href="#guidelines">Guidelines</a></li>
        <li><a href="#translators">Contributors</a></li>
        <li><a title="progress_kanji" href="<?php echo SERVER_URL?>ajax/translation_progress/type/kanji/"><span>Kanji Progress</span></a></li>
        <li><a title="progress_vocab" href="<?php echo SERVER_URL?>ajax/translation_progress/type/vocab/"><span>Vocab Progress</span></a></li>
    </ul>
    <div id="guidelines">
        <p>Hello brave translators from around the world! Below are a couple general tips/rules on how to best contribute translations: please take a second to read them before you start translating away...</p>
        <p>Note that this list is neither exhaustive, nor set in stone: just some guidelines to help you get started and keep the standard of translation high enough. So, if you have any strong opinions on the list below (items that should be changed, items that could be added), do not hesitate to contact me to discuss them!</p>

        <ol class="verbose">
            <li><strong>Getting started:</strong><br/>To contribute translations, change your Language settings (there are two separate options for Vocab and Kanji) on the front page and start using KanjiBox in Vocab or Kanji mode. Click on the small flag or the '✍' button to add or modify a translation.<br/>Enabling <i>Translator Mode</i> in the settings will force Drill mode to only display untranslated entries.</li>
            <li><strong>Be meticulous</strong>.<br/>All translations are subject to review (assuming there are available editors for that language) and can theoretically be corrected later, but there is a very high likelihood that your translations will stay as is: other translators will tend to focus on new entries and ignore those marked as translated. Please contribute: but keep in mind that it is always better to skip a translation (use the cancel button) than logging something you are not sure about, or only partially translating an entry.</li>
            <li><strong>Translate everything</strong>.<br/>Directly related to point 1 above: please strive to translate all the nuances of the English version for a word (there should be more or less as many comma-separated nuances in your language as in the English definition). Partial translations are particularly hard to notice (and fix) once they are in.</li>
            <li><strong>Check with <a href="http://translate.google.com">Google Translate</a></strong>.<br/>WAIT! Come back! I did not say <em>rely</em> on Google Translate: first translate on your own, <em>then</em> double-check with Google Translate (and consider running it from both Japanese and English versions, into your native language). If Google Translate disagrees with you, there are good chances Google is wrong, but you should still make sure you are not missing anything: it is very easy to mistranslate something, particularly in languages that are deceptively close to English (I am looking in your direction, French and German).</li>
            <li><strong>Use a spell-checker</strong> (or a monolingual dictionary) on your translations.<br/>Wouldn't want to be that language-learning tool with misspellings, would we...</li>
            <li><strong>Follow closely the structure of the English version</strong><br/>Particularly with regard to the number of <a href="http://en.wikipedia.org/wiki/Word_sense">senses</a> (separate and generally <em>unrelated</em> meanings of a word, marked by ①, ② etc). Do not split existing senses or create your own unless you are absolutely sure, and in this case, you should use the feedback form to request a correction of the original English version (in case you are adding a new sense or modifying one from the English version, please cite an external source to justify the change).</li>
            <li><strong>Mind your typography</strong><br/>As a convention, nuances within a sense must be separated by commas (&ldquo;,&rdquo;), <em>not</em> semi-colons (&ldquo;;&rdquo;). In most languages (English, French, German, Spanish...), typographic rule for commas is: one space after, <em>none</em> before. Check with your language and mind your spacing.</li>
            <li><strong>No capitalisation</strong><br/>Unless proper nouns or otherwise required by language (e.g. nouns in German), words go uncapitalised.</li>
            <li><strong>Use consistent abreviations and dictionary terms</strong><br/>See table below (and please send me those for your language).</li>
            <li><strong>頑張って！</strong><br/>Do not get scared by the seemingly endless number of rules and tips above: we all make mistakes and as long as you give it your honest best, all efforts are immensely appreciated!</li>
        </ol>

        <p>
        <h3>Translations of common dictionary terms</h3>
        <em>Ongoing (please send for your language).</em>
        <ul>
            <li>French:<ul>
                    <li><span class="trans-src">e.g.</span> <span class="trans-target">par ex.</span></li>
                    <li><span class="trans-src">i.e.</span> <span class="trans-target">c.-à-d.</span></li>
                    <li><span class="trans-src">speaker</span> <span class="trans-target">locuteur</span></li>
                    <li><span class="trans-src">listener</span> <span class="trans-target">interlocuteur</span></li>
                </ul>
            </li>
        </ul>
        </p>

        <h3>Can think of anything else? <a href="https://www.facebook.com/davedv">Contact me</a> about it!</h3>
    </div>
    <div id="translators" class="scoreboard">
        <table class="twocols">
            <tr><td>
                    <h2>Vocab</h2>
                    <?php
                    $query = 'SELECT du.user_id, fb_id, first_name, last_name, prefs, u.privileges, COUNT(*) as c FROM `data_updates` du LEFT JOIN users u ON du.user_id = u.id LEFT JOIN users_ext ux ON ux.user_id = u.id WHERE table_name = \'jmdict_ext\' AND applied = 1 AND reviewed = 1 AND need_work = 0 GROUP BY user_id ORDER BY COUNT(*) DESC LIMIT 10';
                    try {
                        $stmt = DB::getConnection()->prepare($query);
                        $stmt->execute();
                        while ($row = $stmt->fetchObject()) {
                            echo '<div class="user"><fb:profile-pic uid="' . $row->fb_id . '" size="square" linked="true"></fb:profile-pic>';
                            echo '<div class="score_rank">' . $row->c . '</div>';
                            echo '<p>' . ($row->privileges > 0 ? '★ ' : '') . '<fb:name uid=' . $row->fb_id . ' capitalize="true" reflexive="true"></fb:name></p>';
                            echo '<div style="clear: both;"></div>';
                            echo '</div>';
                        }
                        $stmt = null;
                    } catch (PDOException $e) {
                        log_db_error($query, $e->getMessage(), false, true);
                    }
                    ?>
                </td>
                <td><h2>Kanji</h2>
                    <?php
                    $query = 'SELECT du.user_id, fb_id, first_name, last_name, prefs, u.privileges, COUNT(*) as c FROM `data_updates` du LEFT JOIN users u ON du.user_id = u.id LEFT JOIN users_ext ux ON ux.user_id = u.id WHERE table_name = \'kanjis_ext\' AND applied = 1 AND reviewed = 1 AND need_work = 0 GROUP BY user_id ORDER BY COUNT(*) DESC LIMIT 10';
                    try {
                        $stmt = DB::getConnection()->prepare($query);
                        $stmt->execute();
                        while ($row = $stmt->fetchObject()) {
                            echo '<div class="user">';
                            if ($row->fb_id) {
                                echo '<fb:profile-pic uid="' . $row->fb_id . '" size="square" linked="true"></fb:profile-pic>';
                            }
                            echo '<div class="score_rank">' . $row->c . '</div>';
                            echo '<p>' . ($row->privileges > 0 ? '★ ' : '');
                            if ($row->fb_id) {
                                echo '<fb:name uid="' . $row->fb_id . '" capitalize="true" reflexive="true"></fb:name>';
                            } elseif ($row->first_name) {
                                echo $row->first_name;
                            } else {
                                echo '<i>unknown</i>';
                            }
                            echo '</p>';
                            echo '<div style="clear: both;"></div>';
                            echo '</div>';
                        }
                        $stmt = null;
                    } catch (PDOException $e) {
                        log_db_error($query, $e->getMessage(), false, true);
                    }
                    ?>
                </td>
            </tr>
        </table>

    </div>
    <div id="progress_kanji"></div>
    <div id="progress_vocab"></div>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        $('#tabs').tabs().tabs("select", <?php
                    if (!empty($params['tab'])) {
                        echo "'" . $params['tab'] . "'";
                    } else {
                        echo '0';
                    }
                    ?>);

        facebook_onload();
    });

</script>
