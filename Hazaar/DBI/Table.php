<?php

/**
 * @file        Hazaar/DBI/Collection.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */
namespace Hazaar\DBI;

/**
 * @brief Relational Database Interface - Table Class
 *
 * @detail The Table class is used to access table data via an abstracted interface. That means that now SQL is
 * used to access table data and queries are generated automatically using access methods. The generation
 * of SQL is then handled by the database driver so that database specific SQL can be used when required.
 * This allows a common interface for accessing data that is compatible across all of the database drivers.
 *
 * h2. Example Usage
 *
 * <code>
 * $db = new Hazaar\DBI();
 * $result = $db->users->find(array('uname' => 'myusername'))->join('images', array('image' => array('$ref' => 'images.id')));
 * while($row = $result->fetch()){
 * //Do things with $row here
 * }
 * </code>
 */
class Table {

    private $driver;

    private $name;

    private $alias;

    private $criteria = array();

    private $fields = array();

    private $joins = array();

    private $options = array();

    function __construct(DBD\BaseDriver $driver, $name, $alias = NULL, $schema = NULL) {

        $this->driver = $driver;
        
        $this->name = $this->driver->schemaName($name, $schema);
        
        $this->alias = $alias;
    
    }

    private function from() {

        return $this->name . ($this->alias ? ' ' . $this->alias : NULL);
    
    }

    /**
     *
     * @param array $criteria            
     * @param array $fields            
     *
     * @return \Hazaar\DBI\Table
     */
    public function find($criteria = array(), $fields = array(), $options = array()) {

        if (!is_array($criteria))
            $criteria = array();
        
        $this->criteria = $criteria;
        
        if (!is_array($fields))
            $fields = array(
                $fields
            );
        
        $this->fields = $fields;
        
        $this->options = $options;
        
        return new Result($this->execute());
    
    }

    public function findOne($criteria = array(), $fields = array(), $order = NULL) {

        if ($result = $this->find($criteria, $fields, $order, 1))
            return $result->row();
        
        return FALSE;
    
    }

    public function exists($criteria) {

        $sql = 'SELECT EXISTS (SELECT * FROM ' . $this->from() . ' WHERE ' . $this->driver->prepareCriteria($criteria) . ');';
        
        if ($result = $this->driver->query($sql))
            return boolify($result->fetchColumn(0));
        
        return FALSE;
    
    }

    public function __tostring() {

        return $this->toString();
    
    }

    public function toString($terminate_with_colon = TRUE) {

        $sql = 'SELECT';
        
        if (!is_array($this->fields) || count($this->fields) == 0) {
            
            $sql .= ' *';
        } else {
            
            $sql .= ' ' . $this->driver->prepareFields($this->fields);
        }
        
        $sql .= ' FROM ' . $this->from();
        
        if (count($this->joins) > 0) {
            
            foreach($this->joins as $join) {
                
                $sql .= ' ' . $join['type'] . ' JOIN ' . $join['ref'];
                
                if ($join['alias'])
                    $sql .= ' ' . $join['alias'];
                
                $sql .= ' ON ' . $this->driver->prepareCriteria($join['on']);
            }
        }
        
        if (count($this->criteria) > 0)
            $sql .= ' WHERE ' . $this->driver->prepareCriteria($this->criteria);
        
        if (count($this->options) > 0) {
            
            foreach($this->options as $option => $value) {
                
                switch ($option) {
                    case 'sort' :
                        
                        $sql .= ' ORDER BY ';
                        
                        if (is_array($value)) {
                            
                            $order = array();
                            
                            foreach($value as $field => $mode) {
                                
                                if (is_array($mode)) {
                                    
                                    $nulls = ake($mode, '$nulls', 0);
                                    
                                    $mode = ake($mode, '$dir', 1);
                                } else {
                                    
                                    $nulls = 0;
                                }
                                
                                $dir = (($mode == 1) ? 'ASC' : 'DESC');
                                
                                if ($nulls > 0)
                                    $dir .= ' NULLS FIRST';
                                
                                elseif ($nulls < 0)
                                    $dir .= ' NULLS LAST';
                                
                                $order[] = $field . ($dir ? ' ' . $dir : NULL);
                            }
                            
                            $sql .= implode(', ', $order);
                        } else {
                            
                            $desc = false;
                            
                            if (substr($value, 0, 1) == '-') {
                                
                                $desc = true;
                                
                                $value = substr($value, 1);
                            }
                            
                            $sql .= $value . ($desc ? ' DESC' : NULL);
                        }
                        
                        break;
                    
                    default :
                        $sql .= ' ' . strtoupper($option) . (string) (int) $value;
                        break;
                }
            }
        }
        
        if ($terminate_with_colon)
            $sql .= ';';
        
        return $sql;
    
    }

    public function execute() {

        $sql = $this->toString();
        
        return $this->driver->query($sql);
    
    }

    public function fields($fields) {

        $this->fields = array_merge($this->fields, $fields);
        
        return $this;
    
    }

    public function where($criteria) {

        $this->criteria = array_merge($this->criteria, $criteria);
        
        return $this;
    
    }

    public function join($references, $on = array(), $alias = NULL, $type = 'INNER') {

        if (!$type)
            $type = 'INNER';
        
        $this->joins[$alias] = array(
            'type' => $type,
            'ref' => $references,
            'on' => $on,
            'alias' => $alias
        );
        
        return $this;
    
    }

    public function innerJoin($references, $on = array(), $alias = NULL) {

        return $this->join($references, $on, $alias, 'INNER');
    
    }

    public function leftJoin($references, $on = array(), $alias = NULL) {

        return $this->join($references, $on, $alias, 'LEFT');
    
    }

    public function rightJoin($references, $on = array(), $alias = NULL) {

        return $this->join($references, $on, $alias, 'RIGHT');
    
    }

    function fullJoin($references, $on = array(), $alias = NULL) {

        return $this->join($references, $on, $alias, 'FULL');
    
    }

    public function offset($offset) {

        $this->offset = $offset;
        
        return $this;
    
    }

    public function insert($fields, $returning = NULL) {

        return $this->driver->insert($this->name, $fields, $returning);
    
    }

    public function update($criteria, $fields) {

        return $this->driver->update($this->name, $fields, $criteria);
    
    }

    public function delete($criteria) {

        return $this->driver->delete($this->name, $criteria);
    
    }

    public function deleteAll() {

        return $this->driver->deleteAll($this->name);
    
    }

}

