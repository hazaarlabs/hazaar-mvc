<?php

/**
 * @file        Hazaar/DBI/Result.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

/**
 * @brief Relational database namespace
 */
namespace Hazaar\DBI;

/**
 * @brief Relational Database Interface - Result Class
 */
class Result implements \ArrayAccess, \Countable, \Iterator {

    /**
     * The PDO statement object.
     *
     * @var $statement
     */
    private $statement;

    private $record;

    private $records;

    private $wakeup = false;

    /**
     * Flag to remember if we need to reset the statement when using array access methods.
     * A reset is required once an
     * 'execute' is made and then rows are accessed. If no rows are accessed then a reset is not required. This
     * prevents a query from being executed multiple times when it's not necessary.
     */
    private $reset = true;

    private $json_columns = array();

    function __construct(\PDOStatement $statement) {

        $this->statement = $statement;
        
        for($i = 0; $i < $this->statement->columnCount(); $i++) {
            
            $meta = $this->statement->getColumnMeta($i);
            
            $key = $meta['name'];
            
            if ($meta['pdo_type'] == \PDO::PARAM_STR && (substr(ake($meta, 'native_type'), 0, 4) == 'json' || (!array_key_exists('native_type', $meta) && in_array('blob', ake($meta, 'flags')))))
                $this->json_columns[] = $meta['name'];
        }
    
    }

    public function bindColumn($column, &$param, $type, $maxlen, $driverdata) {

        return $this->statement->bindColumn($column, $param, $type, $maxlen, $driverdata);
    
    }

    public function bindParam($parameter, &$variable, $data_type = \PDO::PARAM_STR, $length, $driver_options) {

        return $this->statement->bindParam($parameter, $variable, $data_type, $length, $driver_options);
    
    }

    public function bindValue($parameter, $value, $data_type = \PDO::PARAM_STR) {

        return $this->statement->bindValue($parameter, $value, $data_type);
    
    }

    public function closeCursor() {

        return $this->statement->closeCursor();
    
    }

    public function columnCount() {

        return $this->statement->columnCount();
    
    }

    public function debugDumpParams() {

        return $this->statement->debugDumpParams();
    
    }

    public function errorCode() {

        return $this->statement->errorCode();
    
    }

    public function errorInfo() {

        return $this->statement->errorInfo();
    
    }

    public function execute($input_parameters) {

        if (!is_array($input_parameters))
            $input_parameters = array(
                $input_parameters
            );
        
        $this->reset = false;
        
        return $this->statement->execute($input_parameters);
    
    }

    public function fetch($fetch_style, $cursor_orientation = \PDO::FETCH_ORI_NEXT, $cursor_offset = 0) {

        $this->reset = true;
        
        return $this->statement->fetch($fetch_style, $cursor_orientation, $cursor_offset);
    
    }

    public function fetchAll($fetch_style = \PDO::FETCH_ASSOC, $fetch_argument = null, $ctor_args = array()) {

        $this->reset = true;
        
        if ($fetch_argument !== null) {
            
            return $this->statement->fetchAll($fetch_style, $fetch_argument, $ctor_args);
        }
        
        return $this->statement->fetchAll($fetch_style);
    
    }

    public function fetchColumn($column_number = 0) {

        $this->reset = true;
        
        return $this->statement->fetchColumn($column_number);
    
    }

    public function fetchObject($class_name = "stdClass", $ctor_args) {

        $this->reset = true;
        
        return $this->statement->fetchObject($class_name, $ctor_args);
    
    }

    public function getAttribute($attribute) {

        return $this->statement->getAttribute($attribute);
    
    }

    public function getColumnMeta($column) {

        return $this->statement->getColumnMeta($column);
    
    }

    private function fix(&$record) {

        if (!$record)
            return null;
        
        if (count($this->json_columns) > 0) {
            
            foreach($this->json_columns as $col)
                $record[$col] = json_decode($record[$col], true);
        }
        
        return $record;
    
    }

    public function nextRowset() {

        $this->reset = true;
        
        return $this->statement->nextRowset();
    
    }

    public function rowCount() {

        return $this->statement->rowCount();
    
    }

    public function setAttribute($attribute, $value) {

        return $this->statement->setAttribute($attribute, $value);
    
    }

    public function setFetchMode($mode) {

        return $this->statement->setFetchMode($mode);
    
    }

    public function __get($key) {

        if (!$this->record) {
            
            $this->reset = true;
            
            $this->next();
        }
        
        return $this->record[$key];
    
    }

    public function all() {

        $this->reset = true;
        
        return $this->statement->fetchAll(\PDO::FETCH_ASSOC);
    
    }

    public function row() {

        $this->reset = true;
        
        return $this->statement->fetch(\PDO::FETCH_ASSOC);
    
    }

    private function store() {

        if (!$this->wakeup) {
            
            $this->records = $this->statement->fetchAll(\PDO::FETCH_ASSOC);
            
            foreach($this->records as &$record)
                $this->fix($record);
            
            $this->wakeup = true;
            
            $this->reset = true;
        }
    
    }

    public function __sleep() {

        $this->store();
        
        return array(
            'records'
        );
    
    }

    public function __wakeup() {

        $this->wakeup = true;
    
    }

    /*
     * Array Access
     */
    public function offsetExists($offset) {

        if ($this->wakeup) {
            
            $record = current($this->records);
            
            return array_key_exists($offset, $record);
        }
        
        return (is_array($this->record) && array_key_exists($offset, $this->record));
    
    }

    public function offsetGet($offset) {

        if ($this->wakeup) {
            
            $record = current($this->records);
            
            return $record[$offset];
        }
        
        return $this->__get($offset);
    
    }

    public function offsetSet($offset, $value) {

        throw new \Exception('Updating a value in a database result is not supported!');
    
    }

    public function offsetUnset($offset) {

        throw new \Exception('Unsetting a value in a database result is not supported!');
    
    }

    /*
     * Iterator
     */
    public function current() {

        if ($this->wakeup)
            return current($this->records);
        
        return $this->record;
    
    }

    public function key() {

        return key($this->record);
    
    }

    public function next() {

        if ($this->wakeup)
            return next($this->records);
        
        $this->reset = true;
        
        $this->record = $this->statement->fetch(\PDO::FETCH_ASSOC);
        
        return $this->fix($this->record);
    
    }

    public function rewind() {

        if ($this->wakeup)
            return reset($this->records);
        
        if ($this->reset == true)
            $this->statement->execute();
        
        $this->record = $this->statement->fetch(\PDO::FETCH_ASSOC);
        
        return $this->fix($this->record);
    
    }

    public function valid() {

        if ($this->wakeup)
            return (current($this->records));
        
        return is_array($this->record);
    
    }

    /*
     * Countable
     */
    public function count() {

        return $this->statement->rowCount();
    
    }

}

