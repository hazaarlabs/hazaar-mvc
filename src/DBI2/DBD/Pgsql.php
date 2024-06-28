<?php

declare(strict_types=1);

namespace Hazaar\DBI2\DBD;

use Hazaar\DBI2\Result;
use Hazaar\DBI2\Result\PDO;
use Hazaar\Map;

class Pgsql implements Interfaces\Driver
{
    use Traits\PDO {
        Traits\PDO::query as pdoQuery; // Alias the trait's query method to pdoQuery
    }
    use Traits\PDO\Transaction;
    use Traits\SQL;
    use Traits\SQL\Constraint;
    use Traits\SQL\Extension;
    use Traits\SQL\Index;
    use Traits\SQL\Schema;
    use Traits\SQL\Table;
    use Traits\SQL\View;
    use Traits\SQL\StoredFunction;
    use Traits\SQL\Trigger;
    use Traits\SQL\Sequence;

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

    public function __construct(Map $config)
    {
        $this->initQueryBuilder($config->get('schema', 'public'));
        $driverOptions = [];
        if ($config->has('options')) {
            $driverOptions = $config['options']->toArray();
            foreach ($driverOptions as $key => $value) {
                if (($constKey = constant('\PDO::'.$key)) === null) {
                    continue;
                }
                $driverOptions[$constKey] = $value;
                unset($driverOptions[$key]);
            }
        }
        $this->connect($this->mkdsn($config), $config->get('user'), $config->get('password'), $driverOptions);
    }

    public function getSchemaName(): string
    {
        return $this->queryBuilder->getSchemaName();
    }

    /**
     * Checks if a schema exists in the database.
     *
     * @param string $schemaName the name of the schema to check
     *
     * @return bool returns true if the schema exists, false otherwise
     */
    public function schemaExists(?string $schemaName = null): bool
    {
        if (!$schemaName) {
            $schemaName = $this->queryBuilder->getSchemaName();
        }
        $sql = $this->queryBuilder->exists('information_schema.schemata', ['schema_name' => $schemaName]);
        if ($result = $this->query($sql)) {
            return boolify($result->fetchColumn(0));
        }

        return false;
    }

    /**
     * Creates a new database schema if it does not already exist.
     *
     * @param string $schemaName the name of the schema to create
     *
     * @return bool returns true if the schema was created successfully, false otherwise
     */
    public function createSchema(string $schemaName): bool
    {
        $sql = $this->queryBuilder->create($schemaName, 'schema', true);

        return false !== $this->exec($sql);
    }

    public function query(string $sql): false|Result
    {
        $result = $this->pdoQuery($sql);
        if ($result instanceof \PDOStatement) {
            return new PDO($result);
        }

        return false;
    }

    /**
     * Retrieves a list of tables in the database.
     *
     * @param string $schema the name of the schema to retrieve the tables from. default is current schema
     *
     * @return array<int, array<string>>|false an array of tables, each represented as an associative array with keys 'schema' and 'name'
     */
    public function listTables(?string $schema = null): array|false
    {
        if (!$schema) {
            $schema = $this->queryBuilder->getSchemaName();
        }
        $sql = 'SELECT table_schema as "schema", table_name as name '
            ."FROM information_schema.tables t WHERE table_type = 'BASE TABLE'"
            ." AND table_schema = '{$schema}'"
            .' ORDER BY table_name DESC;';
        if ($result = $this->query($sql)) {
            return $result->fetchAll(\PDO::FETCH_ASSOC);
        }

        return false;
    }

    /**
     * Retrieves a list of constraints for a given table or all tables in the schema.
     *
     * @param null|string $table      The name of the table. If null, constraints for all tables in the schema will be retrieved.
     * @param null|string $type       The type of constraints to retrieve. If null, all types of constraints will be retrieved.
     * @param bool        $invertType Whether to invert the constraint type filter. If true, constraints of types other than the specified type will be retrieved.
     *
     * @return array<int, array<string>>|false an array of constraints or false if constraints are not allowed or an error occurred
     */
    public function listConstraints($table = null, $type = null, $invertType = false): array|false
    {
        if ($table) {
            list($schema, $table) = $this->queryBuilder->parseSchemaName($table);
        } else {
            $schema = $this->queryBuilder->getSchemaName();
        }
        $constraints = [];
        $sql = 'SELECT
                tc.constraint_name as name,
                tc.table_name as '.$this->queryBuilder->field('table').',
                tc.table_schema as '.$this->queryBuilder->field('schema').',
                kcu.column_name as '.$this->queryBuilder->field('column').",
                ccu.table_schema AS foreign_schema,
                ccu.table_name AS foreign_table,
                ccu.column_name AS foreign_column,
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
            JOIN information_schema.constraint_column_usage AS ccu
                ON ccu.constraint_name = tc.constraint_name
            LEFT JOIN information_schema.referential_constraints rc ON tc.constraint_name = rc.constraint_name
            WHERE tc.CONSTRAINT_SCHEMA='{$schema}'";
        if ($table) {
            $sql .= "\nAND tc.table_name='{$table}'";
        }
        if ($type) {
            $sql .= "\nAND tc.constraint_type".($invertType ? '!=' : '=')."'{$type}'";
        }
        $sql .= ';';
        if ($result = $this->query($sql)) {
            while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
                if ($constraint = ake($constraints, $row['name'])) {
                    if (!is_array($constraint['column'])) {
                        $constraint['column'] = [$constraint['column']];
                    }
                    if (!in_array($row['column'], $constraint['column'])) {
                        $constraint['column'][] = $row['column'];
                    }
                } else {
                    $constraint = [
                        'table' => $row['table'],
                        'column' => $row['column'],
                        'type' => $row['type'],
                    ];
                }
                foreach (['match_option', 'update_rule', 'delete_rule'] as $rule) {
                    if ($row[$rule]) {
                        $constraint[$rule] = $row[$rule];
                    }
                }
                if ('FOREIGN KEY' == $row['type'] && $row['foreign_table']) {
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
     * Retrieves a list of indexes for a given table or all tables in the specified schema.
     *
     * @param null|string $table The name of the table. If null, all tables in the schema will be considered.
     *
     * @return array<mixed>|false An array of indexes, where each index is represented by an associative array with the following keys:
     *                            - 'table': The name of the table the index belongs to.
     *                            - 'columns': An array of column names that make up the index.
     *                            - 'unique': A boolean indicating whether the index is unique or not.
     *
     * @throws \Exception if the index list retrieval fails
     */
    public function listIndexes(?string $table = null): array|false
    {
        if ($table) {
            list($schema, $table) = $this->queryBuilder->parseSchemaName($table);
        } else {
            $schema = $this->queryBuilder->getSchemaName();
        }
        $sql = "SELECT s.nspname, t.relname as table_name, i.relname as index_name, array_to_string(array_agg(a.attname), ', ') as column_names, ix.indisunique
            FROM pg_namespace s, pg_class t, pg_class i, pg_index ix, pg_attribute a
            WHERE s.oid = t.relnamespace
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
                'table' => $row['table_name'],
                'columns' => array_map('trim', explode(',', $row['column_names'])),
                'unique' => boolify($row['indisunique']),
            ];
        }

        return $indexes;
    }

    /**
     * Retrieves a list of views from the database.
     *
     * @return array<int, array<string>>|false an array of views, or null if no views are found
     */
    public function listViews(): array|false
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

        return false;
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
        $sql = 'SELECT table_name as name, trim(view_definition) as content FROM INFORMATION_SCHEMA.views WHERE table_schema='
            .$this->queryBuilder->prepareValue($schema).' AND table_name='.$this->queryBuilder->prepareValue($name);
        if ($result = $this->query($sql)) {
            return $result->fetch(\PDO::FETCH_ASSOC);
        }

        return false;
    }
}
