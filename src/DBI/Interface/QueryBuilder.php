<?php

declare(strict_types=1);

namespace Hazaar\DBI\Interface;

use Hazaar\DBI\Table;

interface QueryBuilder
{
    public function create(string $name, string $type, bool $ifNotExists = false): string;

    /**
     * @param array<string>|string $returning
     * @param array<mixed>         $conflictTarget
     */
    public function insert(
        string $tableName,
        mixed $fields,
        null|array|string $returning = null,
        null|array|string $conflictTarget = null,
        mixed $conflictUpdate = null,
        ?Table $table = null
    ): string;

    /**
     * @param array<mixed>  $criteria
     * @param array<string> $from
     * @param array<string> $tables
     */
    public function update(
        string $table,
        mixed $fields,
        array $criteria = [],
        array $from = [],
        mixed $returning = null,
        array $tables = []
    ): string;

    /**
     * @param array<mixed>  $criteria
     * @param array<string> $from
     */
    public function delete(
        string $table,
        array $criteria,
        array $from = []
    ): string;

    public function truncate(string $table, bool $cascade = false): string;

    public function count(): string;

    public function exists(string $table, mixed $criteria = null): string;

    public function select(mixed ...$columns): self;

    public function distinct(string ...$columns): self;

    public function from(string $table): self;

    public function where(mixed ...$criteria): self;

    public function group(string ...$column): self;

    public function having(string ...$columns): self;

    /**
     * @param array<string, int>|string $orderBy
     */
    public function window(string $name, string $partitionBy, null|array|string $orderBy = null): self;

    /**
     * Join a table to the current query using the provided join criteria.
     *
     * @param string              $references the table to join to the query
     * @param array<mixed>|string $on         The join criteria.  This is mostly just a standard query selection criteria.
     * @param string              $alias      an alias to use for the joined table
     * @param string              $type       the join type such as INNER, OUTER, LEFT, RIGHT, etc
     */
    public function join(string $references, null|array|string $on = null, ?string $alias = null, string $type = 'INNER'): self;

    /**
     * @param array<string,int>|string $columns
     */
    public function order(array|string $columns, int $sortDirection = SORT_ASC): self;

    public function limit(int $limit): int|self;

    public function offset(int $offset): int|self;

    public function toString(): string;

    public function getSchemaName(): ?string;

    /**
     * @return array<int,string>
     */
    public function parseSchemaName(string $tableName): array;

    public function schemaName(string $tableName): string;

    public function quoteSpecial(mixed $value): mixed;

    public function field(string $string): string;

    /**
     * @param array<string> $exclude
     * @param array<string> $tables
     */
    public function prepareFields(mixed $fields, array $exclude = [], array $tables = []): string;

    public function prepareValues(mixed $values): string;

    public function prepareValue(mixed $value, ?string $key = null): mixed;

    /**
     * @param array<mixed> $array
     *
     * @return array<mixed>
     */
    public function prepareArrayAliases(array $array): array;

    /**
     * @param array<mixed> $criteria
     */
    public function prepareCriteria(
        array|string $criteria,
        ?string $bindType = null,
        ?string $tissue = null,
        ?string $parentRef = null,
        null|int|string $optionalKey = null,
        bool &$setKey = true
    ): string;

    public function reset(): void;
}
