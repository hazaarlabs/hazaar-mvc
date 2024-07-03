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
    public function createDatabase(string $name): bool;

    /**
     * @return array{string, string, string}
     */
    public function errorInfo(): array|false;

    public function errorCode(): string;

    public function setTimezone(string $tz): bool;

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
}
