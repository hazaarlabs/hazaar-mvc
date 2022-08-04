<?php

/**
 * @file        Hazaar/Cache/Backend/Database.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
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

    private   $namespace;

    private   $timeout;

    private   $cache_clean_period = 0;

    static public function available(){

        return true;

    }

    public function init($namespace) {

        $this->namespace = $namespace;

        $this->addCapabilities('array', 'keepalive');

        $this->configure([
            'cache_table' => 'cache_' . $namespace,
            'cache_clean_period' => 60
        ]);

        if(!trim($this->options->cache_table))
            throw new Exception\NoDBTable();

        if($this->options->has('schema'))
            $this->options->config['schema'] = $this->options['schema'];

        $this->db = \Hazaar\DBI\Adapter::getInstance($this->options->get('config'));

        if(!$this->db->tableExists('__meta__')){

            $fields = [
                'namespace'     => 'TEXT PRIMARY KEY',
                'tablename'     => 'TEXT',
                'timeout'       => 'integer',
                'created_on'    => 'TIMESTAMP WITHOUT TIME ZONE DEFAULT NOW()'
            ];

            $this->db->createTable('__meta__', $fields);

            $index = [
                'columns'   =>  ['namespace'],
                'unique'    => true
            ];

            $this->db->createIndex('meta_namespace_idx', '__meta__', $index);

        }

        if(!$this->db->tableExists($this->options->cache_table)){

            $fields = [
                'key'    => 'TEXT PRIMARY KEY',
                'value'  => 'TEXT',
                'expire' => 'INTEGER'
            ];

            $this->db->createTable($this->options->cache_table, $fields);

        }

        if($this->options->lifetime > 0){

            $this->addCapabilities('expire_ns', 'keepalive');

            $this->cache_clean_period = $this->options->cache_clean_period;

        }

    }

    /**
     * Store the namespace timeout in the cache dir timeout file.
     *
     * This should only happen if a keepalive() has been called.
     */
    function __destruct(){

        if(!$this->db)
            return;

        $meta_table = $this->db->table('__meta__');

        if($this->timeout > 0)
            $meta_table->insert(['namespace' => $this->namespace, 'tablename' => $this->options->cache_table, 'timeout' => $this->timeout], null, 'namespace', true);

        if($this->cache_clean_period > 0){

            $meta = $meta_table->findOne(['namespace' => '__meta__']);

            //Cleanup namespace tables at most every {$cache_clean_period} seconds
            if($meta === null || ($meta['timeout'] + $this->cache_clean_period) <= time()){
                
                $meta_table->insert(['namespace' => '__meta__', 'timeout' => time()], null, 'namespace', true);

                $meta_table->delete(['timeout' => ['$lt' => time()], 'namespace' => ['$ne' => '__meta__']]);

                $all_tables = $meta_table->select('tablename')->find(['namespace' => ['$ne' => '__meta__']])->collate('tablename');

                $zombie_tables = $this->db->listTables();

                foreach($zombie_tables as $table){

                    if($table['name'] === '__meta__')
                        continue;

                    if(!array_key_exists($table['name'], $all_tables))
                        $this->db->dropTable($table['name']);

                }

            }

        }

    }

    private function keepalive(){

        if($this->options->keepalive === true && $this->options->lifetime > 0)
            $this->timeout = time() + $this->options->lifetime;

    }

    public function has($key) {

        $result = $this->db->exists($this->options->cache_table, ['key' => $key, 'expire' => ['$gt' => time()]]);

        $this->keepalive();

        return boolval($result);

    }

    public function get($key) {

        $criteria = [
            'key' => $key, 
            '$or' => [
                ['expire' => null],
                ['expire' => ['$gt' => time()]]
            ]
            ];

        $result = $this->db->table($this->options->cache_table)->findOne($criteria, ['value']);

        $this->keepalive();

        if(! is_array($result))
            return FALSE;

        return stripslashes($result['value']);

    }

    public function set($key, $value, $timeout = NULL) {

        $data = ['key' => $key, 'value' => $value];

        if($timeout > 0)
            $data['expire'] = time() + $timeout;

        $value = addslashes($value);

        $this->db->table($this->options->cache_table)->insert($data, null, 'key', true);

        $this->keepalive();

    }

    public function remove($key) {

        $this->db->table($this->options->cache_table)->delete(['key' => $key]);

        $this->keepalive();

    }

    public function clear() {

        $this->db->table($this->options->cache_table)->deleteAll();

        $this->keepalive();

    }

    public function toArray(){

        $this->keepalive();

        return $this->db->table($this->options->cache_table)->collate('key', 'value');

    }

}