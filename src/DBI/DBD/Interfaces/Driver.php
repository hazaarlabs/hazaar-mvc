<?php

declare(strict_types=1);

namespace Hazaar\DBI\DBD\Interfaces;

use Hazaar\DBI\Table;
use Hazaar\Map;

/**
 * @brief Relational Database Driver Interface
 */
interface Driver
{
    public static function mkdsn(Map $config): false|string;

    /**
     * @param array<int, bool> $driverOptions
     */
    public function connect(string $dsn, ?string $username = null, ?string $password = null, ?array $driverOptions = null): bool;

    public function setTimezone(string $tz): bool;

    public function repair(?string $table = null): bool;

    public function beginTransaction(): bool;

    public function commit(): bool;

    public function rollBack(): bool;

    public function inTransaction(): bool;

    public function schemaExists(string $schema): bool;

    public function getAttribute(int $attribute): mixed;

    public function setAttribute(int $attribute, mixed $value): bool;

    public function lastInsertId(): false|string;

    public function quote(mixed $value): mixed;

    public function quoteSpecial(mixed $value): mixed;

    public function exec(string $sql): false|int;

    public function query(string $sql): false|\PDOStatement;

    public function prepare(string $sql): false|\PDOStatement;

    public function insert(
        string $tableName,
        mixed $fields,
        mixed $returning = null,
        ?string $conflictTarget = null,
        mixed $conflictUpdate = null,
        ?Table $table = null
    ): false|int|\PDOStatement;

    /**
     * @param array<string> $criteria
     * @param array<string> $from
     * @param array<string> $tables
     */
    public function update(
        string $table,
        mixed $fields,
        array $criteria = [],
        array $from = [],
        ?string $returning = null,
        array $tables = []
    ): false|int|\PDOStatement;

    /**
     * @param array<string> $criteria
     * @param array<string> $from
     */
    public function delete(
        string $table,
        array $criteria,
        array $from = []
    ): false|int;

    public function deleteAll(string $table): false|int;
}
