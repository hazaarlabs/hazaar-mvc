<?php

declare(strict_types=1);

namespace Hazaar\DBI\DBD;

use Hazaar\Date;
use Hazaar\DBI\Table;
use Hazaar\Exception;

class Mysql extends BaseDriver
{
    /**
     * @var array<string>
     */
    public static array $dsnElements = [
        'host',
        'port',
        'dbname',
        'unix_socket',
        'charset',
    ];
    protected string $quoteSpecial = '`';

    /**
     * @var array<string>
     */
    protected array $reservedWords = [
        'ACCESSIBLE',
        'ADD',
        'ALL',
        'ALTER',
        'ANALYZE',
        'AND',
        'AS',
        'ASC',
        'ASENSITIVE',
        'BEFORE',
        'BETWEEN',
        'BIGINT',
        'BINARY',
        'BLOB',
        'BOTH',
        'BY',
        'CALL',
        'CASCADE',
        'CASE',
        'CHANGE',
        'CHAR',
        'CHARACTER',
        'CHECK',
        'COLLATE',
        'COLUMN',
        'CONDITION',
        'CONSTRAINT',
        'CONTINUE',
        'CONVERT',
        'CREATE',
        'CROSS',
        'CURRENT_DATE',
        'CURRENT_TIME',
        'CURRENT_TIMESTAMP',
        'CURRENT_USER',
        'CURSOR',
        'DATABASE',
        'DATABASES',
        'DAY_HOUR',
        'DAY_MICROSECOND',
        'DAY_MINUTE',
        'DAY_SECOND',
        'DEC',
        'DECIMAL',
        'DECLARE',
        'DEFAULT',
        'DELAYED',
        'DELETE',
        'DESC',
        'DESCRIBE',
        'DETERMINISTIC',
        'DISTINCT',
        'DISTINCTROW',
        'DIV',
        'DOUBLE',
        'DROP',
        'DUAL',
        'EACH',
        'ELSE',
        'ELSEIF',
        'ENCLOSED',
        'ESCAPED',
        'EXISTS',
        'EXIT',
        'EXPLAIN',
        'FALSE',
        'FETCH',
        'FLOAT',
        'FLOAT4',
        'FLOAT8',
        'FOR',
        'FORCE',
        'FOREIGN',
        'FROM',
        'FULLTEXT',
        'GRANT',
        'GROUP',
        'HAVING',
        'HIGH_PRIORITY',
        'HOUR_MICROSECOND',
        'HOUR_MINUTE',
        'HOUR_SECOND',
        'IF',
        'IGNORE',
        'IN',
        'INDEX',
        'INFILE',
        'INNER',
        'INOUT',
        'INSENSITIVE',
        'INSERT',
        'INT',
        'INT1',
        'INT2',
        'INT3',
        'INT4',
        'INT8',
        'INTEGER',
        'INTERVAL',
        'INTO',
        'IS',
        'ITERATE',
        'JOIN',
        'KEY',
        'KEYS',
        'KILL',
        'LEADING',
        'LEAVE',
        'LEFT',
        'LIKE',
        'LIMIT',
        'LINEAR',
        'LINES',
        'LOAD',
        'LOCALTIME',
        'LOCALTIMESTAMP',
        'LOCK',
        'LONG',
        'LONGBLOB',
        'LONGTEXT',
        'LOOP',
        'LOW_PRIORITY',
        'MASTER_SSL_VERIFY_SERVER_CERT',
        'MATCH',
        'MAXVALUE',
        'MEDIUMBLOB',
        'MEDIUMINT',
        'MEDIUMTEXT',
        'MIDDLEINT',
        'MINUTE_MICROSECOND',
        'MINUTE_SECOND',
        'MOD',
        'MODIFIES',
        'NATURAL',
        'NOT',
        'NO_WRITE_TO_BINLOG',
        'NULL',
        'NUMERIC',
        'ON',
        'OPTIMIZE',
        'OPTION',
        'OPTIONALLY',
        'OR',
        'ORDER',
        'OUT',
        'OUTER',
        'OUTFILE',
        'PRECISION',
        'PRIMARY',
        'PROCEDURE',
        'PURGE',
        'RANGE',
        'READ',
        'READS',
        'READ_WRITE',
        'REAL',
        'REFERENCES',
        'REGEXP',
        'RELEASE',
        'RENAME',
        'REPEAT',
        'REPLACE',
        'REQUIRE',
        'RESIGNAL',
        'RESTRICT',
        'RETURN',
        'REVOKE',
        'RIGHT',
        'RLIKE',
        'SCHEMA',
        'SCHEMAS',
        'SECOND_MICROSECOND',
        'SELECT',
        'SENSITIVE',
        'SEPARATOR',
        'SET',
        'SHOW',
        'SIGNAL',
        'SMALLINT',
        'SPATIAL',
        'SPECIFIC',
        'SQL',
        'SQLEXCEPTION',
        'SQLSTATE',
        'SQLWARNING',
        'SQL_BIG_RESULT',
        'SQL_CALC_FOUND_ROWS',
        'SQL_SMALL_RESULT',
        'SSL',
        'STARTING',
        'STRAIGHT_JOIN',
        'TABLE',
        'TERMINATED',
        'THEN',
        'TINYBLOB',
        'TINYINT',
        'TINYTEXT',
        'TO',
        'TRAILING',
        'TRIGGER',
        'TRUE',
        'UNDO',
        'UNION',
        'UNIQUE',
        'UNLOCK',
        'UNSIGNED',
        'UPDATE',
        'USAGE',
        'USE',
        'USING',
        'UTC_DATE',
        'UTC_TIME',
        'UTC_TIMESTAMP',
        'VALUES',
        'VARBINARY',
        'VARCHAR',
        'VARCHARACTER',
        'VARYING',
        'WHEN',
        'WHERE',
        'WHILE',
        'WITH',
        'WRITE',
        'XOR',
        'YEAR_MONTH',
        'ZEROFILL',
    ];

    public function connect(
        string $dsn,
        ?string $username = null,
        ?string $password = null,
        ?array $driverOptions = null
    ): bool {
        $dPos = strpos($dsn, ':');
        $driver = strtolower(substr($dsn, 0, $dPos));
        if ('mysql' == !$driver) {
            return false;
        }
        $dsnParts = array_unflatten(substr($dsn, $dPos + 1));
        foreach ($dsnParts as $key => $value) {
            switch ($key) {
                case 'dbname':
                    // $this->dbname = $value;

                    break;

                case 'user':
                    $username = $value;
                    unset($dsnParts[$key]);

                    break;

                case 'password':
                    $password = $value;
                    unset($dsnParts[$key]);

                    break;
            }
        }
        $dsn = $driver.':'.array_flatten($dsnParts);

        return parent::connect($dsn, $username, $password, $driverOptions);
    }

    public function setTimezone(string $tz): bool
    {
        return false != $this->exec("SET time_zone = '{$tz}';");
    }

    public function quote(mixed $string, int $type = \PDO::PARAM_STR): string
    {
        if ($string instanceof Date) {
            $string = $string->timestamp();
        }
        if (!is_numeric($string)) {
            $string = $this->pdo->quote((string) $string);
        }

        return $string;
    }

    public function insert(
        string $tableName,
        mixed $fields,
        mixed $returning = null,
        null|array|string $conflictTarget = null,
        mixed $conflictUpdate = null,
        ?Table $table = null
    ): false|int {
        if (!is_bool($returning)) {
            $returning = ('id' == strtolower($returning));
        }

        return parent::insert($tableName, $fields, $returning, $conflictTarget, $conflictUpdate, $table);
    }

    /**
     * @return array<int, array<string>>|false
     */
    public function listTables(): array|false
    {
        $list = [];
        $result = $this->query('SHOW TABLES;');
        while ($table = $result->fetch(\PDO::FETCH_ASSOC)) {
            $list[] = [
                'name' => array_values($table)[0],
            ];
        }

        return $list;
    }

    /**
     * @return array<int, array<string>>|false
     */
    public function listConstraints(?string $table = null, ?string $type = null, bool $invertType = false): array|false
    {
        if (!$this->allowConstraints) {
            return false;
        }
        $constraints = [];
        $sql = 'SELECT
                tc.constraint_name as name,
                tc.table_name as '.$this->field('table').',
                tc.table_schema as '.$this->field('schema').',
                kcu.column_name as '.$this->field('column').",
                kcu.REFERENCED_TABLE_SCHEMA AS foreign_schema,
                kcu.REFERENCED_TABLE_NAME AS foreign_table,
                kcu.REFERENCED_COLUMN_NAME AS foreign_column,
                tc.constraint_type as type,
                rc.match_option,
                rc.update_rule,
                rc.delete_rule
            FROM information_schema.table_constraints tc
            INNER JOIN information_schema.key_column_usage kcu
                ON kcu.constraint_schema = tc.constraint_schema
                AND kcu.constraint_name = tc.constraint_name
                AND kcu.table_schema = tc.table_schema
                AND kcu.table_name = tc.table_name
            LEFT JOIN information_schema.referential_constraints rc ON tc.constraint_name = rc.constraint_name
            WHERE tc.CONSTRAINT_SCHEMA='{$this->schemaName}'";
        if ($table) {
            $sql .= "\nAND tc.table_name='{$table}'";
        }
        if ($type) {
            $sql .= "\nAND tc.constraint_type".($invertType ? '!=' : '=')."'{$type}'";
        }
        $sql .= ';';
        if ($result = $this->query($sql)) {
            while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
                $constraint = [
                    'table' => $row['table'],
                    'column' => $row['column'],
                    'type' => $row['type'],
                ];
                if ($row['foreign_table']) {
                    $constraint['references'] = [
                        'table' => $row['foreign_table'],
                        'column' => $row['foreign_column'],
                    ];
                }
                $constraints[$row['name']] = $constraint;
            }

            return $constraints;
        }

        return false;
    }

    /**
     * @return array<mixed>|false
     */
    public function listIndexes(?string $table = null): array|false
    {
        $sql = 'SELECT table_name AS '.$this->field('table').',
            index_name AS '.$this->field('name').',
            CASE WHEN non_unique=1 THEN FALSE ELSE TRUE END as '.$this->field('unique').',
            GROUP_CONCAT(column_name ORDER BY seq_in_index) AS '.$this->field('columns')."
            FROM information_schema.statistics
            WHERE table_schema = '{$this->schemaName}'";
        if ($table) {
            $sql .= "\nAND table_name='{$table}'";
        }
        $sql .= "\nGROUP BY 1,2;";
        if (!($result = $this->query($sql))) {
            throw new Exception('Index list failed. '.$this->errorInfo()[2]);
        }
        $indexes = [];
        while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
            $indexes[$row['name']] = [
                'columns' => array_map('trim', explode(',', $row['columns'])),
                'unique' => boolify($row['unique']),
            ];
        }

        return $indexes;
    }
}
