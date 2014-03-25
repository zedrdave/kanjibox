<?php
if ($_SESSION['user']->get_load_count() % 2 != 0) {
    //
    // <script type="text/javascript"><!--
    // google_ad_client = "pub-0683135406967625";
    //
    // google_ad_slot = "7734831001";
    // google_ad_width = 160;
    // google_ad_height = 600;
    // //-->
    // </script>
    // <script type="text/javascript"
    // src="http://pagead2.googlesyndication.com/pagead/show_ads.js">
    // </script>
    // kanjiteez_180x660.jpg
} else {
    ?>
    <div style="float:left; margin-top: 50px;">
        <?php
        $two_columns = true;
        switch (rand(0, 8)) {
            case 0:
                ?>
                <div class="ad_column">
                    <div class="title">Support KanjiBox...</div>
                    <br/>
                    <img src="<?php echo SERVER_URL . 'img/kb_iphone/cat_inside_small.jpg' ?>" class="illustration" />
                    <p style="font-size:100%;">It costs time and money to keep KanjiBox running.<br/><br/>If you like KanjiBox and would like to see more of it, consider donating some <a href="http://kanjibox.net/kb/page/faq/#contributing">time</a> or <a href="http://kanjibox.net/kb/page/faq/#donations">money</a>:</p>
                    <p style="text-align:center;">
                    <form action="https://www.paypal.com/cgi-bin/webscr" method="post" style="margin:10px auto 10px auto; text-align:center;">
                        <input type="hidden" name="cmd" value="_s-xclick" />
                        <input type="hidden" name="hosted_button_id" value="3445634" />
                        <input type="image" src="https://www.paypal.com/en_US/i/btn/btn_donate_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!" />
                        <img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1" />
                    </form>
                    </p>
                    <p><em>In addition to bringing <a href="http://kanjibox.net/kb/page/faq/#roadmap">shiny new features</a> faster, your donation will give you premium access to KanjiBox's <a href="http://kanjibox.net/kb/page/faq/#elite">Elite features</a>.</em></p>
                    <div style="clear:both" ></div>
                    <p class="dismiss"><a onclick="$('.ad_column').fadeOut();
                                        return false;" href="#">[dismiss]</a></p>
                </div>
                <?php
                break;
            case 1:
                ?>

                <div class="ad_column">
                    <div class="title">Donate, or we will drown this kitten.</div>
                    <br/>
                    <img src="<?php echo SERVER_URL . 'img/kb_iphone/kitten.jpg' ?>" class="illustration" />
                    <p style="font-size:100%;">It costs time and money to keep KanjiBox up and running.<br/><br/>If you like KanjiBox and would like to see more of it, consider donating some <a href="http://kanjibox.net/kb/page/faq/#contributing">time</a> or <a href="http://kanjibox.net/kb/page/faq/#donations">money</a>...</p>
                    <p><em>In addition to saving this helpless kitten's life and bringing <a href="http://kanjibox.net/kb/page/faq/#roadmap">shiny new features</a> faster to KanjiBox, your donation will give you premium access to KanjiBox's <a href="http://kanjibox.net/kb/page/faq/#elite">Elite features</a>.</em></p>
                    <div style="clear:both" ></div>
                    <p class="dismiss"><a onclick="$('.ad_column').fadeOut();
                                        return false;" href="#">[dismiss]</a></p>
                </div>
                <?php
                break;
            case 2:
            case 3:
            default:
                ?>

                <div class="ad_column">
                    <div class="title">Get KanjiBox on your iPhone!</div>
                    <br/>
                    <a href="http://kanjibox.net/ios/"><img src="<?php
                        echo SERVER_URL . 'img/kb_iphone/';
                        $imgs = array('kb_iphone_vocab.png', 'kb_iphone_main.png', 'kb_iphone_reading.png', 'kb_iphone_stats.png', 'kb_iphone_kanji_quiz.png', 'kb_iphone_kanji.png', 'kb_iphone_kanjidraw.png', 'kb_iphone_kanadraw.png');
                        echo($imgs[array_rand($imgs)]); //
                        ?>" /></a>
                    <p style="font-size:100%;">KanjiBox is also available as an offline application for iOS!<br/><br/>This portable version of KanjiBox includes handwriting practice and many other cool features!</p>
                    <p><em>No internet connection required...</em></p>
                    <p><em>Learn more about <a href="http://kanjibox.net/ios/">KanjiBox for iPhone</a> or go directly to the <a href="itms://itunes.apple.com/WebObjects/MZStore.woa/wa/viewSoftware?id=322311303&amp;mt=8&amp;s=143441">iTunes App Store</a> to purchase it.</em></p>
                    <div style="clear:both" ></div>
                    <p class="dismiss"><a onclick="$('.ad_column').fadeOut();
                                        return false;" href="#">[dismiss]</a></p>
                </div>
                <?php
                break;

            /* 		<div class="ad_column" style="border:none; padding:0 10px 0 0;width:168px;margin-right:10px;">
              <a href="http://www.kanjiteez.com"><img alt="KanjiTeez" src="<?php echo SERVER_URL; ?>img/kb_iphone/kanjiteez_180x660.jpg" /></a>
              <div style="clear:both" ></div>
              <p class="dismiss"><a onclick="$('.ad_column').fadeOut(); return false;" href="#">[dismiss]</a></p>
              </div>
             */
        }
        ?>
        <div style="clear:both;"></div>
    </div>

    <?php
}
