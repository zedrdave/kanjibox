<?php
// die('Print outs are temporarily disabled. Update coming soon.');

require_once '../libs/lib.php';
require_once ABS_PATH . get_mode() . '.config.php';

global $logged_in;
$logged_in = init_app();

if (!isset($_POST) || count($_POST) == 0 || count($_SESSION) == 0) {
    die('please do not call this page directly: use the main interface');
}

include_css('printout.css');

if (isset($_REQUEST['newtoo'])) {
    $extra = ' OR l.curve IS NULL';
} else {
    $extra = '';
}
$show_ex = false;
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
    <head>
        <title>Kanji Box Printout</title>
        <meta http-equiv="Content-type" content="text/html; charset=utf-8" />
    </head>
    <body>
<?php
if (isset($_REQUEST['examples']) && $_REQUEST['examples'] > 0) {
    /*
      SELECT * FROM kanjis k LEFT JOIN kanji2word k2w ON k2w.kanji_id = k.id JOIN kanji2word k2w2 ON k2w2.kanji_id = k.id AND k2w2.pri < k2w.pri WHERE k.jlpt >= '4' GROUP BY k.id, k2w.kanji_id, k2w.word_id HAVING COUNT(k2w2.kanji_id) < 3


     */
    $query = "SELECT k.kanji, l.curve, GROUP_CONCAT( DISTINCT p.pron SEPARATOR ', ' ) AS pron, GROUP_CONCAT( DISTINCT e.meaning SEPARATOR ', ' ) AS mean, GROUP_CONCAT(DISTINCT CONCAT('<span class=\"example_kanji\">', ex.word, '</span> [', ex.reading, '] : ', jx.gloss_english) SEPARATOR '<br/>') AS example
FROM
	(
	SELECT k.id, k.kanji, k2w.word_id
	FROM kanjis k
	LEFT JOIN kanji2word k2w ON k2w.kanji_id = k.id
	JOIN kanji2word k2w2 ON k2w2.kanji_id = k.id
	AND k2w2.pri > k2w.pri
	WHERE k.njlpt >= '" . mysql_real_escape_string((int) $_REQUEST['njlpt']) . "'
	GROUP BY k.id, k2w.kanji_id, k2w.word_id
	HAVING COUNT( k2w2.kanji_id ) <= " . mysql_real_escape_string((int) $_REQUEST['examples']) . "
	) AS k
	LEFT JOIN jmdict ex ON ex.id = k.word_id
	LEFT JOIN jmdict_ext jx ON jx.jmdict_id = ex.id
	LEFT JOIN learning l ON l.kanji_id = k.id AND l.user_id = '" . mysql_real_escape_string($_SESSION['user']->getID()) . "'
	LEFT JOIN pron p ON p.kanji_id = k.id AND p.type != 'nanori'
	LEFT JOIN english e ON e.kanji_id = k.id
WHERE (
	l.curve > '" . mysql_real_escape_string((int) $_REQUEST['curve']) . "' $extra
)
GROUP BY k.id
ORDER BY p.type DESC , l.curve DESC";
    $show_ex = true;
} elseif ($_REQUEST['type'] == 'kanji') {
    $query = 'SELECT k.kanji, l.curve, GROUP_CONCAT(DISTINCT p.pron SEPARATOR \', \') AS pron, GROUP_CONCAT(DISTINCT e.meaning SEPARATOR \', \') AS mean FROM kanjis k LEFT JOIN learning l ON l.kanji_id = k.id AND l.user_id = \'' . $_SESSION['user']->getID() . '\' RIGHT JOIN pron p ON p.kanji_id = k.id AND p.type != \'nanori\' RIGHT JOIN english e ON e.kanji_id = k.id WHERE k.njlpt >= \'' . mysql_real_escape_string((int) $_REQUEST['njlpt']) . '\' AND (l.curve > \'' . mysql_real_escape_string((int) $_REQUEST['curve']) . '\') GROUP BY k.id ORDER BY p.type DESC, l.curve DESC';
} elseif ($_REQUEST['type'] == 'vocab') {
    $query = 'SELECT j.word, j.reading, l.curve, jx.gloss_english AS gloss FROM jmdict j LEFT JOIN jmdict_learning l ON l.jmdict_id = j.id AND l.user_id = ' . $_SESSION['user']->getID() . ' RIGHT JOIN jmdict_ext jx ON jx.jmdict_id = j.id WHERE j.njlpt >= \'' . mysql_real_escape_string((int) $_REQUEST['njlpt']) . '\' AND (l.curve > \'' . mysql_real_escape_string((int) $_REQUEST['curve']) . '\'' . $extra . ') GROUP BY j.id ORDER BY l.curve DESC';
} else {
    die('unknown list type');
}

$res = mysql_query($query) or log_db_error($query, false, true);
$tot = mysql_num_rows($res);

if ($tot) {
    echo "<h2>" . ($_REQUEST['njlpt']) . "-kyuu " . ($_REQUEST['type'] == 'kanji' ? 'Kanjis' : 'Vocab') . " (total: $tot)</h2>"
    ?>
            <table class="printout_table">
            <?php
            while ($row = mysql_fetch_assoc($res)) {
                if ($_REQUEST['type'] == 'kanji') {
                    ?>
                        <tr>
                            <td class="kanji"><?php echo $row['kanji'] ?></td>
                            <td class="meaning<?php if ($show_ex) echo '_show_ex' ?>"><?php echo $row['mean'] ?></td>
                            <td><?php echo $row['pron'] ?></td>
                        <?php
                        if ($show_ex) {
                            ?>
                                <td class="examples"><?php echo $row['example'] ?></td>
                            <?php
                        }
                        ?>
                        </tr>
            <?php
        } else {
            ?>
                        <tr>
                            <td class="word"><?php echo $row['word'] ?></td>
                            <td class="reading"><?php echo $row['reading'] ?></td>
                            <td class="gloss"><?php echo $row['gloss'] ?></td>
                        </tr>
                            <?php
                        }
                    }
                    ?>
            </table>
                <?php
            } else {
                echo "No kanjis/vocab matching your criteria: try again with a higher level or a better score...";
            }
            ?>

    </body>
</html>