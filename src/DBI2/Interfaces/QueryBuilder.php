<?php

declare(strict_types=1);

namespace Hazaar\DBI2\Interfaces;

use Hazaar\DBI2\Table;

interface QueryBuilder
{
    /**
     * @param array<mixed> $conflictTarget
     */
    public function insert(
        string $tableName,
        mixed $fields,
        mixed $returning = null,
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

    public function count(): string;

    public function select(mixed ...$columns): self;

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
     * @param array<mixed> $on
     */
    public function innerJoin(string $references, null|array|string $on = null, ?string $alias = null): self;

    /**
     * @param array<mixed> $on
     */
    public function leftJoin(string $references, null|array|string $on = null, ?string $alias = null): self;

    /**
     * @param array<mixed> $on
     */
    public function rightJoin(string $references, null|array|string $on = null, ?string $alias = null): self;

    /**
     * @param array<mixed>|string $on
     */
    public function fullJoin(string $references, null|array|string $on = null, ?string $alias = null): self;

    /**
     * @param array<string,int>|string $fieldDef
     */
    public function order(array|string $fieldDef, int $sortDirection = SORT_ASC): self;

    public function limit(int $limit): int|self;

    public function offset(int $offset): int|self;

    public function toString(): string;
}