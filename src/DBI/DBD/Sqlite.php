<?php

declare(strict_types=1);

namespace Hazaar\DBI\DBD;

use Hazaar\Application;
use Hazaar\Date;
use Hazaar\DBI\Table;
use Hazaar\Map;

class Sqlite extends BaseDriver
{
    public bool $allowConstraints = false;

    /**
     * @var array<string>
     */
    protected array $reservedWords = [
        'ABORT',
        'ACTION',
        'ADD',
        'AFTER',
        'ALL',
        'ALTER',
        'ANALYZE',
        'AND',
        'AS',
        'ASC',
        'ATTACH',
        'AUTOINCREMENT',
        'BEFORE',
        'BEGIN',
        'BETWEEN',
        'BY',
        'CASCADE',
        'CASE',
        'CAST',
        'CHECK',
        'COLLATE',
        'COLUMN',
        'COMMIT',
        'CONFLICT',
        'CONSTRAINT',
        'CREATE',
        'CROSS',
        'CURRENT_DATE',
        'CURRENT_TIME',
        'CURRENT_TIMESTAMP',
        'DATABASE',
        'DEFAULT',
        'DEFERRABLE',
        'DEFERRED',
        'DELETE',
        'DESC',
        'DETACH',
        'DISTINCT',
        'DROP',
        'EACH',
        'ELSE',
        'END',
        'ESCAPE',
        'EXCEPT',
        'EXCLUSIVE',
        'EXISTS',
        'EXPLAIN',
        'FAIL',
        'FOR',
        'FOREIGN',
        'FROM',
        'FULL',
        'GLOB',
        'GROUP',
        'HAVING',
        'IF',
        'IGNORE',
        'IMMEDIATE',
        'IN',
        'INDEX',
        'INDEXED',
        'INITIALLY',
        'INNER',
        'INSERT',
        'INSTEAD',
        'INTERSECT',
        'INTO',
        'IS',
        'ISNULL',
        'JOIN',
        'KEY',
        'LEFT',
        'LIKE',
        'LIMIT',
        'MATCH',
        'NATURAL',
        'NO',
        'NOT',
        'NOTNULL',
        'NULL',
        'OF',
        'OFFSET',
        'ON',
        'OR',
        'ORDER',
        'OUTER',
        'PLAN',
        'PRAGMA',
        'PRIMARY',
        'QUERY',
        'RAISE',
        'RECURSIVE',
        'REFERENCES',
        'REGEXP',
        'REINDEX',
        'RELEASE',
        'RENAME',
        'REPLACE',
        'RESTRICT',
        'RIGHT',
        'ROLLBACK',
        'ROW',
        'SAVEPOINT',
        'SELECT',
        'SET',
        'TABLE',
        'TEMP',
        'TEMPORARY',
        'THEN',
        'TO',
        'TRANSACTION',
        'TRIGGER',
        'UNION',
        'UNIQUE',
        'UPDATE',
        'USING',
        'VACUUM',
        'VALUES',
        'VIEW',
        'VIRTUAL',
        'WHEN',
        'WHERE',
        'WITH',
        'WITHOUT',
    ];

    public function setTimezone(string $tz): bool
    {
        return false;
    }

    public static function mkdsn(Map $config): string
    {
        $filename = $config->get('filename', 'database.sqlite');
        if (!('/' === substr($filename, 0, 1) || ':' === substr($filename, 1, 1))) {
            $filename = Application::getInstance()->getRuntimePath($filename);
        }

        return 'sqlite:'.$filename;
    }

    public function connect(
        string $dsn,
        ?string $username = null,
        ?string $password = null,
        ?array $driverOptions = null
    ): bool {
        $dPos = strpos($dsn, ':');
        $driver = strtolower(substr($dsn, 0, $dPos));
        if ('sqlite' == !$driver) {
            return false;
        }

        return parent::connect($dsn, $username, $password, $driverOptions);
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

    /**
     * @return array<int, array<string>>|false
     */
    public function listTables(): array|false
    {
        $tables = [];
        $sql = "SELECT tbl_name as name FROM sqlite_master WHERE type = 'table';";
        $result = $this->query($sql);
        while ($table = $result->fetch(\PDO::FETCH_ASSOC)) {
            // Ignore internal SQLite tables.
            if ('sqlite_' == substr($table['name'], 0, 7)) {
                continue;
            }
            $tables[] = ['name' => $table['name']];
        }

        return $tables;
    }

    public function tableExists(string $table): bool
    {
        $info = new Table($this->adapter, 'sqlite_master');

        return $info->exists([
            'name' => $table,
            'type' => 'table',
        ]);
    }

    /**
     * @return array<mixed>
     */
    public function describeTable(string $name, ?string $sort = null): array
    {
        $columns = [];
        $name = $this->tableName($name);
        $sql = "PRAGMA table_info('{$name}');";
        $result = $this->query($sql);
        $ordinalPosition = 0;
        while ($col = $result->fetch(\PDO::FETCH_ASSOC)) {
            // SQLite does not have ordinal position so we generate it
            ++$ordinalPosition;
            $columns[] = [
                'name' => $col['name'],
                'ordinal_position' => $ordinalPosition,
                'default' => $col['dflt_value'],
                'not_null' => boolify($col['notnull']),
                'data_type' => $this->type($col['type']),
                'length' => null,
                'primarykey' => boolify($col['pk']),
            ];
        }

        return $columns;
    }

    public function prepareValue(mixed $value, ?string $key = null): mixed
    {
        if (is_bool($value)) {
            $value = ($value ? 1 : 0);
        }

        return parent::prepareValue($value, $key);
    }

    public function tableName(string $name): string
    {
        $parts = explode('.', $name);
        if (count($parts) > 1) {
            $name = $parts[1];
        }

        return $name;
    }
}
