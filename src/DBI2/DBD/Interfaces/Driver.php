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
    public function setTimezone(string $tz): bool;

    public function exec(string $sql): false|int;

    public function query(string $sql): false|Result;

    public function getQueryBuilder(): QueryBuilder;
}
