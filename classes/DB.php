<?php

class DB
{

    /**
     * Returns the *Singleton* instance of this class.
     *
     * @staticvar Singleton $instance The *Singleton* instances of this class.
     *
     * @return Singleton The *Singleton* instance.
     */
    public static function getConnection()
    {
        static $instance = null;
        if (null === $instance) {
            try {
                $instance = new PDO('mysql:host=' . $GLOBALS['db_ip'] . ';dbname=' . $GLOBALS['db_name'] . ';charset=utf8',
                    $GLOBALS['db_user'], $GLOBALS['db_pass']);
                $instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (Exception $e) {
                log_error('DB Connection failed.', $e->getMessage(), true);
            }
        }
        return $instance;
    }

    public static function count($query, $params = [])
    {
        try {
            $stmt = self::getConnection()->prepare($query);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            log_db_error($query, $e->getMessage(), false, true);
            return false;
        }
    }

    public static function insert($query, $params = [])
    {
        try {
            $stmt = self::getConnection()->prepare($query);
            $stmt->execute($params);
            return self::getConnection()->lastInsertId();
        } catch (PDOException $e) {
            log_db_error($query, $e->getMessage(), false, true);
            return false;
        }
    }

    public static function update($query, $params = [])
    {
        try {
            $stmt = self::getConnection()->prepare($query);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            log_db_error($query, $e->getMessage(), false, true);
            return false;
        }
    }    
    
    public static function delete($query, $params = [])
    {
        try {
            $stmt = self::getConnection()->prepare($query);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            log_db_error($query, $e->getMessage(), false, true);
            return false;
        }
    }

    /**
     * Protected constructor to prevent creating a new instance of the
     * *Singleton* via the `new` operator from outside of this class.
     */
    protected function __construct()
    {
        
    }

    /**
     * Private clone method to prevent cloning of the instance of the
     * *Singleton* instance.
     *
     * @return void
     */
    private function __clone()
    {
        
    }

    /**
     * Private unserialize method to prevent unserializing of the *Singleton*
     * instance.
     *
     * @return void
     */
    private function __wakeup()
    {
        
    }
}
