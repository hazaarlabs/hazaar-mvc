<?php

declare(strict_types=1);

/**
 * @file        Hazaar/DBI/Result.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */
/**
 * @brief Relational database namespace
 */

namespace Hazaar\DBI2\Result;

use Hazaar\DBI2\Result;

class PDO extends Result
{
    private \PDOStatement $statement;

    /**
     * @var array<string,mixed>
     */
    private array $type_map = [
        'numeric' => 'int',
        'int2' => 'int',
        'int4' => 'int',
        'int8' => 'int',
        'float8' => 'float',
        'timestamp' => 'Hazaar\Date',
        'timestamptz' => 'Hazaar\Date',
        'date' => ['Hazaar\Date', ['format' => 'Y-m-d']],
        'bool' => 'boolean',
        'money' => '\Hazaar\Money',
        'bytea' => ['string', ['prepare' => 'stream_get_contents']],
    ];

    public function __construct(\PDOStatement $statement)
    {
        $this->statement = $statement;
        $this->processStatement($statement);
    }

    public function toString(): string
    {
        return $this->statement->queryString;
    }

    public function rowCount(): int
    {
        return $this->statement->rowCount();
    }

    /**
     * @return array<mixed>|false
     */
    public function fetch(
        int $fetchStyle = \PDO::FETCH_ASSOC,
        int $cursorOrientation = \PDO::FETCH_ORI_NEXT,
        int $cursorOffset = 0
    ): array|false {
        if ($record = $this->statement->fetch($fetchStyle, $cursorOrientation, $cursorOffset)) {
            $this->fix($record);

            return $record;
        }

        return false;
    }

    /**
     * @return array<mixed>
     */
    public function fetchAll(
        int $fetchMode = \PDO::FETCH_ASSOC,
        mixed $fetchArgument = null
    ): array {
        if (null !== $fetchArgument) {
            $results = $this->statement->fetchAll($fetchMode, $fetchArgument);
        } else {
            $results = $this->statement->fetchAll($fetchMode);
        }
        foreach ($results as &$record) {
            $this->fix($record);
        }

        return $results;
    }

    public function fetchColumn(int $columnNumber = 0): mixed
    {
        return $this->statement->fetchColumn($columnNumber);
    }

    /**
     * @param array<mixed> $constructorArgs
     */
    public function fetchObject(string $className = 'stdClass', array $constructorArgs = []): false|object
    {
        return $this->statement->fetchObject($className, $constructorArgs);
    }

    private function processStatement(\PDOStatement $statement): bool
    {
        // @var array<mixed>
        $this->meta = [];
        for ($i = 0; $i < $this->statement->columnCount(); ++$i) {
            $meta = $this->statement->getColumnMeta($i);
            $def = ['native_type' => $meta['native_type']];
            if (array_key_exists('table', $meta)) {
                $def['table'] = $meta['table'];
            }
            if ('_' == substr($meta['native_type'], 0, 1)) {
                if (!array_key_exists($meta['name'], $this->arrayColumns)) {
                    $this->arrayColumns[$meta['name']] = [];
                }
                $this->arrayColumns[$meta['name']][] = substr($meta['native_type'], 1);
                $type = substr($meta['native_type'], 1);
                $def['type'] = 'array';
                $def['arrayOf'] = ake($this->type_map, $type, 'string');
                $def['prepare'] = function ($value) {
                    if (is_string($value)) {
                        return explode(',', trim($value, '{}'));
                    }

                    return $value;
                };
            } elseif (\PDO::PARAM_STR == $meta['pdo_type'] && ('json' == substr(ake($meta, 'native_type'), 0, 4)
                    || (!array_key_exists('native_type', $meta) && in_array('blob', ake($meta, 'flags'))))) {
                if (!array_key_exists($meta['name'], $this->arrayColumns)) {
                    $this->arrayColumns[$meta['name']] = [];
                }

                $this->arrayColumns[$meta['name']][] = 'json';
                $def['prepare'] = function ($value) {
                    if (is_string($value)) {
                        return json_decode($value);
                    }

                    return $value;
                };
            } elseif ('record' === $meta['native_type']) {
                if (!array_key_exists($meta['name'], $this->arrayColumns)) {
                    $this->arrayColumns[$meta['name']] = [];
                }

                $this->arrayColumns[$meta['name']][] = 'record';
            } else {
                $type_map = ake($this->type_map, $meta['native_type'], 'string');
                $extra = is_array($type_map) ? $type_map[1] : [];
                $type_map = is_array($type_map) ? $type_map[0] : $type_map;
                $def = array_merge($def, ['type' => $type_map], $extra);
            }
            if (array_key_exists($meta['name'], $this->meta)) {
                if (false === is_array($this->meta[$meta['name']])) {
                    $this->meta[$meta['name']] = [$this->meta[$meta['name']]];
                }
                $this->meta[$meta['name']][] = (object) $def;
            } else {
                $this->meta[$meta['name']] = (object) $def;
            }
        }

        return true;
    }
}