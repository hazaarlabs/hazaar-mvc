<?php

declare(strict_types=1);

namespace Hazaar\DBI2\DBD;

use Hazaar\DBI2\Result;
use Hazaar\DBI2\Result\PDO;
use Hazaar\Map;

class Pgsql implements Interfaces\Driver
{
    use Traits\PDO {
        Traits\PDO::query as pdoQuery; // Alias the trait's query method to pdoQuery
    }
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
        $result = $this->pdoQuery($sql);
        if ($result instanceof \PDOStatement) {
            return new PDO($result);
        }

        return false;
    }
}
