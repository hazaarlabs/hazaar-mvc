<?php

namespace Hazaar\DBI2\DBD\Interfaces\SQL;

interface Index
{
    /**
     * @return array<string,array{table:string,columns:array<string>,unique:bool}>
     */
    public function listIndexes(string $table): array|false;

    public function createIndex(string $indexName, string $tableName, mixed $idxInfo): bool;

    public function dropIndex(string $indexName, bool $ifExists = false): bool;
}
