<?php
if (empty($_SESSION['user'])) {
    log_error('You need to be logged to access this function.', false, true);
}

$kanji = $params['kanji'];
if (empty($kanji)) {
    die('No kanji selected.');
}

$query = 'SELECT * FROM `kanjis` WHERE kanji = :kanji';
try {
    $stmt = DB::getConnection()->prepare($query);
    $stmt->bindValue(':kanji', $kanji, PDO::PARAM_STR);

    $row = $stmt->fetchObject();
    if (!$row) {
        die('Unknown kanji: ' . $kanji);
    }
} catch (PDOException $e) {
    log_db_error($query, $e->getMessage(), false, true);
}
?>
<div class="zoom"><span class="japanese"><?php echo $row->kanji?></span></div>
<div class="details">
    <strong><?php echo Kanji::get_meaning_str($row->id)?></strong><br/>
<?php echo Kanji::get_pronunciations($row)?><br/>
    <strong>Level: </strong><?php echo $row->njlpt;?>級
</div>
<div style="clear:both;" />