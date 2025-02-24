<?php

declare(strict_types=1);

/**
 * Relational Database Driver namespace.
 */

namespace Hazaar\DBI\DBD\Traits;

use Hazaar\DBI\Result;
use Hazaar\DBI\Result\PDO as PDOResult;

/**
 * Relational Database Driver - Base Class.
 */
trait PDO
{
    protected \PDO $pdo;
    private string $lastQueryString = '';

    public function exec(string $sql): false|int
    {
        return $this->pdo->exec($sql);
    }

    public function query(string $sql): false|Result
    {
        $this->lastQueryString = $sql;
        $result = $this->pdo->query($sql);
        if ($result instanceof \PDOStatement) {
            return new PDOResult($result);
        }

        return false;
    }

    public function quote(mixed $string, int $type = \PDO::PARAM_STR): false|string
    {
        if (is_string($string)) {
            $string = $this->pdo->quote($string, $type);
        }

        return $string;
    }

    public function setTimezone(string $tz): bool
    {
        return false;
    }

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * @return array{string, string, string}
     */
    public function errorInfo(): array
    {
        return $this->pdo->errorInfo();
    }

    public function errorCode(): string
    {
        return $this->pdo->errorCode();
    }

    public function lastQueryString(): string
    {
        return $this->lastQueryString;
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

    /**
     * @param array<mixed> $config
     */
    protected function mkdsn(array $config): false|string
    {
        $DBD = 'Hazaar\DBI\DBD\\'.ucfirst($config['type']);
        if (!class_exists($DBD)) {
            return false;
        }
        $options = array_intersect_key($config, array_combine($DBD::$dsnElements, $DBD::$dsnElements));

        return $config['type'].':'.array_flatten($options, '=', ';');
    }
}
