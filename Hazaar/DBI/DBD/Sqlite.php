<?php

namespace Hazaar\DBI\DBD;

class Sqlite extends BaseDriver {

    private $conn;

    public function connect($dsn, $username = null, $password = null, $driver_options = null) {

        $d_pos = strpos($dsn, ':');
        
        $driver = strtolower(substr($dsn, 0, $d_pos));
        
        if (!$driver == 'sqlite')
            return false;
        
        $this->conn = new \PDO($dsn, $username, $password, $driver_options);
        
        return true;
    
    }

    public function beginTransaction() {

        return $this->conn->beginTransaction();
    
    }

    public function commit() {

        return $this->conn->commit();
    
    }

    public function getAttribute($attribute) {

        return $this->conn->getAttribute($attribute);
    
    }

    public function inTransaction() {

        return $this->conn->inTransaction();
    
    }

    public function lastInsertId() {

        return $this->conn->lastInsertId();
    
    }

    public function quote($string) {

        if ($string instanceof \Hazaar\Date) {
            
            $string = $string->timestamp();
        }
        
        if (!is_numeric($string)) {
            
            $string = $this->conn->quote((string) $string);
        }
        
        return $string;
    
    }

    public function rollBack() {

        return $this->conn->rollback();
    
    }

    public function setAttribute($attribute, $value) {

        return $this->conn->setAttribute($attribute, $value);
    
    }

    public function errorCode() {

        return $this->conn->errorCode();
    
    }

    public function errorInfo() {

        return $this->conn->errorInfo();
    
    }

    public function exec($sql) {

        return $this->conn->exec($sql);
    
    }

    public function query($sql) {

        return $this->conn->query($sql);
    
    }

    public function prepare($sql) {

        return $this->conn->prepare($sql);
    
    }

}


