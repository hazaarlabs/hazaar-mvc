<?php

namespace Hazaar\DBI\DBD;

class Mysql extends BaseDriver {

    private $conn;

    protected $reserved_words = array(
        'ACCESSIBLE', 'ADD', 'ALL', 'ALTER', 'ANALYZE', 'AND', 'AS', 'ASC', 'ASENSITIVE', 'BEFORE', 'BETWEEN', 'BIGINT', 'BINARY', 'BLOB', 'BOTH', 'BY', 'CALL',
        'CASCADE', 'CASE', 'CHANGE', 'CHAR', 'CHARACTER', 'CHECK', 'COLLATE', 'COLUMN', 'CONDITION', 'CONSTRAINT', 'CONTINUE', 'CONVERT', 'CREATE', 'CROSS',
        'CURRENT_DATE', 'CURRENT_TIME', 'CURRENT_TIMESTAMP', 'CURRENT_USER', 'CURSOR', 'DATABASE', 'DATABASES', 'DAY_HOUR', 'DAY_MICROSECOND', 'DAY_MINUTE',
        'DAY_SECOND', 'DEC', 'DECIMAL', 'DECLARE', 'DEFAULT', 'DELAYED', 'DELETE', 'DESC', 'DESCRIBE', 'DETERMINISTIC', 'DISTINCT', 'DISTINCTROW', 'DIV',
        'DOUBLE', 'DROP', 'DUAL', 'EACH', 'ELSE', 'ELSEIF', 'ENCLOSED', 'ESCAPED', 'EXISTS', 'EXIT', 'EXPLAIN', 'FALSE', 'FETCH', 'FLOAT', 'FLOAT4', 'FLOAT8',
        'FOR', 'FORCE', 'FOREIGN', 'FROM', 'FULLTEXT', 'GRANT', 'GROUP', 'HAVING', 'HIGH_PRIORITY', 'HOUR_MICROSECOND', 'HOUR_MINUTE', 'HOUR_SECOND', 'IF',
        'IGNORE', 'IN', 'INDEX', 'INFILE', 'INNER', 'INOUT', 'INSENSITIVE', 'INSERT', 'INT', 'INT1', 'INT2', 'INT3', 'INT4', 'INT8', 'INTEGER', 'INTERVAL',
        'INTO', 'IS', 'ITERATE', 'JOIN', 'KEY', 'KEYS', 'KILL', 'LEADING', 'LEAVE', 'LEFT', 'LIKE', 'LIMIT', 'LINEAR', 'LINES', 'LOAD', 'LOCALTIME',
        'LOCALTIMESTAMP', 'LOCK', 'LONG', 'LONGBLOB', 'LONGTEXT', 'LOOP', 'LOW_PRIORITY', 'MASTER_SSL_VERIFY_SERVER_CERT', 'MATCH', 'MAXVALUE', 'MEDIUMBLOB',
        'MEDIUMINT', 'MEDIUMTEXT', 'MIDDLEINT', 'MINUTE_MICROSECOND', 'MINUTE_SECOND', 'MOD', 'MODIFIES', 'NATURAL', 'NOT', 'NO_WRITE_TO_BINLOG', 'NULL',
        'NUMERIC', 'ON', 'OPTIMIZE', 'OPTION', 'OPTIONALLY', 'OR', 'ORDER', 'OUT', 'OUTER', 'OUTFILE', 'PRECISION', 'PRIMARY', 'PROCEDURE', 'PURGE', 'RANGE',
        'READ', 'READS', 'READ_WRITE', 'REAL', 'REFERENCES', 'REGEXP', 'RELEASE', 'RENAME', 'REPEAT', 'REPLACE', 'REQUIRE', 'RESIGNAL', 'RESTRICT', 'RETURN',
        'REVOKE', 'RIGHT', 'RLIKE', 'SCHEMA', 'SCHEMAS', 'SECOND_MICROSECOND', 'SELECT', 'SENSITIVE', 'SEPARATOR', 'SET', 'SHOW', 'SIGNAL', 'SMALLINT',
        'SPATIAL', 'SPECIFIC', 'SQL', 'SQLEXCEPTION', 'SQLSTATE', 'SQLWARNING', 'SQL_BIG_RESULT', 'SQL_CALC_FOUND_ROWS', 'SQL_SMALL_RESULT', 'SSL', 'STARTING',
        'STRAIGHT_JOIN', 'TABLE', 'TERMINATED', 'THEN', 'TINYBLOB', 'TINYINT', 'TINYTEXT', 'TO', 'TRAILING', 'TRIGGER', 'TRUE', 'UNDO', 'UNION', 'UNIQUE',
        'UNLOCK', 'UNSIGNED', 'UPDATE', 'USAGE', 'USE', 'USING', 'UTC_DATE', 'UTC_TIME', 'UTC_TIMESTAMP', 'VALUES', 'VARBINARY', 'VARCHAR', 'VARCHARACTER',
        'VARYING', 'WHEN', 'WHERE', 'WHILE', 'WITH', 'WRITE', 'XOR', 'YEAR_MONTH', 'ZEROFILL'
    );

    private $dbname;

    public function connect($dsn, $username = null, $password = null, $driver_options = null) {

        $d_pos = strpos($dsn, ':');

        $driver = strtolower(substr($dsn, 0, $d_pos));

        if (!$driver == 'mysql')
            return false;

        $dsn_parts = array_unflatten(substr($dsn, $d_pos + 1));

        foreach ($dsn_parts as $key => $value) {

            switch ($key) {

                case 'dbname' :
                    $this->dbname = $value;

                    break;

                case 'user' :
                    $username = $value;

                    unset($dsn_parts[$key]);

                    break;

                case 'password' :
                    $password = $value;

                    unset($dsn_parts[$key]);

                    break;
            }

        }

        $dsn = $driver . ':' . array_flatten($dsn_parts);

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

            $string = $this->conn->quote((string)$string);

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

        if ($result = $this->conn->query($sql))
            return new \Hazaar\DBI\Result($result);

        return false;

    }

    public function prepare($sql) {

        if ($result = $this->conn->prepare($sql))
            return new \Hazaar\DBI\Result($result);

        return false;

    }

    public function listTables($schema = null) {

        if (!$schema || $schema == 'public')
            $schema = $this->dbname;

        return parent::listTables($schema);

    }

    public function tableExists($table, $schema = null) {

        if (!$schema || $schema == 'public')
            $schema = $this->dbname;

        $info = new \Hazaar\DBI\Table($this, 'tables', 'information_schema');

        return $info->exists(array(
            'table_name' => $table, 'table_schema' => $schema
        ));

    }

    public function createTable($name, $columns, $schema = null) {

        if (!$schema || $schema == 'public')
            $schema = $this->dbname;

        return parent::createTable($name, $columns, $schema);

    }

    public function describeTable($name, $schema = null, $sort = null) {

        if (!$schema || $schema == 'public')
            $schema = $this->dbname;

        return parent::describeTable($name, $schema, $sort);

    }

    public function renameTable($from_name, $to_name, $schema = null) {

        if (!$schema || $schema == 'public')
            $schema = $this->dbname;

        return parent::renameTable($from_name, $to_name, $schema);

    }

    public function dropTable($name, $schema = null) {

        if (!$schema || $schema == 'public')
            $schema = $this->dbname;

        return parent::dropTable($name, $schema);

    }

    public function addColumn($table, $column_spec, $schema = null) {

        if (!$schema || $schema == 'public')
            $schema = $this->dbname;

        return parent::addColumn($table, $column_spec, $schema);

    }

    public function alterColumn($table, $column, $column_spec, $schema = null) {

        if (!$schema || $schema == 'public')
            $schema = $this->dbname;

        return parent::alterColumn($table, $column, $column_spec, $schema);

    }

    public function dropColumn($table, $column, $schema = null) {

        if (!$schema || $schema == 'public')
            $schema = $this->dbname;

        return parent::dropColumn($table, $column, $schema);

    }

    public function listSequences($schema = null) {

        if (!$schema || $schema == 'public')
            $schema = $this->dbname;

        return parent::listSequences();

    }

    public function describeSequence($name, $schema = null) {

        if (!$schema || $schema == 'public')
            $schema = $this->dbname;

        return parent::describeSequence($name, $schema);

    }

    public function listIndexes($table, $schema = null) {

        if (!$schema || $schema == 'public')
            $schema = $this->dbname;

        throw new \Exception('MySQL Indexes are not supported yet!');

    }

    public function createIndex($idx_info, $table, $schema = null) {

        if (!$schema || $schema == 'public')
            $schema = $this->dbname;

        if (!array_key_exists('name', $idx_info))
            return false;

        if (!array_key_exists('table', $idx_info))
            return false;

        if (!array_key_exists('columns', $idx_info))
            return false;

        $sql = 'CREATE';

        if (array_key_exists('unique', $idx_info) && $idx_info['unique'])
            $sql .= ' UNIQUE';

        $sql .= ' INDEX ' . $idx_info['name'] . ' ON ' . $idx_info['table'];

        $sql .= ' (' . implode(',', $idx_info['columns']) . ')';

        if (array_key_exists('using', $idx_info) && $idx_info['using'])
            $sql .= ' USING ' . $idx_info['using'];

        $sql .= ';';

        $affected = $this->exec($sql);

        if ($affected === false)
            return false;

        return true;

    }

    public function listTableConstraints($name, $schema = null, $type = null, $invert_type = false) {

        if (!$schema || $schema == 'public')
            $schema = $this->dbname;

        return parent::listTableConstraints($name, $schema, $type, $invert_type);

    }

    public function addConstraint($info, $table, $schema = null) {

        if (!$schema || $schema == 'public')
            $schema = $this->dbname;

        return parent::addConstraint($info, $table, $schema);

    }

    public function dropConstraint($name, $table, $schema = null) {

        if (!$schema || $schema == 'public')
            $schema = $this->dbname;

        return parent::dropConstraint($name, $table, $schema);

    }

}


