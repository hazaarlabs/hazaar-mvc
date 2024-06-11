<?php

declare(strict_types=1);

namespace Hazaar\DBI2\DBD\Interfaces;

use Hazaar\DBI2\Result;

/**
 * @brief Relational Database Driver Interface
 */
interface Driver
{
    public function __toString(): string;

    public function toString(): string;

    public function setTimezone(string $tz): bool;

    public function exec(string $sql): false|int;

    public function query(string $sql): false|Result;

    /**
     * @param array<string>|string $columns
     */
    public function select(array|string $columns = '*'): self;

    public function from(string $table): self;

    /**
     * @param array<string>|string $where
     */
    public function where(array|string $where): self;

    // public function group(string $column): self;

    // public function order(string $column, string $direction = 'ASC'): self;

    public function limit(int $limit): self;

    public function offset(int $offset): self;

    // public function insert(string $table, array $values): self;

    // public function update(string $table, array $values): self;

    // public function delete(string $table): self;

    public function go(): false|Result;
}
