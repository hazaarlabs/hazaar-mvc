<?php

declare(strict_types=1);

namespace Hazaar\DBI2\DBD;

use Hazaar\Map;

class Pgsql implements Interfaces\Driver
{
    use Traits\PDO;

    /**
     * @var array<string>
     */
    public static array $dsnElements = [
        'host',
        'port',
        'dbname',
        'user',
        'password',
    ];

    public function __construct(Map $config)
    {
        $this->connect($this->mkdsn($config));
    }
}
