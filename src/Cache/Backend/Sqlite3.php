<?php

namespace Hazaar\Cache\Backend;

class Sqlite3 extends \Hazaar\Cache\Backend {

    private   $db;

    protected $weight = 5;

    public function init($namespace) {

        $modules = get_loaded_extensions();

        if(! in_array('sqlite3', $modules))
            throw new Exception\NoSQLite3();

        $this->configure(array(
                             'cache_table' => 'cache_' . $namespace,
                             'cache_db'    => \Hazaar\Application::getInstance()->runtimePath('cache', TRUE) . '/sqlite.db'
                         ));

        if(! trim($this->options->cache_db))
            throw new Exception\NoSQLite3DBPath();

        $this->db = new \SQLite3($this->options->cache_db, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE, $this->options->key);

        $this->db->exec("CREATE TABLE IF NOT EXISTS {$this->options->cache_table} ( key TEXT PRIMARY KEY, value TEXT, expire INTEGER);");

    }

    public function close() {

        $this->db->close();

    }

    private function criteria($key) {

        return "WHERE key='$key' AND (expire < 1 OR expire > " . time() . ')';

    }

    public function has($key) {

        $sql = "SELECT EXISTS(SELECT * FROM {$this->options->cache_table} " . $this->criteria($key) . ");";

        $result = $this->db->querySingle($sql);

        return boolval($result);

    }

    public function get($key) {

        $sql = "SELECT value FROM {$this->options->cache_table} " . $this->criteria($key) . ';';

        $result = $this->db->querySingle($sql);

        if(! $result)
            return FALSE;

        return stripslashes($result);

    }

    public function set($key, $value, $timeout = NULL) {

        $value = addslashes($value);

        $expire = time() + $timeout;

        $exists = $this->db->querySingle("SELECT EXISTS(SELECT * FROM {$this->options->cache_table} WHERE key='$key');");

        if(boolval($exists))
            $this->db->exec("UPDATE {$this->options->cache_table} SET value='$value', expire=$expire WHERE key='$key';");

        else
            $this->db->exec("INSERT INTO {$this->options->cache_table} (key,value,expire) VALUES('$key', '$value', $expire);");

    }

    public function remove($key) {

        $sql = "DELETE FROM {$this->options->cache_table} WHERE key='$key';";

        $this->db->exec($sql);

    }

    public function clear() {

        $sql = "DELETE FROM {$this->options->cache_table};";

        $this->db->exec($sql);

    }

}
