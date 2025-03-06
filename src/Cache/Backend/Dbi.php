<?php

declare(strict_types=1);

/**
 * @file        Hazaar/Cache/Backend/Sqlite3.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\Cache\Backend;

use Hazaar\Cache\Backend;
use Hazaar\DateTime;
use Hazaar\DBI\Adapter;

/**
 * The DBI cache backend.
 *
 * Similar to the database backend (which also can use SQLite3) except this has some performance benefits
 * by using some SQLite3 specific database functions.
 *
 * Available config options:
 *
 * * cache_table - The table name to use within the SQLite3 database file. Default: cache_{namespace}.
 * * cache_db - The name of the file to use on disk for the SQLite3 database. Default: APPLICATION_PATH/.runtime/sqlite.db
 */
class Dbi extends Backend
{
    protected int $weight = 5;
    private Adapter $db;

    public static function available(): bool
    {
        return true;
    }

    public function init(string $namespace): void
    {
        $this->db = new Adapter($this->options);
        if (isset($this->options['schema']) && !$this->db->schemaExists($this->options['schema'])) {
            $this->db->createSchema($this->options['schema']);
        }
        if (!$this->db->tableExists($namespace)) {
            $tableSpec = [
                [
                    'name' => 'key',
                    'type' => 'text',
                    'primarykey' => true,
                ],
                [
                    'name' => 'value',
                    'type' => 'text',
                ],
                [
                    'name' => 'expire',
                    'type' => 'timestamp without time zone',
                ],
            ];
            $this->db->createTable($namespace, $tableSpec);
        }
    }

    public function has(string $key, bool $checkEmpty = false): bool
    {
        return $this->db->table($this->namespace)->where($this->criteria($key, $checkEmpty))->exists();
    }

    public function get(string $key): mixed
    {
        $row = $this->db->table($this->namespace)->findOne($this->criteria($key, true));

        return $row['value'] ?? null;
    }

    public function set(string $key, mixed $value, int $timeout = 0): bool
    {
        $row = [
            'key' => $key,
            'value' => $value,
            'expire' => $timeout > 0 ? time() + $timeout : null,
        ];

        $table = $this->db->table($this->namespace);
        $result = $table->insert($row, null, ['key'], ['value', 'expire']);

        return $result > 0;
    }

    public function remove(string $key): bool
    {
        return false;
    }

    public function clear(): bool
    {
        return false;
    }

    public function toArray(): array
    {
        return [];
    }

    public function count(): int
    {
        return 0;
    }

    /**
     * Generate the criteria for a key.
     *
     * @param string $key        The key to generate criteria for
     * @param bool   $checkEmpty Check if the value is empty
     *
     * @return array<mixed>
     */
    private function criteria(string $key, bool $checkEmpty = false): array
    {
        $criteria = [
            'key' => $key,
            '$or' => [
                ['expire' => null],
                ['expire' => ['$gt' => new DateTime()]],
            ],
        ];
        if ($checkEmpty) {
            $criteria['value'] = ['$ne' => null];
        }

        return $criteria;
    }
}
