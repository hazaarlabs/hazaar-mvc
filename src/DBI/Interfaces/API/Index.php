<?php

declare(strict_types=1);

namespace Hazaar\DBI\Interfaces\API;

interface Index
{
    /**
     * List all indexes in the database.
     *
     * @return array<mixed>
     */
    public function listIndexes(?string $table = null): array;

    /**
     * List all indexes for a table.
     *
     * @param string $indexName The name of the table to list indexes for
     */
    public function indexExists(string $indexName, ?string $table = null): bool;

    /**
     * Create a new index.
     *
     * @param string $indexName The name of the index
     * @param string $tableName The name of the table to create the index on
     * @param mixed  $idxInfo   The index information
     */
    public function createIndex(string $indexName, string $tableName, mixed $idxInfo): bool;

    /**
     * Drop an index.
     *
     * @param string $indexName The name of the index to drop
     * @param bool   $ifExists  If true, the index will only be dropped if it exists
     */
    public function dropIndex(string $indexName, bool $ifExists = false): bool;
}
