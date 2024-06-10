<?php

declare(strict_types=1);

namespace Hazaar\DBI2;

class Result
{
    public \PDOStatement $stmt;

    public function __construct(\PDOStatement $stmt)
    {
        $this->stmt = $stmt;
    }
}
