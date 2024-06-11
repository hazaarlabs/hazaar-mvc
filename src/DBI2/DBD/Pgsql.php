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

    public function __toString(): string
    {
        return $this->toString();
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

    public function go(): false|Result
    {
        return $this->query($this->toString());
    }

    /**
     * Converts the given value to a string representation suitable for use in a database query.
     *
     * @param mixed $string the value to be converted
     *
     * @return string the converted string representation of the value
     */
    protected function field($string): string
    {
        if (!is_string($string)) {
            if (is_bool($string)) {
                return strbool($string);
            }
            if (is_array($string) && array_key_exists('schema', $string) && array_key_exists('name', $string)) {
                $string = $string['schema'].'.'.$string['name'];
            } elseif (null === $string) {
                return 'NULL';
            } else {
                return (string) $string;
            }
        } else {
            $string = trim($string);
        }
        // This matches an string that contain a non-word character, which means it is either a function call, concat or
        // at least definitely not a reserved word as all reserved words have only word characters
        if (preg_match('/\W/', $string)) {
            return $string;
        }

        return $this->quoteSpecial($string);
    }
}
