<?php

declare(strict_types=1);

/**
 * Relational Database Driver namespace.
 */

namespace Hazaar\DBI2\DBD\Traits;

use Hazaar\Map;

/**
 * Relational Database Driver - Base Class.
 */
trait PDO
{
    protected \PDO $pdo;

    public function setTimezone(string $tz): bool
    {
        return false !== $this->exec('SET TIMEZONE TO \''.$tz.'\'');
    }

    public function exec(string $sql): false|int
    {
        return $this->pdo->exec($sql);
    }

    public function query(string $sql): false|\PDOStatement
    {
        return $this->pdo->query($sql);
    }

    protected function mkdsn(Map $config): false|string
    {
        $options = $config->toArray();
        $DBD = 'Hazaar\\DBI\\DBD\\'.ucfirst($config['driver']);
        if (!class_exists($DBD)) {
            return false;
        }
        $options = array_intersect_key($options, array_combine($DBD::$dsnElements, $DBD::$dsnElements));

        return $config['driver'].':'.array_flatten($options, '=', ';');
    }

    /**
     * @param array<int, bool> $driverOptions
     */
    public function connect(
        string $dsn,
        ?string $username = null,
        ?string $password = null,
        ?array $driverOptions = null
    ): bool {
        $this->pdo = new \PDO($dsn, $username, $password, $driverOptions);

        return true;
    }
}
