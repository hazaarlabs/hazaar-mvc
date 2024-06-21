<?php

declare(strict_types=1);

namespace Hazaar\DBI2\DBD\Traits;

use Hazaar\DBI2\Interfaces\QueryBuilder;
use Hazaar\DBI2\QueryBuilder\SQL as SQLBuilder;

trait SQL
{
    private QueryBuilder $queryBuilder;

    public function initQueryBuilder(string $schema): void
    {
        $this->queryBuilder = new SQLBuilder($schema);
    }

    public function getQueryBuilder(): QueryBuilder
    {
        return $this->queryBuilder;
    }

    public function listTables(): array
    {
        return $this->listInformationSchema('tables', ['table_name'], [
            'table_schema' => $this->queryBuilder->getSchemaName(),
        ]);
    }

    public function createTable(string $tableName, mixed $columns): bool
    {
        $sql = 'CREATE TABLE '.$this->queryBuilder->schemaName($tableName)." (\n";
        $coldefs = [];
        $constraints = [];
        foreach ($columns as $name => $info) {
            if (is_array($info)) {
                if (is_numeric($name)) {
                    if (!array_key_exists('name', $info)) {
                        throw new \Exception('Error creating new table.  Name is a number which is not allowed!');
                    }
                    $name = $info['name'];
                }
                if (!($type = $this->type($info))) {
                    throw new \Exception("Column '{$name}' has no data type!");
                }
                $def = $this->queryBuilder->field($name).' '.$type;
                if (array_key_exists('default', $info) && null !== $info['default']) {
                    $def .= ' DEFAULT '.$info['default'];
                }
                if (array_key_exists('not_null', $info) && $info['not_null']) {
                    $def .= ' NOT NULL';
                }
                if (array_key_exists('primarykey', $info) && $info['primarykey']) {
                    $driver = strtolower(basename(str_replace('\\', '/', get_class($this))));
                    if ('pgsql' == $driver) {
                        $constraints[] = ' PRIMARY KEY('.$this->queryBuilder->field($name).')';
                    } else {
                        $def .= ' PRIMARY KEY';
                    }
                }
            } else {
                $def = "\t".$this->queryBuilder->field($name).' '.$info;
            }
            $coldefs[] = $def;
        }
        $sql .= implode(",\n", $coldefs);
        if (count($constraints) > 0) {
            $sql .= ",\n".implode(",\n", $constraints);
        }
        $sql .= "\n);";
        $affected = $this->exec($sql);
        if (false === $affected) {
            throw new \Exception("Could not create table '{$tableName}'. ".$this->errorInfo()[2]);
        }

        return true;
    }

    /**
     * @return array<int, array<string>>|false
     */
    public function describeTable(string $tableName, ?string $sort = null): array|false
    {
        if (!$sort) {
            $sort = 'ordinal_position';
        }
        $result = $this->query('SELECT * FROM information_schema.columns WHERE table_name='
            .$this->quote($tableName).' AND table_schema='
            .$this->quote($this->queryBuilder->getSchemaName()).' ORDER BY '
            .$sort);
        if (false === $result) {
            throw new \Exception(ake($this->errorInfo(), 2));
        }
        $columns = [];
        while ($col = $result->fetch(\PDO::FETCH_ASSOC)) {
            $col = array_change_key_case($col, CASE_LOWER);
            if (array_key_exists('column_default', $col)
                && $col['column_default']
                && preg_match('/nextval\(\'(\w*)\'::regclass\)/', $col['column_default'], $matches)) {
                if ($info = $this->describeSequence($matches[1])) {
                    $col['data_type'] = 'serial';
                    $col['column_default'] = null;
                    $col['sequence'] = $info;
                }
            }
            // Fixed array types to their actual SQL array data type
            if ('ARRAY' == $col['data_type']
                && ($udtName = ake($col, 'udt_name'))) {
                if ('_' == $udtName[0]) {
                    $col['data_type'] = substr($udtName, 1).'[]';
                }
            }
            $coldata = [
                'name' => $col['column_name'],
                'default' => $this->fixValue($col['column_default']),
                'not_null' => (('NO' == $col['is_nullable']) ? true : false),
                'data_type' => $this->type($col),
                'length' => $col['character_maximum_length'],
            ];
            if (array_key_exists('sequence', $col)) {
                $coldata['sequence'] = $col['sequence']['sequence_schema'].'.'.$col['sequence']['sequence_name'];
            }
            $columns[] = $coldata;
        }

        return $columns;
    }

    public function renameTable(string $fromName, string $toName): bool
    {
        if (strpos($toName, '.')) {
            list($fromSchemaName, $fromName) = explode('.', $fromName);
            list($toSchemaName, $toName) = explode('.', $toName);
            if ($toSchemaName != $fromSchemaName) {
                throw new \Exception('You can not rename tables between schemas!');
            }
        }
        $sql = 'ALTER TABLE '.$this->queryBuilder->schemaName($fromName).' RENAME TO '.$this->queryBuilder->field($toName).';';
        $affected = $this->exec($sql);
        if (false === $affected) {
            return false;
        }

        return true;
    }

    public function dropTable(string $name, bool $cascade = false, bool $ifExists = false): bool
    {
        $sql = 'DROP TABLE ';
        if (true === $ifExists) {
            $sql .= 'IF EXISTS ';
        }
        $sql .= $this->queryBuilder->schemaName($name).($cascade ? ' CASCADE' : '').';';
        $affected = $this->exec($sql);
        if (false === $affected) {
            return false;
        }

        return true;
    }

    public function addColumn(string $tableName, mixed $columnSpec): bool
    {
        if (!array_key_exists('name', $columnSpec)) {
            return false;
        }
        if (!array_key_exists('data_type', $columnSpec)) {
            return false;
        }
        $sql = 'ALTER TABLE '.$this->queryBuilder->schemaName($tableName).' ADD COLUMN '.$this->queryBuilder->field($columnSpec['name']).' '.$this->type($columnSpec);
        if (array_key_exists('not_null', $columnSpec) && $columnSpec['not_null']) {
            $sql .= ' NOT NULL';
        }
        if (array_key_exists('default', $columnSpec) && null !== $columnSpec['default']) {
            $sql .= ' DEFAULT '.$columnSpec['default'];
        }
        $affected = $this->exec($sql);
        if (false === $affected) {
            return false;
        }

        return true;
    }

    public function alterColumn(string $tableName, string $column, mixed $columnSpec): bool
    {
        $sqls = [];
        // Check if the column is being renamed and update the name first.
        if (array_key_exists('name', $columnSpec)) {
            $sql = 'ALTER TABLE '.$this->queryBuilder->schemaName($tableName).' RENAME COLUMN '.$this->queryBuilder->field($column).' TO '.$this->queryBuilder->field($columnSpec['name']);
            $this->exec($sql);
            $column = $columnSpec['name'];
        }
        $prefix = 'ALTER TABLE '.$this->queryBuilder->schemaName($tableName).' ALTER COLUMN '.$this->queryBuilder->field($column);
        if (array_key_exists('data_type', $columnSpec)) {
            $alterType = $prefix.' TYPE '.$this->type($columnSpec);
            if (array_key_exists('using', $columnSpec)) {
                $alterType .= ' USING '.$columnSpec['using'];
            }
            $sqls[] = $alterType;
        }
        if (array_key_exists('not_null', $columnSpec)) {
            $sqls[] = $prefix.' '.($columnSpec['not_null'] ? 'SET' : 'DROP').' NOT NULL';
        }
        if (array_key_exists('default', $columnSpec)) {
            $sqls[] = $prefix.' '.(null === $columnSpec['default']
                ? 'DROP DEFAULT'
                : 'SET DEFAULT '.$columnSpec['default']);
        }
        foreach ($sqls as $sql) {
            $this->exec($sql);
        }

        return true;
    }

    public function dropColumn(string $tableName, string $column, bool $ifExists = false): bool
    {
        $sql = 'ALTER TABLE ';
        if (true === $ifExists) {
            $sql .= 'IF EXISTS ';
        }
        $sql .= $this->queryBuilder->schemaName($tableName).' DROP COLUMN ';
        if (true === $ifExists) {
            $sql .= 'IF EXISTS ';
        }
        $sql .= $this->queryBuilder->field($column).';';
        $affected = $this->exec($sql);
        if (false === $affected) {
            return false;
        }

        return true;
    }

    public function listSequences(): array
    {
        return $this->listInformationSchema('sequences', ['sequence_name'], [
            'sequence_schema' => $this->queryBuilder->getSchemaName(),
        ]);
    }

    /**
     * @return array<int, array<string>>|false
     */
    public function describeSequence(string $name): array|false
    {
        $sql = $this->queryBuilder->select('*')
            ->from('information_schema.sequences')
            ->where(['sequence_name' => $name, 'sequence_schema' => $this->queryBuilder->getSchemaName()])
            ->toString()
        ;
        $result = $this->query($sql);

        return $result->fetch(\PDO::FETCH_ASSOC);
    }

    public function listIndexes(?string $table = null): array|false
    {
        return false;
    }

    public function createIndex(string $indexName, string $tableName, mixed $idxInfo): bool
    {
        if (!array_key_exists('columns', $idxInfo)) {
            return false;
        }
        $indexes = $this->listIndexes($tableName);
        if (array_key_exists($indexName, $indexes)) {
            return true;
        }
        $sql = 'CREATE';
        if (array_key_exists('unique', $idxInfo) && $idxInfo['unique']) {
            $sql .= ' UNIQUE';
        }
        $sql .= ' INDEX '.$this->queryBuilder->field($indexName).' ON '.$this->queryBuilder->schemaName($tableName).' ('.implode(',', array_map([$this, 'field'], $idxInfo['columns'])).')';
        if (array_key_exists('using', $idxInfo) && $idxInfo['using']) {
            $sql .= ' USING '.$idxInfo['using'];
        }
        $affected = $this->exec($sql);
        if (false === $affected) {
            return false;
        }

        return true;
    }

    public function dropIndex(string $indexName, bool $ifExists = false): bool
    {
        $sql = 'DROP INDEX ';
        if (true === $ifExists) {
            $sql .= 'IF EXISTS ';
        }
        $sql .= $this->queryBuilder->field($indexName);
        $affected = $this->exec($sql);
        if (false === $affected) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string>|false
     */
    public function listConstraints(
        ?string $tableName = null,
        ?string $type = null,
        bool $invertType = false
    ): array|false {
        $criteria = ['constraint_schema' => $this->queryBuilder->getSchemaName()];
        if ($tableName) {
            $criteria['table_name'] = $tableName;
        }
        if ($type) {
            $criteria['constraint_type'] = $type;
        }
        $constraints = $this->listInformationSchema('table_constraints', ['constraint_name'], $criteria);
        if ($invertType) {
            $constraints = array_diff($this->listInformationSchema('table_constraints', ['constraint_name'], $criteria), $constraints);
        }

        return $constraints;
    }

    public function addConstraint(string $constraintName, mixed $info): bool
    {
        if (!array_key_exists('table', $info)) {
            return false;
        }
        if (!array_key_exists('type', $info) || !$info['type']) {
            if (array_key_exists('references', $info)) {
                $info['type'] = 'FOREIGN KEY';
            } else {
                return false;
            }
        }
        if ('FOREIGN KEY' == $info['type']) {
            if (!array_key_exists('update_rule', $info)) {
                $info['update_rule'] = 'NO ACTION';
            }
            if (!array_key_exists('delete_rule', $info)) {
                $info['delete_rule'] = 'NO ACTION';
            }
        }
        $column = $info['column'];
        if (is_array($column)) {
            foreach ($column as &$col) {
                $col = $this->queryBuilder->field($col);
            }
            $column = implode(', ', $column);
        } else {
            $column = $this->queryBuilder->field($column);
        }
        $sql = 'ALTER TABLE '.$this->queryBuilder->schemaName($info['table']).' ADD CONSTRAINT '.$this->queryBuilder->field($constraintName)." {$info['type']} (".$column.')';
        if (array_key_exists('references', $info)) {
            $sql .= ' REFERENCES '.$this->queryBuilder->schemaName($info['references']['table']).' ('.$this->queryBuilder->field($info['references']['column']).") ON UPDATE {$info['update_rule']} ON DELETE {$info['delete_rule']}";
        }
        $affected = $this->exec($sql);
        if (false === $affected) {
            return false;
        }

        return true;
    }

    public function dropConstraint(string $constraintName, string $tableName, bool $cascade = false, bool $ifExists = false): bool
    {
        $sql = 'ALTER TABLE ';
        if (true === $ifExists) {
            $sql .= 'IF EXISTS ';
        }
        $sql .= $this->queryBuilder->schemaName($tableName).' DROP CONSTRAINT ';
        if (true === $ifExists) {
            $sql .= 'IF EXISTS ';
        }
        $sql .= $this->queryBuilder->field($constraintName).($cascade ? ' CASCADE' : '');
        $affected = $this->exec($sql);
        if (false === $affected) {
            return false;
        }

        return true;
    }

    /**
     * @return array<int, array<string>>|false
     */
    public function listViews(): array|false
    {
        return false;
    }

    /**
     * @return array<int, array<string>>|false
     */
    public function describeView(string $name): array|false
    {
        return false;
    }

    public function createView(string $name, mixed $content): bool
    {
        $sql = 'CREATE OR REPLACE VIEW '.$this->queryBuilder->schemaName($name).' AS '.rtrim($content, ' ;');

        return false !== $this->exec($sql);
    }

    public function viewExists(string $viewName): bool
    {
        $views = $this->listViews();

        return false !== $views && in_array($viewName, $views);
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

    /**
     * List defined functions.
     *
     * @return array<int,array<mixed>|string>|false
     */
    public function listFunctions(?string $schemaName = null, bool $includeParameters = false): array|false
    {
        if (null === $schemaName) {
            $schemaName = $this->queryBuilder->getSchemaName();
        }
        $sql = "SELECT r.specific_name, 
                r.routine_schema, 
                r.routine_name, 
                p.data_type 
            FROM INFORMATION_SCHEMA.routines r 
            LEFT JOIN INFORMATION_SCHEMA.parameters p ON p.specific_name=r.specific_name
            WHERE r.routine_type='FUNCTION'
            AND r.specific_schema=".$this->queryBuilder->prepareValue($schemaName)."
            AND NOT (r.routine_body='EXTERNAL' AND r.external_language='C')
            ORDER BY r.routine_name, p.ordinal_position;";
        $q = $this->query($sql);
        $list = [];
        while ($row = $q->fetch(\PDO::FETCH_ASSOC)) {
            $id = $includeParameters ? $row['specific_name'] : $row['routine_schema'].$row['routine_name'];
            if (true !== $includeParameters) {
                if (!array_key_exists($id, $list)) {
                    $list[$id] = $row['routine_name'];
                }

                continue;
            }
            if (!array_key_exists($id, $list)) {
                $list[$id] = [
                    'name' => $row['routine_name'],
                    'parameters' => [],
                ];
            }
            if ($row['data_type']) {
                $list[$id]['parameters'][] = $row['data_type'];
            }
        }

        return array_values($list);
    }

    /**
     * @return array<int,array{
     *  name:string,
     *  return_type:string,
     *  content:string,
     *  parameters:?array<int,array{name:string,type:string,mode:string,ordinal_position:int}>,
     *  lang:string
     * }>|false
     */
    public function describeFunction(string $name, ?string $schemaName = null): array|false
    {
        if (null === $schemaName) {
            $schemaName = $this->queryBuilder->getSchemaName();
        }
        $sql = "SELECT r.specific_name,
                    r.routine_schema,
                    r.routine_name,
                    r.data_type AS return_type,
                    r.routine_body,
                    r.routine_definition,
                    r.external_language,
                    p.parameter_name,
                    p.data_type,
                    p.parameter_mode,
                    p.ordinal_position
                FROM INFORMATION_SCHEMA.routines r
                LEFT JOIN INFORMATION_SCHEMA.parameters p ON p.specific_name=r.specific_name
                WHERE r.routine_type='FUNCTION'
                AND r.routine_schema=".$this->queryBuilder->prepareValue($schemaName).'
                AND r.routine_name='.$this->queryBuilder->prepareValue($name).'
                ORDER BY r.routine_name, p.ordinal_position;';
        if (!($q = $this->query($sql))) {
            throw new \Exception($this->errorInfo()[2]);
        }
        $info = [];
        while ($row = $q->fetch(\PDO::FETCH_ASSOC)) {
            if (!array_key_exists($row['specific_name'], $info)) {
                if (!($routineDefinition = ake($row, 'routine_definition'))) {
                    continue;
                }
                $item = [
                    'name' => $row['routine_name'],
                    'return_type' => $row['return_type'],
                    'content' => trim($routineDefinition),
                ];
                $item['parameters'] = [];
                $item['lang'] = ('EXTERNAL' === strtoupper($row['routine_body']))
                    ? $row['external_language']
                    : $row['routine_body'];
                $info[$row['specific_name']] = $item;
            }
            if (null === $row['parameter_name']) {
                continue;
            }
            $info[$row['specific_name']]['parameters'][] = [
                'name' => $row['parameter_name'],
                'type' => $row['data_type'],
                'mode' => $row['parameter_mode'],
                'ordinal_position' => $row['ordinal_position'],
            ];
        }
        usort($info, function ($a, $b) {
            if (count($a['parameters']) === count($b['parameters'])) {
                return 0;
            }

            return count($a['parameters']) < count($b['parameters']) ? -1 : 1;
        });

        return $info;
    }

    /**
     * Create a new database function.
     *
     * @param mixed $name The name of the function to create
     * @param mixed $spec A function specification.  This is basically the array returned from describeFunction()
     *
     * @return bool
     */
    public function createFunction($name, $spec)
    {
        $sql = 'CREATE OR REPLACE FUNCTION '.$this->queryBuilder->schemaName($name).' (';
        if ($params = ake($spec, 'parameters')) {
            $items = [];
            foreach ($params as $param) {
                $items[] = ake($param, 'mode', 'IN').' '.ake($param, 'name').' '.ake($param, 'type');
            }
            $sql .= implode(', ', $items);
        }
        $sql .= ') RETURNS '.ake($spec, 'return_type', 'TEXT').' LANGUAGE '.ake($spec, 'lang', 'SQL')." AS\n\$BODY$ ";
        $sql .= ake($spec, 'content');
        $sql .= '$BODY$;';

        return false !== $this->exec($sql);
    }

    /**
     * Remove a function from the database.
     *
     * @param string                    $name     The name of the function to remove
     * @param null|array<string>|string $argTypes the argument list of the function to remove
     * @param bool                      $cascade  Whether to perform a DROP CASCADE
     */
    public function dropFunction(
        string $name,
        null|array|string $argTypes = null,
        bool $cascade = false,
        bool $ifExists = false
    ): bool {
        $sql = 'DROP FUNCTION ';
        if (true === $ifExists) {
            $sql .= 'IF EXISTS ';
        }
        $sql .= $this->queryBuilder->schemaName($name);
        if (null !== $argTypes) {
            $sql .= '('.(is_array($argTypes) ? implode(', ', $argTypes) : $argTypes).')';
        }
        if (true === $cascade) {
            $sql .= ' CASCADE';
        }

        return false !== $this->exec($sql);
    }

    /**
     * List defined triggers.
     *
     * @param string $schemaName Optional: schema name.  If not supplied the current schemaName is used.
     *
     * @return array<int,array{schema:string,name:string}>|false
     */
    public function listTriggers(?string $tableName = null, ?string $schemaName = null): array|false
    {
        if (null === $schemaName) {
            $schemaName = $this->queryBuilder->getSchemaName();
        }
        $sql = 'SELECT DISTINCT trigger_schema AS schema, trigger_name AS name
                    FROM INFORMATION_SCHEMA.triggers
                    WHERE event_object_schema='.$this->queryBuilder->prepareValue($schemaName);
        if (null !== $tableName) {
            $sql .= ' AND event_object_table='.$this->queryBuilder->prepareValue($tableName);
        }
        if ($result = $this->query($sql)) {
            return $result->fetchAll(\PDO::FETCH_ASSOC);
        }

        return false;
    }

    /**
     * Describe a database trigger.
     *
     * This will return an array as there can be multiple triggers with the same name but with different attributes
     *
     * @param string $schemaName Optional: schemaName name.  If not supplied the current schemaName is used.
     *
     * @return array{
     *  name:string,
     *  events:array<string>,
     *  table:string,
     *  content:string,
     *  orientation:string,
     *  timing:string
     * }|false
     */
    public function describeTrigger(string $triggerName, ?string $schemaName = null): array|false
    {
        if (null === $schemaName) {
            $schemaName = $this->queryBuilder->getSchemaName();
        }
        $sql = 'SELECT trigger_name AS name,
                        event_manipulation AS events,
                        event_object_table AS table,
                        action_statement AS content,
                        action_orientation AS orientation,
                        action_timing AS timing
                    FROM INFORMATION_SCHEMA.triggers
                    WHERE trigger_schema='.$this->queryBuilder->prepareValue($schemaName)
                    .' AND trigger_name='.$this->queryBuilder->prepareValue($triggerName);
        if (!($result = $this->query($sql))) {
            return false;
        }
        $info = $result->fetch(\PDO::FETCH_ASSOC);
        $info['events'] = [$info['events']];
        while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
            $info['events'][] = $row['events'];
        }

        return $info;
    }

    /**
     * Summary of createTrigger.
     *
     * @param string $tableName The table on which the trigger is being created
     * @param mixed  $spec      The spec of the trigger.  Basically this is the array returned from describeTriggers()
     */
    public function createTrigger(string $triggerName, string $tableName, mixed $spec = []): bool
    {
        $sql = 'CREATE TRIGGER '.$this->queryBuilder->field($triggerName)
            .' '.ake($spec, 'timing', 'BEFORE')
            .' '.implode(' OR ', ake($spec, 'events', ['INSERT']))
            .' ON '.$this->queryBuilder->schemaName($tableName)
            .' FOR EACH '.ake($spec, 'orientation', 'ROW')
            .' '.ake($spec, 'content', 'EXECUTE');

        return false !== $this->exec($sql);
    }

    /**
     * Drop a trigger from a table.
     *
     * @param string $tableName The name of the table to remove the trigger from
     * @param bool   $cascade   Whether to drop CASCADE
     */
    public function dropTrigger(string $triggerName, string $tableName, bool $cascade = false, bool $ifExists = false): bool
    {
        $sql = 'DROP TRIGGER ';
        if (true === $ifExists) {
            $sql .= 'IF EXISTS ';
        }
        $sql .= $this->queryBuilder->field($triggerName).' ON '.$this->queryBuilder->schemaName($tableName);
        $sql .= ' '.((true === $cascade) ? ' CASCADE' : ' RESTRICT');

        return false !== $this->exec($sql);
    }

    /**
     * @return array<int, array<string>>|false
     */
    public function listUsers(): array|false
    {
        return false;
    }

    /**
     * @return array<int, array<string>>|false
     */
    public function listGroups(): array|false
    {
        return false;
    }

    /**
     * @param array<string> $privileges
     */
    public function createRole(string $name, ?string $password = null, array $privileges = []): bool
    {
        return false;
    }

    public function dropRole(string $name, bool $ifExists = false): bool
    {
        return false;
    }

    /**
     * @return array<int, array<string>>|false
     */
    public function listExtensions(): array|false
    {
        return false;
    }

    public function createExtension(string $name): bool
    {
        return false;
    }

    public function dropExtension(string $name, bool $ifExists = false): bool
    {
        return false;
    }

    public function createDatabase(string $name): bool
    {
        $sql = 'CREATE DATABASE '.$this->queryBuilder->quoteSpecial($name).';';
        $result = $this->query($sql);

        return true;
    }

    /**
     * TRUNCATE empty a table or set of tables.
     *
     * TRUNCATE quickly removes all rows from a set of tables. It has the same effect as an unqualified DELETE on
     * each table, but since it does not actually scan the tables it is faster. Furthermore, it reclaims disk space
     * immediately, rather than requiring a subsequent VACUUM operation. This is most useful on large tables.
     *
     * @param string $tableName       The name of the table(s) to truncate.  Multiple tables are supported.
     * @param bool   $only            Only the named table is truncated. If FALSE, the table and all its descendant tables (if any) are truncated.
     * @param bool   $restartIdentity Automatically restart sequences owned by columns of the truncated table(s).  The default is to no restart.
     * @param bool   $cascade         If TRUE, automatically truncate all tables that have foreign-key references to any of the named tables, or
     *                                to any tables added to the group due to CASCADE.  If FALSE, Refuse to truncate if any of the tables have
     *                                foreign-key references from tables that are not listed in the command. FALSE is the default.
     */
    public function truncate(string $tableName, bool $only = false, bool $restartIdentity = false, bool $cascade = false): bool
    {
        $sql = 'TRUNCATE TABLE '.($only ? 'ONLY ' : '').$this->queryBuilder->schemaName($tableName);
        $sql .= ' '.($restartIdentity ? 'RESTART IDENTITY' : 'CONTINUE IDENTITY');
        $sql .= ' '.($cascade ? 'CASCADE' : 'RESTRICT');

        return false !== $this->exec($sql);
    }

    protected function fixValue(mixed $value): mixed
    {
        return $value;
    }

    /**
     * @param array<string> $info
     */
    protected function type(array $info): string
    {
        if (!($type = ake($info, 'data_type'))) {
            return 'character varying';
        }
        if ($array = ('[]' === substr($type, -2))) {
            $type = substr($type, 0, -2);
        }

        return $type.(ake($info, 'length') ? '('.$info['length'].')' : null).($array ? '[]' : '');
    }

    /**
     * @param array<string> $columns
     *
     * @return array<mixed>|false
     */
    private function listInformationSchema(string $table, array $columns, mixed $criteria): array|false
    {
        $queryBuilder = $this->getQueryBuilder();
        $sql = $queryBuilder->select($columns)
            ->from('information_schema.'.$table)
            ->where($criteria)
            ->toString()
        ;

        $rows = [];
        $result = $this->query($sql);
        if ($result) {
            while ($row = $result->fetch()) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}
