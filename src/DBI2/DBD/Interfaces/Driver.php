<?php

declare(strict_types=1);

namespace Hazaar\DBI2\DBD\Interfaces;

use Hazaar\DBI2\Interfaces\QueryBuilder;
use Hazaar\DBI2\Result;

/**
 * @brief Relational Database Driver Interface
 */
interface Driver
{
    /**
     * @return array{string, string, string}
     */
    public function errorInfo(): array;

    public function errorCode(): string;

    public function setTimezone(string $tz): bool;

    public function getSchemaName(): string;

    public function schemaExists(?string $schemaName = null): bool;

    public function createSchema(string $name): bool;

    /**
     * @param array<string>|string $privilege
     */
    public function grant(array|string $privilege, string $object, string $to, ?string $schema = null): bool;

    /**
     * @param array<string>|string $privilege
     */
    public function revoke(array|string $privilege, string $object, string $from, ?string $schema = null): bool;

    /**
     * Begins a database transaction.
     *
     * @return bool returns true if the transaction was successfully started, false otherwise
     */
    public function begin(): bool;

    /**
     * Commits the current database transaction.
     *
     * @return bool returns true if the transaction was successfully committed, false otherwise
     */
    public function commit(): bool;

    /**
     * Cancel and rollback the current database transaction.
     *
     * @return bool returns true if the operation was successfully canceled, false otherwise
     */
    public function cancel(): bool;

    /**
     * Executes an SQL statement and returns the number of affected rows or false on failure.
     *
     * @param string $sql the SQL statement to execute
     *
     * @return false|int the number of affected rows or false on failure
     */
    public function exec(string $sql): false|int;

    /**
     * Executes a SQL query.
     *
     * @param string $sql the SQL query to execute
     *
     * @return false|Result returns a Result object if the query is successful, or false if there was an error
     */
    public function query(string $sql): false|Result;

    /**
     * Returns a query builder instance.
     *
     * @return QueryBuilder the query builder instance
     */
    public function getQueryBuilder(): QueryBuilder;

    /**
     * @return array<array{name:string,schema:string}>
     */
    public function listTables(): array|false;

    public function createTable(string $tableName, mixed $columns): bool;

    /**
     * @return array<int, array<string>>|false
     */
    public function describeTable(string $tableName, ?string $sort = null): array|false;

    public function renameTable(string $fromName, string $toName): bool;

    public function dropTable(string $name, bool $cascade = false, bool $ifExists = false): bool;

    public function addColumn(string $tableName, mixed $columnSpec): bool;

    public function alterColumn(string $tableName, string $column, mixed $columnSpec): bool;

    public function dropColumn(string $tableName, string $column, bool $ifExists = false): bool;

    /**
     * @return array<string>
     */
    public function listSequences(): array|false;

    /**
     * @return array<int, array<string>>|false
     */
    public function describeSequence(string $name): array|false;

    /**
     * @return array<string,array{table:string,columns:array<string>,unique:bool}>
     */
    public function listIndexes(string $table): array|false;

    public function createIndex(string $indexName, string $tableName, mixed $idxInfo): bool;

    public function dropIndex(string $indexName, bool $ifExists = false): bool;

    /**
     * @return array<int, array<string>>|false
     */
    public function listConstraints(
        ?string $tableName = null,
        ?string $type = null,
        bool $invertType = false
    ): array|false;

    public function addConstraint(string $constraintName, mixed $info): bool;

    public function dropConstraint(string $constraintName, string $tableName, bool $cascade = false, bool $ifExists = false): bool;

    /**
     * @return array<int, array<string>>|false
     */
    public function listViews(): array|false;

    /**
     * @return array<int, array<string>>|false
     */
    public function describeView(string $name): array|false;

    public function createView(string $name, mixed $content): bool;

    public function viewExists(string $viewName): bool;

    public function dropView(string $name, bool $cascade = false, bool $ifExists = false): bool;

    /**
     * List defined functions.
     *
     * @return array<int,array<mixed>|string>|false
     */
    public function listFunctions(?string $schemaName = null, bool $includeParameters = false): array|false;

    /**
     * @return array<int, array<string>>|false
     */
    public function describeFunction(string $name, ?string $schemaName = null): array|false;

    /**
     * Create a new database function.
     *
     * @param mixed $name The name of the function to create
     * @param mixed $spec A function specification.  This is basically the array returned from describeFunction()
     *
     * @return bool
     */
    public function createFunction($name, $spec);

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
    ): bool;

    /**
     * List defined triggers.
     *
     * @param string $schemaName Optional: schema name.  If not supplied the current schemaName is used.
     *
     * @return array<int,array<string>>|false
     */
    public function listTriggers(?string $tableName = null, ?string $schemaName = null): array|false;

    /**
     * Describe a database trigger.
     *
     * This will return an array as there can be multiple triggers with the same name but with different attributes
     *
     * @param string $schemaName Optional: schemaName name.  If not supplied the current schemaName is used.
     *
     * @return array<int, array<string>>|false
     */
    public function describeTrigger(string $triggerName, ?string $schemaName = null): array|false;

    /**
     * Summary of createTrigger.
     *
     * @param string $tableName The table on which the trigger is being created
     * @param mixed  $spec      The spec of the trigger.  Basically this is the array returned from describeTriggers()
     */
    public function createTrigger(string $triggerName, string $tableName, mixed $spec = []): bool;

    /**
     * Drop a trigger from a table.
     *
     * @param string $tableName The name of the table to remove the trigger from
     * @param bool   $cascade   Whether to drop CASCADE
     */
    public function dropTrigger(string $triggerName, string $tableName, bool $cascade = false, bool $ifExists = false): bool;

    /**
     * @return array<int, array<string>>|false
     */
    public function listUsers(): array|false;

    /**
     * @return array<int, array<string>>|false
     */
    public function listGroups(): array|false;

    /**
     * @param array<string> $privileges
     */
    public function createRole(string $name, ?string $password = null, array $privileges = []): bool;

    public function dropRole(string $name, bool $ifExists = false): bool;

    /**
     * @return array<string>|false
     */
    public function listExtensions(): array|false;

    public function createExtension(string $name): bool;

    public function dropExtension(string $name, bool $ifExists = false): bool;

    public function createDatabase(string $name): bool;

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
