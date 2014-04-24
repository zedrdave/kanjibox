<?php

class DB {

    /**
     * Returns the *Singleton* instance of this class.
     *
     * @staticvar Singleton $instance The *Singleton* instances of this class.
     *
     * @return Singleton The *Singleton* instance.
     */
    public static function getConnection() {
        static $instance = null;
        if (null === $instance) {
            try {
                $instance = new PDO('mysql:host=' . $GLOBALS['db_ip'] . ';dbname=' . $GLOBALS['db_name'] . ';charset=utf8', $GLOBALS['db_user'], $GLOBALS['db_pass']);
                $instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (Exception $e) {
                return 'PDO Connection failed: ' . $e->getMessage();
            }
        }
        return $instance;
    }

    /**
     * Protected constructor to prevent creating a new instance of the
     * *Singleton* via the `new` operator from outside of this class.
     */
    protected function __construct() {
        
    }

    /**
     * Private clone method to prevent cloning of the instance of the
     * *Singleton* instance.
     *
     * @return void
     */
    private function __clone() {
        
    }

    /**
     * Private unserialize method to prevent unserializing of the *Singleton*
     * instance.
     *
     * @return void
     */
    private function __wakeup() {
        
    }

}
