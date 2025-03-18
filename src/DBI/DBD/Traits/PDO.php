<?php

declare(strict_types=1);

/**
 * Relational Database Driver namespace.
 */

namespace Hazaar\DBI\DBD\Traits;

use Hazaar\DBI\Interface\QueryBuilder;
use Hazaar\DBI\Result;
use Hazaar\DBI\Result\PDO as PDOResult;
use Hazaar\DBI\Statement;
use Hazaar\Util\Arr;

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

    public function prepare(string $sql): false|Statement
    {
        return $this->pdo->prepare($sql);
    }

    public function prepareQuery(QueryBuilder $queryBuilder): false|Statement
    {
        $statement = $this->pdo->prepare($queryBuilder->toString());
        $values = $queryBuilder->getCriteriaValues();
        foreach ($values as $key => $value) {
            $statement->bindValue($key, $value);
        }
        $this->lastQueryString = $statement->queryString;

        return $statement;
    }

    public function lastQueryString(): string
    {
        return $this->lastQueryString;
    }

    /**
     * @param array<mixed> $parameters
     */
    public function query(string $sql, array $parameters = []): false|Result
    {
        $statement = $this->pdo->prepare($sql);
        foreach ($parameters as $key => $value) {
            $statement->bindValue($key, $value);
        }
        $result = $statement->execute();
        if (false === $result) {
            return false;
        }

        return new PDOResult($statement);
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

    /**
     * @param array<int, bool> $driverOptions
     */
    protected function connect(
        string $dsn,
        ?string $username = null,
        ?string $password = null,
        ?array $driverOptions = null
    ): bool {
        $driverOptions[\PDO::ATTR_STATEMENT_CLASS] = ['\Hazaar\DBI\Statement', []];
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

        return $config['type'].':'.Arr::flatten($options, '=', ';');
    }
}
