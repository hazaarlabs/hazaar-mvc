<?php

namespace Hazaar\Cache;

abstract class Backend implements Backend\_Interface {

    public    $options      = array();

    private   $capabilities = array();

    protected $weight       = 10;

    final function __construct($options, $namespace) {

        $this->options = new \Hazaar\Map($options);

        /*
         * Initialise the frontend.  This allows the frontend to return some default options.
         */
        $this->init($namespace);

    }

    protected function configure($options) {

        $this->options->enhance($options);

    }

    function __destruct() {

        if(method_exists($this, 'close')) {

            $this->close();

        }

    }

    protected function addCapabilities() {

        foreach(func_get_args() as $arg) {

            if(! in_array($arg, $this->capabilities))
                $this->capabilities[] = $arg;

        }

    }

    public function can($key) {

        return in_array($key, $this->capabilities);

    }

    public function getWeight() {

        return $this->weight;

    }

}
