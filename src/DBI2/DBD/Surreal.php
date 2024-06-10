<?php

declare(strict_types=1);

namespace Hazaar\DBI2\DBD;

use Hazaar\Map;

class Surreal
{
    public function __construct(Map $config)
    {
        dump($config->toArray());
    }

    public function connect(
        string $host,
        ?string $username = null,
        ?string $password = null,
    ): bool {
        dump($host);

        return false;
    }
}
