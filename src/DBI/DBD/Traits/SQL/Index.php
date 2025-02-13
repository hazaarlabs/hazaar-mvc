<?php

namespace Hazaar\DBI\DBD\Traits\SQL;

trait Index
{
    /**
     * Retrieves a list of indexes for a given table or all tables in the specified schema.
     *
     * @param null|string $tableName The name of the table. If null, all tables in the schema will be considered.
     *
     * @return array<mixed>|false An array of indexes, where each index is represented by an associative array with the following keys:
     *                            - 'table': The name of the table the index belongs to.
     *                            - 'columns': An array of column names that make up the index.
     *                            - 'unique': A boolean indicating whether the index is unique or not.
     *
     * @throws \Exception if the index list retrieval fails
     */
    public function listIndexes(?string $tableName = null): array
    {
        return [];
    }

    public function indexExists(string $indexName, ?string $tableName = null): bool
    {
        $indexes = $this->listIndexes($tableName);

        return array_key_exists($indexName, $indexes);
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
        $sql .= ' INDEX '.$this->queryBuilder->field($indexName).' ON '.$this->queryBuilder->schemaName($tableName).' ('.implode(',', array_map([$this->queryBuilder, 'field'], $idxInfo['columns'])).')';
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
