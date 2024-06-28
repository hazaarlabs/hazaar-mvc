<?php

namespace Hazaar\DBI2\DBD\Interfaces\SQL;

interface Table
{
    /**
     * @return array<array{name:string,schema:string}>
     */
    public function listTables(): array|false;

    public function createTable(string $tableName, mixed $columns): bool;

    /**
     * @return array<array{name:string,data_type:string,not_null:bool,default:?mixed,length:?int,sequence:?string}>|false
     */
    public function describeTable(string $tableName, ?string $sort = null): array|false;

    public function renameTable(string $fromName, string $toName): bool;

    public function dropTable(string $name, bool $cascade = false, bool $ifExists = false): bool;

    public function addColumn(string $tableName, mixed $columnSpec): bool;

    public function alterColumn(string $tableName, string $column, mixed $columnSpec): bool;

    public function dropColumn(string $tableName, string $column, bool $ifExists = false): bool;

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
    public function truncate(string $tableName, bool $only = false, bool $restartIdentity = false, bool $cascade = false): bool;
}
