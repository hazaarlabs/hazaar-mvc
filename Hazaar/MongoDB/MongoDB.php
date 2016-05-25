<?php
/**
 * @file        Hazaar/MongoDB/MongoDB.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar;

/**
 * @brief       MongoDB database access class
 *
 * @detail      The MongoDB class helps streamline access to MongoDB database servers by providing configuration
 *              methods and helper methods for executing server-side code.
 *
 *              It is serializable and as such is safe to store in a session or other cache mechanism such as the
 *              Hazaar MVC Cache module.
 *
 * @since       1.0.0
 */
class MongoDB extends \MongoDB {

    static public $config         = array(
        'hosts'    => array('localhost'),
        'args'     => array(
            'connect'          => FALSE,
            'connectTimeoutMS' => 5000,
            'readPreference'   => 'nearest'
        ),
        'database' => 'test'
    );

    private       $current_config = array();

    public        $connection     = NULL;

    function __construct($args = array()) {

        /*
         * Merge any config arguments with defaults
         */
        $config = MongoDB::configure($args, FALSE);

        if(array_key_exists('database', $config))
            $config['args']['db'] = $config['database'];

        /*
         * Instantiate the parent with the hosts list and arguments
         */
        $valid = array(
            'connect',
            'connectTimeoutMS',
            'db',
            'fsync',
            'journal',
            'password',
            'readPreference',
            'readPreferenceTags',
            'replicaSet',
            'socketTimeoutMS',
            'ssl',
            'username',
            'w',
            'wTimeoutMS'
        );

        $args = array_intersect_key($config['args'], array_flip($valid));

        $class = '\MongoClient';

        if(! class_exists($class)) {

            $class = '\Mongo';

        }

        $this->connection = new $class(implode(',', $config['hosts']), $args);

        parent::__construct($this->connection, $config['database']);

        $this->current_config = $config;

    }

    public function __sleep() {

        return array('current_config');

    }

    public function __wakeup() {

        $conn = new \Mongo(implode(',', $this->current_config['hosts']), $this->current_config['args']);

        parent::__construct($conn, $this->current_config['database']);

        $this->connection = $conn;

        if($this->current_config['args']['slaveok'] === TRUE) {

            $this->connection->setSlaveOkay();

        }

    }

    /**
     * @brief           Configure the Hazaar\MongoDB object with defaults.
     *
     * @detail          This means configuration parameters can be omitted from the constructor. These defaults can also
     *                  be overridden by specifying parameters in the constructor.
     *
     *                  Options:
     *
     *                  * _hosts_ - Array or comma delimited string of hosts to connect to.
     *                  * _slaveok_ - Boolean specifying that it's ok to query slaves
     *                  * _replset_ - String of the name of the replica set to use.
     *
     *                  h3. Example
     *
     *                  <code class="php">
     *                  $config = array(
     *                      'hosts' => 'mongodb.mydomain.com',
     *                      'slaveok' => false,
     *                      'replset' => 'myReplSet'
     *                  );
     *                  Hazaar\MongoDB::configure($config);
     *                  </code>
     *
     * @since           1.0.0
     *
     * @param           Array $args Array of configuration options.
     *
     * @param           boolean $default Use this config array as the default configuration.  True by default.
     *
     */
    static public function configure($args, $default = TRUE) {

        $config = MongoDB::$config;

        if(is_string($args)) $args = array($args);

        foreach($args as $key => $value) {

            if($key == 'hosts') {

                if(! is_array($value)) {

                    $config['hosts'] = preg_split('/\s*,\s*/', $value);

                } else {

                    $config['hosts'] = $value;
                }

            } elseif($key == 'database') {

                $config['database'] = $value;

            } else {

                if($key == 'slaveok') {

                    $value = boolify($value);

                }

                $config['args'][$key] = $value;

            }

        }

        /*
         * If we have set a replicaSet we need to connect immediately or we'll get an error
         */

        if(isset($config['args']['replicaSet']))
            $config['args']['connect'] = TRUE;

        /*
         * If we are setting the default, MERGE THE SUPPLIED CONFIG params on to the default and return
         */
        if($default === TRUE) {

            return MongoDB::$config = $config;

        }

        /*
         * If we are not setting the default, MERGE THE DEFAULT CONFIG onto the supplied config and return
         */

        return $config;

    }

    public function connect() {

        return $this->connection->connect();

    }

    /**
     * @brief           Magic method for executing functions on the MongoDB node as methods of the object.
     *
     * @since           1.0.0
     *
     * @return          mixed Returns the result from the server-side function call.
     */
    public function __call($method, $args) {

        return $this->call($method, $args);

    }

    /**
     * @brief           Method for executing server-side functions on the MongoDB node.
     *
     * @since           1.0.0
     *
     * @return          mixed Returns the result from the server-side function call.
     */
    public function call($method, $args = array()) {

        if(! $method) {

            throw new \Exception('no calling method defined');

        }

        /*
         * Generate an argument definition list for substituting arguments
         */
        $argdef = array();

        $range = range(0, count($args));

        foreach($range as $idx) {

            $argdef[] = chr(65 + $idx);

        }

        $argdef = implode(',', $argdef);

        /*
         * Using anonymous function to prevent "NoSQL Injection" attacks as arguments are passed separately
         */
        $ret = $this->execute("function($argdef) { return {$method}($argdef); }", $args);

        if($ret['ok'] != 1) {

            throw new \Exception($ret['errmsg'], $ret['code']);

        }

        return $ret['retval'];

    }

    /**
     * @brief           Retrieve a document object from the database.
     *
     * @detail          This method is useful when you want to work with a single document using the enhanced
     *                  Hazaar\MongoDB\Document class.
     *
     * @since           1.0.0
     *
     * @param           string $collection The name of the collection to search.
     *
     * @param           Array $criteria An array of search criteria supported by MongoDB.
     *
     * @param           Array $fields An array of field names to return.
     *
     * @return          MongoDB\Document A new document object.
     */
    public function getDocument($collection, $criteria = array(), $fields = array()) {

        return new MongoDB\Document($this, $collection, $criteria, $fields);

    }

    /**
     * @brief           Stores or updates JavaScript code as a server-side function on a MongoDB node.
     *
     * @since           1.0.0
     *
     * @param           string $name The name of the function
     *
     * @param           string $code The javascript code to store
     *
     * @param           Array $args Function argument definition
     *
     * @return          boolean Status of save command.
     */
    public function storeFunction($name, $code, $args = array()) {

        $argdef = '';

        if(is_array($args) && count($args) > 0) {

            $argdef = implode(', ', $args);

        }

        $source = "function($argdef){ $code }";

        $doc = array(
            '_id'   => $name,
            'value' => new \MongoCode($source)
        );

        return $this->system->js->save($doc);

    }

}

