<?php

namespace Hazaar\DBI2\DBD\Traits\SQL;

trait Index
{
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
}
