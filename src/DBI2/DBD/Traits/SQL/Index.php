<?php

namespace Hazaar\DBI2\DBD\Traits\SQL;

trait Index
{
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
}
