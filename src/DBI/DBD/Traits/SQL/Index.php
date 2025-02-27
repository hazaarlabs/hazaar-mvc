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
     */
    public function listIndexes(?string $tableName = null): array|false
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
        $queryBuilder = $this->getQueryBuilder();
        $sql = 'CREATE';
        if (array_key_exists('unique', $idxInfo) && $idxInfo['unique']) {
            $sql .= ' UNIQUE';
        }
        $sql .= ' INDEX '.$queryBuilder->field($indexName).' ON '.$queryBuilder->schemaName($tableName).' ('.implode(',', array_map([$queryBuilder, 'field'], $idxInfo['columns'])).')';
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
        $queryBuilder = $this->getQueryBuilder();
        $sql = 'DROP INDEX ';
        if (true === $ifExists) {
            $sql .= 'IF EXISTS ';
        }
        $sql .= $queryBuilder->field($indexName);
        $affected = $this->exec($sql);
        if (false === $affected) {
            return false;
        }

        return true;
    }
}
