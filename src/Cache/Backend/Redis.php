<?php

/**
 * @file        Hazaar/Cache/Backend/Redis.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2016 Jamie Carl (http://www.hazaarlabs.com)
 */
namespace Hazaar\Cache\Backend;

/**
 * @brief The Redis cache backend.
 *
 * @detail This is a nice, reliable caching backend that allows for clustering using the Redis clustering feature.
 *
 * Available config options:
 *
 * * server - The redis server to connect to.  Currently only a single server is supported. Default: localhost.
 * * port - The port to connect to the server on.  Default: 6379
 *
 * @since 2.2.0
 */
class Redis extends \Hazaar\Cache\Backend {

    protected $weight = 2;

    private $namespace;

    private $role;

    private $socket;        //The socket for the configured host.

    private $master;        //The socket for the master host if we are on a slave.

    private $buffer = '';

    private $offset = 0;

    private $delim = "\r\n";

    private $local = [];

    private $update_expire = false;

    private $garbage = []; //This keeps a list of keys that should be deleted on close because they have expired.

    static public function available(){

        return function_exists('socket_create');

    }

    function init($namespace) {

        $this->namespace = $namespace;

        $this->addCapabilities('store_objects', 'array', 'expire_ns', 'expire_val', 'keepalive');

        $this->configure([
           'server'          => 'localhost',
           'port'            => 6379,
           'dbIndex'         => 0,
           'keepalive'       => false,
           'keeplocalcopy'   => true
        ]);

        $this->socket = $this->connect($this->options['server'], $this->options['port']);

        if($this->options->has('serverpass') && ($serverpass = $this->options['serverpass']))
            $this->cmd(['AUTH', $serverpass]);

        $cmds = [
            ['SELECT', (string)$this->options['dbIndex']],
            ['ROLE'],
            ['TTL', $this->namespace]
        ];

        $result = $this->cmd($cmds);

        if($result[0] !== 'OK')
            throw new \Exception('Redis: Unable to select DB index ' . $this->options['dbIndex']);

        $this->role = $result[1];

        //Check that there is a TTL set when there is supposed to be.  Redis will return -1 for no TTL (-2 means keys doesn't exist).
        if($this->options->lifetime > 0 && $result[2] == -1)
            $this->update_expire = true;

    }

    private function connect($host, $port = 6379){

        $socket = new \Hazaar\Socket\Client();

        if(!$socket->connect($host, $port))
            throw new \Exception('Could not connect to Redis server: ' . $host . ':' . $port);

        return $socket;

    }

    public function cmd($cmds, $use_master = false){

        if($use_master){

            if(!$this->master instanceof \Hazaar\Socket\Client){

                //If we are on the master, just set the master to the current socket.
                if($this->role[0] == 'master'){

                    $this->master = $this->socket;

                }elseif($this->role[0] == 'slave'){

                    $this->master = $this->connect($this->role[1], $this->role[2]);

                    if($this->options->has('serverpass'))
                        $this->cmd(['AUTH', $this->options['serverpass']], true);

                    $index = (string)$this->options['dbIndex'];

                    $result = $this->cmd(['SELECT', $index], true);

                    if(!$result)
                        throw new \Exception('Could not select DB ' . $index . ' on master');

                }else{

                    throw new \Exception("Redis cache backend does not support writing to hosts of role '{$this->role[0]}'");

                }

            }

            $socket = $this->master;

        }else{

            $socket = $this->socket;

        }

        if(!$socket->isConnected())
            return false;

        $packets = $this->encode($cmds);

        $count = count($packets);

        if(!$socket->send(implode('', $packets)) > 0)
            throw new Exception\RedisError("Error sending $count packets!");

        $this->offset = 0;

        $this->buffer = '';

        $result = [];

        for($i=0;$i<$count;$i++)
            $result[] = $this->decode($socket);

        if($count == 1)
            return $result[0];

        return $result;

    }

    private function getChunk($socket, $bytes = null){

        if($bytes !== null){

            //Check there is enough data in the buffer to satisfy the request
            while(($this->offset + $bytes) > strlen($this->buffer))
                $this->buffer .= $socket->recv();

            $chunk = substr($this->buffer, $this->offset, $bytes);

            $this->offset += ($bytes + 2);

        }else{

            //Keep receiving data from the socket if the current chunk is incomplete
            while(!($offset = strpos($this->buffer, $this->delim, $this->offset)))
                $this->buffer .= $socket->recv();

            $chunk = substr($this->buffer, $this->offset, $offset - $this->offset);

            $this->offset = $offset + 2;

        }

        return $chunk;

    }

    /**
     * Decodes a RESP data chunk
     *
     * @param \Hazaar\Socket\Client $socket The socket we are currently using
     *
     * @param mixed $chunk The current chunk to start decoding
     *
     * @throws Exception\RedisError
     *
     * @return \array|integer|null|string
     */
    private function decode(\Hazaar\Socket\Client $socket, $chunk = null){

        if($chunk === null)
            $chunk = $this->getChunk($socket);

        do{

            $prefix = $chunk[0];

            switch($prefix){

                case '-': //Error response
                    throw new Exception\RedisError($chunk);

                case '+': //Simple string response

                    return substr($chunk, 1);

                case ':': //Integer response

                    return intval(substr($chunk, 1));

                case '$': //Bulk string response

                    $size = intval(substr($chunk, 1));

                    if($size === -1)
                        return null;

                    return $this->getChunk($socket, $size);

                case '*': //Array response

                    $count = intval(substr($chunk, 1));

                    if($count === -1)
                        return null;

                    $array = [];

                    for($i = 0; $i < $count; $i++)
                        $array[$i] = $this->decode($socket, $this->getChunk($socket));

                    return $array;

            }

        }while($chunk = $this->getChunk($socket));

    }

    private function encode($data){

        if(!is_array($data))
            $data = [[$data]];
        elseif(!is_array($data[0]))
            $data = [$data];

        $packets = [];

        foreach($data as &$command){

            $packet = '*' . count($command) . "\r\n";

            foreach($command as $payload){

                if(is_array($payload))
                    throw new Exception\RedisError('Storing arrays is not supported by the RESP protocol');

                if(is_string($payload)){

                    $packet .= '$' . strlen($payload) . "\r\n$payload\r\n";

                }elseif(is_int($payload)){

                    $packet .= ':' . $payload . "\r\n";

                }else{

                    throw new Exception\RedisError('Error setting unknown data type!');

                }

            }

            $packets[] = $packet;

        }

        return $packets;

    }

    public function select($db){

        return boolify($this->cmd(['SELECT', "$db"]));

    }

    function close(){

        if($this->socket){

            if($this->update_expire === true)
                $this->cmd(['EXPIRE', $this->namespace, (string)$this->options->lifetime]);

            $this->socket->close();

        }

    }

    private function keepalive(){

        if($this->options->keepalive === true && $this->options->lifetime > 0)
            $this->update_expire = true;

    }

    public function has($key) {

        return ($this->cmd(['HEXISTS', $this->namespace, $key]) == 1);

    }

    public function get($key) {

        //This value is due to be deleted so just return null now.
        if(in_array($key, $this->garbage))
            return null;

        $this->keepalive();

        if(!array_key_exists($key, $this->local)){

            if(!($data = unserialize($this->cmd(['HGET', $this->namespace, $key]))))
                return null;

            if(array_key_exists('expire', $data) && time() > $data['expire']){

                $this->garbage[] = $key;

                return null;

            }

            if(!$this->options['keeplocalcopy'])
                return $data['value'];

            $this->local[$key] = $data['value'];

        }

        return $this->local[$key];

    }

    public function set($key, $value, $timeout = NULL) {

        //If this has expired and is being bined, recycle the garbage (see what I did there?).
        if(($gkey = array_search($key, $this->garbage)) !== false)
            unset($this->garbage[$gkey]);

        if($this->options['keeplocalcopy'])
            $this->local[$key] = $value;

        $data = [
            'value' => $value
        ];

        if($timeout > 0)
            $data['expire'] = time() + $timeout;

        //Piplining!
        $cmds = [
            ['EXISTS', $this->namespace],
            ['HSET', $this->namespace, $key, serialize($data)]
        ];

        $result = $this->cmd($cmds, true);

        $this->update_expire = ($this->options->lifetime > 0 && !boolify($result[0]));

        if(!($result[1] === 0 || $result[1] === 1))
            return false;

        $this->keepalive();

        return true;

    }

    public function remove($key) {

        if(array_key_exists($key, $this->local))
            unset($this->local[$key]);

        return boolify($this->cmd(['HDEL', $this->namespace, $key], true));

    }

    public function clear() {

        $this->local = [];

        return boolify($this->cmd(['DEL', $this->namespace], true));

    }

    public function toArray(){

        $array = [];

        $items = $this->cmd(['HGETALL', $this->namespace]);

        for($i=0; $i<count($items);$i+=2){

            if(!($data = unserialize($items[$i+1])))
                continue;

            if(array_key_exists('expire', $data) && time() > $data['expire'])
                continue;

            $array[$items[$i]] = $data['value'];

        }

        $this->local = $array;

        return $array;

    }

}