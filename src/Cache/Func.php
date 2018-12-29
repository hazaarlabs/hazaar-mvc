<?php

namespace Hazaar\Cache;

class Func extends \Hazaar\Cache {

    function __construct($backend = NULL, $options = array()) {

        parent::__construct($backend, $options);

        $this->configure(array(
                             'cache_by_default'     => TRUE,
                             'cached_functions'     => array(),
                             'non_cached_functions' => array()
                         ));

    }

    private function generateKey($function, $param_arr) {

        /*
         * Generate a nice unique key string for this function/arg pair
         */
        if(is_array($function)) {

            if(count($function) != 2)
                throw new \Exception('Unsupported callback declaration calling cached function.');

            $func_string = get_class($function[0]) . '::' . $function[1];

        } else {

            $func_string = $function;

        }

        $args = ((count($param_arr) > 0) ? serialize($param_arr) : NULL);

        return $this->options->get('prefix') . md5($func_string . $args);

    }

    public function call() {

        $param_arr = func_get_args();

        if(! $function = array_shift($param_arr))
            throw new Exception\NoFunction();

        $key = $this->generateKey($function, $param_arr);

        $use_cache = TRUE;

        $result = FALSE;

        if($this->options->cache_by_default) {

            if(count($non_cached_function = $this->options->get('non_cached_functions')) > 0 && $this->options->non_cached_functions->in($function)) {

                $use_cache = FALSE;

            }

        } elseif(count($cached_functions = $this->options->get('cached_functions')) > 0 && ! $cached_functions->in($function)) {

            $use_cache = FALSE;

        }

        if($use_cache)
            $result = $this->get($key);

        if($result === FALSE) {

            $result = call_user_func_array($function, $param_arr);

            $this->set($key, $result);

        }

        return $result;

    }

}
