<?php

namespace Hazaar\DBI2\DBD\Traits\SQL;

trait Table
{
    use Schema;

    /**
     * @return array{name:string,schema:string}|false
     */
    public function listTables(?string $schema = null): array|false
    {
        return $this->listInformationSchema('tables', ['name' => 'table_name', 'schema' => 'table_schema'], [
            'table_schema' => $this->queryBuilder->getSchemaName(),
        ]);
    }

    public function tableExists(string $tableName): bool
    {
        $criteria = [
            'table_name' => $tableName,
            'table_schema' => $this->queryBuilder->getSchemaName(),
        ];
        $sql = $this->queryBuilder->exists('information_schema.tables', $criteria);

        return $this->query($sql)->fetchColumn(0);
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
     * @return array<array{name:string,type:string,not_null:bool,default:?mixed,length:?int,sequence:?string}>|false
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
                    $col['type'] = 'serial';
                    $col['column_default'] = null;
                    $col['sequence'] = $info;
                }
            }
            // Fixed array types to their actual SQL array data type
            if ('ARRAY' == $col['type']
                && ($udtName = ake($col, 'udt_name'))) {
                if ('_' == $udtName[0]) {
                    $col['type'] = substr($udtName, 1).'[]';
                }
            }
            $coldata = [
                'name' => $col['column_name'],
                'default' => $this->fixValue($col['column_default']),
                'not_null' => (('NO' == $col['is_nullable']) ? true : false),
                'type' => $this->type($col),
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
        if (!array_key_exists('type', $columnSpec)) {
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
        if (array_key_exists('type', $columnSpec)) {
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
}