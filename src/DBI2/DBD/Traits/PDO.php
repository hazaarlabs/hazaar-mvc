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

    protected function __exec(string $sql): false|int
    {
        return $this->pdo->exec($sql);
    }

    protected function __query(string $sql): false|\PDOStatement
    {
        return $this->pdo->query($sql);
    }

    public function setTimezone(string $tz): bool
    {
        return false !== $this->__exec('SET TIMEZONE TO \''.$tz.'\'');
    }

    protected function quote(mixed $string, int $type = \PDO::PARAM_STR): false|string
    {
        if (is_string($string)) {
            $string = $this->pdo->quote($string, $type);
        }

        return $string;
    }

    protected function quoteSpecial(mixed $value): mixed
    {
        if (false === is_string($value)) {
            return $value;
        }
        $parts = explode('.', $value);
        array_walk($parts, function (&$item) {
            $item = $this->quoteSpecial.$item.$this->quoteSpecial;
        });

        return implode('.', $parts);
    }

    /**
     * @param array<int, bool> $driverOptions
     */
    protected function connect(
        string $dsn,
        ?string $username = null,
        ?string $password = null,
        ?array $driverOptions = null
    ): bool {
        $this->pdo = new \PDO($dsn, $username, $password, $driverOptions);

        return true;
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
}
