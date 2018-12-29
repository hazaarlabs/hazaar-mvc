<?php

/**
 * @file        Hazaar/Cache/Backend/Database.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */
namespace Hazaar\Cache\Backend;

/**
 * @brief The database cache backend.
 *
 * @detail This is another reliable almost always available cache backend, similar to file.  However if your database supports clustering/replication
 * such as MySQL or PostgreSQL, then you have just given yourself a cheap reliable clustered cache backend.
 *
 * This backend works by creating tables in the database server by default with a name of cache_{namespace}.  You can of course override this
 * but why would you want to?  It works.  Just make sure you have your databas environment configred and the user has create privileges.
 *
 * NOTE: The configuration options are passed directly "as is" to the database backend.  This allows the backend to override the default
 * database settings when creating the DBI object.  This allows you to have a completely separate database where the cache user has table
 * create privileges in a database that is not where you keep all your important stuff.
 *
 * Available config options:
 *
 * * cache_table - The name of the table to use for this instance of the cache backend. Default: cache_{namespace}.
 * * ...DBI options... - See the DBI documentation for options you can use to configure your DBI instance.
 *
 * @since 1.0.0
 */
class Database extends \Hazaar\Cache\Backend {

    private   $db;

    protected $weight = 6;

    static public function available(){

        return true;

    }

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