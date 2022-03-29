<?php

namespace Hazaar\Cache;

abstract class Backend implements Backend\_Interface {

    public    $options      = [];

    /**
     * The backends list of capabilities
     *
     * Standard capabilities are:
     * * store_objects - Backend can directly store & return objects.  Whether the backend itself really can (like APCu) or the backend class takes care of this.
     * * compress - Can compress objects being stored.  If the backend can do this, then we don't want the frontend to ever do it.
     * * array - Can return all elements in the cache as an associative array
     * * expire_ns - Backend supports storage TTLs on the namespace as a whole.
     * * expire_val - Backend supports storage TTLs on individual values stored within the namespace
     * * keepalive - When a value is accessed it's TTL can be reset to keep it alive in cache.
     * @var mixed
     */
    private   $capabilities = [];

    protected $weight       = 10;

    final function __construct($options, $namespace) {

        $this->options = new \Hazaar\Map($options);

        /*
         * Initialise the frontend.  This allows the frontend to return some default options.
         */
        $this->init($namespace);

    }

    function __destruct() {

        $this->close();

    }

    public function close(){

    }

    protected function configure($options) {

        $this->options->enhance($options);

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
