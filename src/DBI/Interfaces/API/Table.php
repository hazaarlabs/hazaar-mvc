<?php

declare(strict_types=1);

namespace Hazaar\DBI\Interfaces\API;

interface Table
{
    /**
     * List all tables in the database.
     *
     * @return array<mixed>
     */
    public function listTables(): array;

    /**
     * Check if a table exists in the database.
     *
     * @param string $tableName the name of the table to check
     */
    public function tableExists(string $tableName): bool;

    /**
     * Create a new table in the database.
     *
     * @param string $tableName the name of the table to create
     * @param mixed  $columns   the columns to create in the table
     */
    public function createTable(string $tableName, mixed $columns): bool;

    /**
     * Describe a table in the database.
     *
     * @param string      $tableName the name of the table to describe
     * @param null|string $sort      the column to sort the results by
     *
     * @return array<mixed>|false
     */
    public function describeTable(string $tableName, ?string $sort = null): array|false;

    /**
     * Rename a table in the database.
     *
     * @param string $fromName the current name of the table
     * @param string $toName   the new name of the table
     */
    public function renameTable(string $fromName, string $toName): bool;

    /**
     * Drop a table from the database.
     *
     * @param string $name     the name of the table to drop
     * @param bool   $ifExists whether to only drop the table if it exists
     * @param bool   $cascade  whether to drop any dependent objects
     */
    public function dropTable(string $name, bool $ifExists = false, bool $cascade = false): bool;

    /**
     * Add a column to a table in the database.
     *
     * @param string $tableName  the name of the table to add the column to
     * @param mixed  $columnSpec the column to add to the table
     */
    public function addColumn(string $tableName, mixed $columnSpec): bool;

    /**
     * Alter a column in a table in the database.
     *
     * @param string $tableName  the name of the table to alter the column in
     * @param string $column     the name of the column to alter
     * @param mixed  $columnSpec the new column definition
     */
    public function alterColumn(string $tableName, string $column, mixed $columnSpec): bool;

    /**
     * Drop a column from a table in the database.
     *
     * @param string $tableName the name of the table to drop the column from
     * @param string $column    the name of the column to drop
     * @param bool   $ifExists  whether to only drop the column if it exists
     */
    public function dropColumn(string $tableName, string $column, bool $ifExists = false): bool;

    /**
     * Rename a column in a table in the database.
     *
     * @param string $tableName the name of the table to rename the column in
     */
    public function truncate(string $tableName, bool $only = false, bool $restartIdentity = false, bool $cascade = false): bool;
}
