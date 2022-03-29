<?php

namespace Hazaar\Cache;

/**
 * Benchmark short summary.
 *
 * Benchmark description.
 *
 * @version 1.0
 * @author jamiec
 */
class Benchmark {

    private $backends;

    private $configs;

    function __construct($backends = [], $configs = []){

        if($backends && !is_array($backends))
            $backends = [$backends];

        if(count($backends) == 0)
            $backends = $this->getAvailableBackends();

        $this->backends = $backends;

        $this->configs = $configs;

    }

    static public function getAvailableBackends(){

        $all = ['apc', 'database', 'file', 'memcached', 'redis', 'session', 'shm', 'sqlite3'];

        $available = [];

        foreach($all as $backend){

            $class = 'Hazaar\Cache\Backend\\' . ucfirst($backend);

            if($class::available())
                $available[] = $backend;

        }

        return $available;

    }

    public function run($start = 2, $end = 2048){

        $results = [];

        foreach($this->backends as $backend){

            try{

                $tests = [];

                $cache = new \Hazaar\Cache($backend, ake($this->configs, $backend));

                for($i = $start; $i <= $end; $i=$i*2){

                    $w_bytes = str_repeat('.', $i);

                    //Test write speed
                    $s = microtime(true);

                    $cache->set('test_value', $w_bytes);

                    $tests[$i]['w'] = round((microtime(true) - $s) * 1000, 4);

                    //Test read speed
                    $s = microtime(true);

                    $r_bytes = $cache->get('test_value');

                    $tests[$i]['r'] = round((microtime(true) - $s) * 1000, 4);

                    $tests[$i]['valid'] = ($r_bytes === $w_bytes);

                }

                $results[$backend] = $tests;

            }
            catch(\Exception $e){

                continue;
            }

        }

        return $results;

    }

}