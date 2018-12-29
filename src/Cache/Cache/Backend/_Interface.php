<?php

namespace Hazaar\Cache\Backend;

interface _Interface {

    public function init($namespace);

    public function has($key);

    public function get($key);

    public function set($key, $value, $timeout = NULL);

    public function remove($key);

    public function clear();

}

