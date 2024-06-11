<?php

declare(strict_types=1);

namespace Hazaar\DBI2\DBD;

use Hazaar\DBI2\Result;
use Hazaar\Map;

class Surreal
{
    public function __construct(Map $config)
    {
        dump($config->toArray());
    }

    public function __toString(): string
    {
        return '';
    }

    public function connect(
        string $host,
        ?string $username = null,
        ?string $password = null,
    ): bool {
        dump($host);

        return false;
    }

    public function query(string $sql): false|Result
    {
        return false;
    }

    public function exec(string $sql): false|int
    {
        return false;
    }

    public function setTimezone(string $tz): bool
    {
        return false;
    }
}
