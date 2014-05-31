<?php

if (empty($_SESSION['user']) || !$_SESSION['user']->isEditor()) {
    die('editors only');
}

if (!empty($_REQUEST['validate']) && !empty($_REQUEST['update_id'])) {
    $updateID = (int) $_REQUEST['update_id'];
    $needWork = (int) $_REQUEST['need_work'];

    $query = 'SELECT update_id FROM data_updates WHERE (table_name, id_name, id_value, id_name_2, id_val_2, col_name) = (SELECT table_name, id_name, id_value, id_name_2, id_val_2, col_name FROM data_updates WHERE update_id = :update_id) AND ts <= (SELECT ts FROM data_updates WHERE update_id = :update_id)';
    try {
        $stmt = DB::getConnection()->prepare($query);
        $stmt->bindValue(':update_id', $updateID, PDO::PARAM_INT);
        $stmt->execute();
        $rowCount = $stmt->rowCount();

        $stmt = null;

        if ($rowCount == 0) {
            echo 'No updates selected';
        } elseif ($rowCount > 50) {
            echo 'Too many updates selected (' . $rowCount . ')';
        } else {
            $updateIDs = $stmt->fetchAll(PDO::FETCH_OBJ);

            $query = 'SELECT * FROM data_updates WHERE update_id = :update_id';
            try {
                $stmt = DB::getConnection()->prepare($query);
                $stmt->bindValue(':update_id', $updateID, PDO::PARAM_INT);
                $row = $stmt->fetchObject();
                $stmt = null;
            } catch (PDOException $e) {
                log_db_error($query, $e->getMessage(), true, true);
            }

            if (!$row->applied || ($needWork != (substr($row->new_value, 0, 3) == '(~)'))) {
                $table_name = DB::getConnection()->quote($row->table_name);
                $col_name = DB::getConnection()->quote($row->col_name);
                $id_name = DB::getConnection()->quote($row->id_name);
                $_table_name = '`' . str_replace('`', '', $row->table_name) . '`';
                $_col_name = '`' . str_replace('`', '', $row->col_name) . '`';
                $_id_name = '`' . str_replace('`', '', $row->id_name) . '`';
                $id_value = (int) $row->id_value;

                if ($row->id_name_2) {
                    $select_id_2 = ' AND `' . str_replace('`', '', $row->id_name_2) . '` = ' . DB::getConnection()->quote($row->id_val_2);
                } else {
                    $select_id_2 = '';
                }

                DB::update('UPDATE ' . $_table_name . ' SET ' . $_col_name . ' = :newvalue' . ' WHERE ' . $_id_name . ' = .' . $id_value . ' ' . $select_id_2,
                    [':newvalue' => ($needWork ? '(~)' : '') . $row->new_value]);
            }

            DB::update('UPDATE data_updates SET applied = 1, reviewed = 1, need_work = :needwork WHERE update_id IN (' . implode(',',
                    $updateIDs) . ')', [':needwork' => $needWork]);
            echo '<div><div id="data_update_success">Validated ' . $c . ' update(s).</div></div>';
        }
    } catch (PDOException $e) {
        log_db_error($query, $e->getMessage(), false, true);
    }

    $query = 'SELECT update_id FROM data_updates WHERE (table_name, id_name, id_value, id_name_2, id_val_2, col_name) = (SELECT table_name, id_name, id_value, id_name_2, id_val_2, col_name FROM data_updates WHERE update_id = :updateID) AND ts <= (SELECT ts FROM data_updates WHERE update_id = :updateID)';
    try {
        $stmt = DB::getConnection()->prepare($query);
        $stmt->bindValue(':updateID', $updateID, PDO::PARAM_INT);
        $stmt->execute();
        $c = $stmt->rowCount();

        if ($c == 0) {
            echo 'No updates selected';
        } elseif ($c > 50) {
            echo 'Too many updates selected (' . $c . ')';
        } else {
            $updateIDs = [];
            while ($row = mysql_fetch_object($res)) {
                $updateIDs[] = $row->update_id;
            }

            $query = 'SELECT * FROM data_updates WHERE update_id = :update_id';
            try {
                $stmt = DB::getConnection()->prepare($query);
                $stmt->bindValue(':update_id', $updateID, PDO::PARAM_INT);
                $stmt->execute();

                if (!$row->applied || ($needWork != (substr($row->new_value, 0, 3) == '(~)'))) {
                    $table_name = DB::getConnection()->quote($row->table_name);
                    $col_name = DB::getConnection()->quote($row->col_name);
                    $id_name = DB::getConnection()->quote($row->id_name);
                    $_table_name = '`' . str_replace('`', '', $row->table_name) . '`';
                    $_col_name = '`' . str_replace('`', '', $row->col_name) . '`';
                    $_id_name = '`' . str_replace('`', '', $row->id_name) . '`';
                    $id_value = (int) $row->id_value;

                    if ($row->id_name_2) {
                        $select_id_2 = ' AND `' . str_replace('`', '', $row->id_name_2) . '` = ' . DB::getConnection()->quote($row->id_val_2);
                    } else {
                        $select_id_2 = '';
                    }

                    DB::update('UPDATE ' . $_table_name . ' SET ' . $_col_name . ' = :new_value WHERE ' . $_id_name . ' = :id_value ' . $select_id_2,
                        [
                        ':new_value' => ($needWork ? '(~)' : '') . $row->new_value,
                        ':id_value' => $id_value
                        ]
                    );
                }

                $stmt = null;
            } catch (PDOException $e) {
                log_db_error($query, $e->getMessage());
            }

            DB::update('UPDATE data_updates SET applied = 1, reviewed = 1, need_work = :needWork WHERE update_id IN (' . implode(',',
                    $updateIDs) . ')', [':needWork' => $needWork]);

            echo '<div><div id="data_update_success">Validated ' . $c . ' update(s).</div></div>';
        }

        $stmt = null;
    } catch (PDOException $e) {
        log_db_error($query, $e->getMessage(), false, true);
    }
}

if ($_REQUEST['revert'] && $_REQUEST['update_id']) {
    $updateID = (int) $_REQUEST['update_id'];

    $query = "SELECT update_id FROM data_updates WHERE (table_name, id_name, id_value, id_name_2, id_val_2, col_name) = (SELECT table_name, id_name, id_value, id_name_2, id_val_2, col_name FROM data_updates WHERE update_id = $updateID) AND ts >= (SELECT ts FROM data_updates WHERE update_id = $updateID)";
    $res = mysql_query($query) or die(mysql_error());

    $c = mysql_num_rows($res);
    $updateIDs = [];
    while ($row = mysql_fetch_object($res)) {
        $updateIDs[] = $row->update_id;
    }

    if ($c == 0) {
        echo 'No updates selected';
    } elseif ($c > 10) {
        echo 'Too many updates selected (' . $c . ')';
    } else {
        $query = "SELECT * FROM data_updates WHERE update_id = $updateID";
        $res = mysql_query($query) or die(mysql_error());
        $row = mysql_fetch_object($res);
        $restored_value = $row->old_value;

        $table_name = DB::getConnection()->quote($row->table_name);
        $col_name = DB::getConnection()->quote($row->col_name);
        $id_name = DB::getConnection()->quote($row->id_name);
        $_table_name = "`" . str_replace("`", "", $row->table_name) . "`";
        $_col_name = "`" . str_replace("`", "", $row->col_name) . "`";
        $_id_name = "`" . str_replace("`", "", $row->id_name) . "`";
        $id_value = (int) $row->id_value;

        if ($row->id_name_2) {
            $select_id_2 = ' AND `' . str_replace("`", "", $row->id_name_2) . "` = '" . DB::getConnection()->quote($row->id_val_2) . "'";
        } else {
            $select_id_2 = '';
        }

        DB::update('UPDATE ' . $_table_name . ' SET ' . $_col_name . ' = :restored_value WHERE ' . $_id_name . ' = :idvalue ' . $select_id_2,
            [':restoredvalue' => $restored_value, ':idvalue' => $id_value]);
        DB::update('UPDATE data_updates SET applied = 0 WHERE update_id IN (' . implode(',', $updateIDs) . ')');

        echo '<div><div id="data_update_success">Reverted ' . $c . ' update(s).</div><br/>Restored to value: <i>' . $restored_value . '</i></div>';
    }
}

if ($_REQUEST['discard'] && $_REQUEST['update_id']) {
    $updateID = (int) $_REQUEST['update_id'];

    $query = "SELECT update_id FROM data_updates WHERE (table_name, id_name, id_value, id_name_2, id_val_2, col_name) = (SELECT table_name, id_name, id_value, id_name_2, id_val_2, col_name FROM data_updates WHERE update_id = $updateID) AND ts >= (SELECT ts FROM data_updates WHERE update_id = $updateID)";
    $res = mysql_query($query) or die(mysql_error());

    $c = mysql_num_rows($res);
    $updateIDs = [];
    while ($row = mysql_fetch_object($res)) {
        $updateIDs[] = $row->update_id;
    }

    if ($c == 0) {
        echo 'No updates selected';
    } elseif ($c > 10) {
        echo 'Too many updates selected (.' . $c . ')';
    } else {
        DB::delete('DELETE FROM data_updates WHERE update_id IN (' . implode(',', $updateIDs) . ')');
        echo '<div><div id="data_update_success">Deleted ' . $c . ' update(s).</div></div>';
    }
}
