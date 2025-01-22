<?php

declare(strict_types=1);

namespace Hazaar\DBI\Interface\API;

use Hazaar\DBI\Interface\QueryBuilder;
use Hazaar\DBI\Result;

interface SQL
{
    /**
     * Create a new database.
     *
     * @param string $name The name of the database to create
     */
    public function createDatabase(string $name): bool;

    /**
     * Drop a database.
     */
    public function exec(string $sql): false|int;

    /**
     * Execute an SQL query.
     *
     * @param string $sql The SQL query to execute
     *
     * @return false|Result Returns a result object or false if the query failed
     */
    public function query(string $sql): false|Result;

    /**
     * Quote a string for use in an SQL query.
     *
     * @param mixed $string The string to quote
     * @param int   $type   The type of the string
     */
    public function quote(mixed $string, int $type = \PDO::PARAM_STR): false|string;

    /**
     * Set the timezone for the database connection.
     *
     * @param string $tz The timezone to set
     */
    public function setTimezone(string $tz): bool;

    /**
     * Get the last error info.
     *
     * @return array<mixed>
     */
    public function errorInfo(): array|false;

    /**
     * Get the last error code.
     */
    public function errorCode(): string;

    /**
     * Get a new QueryBuilder object.
     */
    public function getQueryBuilder(): QueryBuilder;

    /**
     * Execute any database repair operations.
     */
    public function repair(): bool;

    /**
     * Get the last query string.
     */
    public function lastQueryString(): string;
}
