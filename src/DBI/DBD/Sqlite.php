<?php

declare(strict_types=1);

namespace Hazaar\DBI\DBD;

use Hazaar\DBI\DBD\Interface\Driver;
use Hazaar\DBI\Interface\API\SQL;
use Hazaar\DBI\Interface\API\Table;
use Hazaar\DBI\Interface\API\Transaction;
use Hazaar\DBI\Interface\API\Trigger;

class Sqlite implements Driver, SQL, Table, Transaction, Trigger
{
    use Traits\PDO {
        Traits\PDO::query as pdoQuery; // Alias the trait's query method to pdoQuery
    }
    use Traits\PDO\Transaction;
    use Traits\SQL;
    use Traits\SQL\Table;
    use Traits\SQL\Index;
    use Traits\SQL\Trigger;

    /**
     * @var array<string>
     */
    public static array $dsnElements = [
        'database',
    ];

    /**
     * @var array<string>
     */
    private static array $reservedWords = [];

    /**
     * @param array<mixed> $config
     */
    public function __construct(array $config)
    {
        $this->initQueryBuilder();
        if (!array_key_exists('database', $config)) {
            throw new \Exception('Database not specified in configuration');
        }
        $this->connect('sqlite:'.$config['database']);
    }

    public function dropTrigger(string $name, string $table, bool $ifExists = false, bool $cascade = false): bool
    {
        $sql = 'DROP TRIGGER '.($ifExists ? 'IF EXISTS ' : '').$name;

        return false !== $this->exec($sql);
    }

    public function repair(): bool
    {
        $sql = 'VACUUM ANALYZE';

        return false !== $this->exec($sql);
    }

    public function listTables(): array
    {
        $sql = 'SELECT name FROM sqlite_master WHERE type = \'table\'';

        return $this->query($sql)->fetchAll();
    }

    public function tableExists(string $name): bool
    {
        $sql = 'SELECT name FROM sqlite_master WHERE type = \'table\' AND name = ?';

        return false !== $this->query($sql, [$name])->fetch();
    }

    public function describeTable(string $tableName, ?string $sort = null): array|false
    {
        $sql = 'PRAGMA table_info('.$tableName.')';
        $info = $this->query($sql)->fetchAll();
        $columns = [];
        foreach ($info as &$row) {
            $default = null;
            if (is_string($row['dflt_value'])) {
                $default = trim($row['dflt_value']);
                $default = "'" !== substr($default, 0, 1) ? strtolower($default) : $default;
            }
            $column = [
                'name' => $row['name'],
                'default' => $default,
                'not_null' => boolify($row['notnull']),
                'type' => strtolower($row['type']),
                'length' => null,
            ];
            if (1 == $row['pk']) {
                $column['primarykey'] = true;
            }
            $columns[] = $column;
        }

        return $columns;
    }
}
