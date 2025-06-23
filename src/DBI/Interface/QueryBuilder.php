<?php

declare(strict_types=1);

namespace Hazaar\DBI\Interface;

use Hazaar\DBI\Table;

interface QueryBuilder
{
    /**
     * @param array<string> $words
     */
    public function setReservedWords(array $words): void;

    public function create(string $name, string $type, bool $ifNotExists = false): string;

    public function insert(mixed $fields): self;

    /**
     * @param array<mixed> $fields
     */
    public function update(array $fields): self;

    public function delete(): self;

    public function truncate(bool $cascade = false): self;

    public function count(): string;

    public function exists(string $table, mixed $criteria = null): string;

    public function select(mixed ...$columns): self;

    public function distinct(string ...$columns): self;

    public function table(string $table, ?string $alias = null): self;

    public function from(string $table, ?string $alias = null): self;

    /**
     * @param array<mixed>|string $criteria
     */
    public function where(array|string $criteria): self;

    public function group(string ...$column): self;

    /**
     * @param array<string> $criteria
     */
    public function having(array $criteria): self;

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

    public function prepareValue(string $key, mixed $value): mixed;

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
        string $bindType = 'AND',
        string $tissue = '=',
        ?string $parentRef = null,
        bool &$setKey = true,
    ): string;

    public function reset(): self;

    /**
     * @return array<string,mixed>
     */
    public function getCriteriaValues(): array;

    public function returning(mixed ...$columns): self;

    /**
     * @param array<string> $target
     * @param array<string> $update
     */
    public function onConflict(
        null|array|string $target = null,
        null|array|bool $update = null
    ): self;
}
