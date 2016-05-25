<?php

namespace Hazaar\Cache\Backend;

class Database extends \Hazaar\Cache\Backend {

    private   $db;

    protected $weight = 6;

    public function init($namespace) {

        $this->addCapabilities('array');

        $this->configure(array(
                             'cache_table' => 'cache_' . $namespace
                         ));

        if(! trim($this->options->config))
            throw new Exception\NoDBConfig();

        if(! trim($this->options->cache_table))
            throw new Exception\NoDBTable();

        $this->db = new \Hazaar\DBI($this->options->config);

        $fields = array(
            'key'    => 'TEXT PRIMARY KEY',
            'value'  => 'TEXT',
            'expire' => 'INTEGER'
        );

        $this->db->createTable($this->options->cache_table, $fields);

    }

    public function has($key) {

        $result = $this->db->exists($this->options->cache_table, array('key' => $key, 'expire' => array('$gt' => time())));

        return boolval($result);

    }

    public function get($key) {

        $result = $this->db->table($this->options->cache_table)->findOne(array('key' => $key, 'expire' => array('$gt' => time())), array('value'));

        if(! is_array($result))
            return FALSE;

        return stripslashes($result['value']);

    }

    public function set($key, $value, $timeout = NULL) {

        $expire = time() + $timeout;

        $value = addslashes($value);

        if($this->db->table($this->options->cache_table)->exists(array('key' => $key)))
            $this->db->table($this->options->cache_table)->update(array('key' => $key), array('value' => $value, 'expire' => $expire));

        else
            $this->db->table($this->options->cache_table)->insert(array('key' => $key, 'value' => $value, 'expire' => $expire));

    }

    public function remove($key) {

        $this->db->table($this->options->cache_table)->delete(array('key' => $key));

    }

    public function clear() {

        $this->db->table($this->options->cache_table)->delete();

    }

}