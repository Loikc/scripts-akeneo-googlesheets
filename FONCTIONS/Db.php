<?php

abstract class Db {
    protected $pdo_instance = null;

    protected static $instance = null;

    protected abstract function __construct();

    public abstract static function getInstance();

    public function query($query) {
        $response = $this->pdo_instance->prepare($query);
        $success = $response->execute();

        if(!$success)
            return $response->errorInfo();

        return $response->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getValue($query) {
        $response = $this->pdo_instance->prepare($query . ' LIMIT 1');
        $success = $response->execute();

        if(!$success)
            return $response->errorInfo();

        $value = $response->fetch();

        return $value ? $value[0] : null;
    }
}
