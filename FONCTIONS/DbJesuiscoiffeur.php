<?php

require_once(realpath(dirname(__FILE__) . '/../CONFIG/prestashop.php'));
require_once(realpath(dirname(__FILE__) . '/Db.php'));

class DbJesuiscoiffeur extends Db {
    public static $SQL_USER = JSC_DB_USER;
    public static $SQL_HOST = JSC_DB_HOST;
    public static $SQL_PASSWD = JSC_DB_PASSWD;
    public static $SQL_DB = JSC_DB_NAME;

    protected function __construct() {
        $this->pdo_instance = new PDO('mysql:dbname='.self::$SQL_DB.';host='.self::$SQL_HOST,self::$SQL_USER ,self::$SQL_PASSWD);
    }

    public static function getInstance() {
        if(is_null(self::$instance))
            self::$instance = new DbJesuiscoiffeur();

        return self::$instance;
    }
}
