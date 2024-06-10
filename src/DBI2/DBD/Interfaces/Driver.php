<?php

declare(strict_types=1);

namespace Hazaar\DBI2\DBD\Interfaces;

/**
 * @brief Relational Database Driver Interface
 */
interface Driver
{
    public function setTimezone(string $tz): bool;

    public function exec(string $sql): false|int;

    public function query(string $sql): false|\PDOStatement;
}
