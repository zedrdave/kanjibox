<?php

if (empty($_SESSION['user']) || !$_SESSION['user']->isEditor()) {
    die('editors only');
}

if (!empty($_REQUEST['validate']) && !empty($_REQUEST['update_id'])) {
    $update_id = (int) $_REQUEST['update_id'];
    $need_work = (int) $_REQUEST['need_work'];

    $query = 'SELECT update_id FROM data_updates WHERE (table_name, id_name, id_value, id_name_2, id_val_2, col_name) = (SELECT table_name, id_name, id_value, id_name_2, id_val_2, col_name FROM data_updates WHERE update_id = :update_id) AND ts <= (SELECT ts FROM data_updates WHERE update_id = :update_id)';
    try {
        $stmt = DB::getConnection()->prepare($query);
        $stmt->bindValue(':update_id', $update_id, PDO::PARAM_STR);
        $stmt->execute();
        $rowCount = $stmt->rowCount();

        if ($rowCount == 0) {
            echo 'No updates selected';
        } elseif ($rowCount > 50) {
            echo "Too many updates selected ($rowCount)";
        } else {
            $update_ids = $stmt->fetchAll(PDO::FETCH_OBJ);

            $query = 'SELECT * FROM data_updates WHERE update_id = :update_id';
            try {
                $stmt = DB::getConnection()->prepare($query);
                $stmt->bindValue(':update_id', $update_id, PDO::PARAM_STR);
                $row = $stmt->fetchObject();
            } catch (PDOException $e) {
                log_db_error($query, $e->getMessage(), true, true);
            }

            if (!$row->applied || ($need_work != (substr($row->new_value, 0, 3) == '(~)'))) {
                $table_name = mysql_real_escape_string($row->table_name);
                $col_name = mysql_real_escape_string($row->col_name);
                $id_name = mysql_real_escape_string($row->id_name);
                $_table_name = "`" . str_replace("`", "", $row->table_name) . "`";
                $_col_name = "`" . str_replace("`", "", $row->col_name) . "`";
                $_id_name = "`" . str_replace("`", "", $row->id_name) . "`";
                $id_value = (int) $row->id_value;

                if ($row->id_name_2) {
                    $select_id_2 = ' AND `' . str_replace("`", "", $row->id_name_2) . "` = '" . mysql_real_escape_string($row->id_val_2) . "'";
                } else {
                    $select_id_2 = '';
                }

                $query = "UPDATE $_table_name SET $_col_name = '" . ($need_work ? '(~)' : '') . mysql_real_escape_string($row->new_value) . "' WHERE $_id_name = $id_value $select_id_2";

                $res = mysql_query($query) or die(mysql_error());
            }

            $query = "UPDATE data_updates SET applied = 1, reviewed = 1, need_work = $need_work WHERE update_id IN (" . implode(',',
                    $update_ids) . ")";
            $res = mysql_query($query) or die(mysql_error());

            echo "<div><div id=\"data_update_success\">Validated $c update(s).</div></div>";
        }
        $stmt = null;
    } catch (PDOException $e) {
        log_db_error($query, $e->getMessage(), false, true);
    }




    $query = "SELECT update_id FROM data_updates WHERE (table_name, id_name, id_value, id_name_2, id_val_2, col_name) = (SELECT table_name, id_name, id_value, id_name_2, id_val_2, col_name FROM data_updates WHERE update_id = $update_id) AND ts <= (SELECT ts FROM data_updates WHERE update_id = $update_id)";
    $res = mysql_query($query) or die(mysql_error());

    $c = mysql_num_rows($res);

    if ($c == 0)
        echo "No updates selected";
    elseif ($c > 50) {
        echo "Too many updates selected ($c)";
    } else {
        $update_ids = array();
        while ($row = mysql_fetch_object($res))
            $update_ids[] = $row->update_id;
////
        $query = "SELECT * FROM data_updates WHERE update_id = $update_id";
        $res = mysql_query($query) or die(mysql_error());
        $row = mysql_fetch_object($res);

        if (!$row->applied || ($need_work != (substr($row->new_value, 0, 3) == '(~)'))) {
            $table_name = mysql_real_escape_string($row->table_name);
            $col_name = mysql_real_escape_string($row->col_name);
            $id_name = mysql_real_escape_string($row->id_name);
            $_table_name = "`" . str_replace("`", "", $row->table_name) . "`";
            $_col_name = "`" . str_replace("`", "", $row->col_name) . "`";
            $_id_name = "`" . str_replace("`", "", $row->id_name) . "`";
            $id_value = (int) $row->id_value;

            if ($row->id_name_2)
                $select_id_2 = ' AND `' . str_replace("`", "", $row->id_name_2) . "` = '" . mysql_real_escape_string($row->id_val_2) . "'";
            else
                $select_id_2 = '';

            $query = "UPDATE $_table_name SET $_col_name = '" . ($need_work ? '(~)' : '') . mysql_real_escape_string($row->new_value) . "' WHERE $_id_name = $id_value $select_id_2";
            // $query = "SELECT * FROM $_table_name WHERE $_id_name = $id_value" . $select_id_2;
            $res = mysql_query($query) or die(mysql_error());
        }

        $query = "UPDATE data_updates SET applied = 1, reviewed = 1, need_work = $need_work WHERE update_id IN (" . implode(',',
                $update_ids) . ")";
        $res = mysql_query($query) or die(mysql_error());

        echo "<div><div id=\"data_update_success\">Validated $c update(s).</div></div>";
    }
}



if (@$_REQUEST['revert'] && @$_REQUEST['update_id']) {
    $update_id = (int) $_REQUEST['update_id'];

    $query = "SELECT update_id FROM data_updates WHERE (table_name, id_name, id_value, id_name_2, id_val_2, col_name) = (SELECT table_name, id_name, id_value, id_name_2, id_val_2, col_name FROM data_updates WHERE update_id = $update_id) AND ts >= (SELECT ts FROM data_updates WHERE update_id = $update_id)";
    $res = mysql_query($query) or die(mysql_error());

    $c = mysql_num_rows($res);
    $update_ids = array();
    while ($row = mysql_fetch_object($res))
        $update_ids[] = $row->update_id;

    if ($c == 0)
        echo "No updates selected";
    elseif ($c > 10) {
        echo "Too many updates selected ($c)";
    } else {
        $query = "SELECT * FROM data_updates WHERE update_id = $update_id";
        $res = mysql_query($query) or die(mysql_error());
        $row = mysql_fetch_object($res);
        $restored_value = $row->old_value;

        $table_name = mysql_real_escape_string($row->table_name);
        $col_name = mysql_real_escape_string($row->col_name);
        $id_name = mysql_real_escape_string($row->id_name);
        $_table_name = "`" . str_replace("`", "", $row->table_name) . "`";
        $_col_name = "`" . str_replace("`", "", $row->col_name) . "`";
        $_id_name = "`" . str_replace("`", "", $row->id_name) . "`";
        $id_value = (int) $row->id_value;

        if ($row->id_name_2)
            $select_id_2 = ' AND `' . str_replace("`", "", $row->id_name_2) . "` = '" . mysql_real_escape_string($row->id_val_2) . "'";
        else
            $select_id_2 = '';

        $query = "UPDATE $_table_name SET $_col_name = '" . mysql_real_escape_string($restored_value) . "' WHERE $_id_name = $id_value $select_id_2";
        // $query = "SELECT * FROM $_table_name WHERE $_id_name = $id_value" . $select_id_2;
        $res = mysql_query($query) or die(mysql_error());

        $query = "UPDATE data_updates SET applied = 0 WHERE update_id IN (" . implode(',', $update_ids) . ")";
        $res = mysql_query($query) or die(mysql_error());


        echo "<div><div id=\"data_update_success\">Reverted $c update(s).</div><br/>Restored to value: <i>$restored_value</i></div>";
    }
}

if (@$_REQUEST['discard'] && @$_REQUEST['update_id']) {
    $update_id = (int) $_REQUEST['update_id'];

    $query = "SELECT update_id FROM data_updates WHERE (table_name, id_name, id_value, id_name_2, id_val_2, col_name) = (SELECT table_name, id_name, id_value, id_name_2, id_val_2, col_name FROM data_updates WHERE update_id = $update_id) AND ts >= (SELECT ts FROM data_updates WHERE update_id = $update_id)";
    $res = mysql_query($query) or die(mysql_error());

    $c = mysql_num_rows($res);
    $update_ids = array();
    while ($row = mysql_fetch_object($res))
        $update_ids[] = $row->update_id;

    if ($c == 0)
        echo "No updates selected";
    elseif ($c > 10) {
        echo "Too many updates selected ($c)";
    } else {
        $query = "DELETE FROM data_updates WHERE update_id IN (" . implode(',', $update_ids) . ")";
        $res = mysql_query($query) or die(mysql_error());


        echo "<div><div id=\"data_update_success\">Deleted $c update(s).</div></div>";
    }
}
?>
