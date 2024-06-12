<?php

declare(strict_types=1);

namespace Hazaar\DBI2\DBD;

use Hazaar\DBI2\Result;
use Hazaar\DBI2\Result\PDO;
use Hazaar\Map;

class Pgsql implements Interfaces\Driver
{
    use Traits\PDO;
    use Traits\SQL;

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

    public function query(string $sql): false|Result
    {
        $result = $this->__query($sql);
        if ($result instanceof \PDOStatement) {
            return new PDO($result);
        }

        return false;
    }

    public function exec(string $sql): false|int
    {
        return $this->__exec($sql);
    }
}
