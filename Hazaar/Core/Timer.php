<?php

/**
 * @file        Hazaar/Timer.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar;

/**
 * @brief       Timer class
 *
 * @detail      Used for timing things
 *
 * @since       1.0.0
 */
class Timer {

    /**
     * Array of timers
     */
    private $timers = array();

    /**
     * Timer Precision
     *
     * This sets the precision of the output.  By default it is 1000 which means output in milliseconds.
     */
    private $precision = 2;

    /**
     * @brief       Start the timer
     */
    function __construct($precision = 2) {

        $this->precision = $precision;

        $this->start('global', HAZAAR_EXEC_START);

    }
    
    public function start($name = 'default', $when = null) {

        if(!$when)
            $when = microtime(true);

        $this->timers[$name] = array('start' => $when);

    }

    public function stop($name = 'default') {

        if(!array_key_exists($name, $this->timers)) {

            throw new Exception("Error trying to stop non-existent timer '$name'.");

        }

        if($name == 'global') {

            throw new Exception('You can not stop the global timer!');

        }

        if(!array_key_exists('stop', $this->timers[$name])) {

            $this->timers[$name]['stop'] = microtime(true);

        }

        return $this->get($name);

    }

    public function __tostring() {

        return (string)$this->get();

    }

    public function get($name = 'default', $precision = null) {

        if(!array_key_exists($name, $this->timers)) {

            throw new Exception("Unable to return current value of non-existent timer '$name'.");
        }

        if(!$precision)
            $precision = $this->precision;

        $timer = $this->timers[$name];

        $start = $timer['start'];

        $stop = (array_key_exists('stop', $timer) ? $timer['stop'] : microtime(true));

        return round(($stop - $start) * 1000, $precision);

    }

    public function all() {

        $results = array();

        foreach(array_keys($this->timers) as $name) {

            $results[$name] = $this->get($name);

        }

        return $results;

    }

}
