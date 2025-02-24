<?php

declare(strict_types=1);

namespace Hazaar\DBI\DBD;

use Hazaar\DBI\DBD\Interface\Driver;
use Hazaar\DBI\Interface\API\Constraint;
use Hazaar\DBI\Interface\API\Extension;
use Hazaar\DBI\Interface\API\Group;
use Hazaar\DBI\Interface\API\Index;
use Hazaar\DBI\Interface\API\Schema;
use Hazaar\DBI\Interface\API\Sequence;
use Hazaar\DBI\Interface\API\SQL;
use Hazaar\DBI\Interface\API\StoredFunction;
use Hazaar\DBI\Interface\API\Table;
use Hazaar\DBI\Interface\API\Transaction;
use Hazaar\DBI\Interface\API\Trigger;
use Hazaar\DBI\Interface\API\User;
use Hazaar\DBI\Interface\API\View;

class Pgsql implements Driver, Constraint, Extension, Group, Index, Schema, Sequence, SQL, StoredFunction, Table, Trigger, User, View, Transaction
{
    use Traits\PDO {
        Traits\PDO::query as pdoQuery; // Alias the trait's query method to pdoQuery
    }
    use Traits\PDO\Transaction;
    use Traits\SQL;
    use Traits\SQL\Extension;
    use Traits\SQL\Constraint;
    use Traits\SQL\Index;
    use Traits\SQL\Schema;
    use Traits\SQL\Table;
    use Traits\SQL\View;
    use Traits\SQL\StoredFunction;
    use Traits\SQL\Trigger;
    use Traits\SQL\Sequence;
    use Traits\SQL\User;
    use Traits\SQL\Group;

    /**
     * @var array<string>
     */
    public static array $dsnElements = [
        'host',
        'port',
        'dbname',
        'user',
        'password',
    ];

    /**
     * @var array<mixed>
     */
    private array $config;

    /**
     * @var array<string>
     */
    private static array $reservedWords = [
        'ALL',
        'ANALYSE',
        'ANALYZE',
        'AND',
        'ANY',
        'ARRAY',
        'AS',
        'ASC',
        'ASYMMETRIC',
        'AUTHORIZATION',
        'BINARY',
        'BOTH',
        'CASE',
        'CAST',
        'CHECK',
        'COLLATE',
        'COLLATION',
        'COLUMN',
        'CONCURRENTLY',
        'CONSTRAINT',
        'CREATE',
        'CROSS',
        'CURRENT_CATALOG',
        'CURRENT_DATE',
        'CURRENT_ROLE',
        'CURRENT_SCHEMA',
        'CURRENT_TIME',
        'CURRENT_TIMESTAMP',
        'CURRENT_USER',
        'DEFAULT',
        'DEFERRABLE',
        'DESC',
        'DISTINCT',
        'DO',
        'ELSE',
        'END',
        'EXCEPT',
        'FALSE',
        'FETCH',
        'FOR',
        'FOREIGN',
        'FREEZE',
        'FROM',
        'FULL',
        'GRANT',
        'GROUP',
        'HAVING',
        'ILIKE',
        'IN',
        'INITIALLY',
        'INNER',
        'INTERSECT',
        'INTO',
        'IS',
        'ISNULL',
        'JOIN',
        'LATERAL',
        'LEADING',
        'LEFT',
        'LIKE',
        'LIMIT',
        'LOCALTIME',
        'LOCALTIMESTAMP',
        'NATURAL',
        'NOT',
        'NOTNULL',
        'NULL',
        'OFFSET',
        'ON',
        'ONLY',
        'OR',
        'ORDER',
        'OUTER',
        'OVERLAPS',
        'PLACING',
        'PRIMARY',
        'REFERENCES',
        'RETURNING',
        'RIGHT',
        'SELECT',
        'SESSION_USER',
        'SIMILAR',
        'SOME',
        'SYMMETRIC',
        'TABLE',
        'TABLESAMPLE',
        'THEN',
        'TO',
        'TRAILING',
        'TRUE',
        'UNION',
        'UNIQUE',
        'USER',
        'USING',
        'VARIADIC',
        'VERBOSE',
        'WHEN',
        'WHERE',
        'WINDOW',
        'WITH',
        'WITHIN',
    ];

    /**
     * @param array<mixed> $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        if (!array_key_exists('timezone', $config)) {
            $config['timezone'] = date_default_timezone_get();
        }
        $this->initQueryBuilder($this->config['schema'] ?? 'public');
        $this->queryBuilder->setReservedWords(self::$reservedWords);
        $driverOptions = [];
        if (isset($config['options'])) {
            $driverOptions = $config['options']->toArray();
            foreach ($driverOptions as $key => $value) {
                if (($constKey = constant('\PDO::'.$key)) === null) {
                    continue;
                }
                $driverOptions[$constKey] = $value;
                unset($driverOptions[$key]);
            }
        }
        $this->connect($this->mkdsn($config), $config['user'] ?? '', $config['password'] ?? '', $driverOptions);
    }

    /**
     * Retrieves a list of extensions in the specified schema.
     *
     * @return array<int, array<string>> an array containing the names of the extensions
     */
    public function listExtensions(): array
    {
        $results = $this->query('SELECT e.extname FROM pg_catalog.pg_extension e
            INNER JOIN pg_catalog.pg_namespace n ON e.extnamespace=n.oid
            WHERE n.nspname=\''.$this->queryBuilder->getSchemaName().'\';')->fetchAll(\PDO::FETCH_NUM);

        return array_column($results, 0);
    }

    /**
     * Creates a PostgreSQL extension.
     *
     * @param string $name the name of the extension to create
     *
     * @return bool returns true if the extension was created successfully, false otherwise
     */
    public function createExtension($name): bool
    {
        $sql = 'CREATE EXTENSION IF NOT EXISTS '.$this->queryBuilder->quoteSpecial($name).';';

        return false !== $this->exec($sql);
    }

    /**
     * Drops a PostgreSQL extension from the database.
     *
     * @param string $name     the name of the extension to drop
     * @param bool   $ifExists (optional) Whether to drop the extension only if it exists. Default is false.
     *
     * @return bool returns true if the extension was successfully dropped, false otherwise
     */
    public function dropExtension($name, $ifExists = false): bool
    {
        $sql = 'DROP EXTENSION ';
        if (true === $ifExists) {
            $sql .= 'IF EXISTS ';
        }
        $sql .= $this->queryBuilder->quoteSpecial($name).';';

        return false !== $this->exec($sql);
    }

    public function listUsers(): array
    {
        $sql = 'SELECT rolname FROM pg_roles WHERE rolcanlogin = true';

        return $this->query($sql)->fetchAll();
    }

    public function createUser(string $name, ?string $password = null, array $privileges = []): bool
    {
        $sql = 'CREATE ROLE '
            .$this->queryBuilder->quoteSpecial($name)
            .' WITH LOGIN';
        if (null !== $password) {
            $sql .= ' PASSWORD '.$this->queryBuilder->prepareValue($password);
        }
        if (!empty($privileges)) {
            $sql .= ' '.implode(' ', $privileges);
        }

        return false !== $this->exec($sql);
    }

    public function dropUser(string $name, bool $ifExists = false): bool
    {
        $sql = 'DROP ROLE '.($ifExists ? 'IF EXISTS ' : '')
            .$this->queryBuilder->quoteSpecial($name);

        return false !== $this->exec($sql);
    }

    public function listGroups(): array
    {
        $sql = 'SELECT rolname FROM pg_roles WHERE rolcanlogin = false';

        return $this->query($sql)->fetchAll();
    }

    public function createGroup(string $name): bool
    {
        $sql = 'CREATE ROLE '
            .$this->queryBuilder->prepareValue($name);

        return false !== $this->exec($sql);
    }

    public function dropGroup(string $name, bool $ifExists = false): bool
    {
        $sql = 'DROP ROLE '.($ifExists ? 'IF EXISTS ' : '')
            .$this->queryBuilder->prepareValue($name);

        return false !== $this->exec($sql);
    }

    /**
     * Retrieves a list of indexes for a given table or all tables in the specified schema.
     *
     * @param null|string $table The name of the table. If null, all tables in the schema will be considered.
     *
     * @return array<mixed> An array of indexes, where each index is represented by an associative array with the following keys:
     *                      - 'table': The name of the table the index belongs to.
     *                      - 'columns': An array of column names that make up the index.
     *                      - 'unique': A boolean indicating whether the index is unique or not.
     *
     * @throws \Exception if the index list retrieval fails
     */
    public function listIndexes(?string $table = null): array
    {
        if ($table) {
            list($schema, $table) = $this->queryBuilder->parseSchemaName($table);
        } else {
            $schema = $this->queryBuilder->getSchemaName();
        }
        $sql = "SELECT s.nspname, t.relname as table_name, i.relname as index_name, array_to_string(array_agg(a.attname), ', ') as column_names, ix.indisunique
            FROM pg_namespace s, pg_class t, pg_class i, pg_index ix, pg_attribute a
            WHERE s.oid = t.relnamespace
                AND ix.indisprimary = FALSE
                AND t.oid = ix.indrelid
                AND i.oid = ix.indexrelid
                AND a.attrelid = t.oid
                AND a.attnum = ANY(ix.indkey)
                AND t.relkind = 'r'
                AND s.nspname = '{$schema}'";
        if ($table) {
            $sql .= "\nAND t.relname = '{$table}'";
        }
        $sql .= "\nGROUP BY s.nspname, t.relname, i.relname, ix.indisunique ORDER BY t.relname, i.relname;";
        if (!($result = $this->query($sql))) {
            throw new \Exception('Index list failed. '.$this->errorInfo()[2]);
        }
        $indexes = [];
        while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
            $indexes[$row['index_name']] = [
                'name' => $row['index_name'],
                'table' => $row['table_name'],
                'columns' => array_map('trim', explode(',', $row['column_names'])),
                'unique' => boolify($row['indisunique']),
            ];
        }

        return $indexes;
    }

    public function listViews(): array
    {
        $sql = 'SELECT table_schema as "schema", table_name as name FROM INFORMATION_SCHEMA.views WHERE ';
        if ('public' != $this->queryBuilder->getSchemaName()) {
            $sql .= "table_schema = '{$this->queryBuilder->getSchemaName()}'";
        } else {
            $sql .= "table_schema NOT IN ( 'information_schema', 'pg_catalog' )";
        }
        $sql .= ' ORDER BY table_name DESC;';
        if ($result = $this->query($sql)) {
            return $result->fetchAll(\PDO::FETCH_ASSOC);
        }

        return [];
    }

    /**
     * Retrieves the description of a database view.
     *
     * @param string $name the name of the view
     *
     * @return array<int, array<string>>|false the description of the view as an associative array, or null if the view does not exist
     */
    public function describeView($name): array|false
    {
        list($schema, $name) = $this->queryBuilder->parseSchemaName($name);
        $sql = 'SELECT table_name as name, trim(view_definition) as query FROM INFORMATION_SCHEMA.views WHERE table_schema='
            .$this->queryBuilder->prepareValue($schema).' AND table_name='.$this->queryBuilder->prepareValue($name);
        if ($result = $this->query($sql)) {
            return $result->fetch(\PDO::FETCH_ASSOC);
        }

        return false;
    }

    public function createView(string $name, mixed $content, bool $replace = false): bool
    {
        $sql = 'CREATE '
            .($replace ? 'OR REPLACE ' : '')
            .'VIEW '.$this->queryBuilder->schemaName($name).' AS '.rtrim($content, ' ;');

        return false !== $this->exec($sql);
    }

    public function viewExists(string $viewName): bool
    {
        $views = $this->listViews();

        return in_array($viewName, $views);
    }

    public function dropView(string $name, bool $cascade = false, bool $ifExists = false): bool
    {
        $sql = 'DROP VIEW ';
        if (true === $ifExists) {
            $sql .= 'IF EXISTS ';
        }
        $sql .= $this->queryBuilder->schemaName($name);
        if (true === $cascade) {
            $sql .= ' CASCADE';
        }

        return false !== $this->exec($sql);
    }

    public function setTimezone(string $tz): bool
    {
        return false !== $this->exec('SET TIMEZONE TO \''.$tz.'\'');
    }

    public function repair(): bool
    {
        $sql = 'VACUUM ANALYZE';

        return false !== $this->exec($sql);
    }
}
