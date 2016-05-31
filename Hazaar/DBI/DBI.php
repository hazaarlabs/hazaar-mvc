<?php

/**
 * @file        Hazaar/DBI/DBI.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */
namespace Hazaar;

/**
 * @brief Relational Database Interface
 *
 * @detail The DB module provides classes for access to relational database via
 * "PDO":http://www.php.net/manual/en/book.pdo.php (PHP Data Object) drivers and classes. This
 * approach allows developers to use these classes to access a range of different database servers.
 *
 * PDO has supporting drivers for:
 *
 * * "PostgreSQL":http://www.postgresql.org
 * * "MySQL":http://www.mysql.com
 * * "SQLite":http://www.sqlite.org
 * * "MS SQL Server":http://www.microsoft.com/sqlserver
 * * "Oracle":http://www.oracle.com
 * * "IBM Informix":http://www.ibm.com/software/data/informix
 * * "Interbase":http://www.embarcadero.com/products/interbase
 *
 * Access to database functions is all done using a common class structure.
 *
 * h2. Example Usage
 *
 * <code>
 * $db = new Hazaar\DBI();
 * $result = $db->execute('SELECT * FROM users');
 * while($row = $result->fetch()){
 * //Do things with $row here
 * }
 * </code>
 */
class DBI {

    private static $default_config = array();

    private static $connections = array();

    private $driver;

    function __construct($config_env = NULL) {

        $config = NULL;
        
        if ($config_env == NULL || is_string($config_env)) {
            
            $config = $this->getDefaultConfig($config_env);
        } elseif (is_array($config_env)) {
            
            $config = new \Hazaar\Map($config_env);
        } elseif ($config_env instanceof \Hazaar\Map) {
            
            $config = $config_env;
        }
        
        if (\Hazaar\Map::is_array($config)) {
            
            if (!$config->has('dsn')) {
                
                $dsn = $config->driver . ':';
                
                $config->del('driver');
                
                $dsn .= $config->flatten('=', ';');
                
                $config->dsn = $dsn;
            }
            
            $this->connect($config->dsn, $config->user, $config->password);
        } else {
            
            throw new \Exception('No DBI configuration found!');
        }
    
    }

    static public function getDefaultConfig($env = NULL) {

        if (!array_key_exists($env, DBI::$default_config)) {
            
            DBI::$default_config[$env] = new \Hazaar\Application\Config('database.ini', $env);
        }
        
        return DBI::$default_config[$env];
    
    }

    public function connect($dsn, $username = NULL, $password = NULL, $driver_options = NULL) {

        $driver = ucfirst(substr($dsn, 0, strpos($dsn, ':')));
        
        if (!$driver)
            throw new DBI\Exception\DriverNotSpecified();
        
        if (!array_key_exists($driver, DBI::$connections))
            DBI::$connections[$driver] = array();
        
        $hash = md5(serialize(array(
            $driver,
            $dsn,
            $username,
            $password,
            $driver_options
        )));
        
        if (array_key_exists($hash, DBI::$connections)) {
            
            $this->driver = DBI::$connections[$hash];
        } else {
            
            $class = 'Hazaar\DBI\DBD\\' . $driver;
            
            if (!class_exists($class))
                throw new DBI\Exception\DriverNotFound($driver);
            
            $this->driver = new $class();
            
            if (!$driver_options)
                $driver_options = array();
            
            $driver_options = array_replace(array(
                \PDO::ATTR_STRINGIFY_FETCHES => FALSE,
                \PDO::ATTR_EMULATE_PREPARES => FALSE
            ), $driver_options);
            
            if (!($this->conn = $this->driver->connect($dsn, $username, $password, $driver_options)))
                throw new DBI\Exception\ConnectionFailed($dsn);
            
            DBI::$connections[$hash] = $this->driver;
        }
        
        return TRUE;
    
    }

    public function getDriver() {

        $class = get_class($this->driver);
        
        return substr($class, strrpos($class, '\\') + 1);
    
    }

    public function beginTransaction() {

        return $this->driver->beginTransaction();
    
    }

    public function commit() {

        return $this->driver->commit();
    
    }

    public function getAttribute($option) {

        return $this->driver->getAttribute($option);
    
    }

    public function getAvailableDrivers() {

        $drivers = array();
        
        $dir = new \Hazaar\File\Dir(dirname(__FILE__) . '/DBD');
        
        while($file = $dir->read()) {
            
            if (preg_match('/class (\w*) extends \\\Hazaar\\\DBI\\\BaseDriver\W/m', $file->getContents(), $matches)) {
                
                $drivers[] = $matches[1];
            }
        }
        
        return $drivers;
    
    }

    public function inTransaction() {

        return $this->driver->inTransaction();
    
    }

    public function lastInsertId() {

        return $this->driver->lastInsertId();
    
    }

    public function quote($string) {

        return $this->driver->quote($string);
    
    }

    public function rollBack() {

        return $this->driver->rollback();
    
    }

    public function setAttribute() {

        return $this->driver->setAttribute();
    
    }

    public function errorCode() {

        return $this->driver->errorCode();
    
    }

    public function errorInfo() {

        return $this->driver->errorInfo();
    
    }

    public function exec($sql) {

        return $this->driver->exec($sql);
    
    }

    public function query($sql) {

        return $this->driver->query($sql);
    
    }

    public function prepare($sql) {

        return $this->driver->prepare($sql);
    
    }

    public function exists($table, $criteria = array()) {

        return $this->table($table)->exists($criteria);
    
    }

    public function insert($table, $fields, $returning = NULL) {

        return $this->driver->insert($table, $fields, $returning);
    
    }

    public function update($table, $fields, $criteria = array()) {

        return $this->driver->update($table, $fields, $criteria);
    
    }

    public function delete($table, $criteria) {

        return $this->driver->delete($table, $criteria);
    
    }

    public function deleteAll($table) {

        return $this->driver->deleteAll($table);
    
    }

    public function __get($tablename) {

        return $this->table($tablename);
    
    }

    public function __call($tablename, $args) {

        $args = array_merge(array(
            $tablename
        ), $args);
        
        return call_user_func_array(array(
            $this,
            'table'
        ), $args);
    
    }

    public function table($name, $alias = NULL, $schema = NULL) {

        return new DBI\Table($this->driver, $name, $alias, $schema);
    
    }

    public function call($method, $args = array()) {

        $arglist = array();
        
        foreach($args as $arg)
            $arglist[] = (is_numeric($arg) ? $arg : $this->quote($arg));
        
        $sql = 'SELECT ' . $method . '(' . implode(',', $arglist) . ');';
        
        return $this->query($sql);
    
    }

    public function listTables() {

        return $this->driver->listTables();
    
    }

    public function tableExists($table, $schema = 'public') {

        return $this->driver->tableExists($table, $schema);
    
    }

    public function createTable($name, $columns, $schema = NULL) {

        return $this->driver->createTable($name, $columns, $schema);
    
    }

    public function describeTable($name, $schema = NULL, $sort = NULL) {

        return $this->driver->describeTable($name, $schema, $sort);
    
    }

    public function renameTable($from_name, $to_name, $schema = NULL) {

        return $this->driver->renameTable($from_name, $to_name, $schema);
    
    }

    public function dropTable($name, $schema = NULL) {

        return $this->driver->dropTable($name, $schema);
    
    }

    public function addColumn($table, $column_spec, $schema = NULL) {

        return $this->driver->addColumn($table, $column_spec, $schema);
    
    }

    public function alterColumn($table, $column, $column_spec, $schema = NULL) {

        return $this->driver->alterColumn($table, $column, $column_spec, $schema);
    
    }

    public function dropColumn($table, $column, $schema = NULL) {

        return $this->driver->dropColumn($table, $column, $schema);
    
    }

    public function listSequences() {

        return $this->driver->listSequences();
    
    }

    public function describeSequence($name, $schema = NULL) {

        return $this->driver->describeSequence($name, $schema);
    
    }

    public function listIndexes($table, $schema = NULL) {

        return $this->driver->listIndexes($table, $schema);
    
    }

    public function createIndex($idx_info, $table, $schema = NULL) {

        return $this->driver->createIndex($idx_info, $table, $schema);
    
    }

    public function dropIndex($name) {

        return $this->driver->dropIndex($name);
    
    }

    public function listTableConstraints($name = NULL, $schema = NULL, $type = NULL, $invert_type = FALSE) {

        return $this->driver->listTableConstraints($name, $schema, $type, $invert_type);
    
    }

    public function addConstraint($info, $table, $schema = NULL) {

        return $this->driver->addConstraint($info, $table, $schema);
    
    }

    public function dropConstraint($name, $table, $schema = NULL) {

        return $this->driver->dropConstraint($name, $table, $schema);
    
    }

    public function execCount() {

        return $this->driver->execCount();
    
    }

}

