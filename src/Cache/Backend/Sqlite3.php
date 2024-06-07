<?php

declare(strict_types=1);

/**
 * @file        Hazaar/Cache/Backend/Sqlite3.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\Cache\Backend;

use Hazaar\Application;
use Hazaar\Cache\Backend;

/**
 * @brief The SQLite3 cache backend.
 *
 * @detail Similar to the database backend (which also can use SQLite3) except this has some performance benefits
 * by using some SQLite3 specific database functions.
 *
 * Available config options:
 *
 * * cache_table - The table name to use within the SQLite3 database file. Default: cache_{namespace}.
 * * cache_db - The name of the file to use on disk for the SQLite3 database. Default: APPLICATION_PATH/.runtime/sqlite.db
 */
class Sqlite3 extends Backend
{
    protected int $weight = 5;
    private \SQLite3 $db;

    public static function available(): bool
    {
        $modules = get_loaded_extensions();

        return in_array('sqlite3', $modules);
    }

    public function init(string $namespace): void
    {
        $this->configure([
            'cache_table' => 'cache_'.$namespace,
            'cache_db' => Application::getInstance()->runtimePath('cache', true).'/sqlite.db',
        ]);
        if (!trim($this->options->cache_db ?? '')) {
            throw new Exception\NoSQLite3DBPath();
        }
        $this->db = new \SQLite3($this->options['cache_db'], SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE, $this->options['key']);
        $this->db->exec("CREATE TABLE IF NOT EXISTS {$this->options['cache_table']} ( key TEXT PRIMARY KEY, value TEXT, expire INTEGER);");
    }

    public function close(): bool
    {
        return $this->db->close();
    }

    public function has(string $key, bool $check_empty = false): bool
    {
        $sql = "SELECT EXISTS(SELECT * FROM {$this->options['cache_table']} ".$this->criteria($key, $check_empty).');';
        $result = $this->db->querySingle($sql);

        return boolval($result);
    }

    public function get(string $key): mixed
    {
        $sql = "SELECT value FROM {$this->options['cache_table']} ".$this->criteria($key).';';
        $result = $this->db->querySingle($sql);
        if (!$result) {
            return false;
        }

        return stripslashes($result);
    }

    public function set(string $key, mixed $value, int $timeout = 0): bool
    {
        $value = addslashes($value);
        $expire = time() + $timeout;
        $exists = $this->db->querySingle("SELECT EXISTS(SELECT * FROM {$this->options['cache_table']} WHERE key='{$key}');");
        if (boolval($exists)) {
            return $this->db->exec("UPDATE {$this->options['cache_table']} SET value='{$value}', expire={$expire} WHERE key='{$key}';");
        }

        return $this->db->exec("INSERT INTO {$this->options['cache_table']} (key,value,expire) VALUES('{$key}', '{$value}', {$expire});");
    }

    public function remove(string $key): bool
    {
        $sql = "DELETE FROM {$this->options['cache_table']} WHERE key='{$key}';";

        return $this->db->exec($sql);
    }

    public function clear(): bool
    {
        $sql = "DELETE FROM {$this->options['cache_table']};";

        return $this->db->exec($sql);
    }

    public function toArray(): array
    {
        $data = [];
        $sql = "SELECT key,value FROM {$this->options['cache_table']};";
        $result = $this->db->query($sql);
        if (!$result) {
            return $data;
        }
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $data[$row['key']] = $row['value'];
        }

        return $data;
    }

    public function count(): int
    {
        $result = $this->db->querySingle('SELECT COUNT(*) FROM '.$this->options['cache_table'].';');

        return intval($result);
    }

    private function criteria(string $key, bool $check_empty = false): string
    {
        return "WHERE key='{$key}'"
            .($check_empty ? ' AND value IS NOT NULL' : '')
            .' AND (expire < 1 OR expire > '.time().')';
    }
}
