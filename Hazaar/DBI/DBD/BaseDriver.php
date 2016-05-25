<?php
/**
 * @file        Hazaar/Db/Adapter.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

/**
 * @brief       Relational Database Driver namespace
 */
namespace Hazaar\DBI\DBD;

/**
 * @brief       Relational Database Driver Interface
 */
interface Driver_Interface {

    public function connect($dsn, $username = NULL, $password = NULL, $driver_options = NULL);

    public function beginTransaction();

    public function commit();

    public function rollBack();

    public function inTransaction();

    public function getAttribute($attribute);

    public function setAttribute($attribute, $value);

    public function lastInsertId();

    public function quote($string);

    public function exec($sql);

    /**
     * @param $sql
     *
     * @return \Hazaar\DBI\Result
     */
    public function query($sql);

    public function prepare($sql);

    public function insert($table, $fields, $returning = 'id');

    public function update($table, $fields, $criteria = array());

    public function delete($table, $criteria);

    public function deleteAll($table);

}

/**
 * @brief       Relational Database Driver - Base Class
 */
abstract class BaseDriver implements Driver_Interface {

    protected $reserved_words = array();

    public function field($string) {

        if(in_array(strtoupper($string), $this->reserved_words)) {

            $string = '"' . $string . '"';

        }

        return $string;

    }

    public function type($string) {

        $types = array(
            'timestamp without time zone' => 'timestamp', 'timestamp with time zone' => 'timestamp', 'character varying' => 'varchar'
        );

        if(array_key_exists($string, $types))
            return $types[$string];

        return $string;
    }

    public function prepareCriteria($criteria, $bind_type = 'AND', $tissue = '=', $parent_ref = NULL) {

        $parts = array();

        foreach($criteria as $key => $value) {

            if(substr($key, 0, 1) == '$') {

                $action = strtolower(substr($key, 1));

                switch($action) {
                    case 'or':
                        $parts[] = $this->prepareCriteria($value, 'OR');

                        break;

                    case 'not' :

                        if(is_null($value))
                            $parts[] = 'IS NOT NULL';
                        else
                            $parts[] = '!= ' . $this->prepareValue($value);

                        break;

                    case  'ref' :
                        $parts[] = $tissue . ' ' . $value;

                        break;

                    case 'nin' :
                    case 'in' :
                        if(is_array($value)) {

                            $values = array();

                            foreach($value as $val) {

                                $values[] = $this->quote($val);

                            }

                            $parts[] = (($action == 'nin') ? 'NOT ' : NULL) . 'IN ( ' . implode(', ', $values) . ' )';

                        }

                        break;

                    case 'gt':

                        $parts[] = '> ' . $this->prepareValue($value);

                        break;

                    case 'lt':

                        $parts[] = '< ' . $this->prepareValue($value);

                        break;

                    case 'ilike': //iLike
                        $parts[] = 'ILIKE ' . $this->quote($value);

                        break;

                    case 'like': //Like
                        $parts[] = 'LIKE ' . $this->quote($value);

                        break;

                    case '~':
                    case '~*':
                    case '!~':
                    case '!~*':

                        $parts[] = $action . ' ' . $this->quote($value);

                        break;

                    case 'exists': //exists

                        foreach($value as $table => $criteria)
                            $parts[] = 'EXISTS ( SELECT * FROM ' . $table . ' WHERE ' . $this->prepareCriteria($criteria) . ' )';

                        break;

                    case 'sub': //sub query

                        $parts[] = '( ' . $value[0]->toString(FALSE) . ' ) ' . $this->prepareCriteria($value[1]);

                        break;

                    default :
                        $parts[] = ' ' . $tissue . ' ' . $this->prepareCriteria($value, strtoupper(substr($key, 1)));

                        break;
                }

            } else {

                if(is_array($value)) {

                    $sub_value = $this->prepareCriteria($value);

                    if(! is_numeric($key)) {

                        if($parent_ref && strpos($key, '.') === FALSE)
                            $key = $parent_ref . '.' . $key;

                        $parts[] = $key . ' ' . $sub_value;

                    } else {

                        $parts[] = $sub_value;

                    }

                } else {

                    if($parent_ref && strpos($key, '.') === FALSE)
                        $key = $parent_ref . '.' . $key;

                    if(is_null($value))
                        $joiner = 'IS ' . (($tissue == '!=') ? 'NOT ' : NULL);
                    else
                        $joiner = $tissue;

                    $parts[] = $this->field($key) . ' ' . $joiner . ' ' . $this->prepareValue($value);

                }

            }

        }

        $sql = '';

        if(count($parts) > 0) {

            $sql = ((count($parts) > 1) ? '( ' : NULL) . implode(" $bind_type ", $parts) . ((count($parts) > 1) ? ' )' : NULL);

        }

        return $sql;

    }

    public function prepareFields($fields) {

        $field_def = array();

        foreach($fields as $key => $value) {

            if(is_numeric($key)) {

                $field_def[] = $this->field($value);

            } else {

                $field_def[] = $this->field($key) . ' AS ' . $this->field($value);

            }

        }

        return implode(', ', $field_def);

    }

    public function prepareValue($value) {

        if(is_array($value)) {

            $value = $this->prepareCriteria($value, NULL, NULL);

        } else if($value instanceof \Hazaar\Date) {

            $value = $this->quote($value->format('Y-m-d H:i:s'));

        } else if(is_null($value)) {

            $value = 'NULL';

        } else if(is_bool($value)) {

            $value = ($value ? 'TRUE' : 'FALSE');

        } else if(! is_int($value)) {

            $value = $this->quote((string)$value);

        }

        return $value;

    }

    public function insert($table, $fields, $returning = TRUE) {

        $field_def = array_keys($fields);

        foreach($field_def as &$field) {

            $field = $this->field($field);

        }

        $value_def = array_values($fields);

        foreach($value_def as &$value) {

            $value = $this->prepareValue($value);

        }

        $sql = 'INSERT INTO ' . $this->field($table) . ' ( ' . implode(', ', $field_def) . ' ) VALUES ( ' . implode(', ', $value_def) . ' )';

        $return_value = FALSE;

        if($returning === NULL) {

            $sql .= ';';

            $return_value = $this->exec($sql);

        } elseif($returning === TRUE) {

            $sql .= ';';

            if($result = $this->query($sql)) {

                $return_value = (int)$this->lastinsertid();

            }

        } elseif(is_string($returning)) {

            $sql .= ' ' . $returning . ';';

            if($result = $this->query($sql)) {

                $return_value = $result->fetchColumn(0);

            }

        }

        return $return_value;

    }

    public function update($table, $fields, $criteria = array()) {

        $field_def = array();

        foreach($fields as $key => $value) {

            $field_def[] = $this->field($key) . ' = ' . $this->prepareValue($value);

        }

        if(count($field_def) == 0)
            throw new Exception\NoUpdate();

        $sql = 'UPDATE ' . $this->field($table) . ' SET ' . implode(', ', $field_def);

        if(count($criteria) > 0)
            $sql .= ' WHERE ' . $this->prepareCriteria($criteria);

        $sql .= ';';

        return $this->exec($sql);

    }

    public function delete($table, $criteria) {

        $sql = 'DELETE FROM ' . $this->field($table) . ' WHERE ' . $this->prepareCriteria($criteria) . ';';

        return $this->exec($sql);

    }

    public function deleteAll($table) {

        $sql = 'DELETE FROM ' . $this->field($table) . ';';

        return $this->exec($sql);

    }

    /*
     * Database information methods
     */

    public function schemaName($table, $schema = NULL) {

        if($schema && ! strpos($table, '.'))
            $table = trim($schema) . '.' . trim($table);

        return $table;

    }

    public function listTables() {

        $sql = "
            SELECT
                table_schema as schema,
                table_name as name
            FROM information_schema.tables t
            WHERE table_type = 'BASE TABLE'
                AND table_schema NOT IN ( 'information_schema', 'pg_catalog' )
            ORDER BY table_name DESC;
        ";

        if($result = $this->query($sql)) {

            return $result->fetchAll(\PDO::FETCH_ASSOC);

        }

        return NULL;

    }

    public function tableExists($table, $schema = NULL) {

        $info = new \Hazaar\DBI\Table($this, 'tables', NULL, 'information_schema');

        return $info->exists(array(
                                 'table_name' => $table, 'table_schema' => $schema
                             ));

    }

    public function createTable($name, $columns, $schema = NULL) {

        $name = $this->schemaName($name, $schema);

        $sql = "CREATE TABLE $name (\n";

        $coldefs = array();

        $constraints = array();

        foreach($columns as $name => $info) {

            if(is_array($info)) {

                if(is_numeric($name)) {

                    if(! array_key_exists('name', $info)) {

                        throw new \Exception('Error creating new table.  Name is a number which is not allowed!');

                    }

                    $name = $info['name'];

                }

                $def = $this->field($name) . ' ' . $this->type($info['data_type']) . ($info['length'] ? '(' . $info['length'] . ')' : NULL);

                if(array_key_exists('default', $info) && ! empty($info['default']))
                    $def .= ' DEFAULT ' . $info['default'];

                if(array_key_exists('not_null', $info) && $info['not_null'])
                    $def .= ' NOT NULL';

                if(array_key_exists('primarykey', $info) && $info['primarykey']) {

                    $driver = strtolower(basename(str_replace('\\', '/', get_class($this))));

                    if($driver == 'pgsql') {

                        $constraints[] = ' PRIMARY KEY(' . $this->field($name) . ')';

                    } else {

                        $def .= ' PRIMARY KEY';

                    }

                }

            } else {

                $def = "\t$name $info";

            }

            $coldefs[] = $def;

        }

        $sql .= implode(",\n", $coldefs);

        if(count($constraints) > 0)
            $sql .= ",\n" . implode(",\n", $constraints);

        $sql .= "\n);";

        $affected = $this->exec($sql);

        if($affected === FALSE)
            return FALSE;

        return TRUE;

    }

    public function describeTable($name, $schema = NULL, $sort = NULL) {

        if(! $sort)
            $sort = 'ordinal_position';

        $info = new \Hazaar\DBI\Table($this, 'columns', NULL, 'information_schema');

        $result = $info->find(array(
                                  'table_name' => $name, 'table_schema' => $schema
                              ))->sort($sort);

        $pkeys = array();

        if($constraints = $this->listTableConstraints($name, $schema, 'PRIMARY KEY')) {

            foreach($constraints as $constraint) {

                $pkeys[] = $constraint['column'];

            }

        }

        $columns = array();

        foreach($result as $col) {

            $col = array_change_key_case($col, CASE_LOWER);

            if(preg_match('/nextval\(\'(\w*)\'::regclass\)/', $col['column_default'], $matches)) {

                if($info = $this->describeSequence($matches[1])) {

                    $col['data_type'] = 'serial';

                    $col['column_default'] = NULL;

                }

            }

            $columns[] = array(
                'name'     => $col['column_name'], 'ordinal_position' => $col['ordinal_position'], 'default' => $col['column_default'],
                'not_null' => (($col['is_nullable'] == 'NO') ? TRUE : FALSE), 'data_type' => $this->type($col['data_type']),
                'length'   => $col['character_maximum_length'], 'primarykey' => in_array($col['column_name'], $pkeys)
            );

        }

        return $columns;

    }

    public function renameTable($from_name, $to_name, $schema = NULL) {

        $from_name = $this->schemaName($from_name, $schema);

        if(strpos($to_name, '.')) {

            list($to_schema, $to_name) = explode('.', $to_name);

            if($to_schema != $schema)
                throw new \Exception('You can not rename tables between schemas!');

        }

        $sql = "ALTER TABLE $from_name RENAME TO $to_name;";

        $affected = $this->exec($sql);

        if($affected === FALSE)
            return FALSE;

        return TRUE;

    }

    public function dropTable($name, $schema = NULL) {

        $name = $this->schemaName($name, $schema);

        $sql = "DROP TABLE $name;";

        $affected = $this->exec($sql);

        if($affected === FALSE)
            return FALSE;

        return TRUE;

    }

    public function addColumn($table, $column_spec, $schema = NULL) {

        $table = $this->schemaName($table, $schema);

        if(! array_key_exists('name', $column_spec))
            return FALSE;

        if(! array_key_exists('data_type', $column_spec))
            return FALSE;

        $sql = "ALTER TABLE $table ADD COLUMN $column_spec[name] " . $this->type($column_spec['data_type']);

        if(array_key_exists('not_null', $column_spec) && $column_spec['not_null'])
            $sql .= ' NOT NULL';

        if(array_key_exists('default', $column_spec) && $column_spec['default'])
            $sql .= ' DEFAULT ' . $column_spec['default'];

        $sql .= ';';

        $affected = $this->exec($sql);

        if($affected === FALSE)
            return FALSE;

        return TRUE;

    }

    public function alterColumn($table, $column, $column_spec, $schema = NULL) {

        $table = $this->schemaName($table, $schema);

        $sqls = array();

        $prefix = "ALTER TABLE $table ALTER COLUMN $column";

        if(array_key_exists('data_type', $column_spec))
            $sqls[] = $prefix . " TYPE " . $this->type($column_spec['data_type']) . ((array_key_exists('length', $column_spec) && $column_spec['length'] > 0) ? '(' . $column_spec['length'] . ')' : NULL);

        if(array_key_exists('not_null', $column_spec))
            $sqls[] = $prefix . ' ' . ($column_spec['not_null'] ? 'SET' : 'DROP') . ' NOT NULL';

        if(array_key_exists('default', $column_spec))
            $sqls[] .= $prefix . ' ' . ($column_spec['default'] ? 'SET DEFAULT ' . $column_spec['default'] : 'DROP DEFAULT');

        foreach($sqls as $sql) {

            $this->exec($sql);

        }

        return TRUE;

    }

    public function dropColumn($table, $column, $schema = NULL) {

        $table = $this->schemaName($table, $schema);

        $sql = "ALTER TABLE $table DROP COLUMN $column;";

        $affected = $this->exec($sql);

        if($affected === FALSE)
            return FALSE;

        return TRUE;

    }

    public function listSequences() {

        $sql = "SELECT sequence_schema as schema, sequence_name as name
            FROM information_schema.sequences
            WHERE sequence_schema NOT IN ( 'information_schema', 'pg_catalog');";

        $result = $this->query($sql);

        return $result->fetchAll();

    }

    public function describeSequence($name, $schema = NULL) {

        $sql = "SELECT * FROM information_schema.sequences WHERE sequence_name = '$name'";

        if($schema)
            $sql .= " AND sequence_schema = '$schema'";

        $sql .= ';';

        $result = $this->query($sql);

        return $result->fetchAll(\PDO::FETCH_ASSOC);

    }

    public function dropIndex($name) {

        $sql = $this->exec('DROP INDEX ' . $name);

        $affected = $this->exec($sql);

        if($affected === FALSE)
            return FALSE;

        return TRUE;

    }

    public function listTableConstraints($name, $schema = NULL, $type = NULL, $invert_type = FALSE) {

        $constraints = array();

        $sql = "
            SELECT 
                tc.constraint_name as name,
                tc.table_name as table,
                tc.table_schema as schema,
                kcu.column_name as column,
                ccu.table_name AS foreign_table,
                ccu.column_name AS foreign_column,
                tc.constraint_type as type,
                rc.match_option,
                rc.update_rule,
                rc.delete_rule
            FROM information_schema.table_constraints tc
            INNER JOIN information_schema.key_column_usage kcu ON tc.constraint_name = kcu.constraint_name
            INNER JOIN information_schema.constraint_column_usage ccu ON ccu.constraint_name = tc.constraint_name
            LEFT JOIN information_schema.referential_constraints rc ON tc.constraint_name = rc.constraint_name
            WHERE tc.table_name = '$name'";

        if($schema)
            $sql .= "\nAND tc.table_schema = '$schema'";

        if($type)
            $sql .= "\nAND tc.constraint_type" . ($invert_type ? '!=' : '=') . "'$type'";

        $sql .= ';';

        if($result = $this->query($sql)) {

            while($row = $result->row()) {

                $constraints[] = $row;

            }

            return $constraints;

        }

        return FALSE;

    }

    public function addConstraint($info, $table, $schema = NULL) {

        $table = $this->schemaName($table, $schema);

        if(! array_key_exists('type', $info) || ! $info['type'])
            return FALSE;

        $sql = "ALTER TABLE $table ADD ";

        if(array_key_exists('name', $info) && $info['name'])
            $sql .= "CONSTRAINT $info[name] ";

        $sql .= "$info[type] ($info[column])";

        if(array_key_exists('foreign_table', $info) && $info['foreign_table']) {

            $sql .= " REFERENCES $info[foreign_table] ($info[foreign_column]) ON UPDATE $info[update_rule] ON DELETE $info[delete_rule]";

        }

        $affected = $this->exec($sql);

        if($affected === FALSE)
            return FALSE;

        return TRUE;

    }

    public function dropConstraint($name, $table, $schema = NULL) {

        $table = $this->schemaName($table, $schema);

        $sql = "ALTER TABLE $table DROP CONSTRAINT $name";

        $affected = $this->exec($sql);

        if($affected === FALSE)
            return FALSE;

        return TRUE;

    }

}

