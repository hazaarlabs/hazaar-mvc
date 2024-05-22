<?php

declare(strict_types=1);

namespace Hazaar\Cache;

use Hazaar\Cache;
use Hazaar\Map;

class Func extends Cache
{
    /**
     * @param array<mixed>|Map $options
     */
    public function __construct(?string $backend = null, array|Map $options = [])
    {
        parent::__construct($backend, $options);
        $this->configure([
            'cache_by_default' => true,
            'cached_functions' => [],
            'non_cached_functions' => [],
        ]);
    }

    public function call(): mixed
    {
        $param_arr = func_get_args();
        if (!$function = array_shift($param_arr)) {
            throw new Exception\NoFunction();
        }
        $key = $this->generateKey($function, $param_arr);
        $use_cache = true;
        $result = false;
        if ($this->options['cache_by_default']) {
            if (count($non_cached_function = $this->options->get('non_cached_functions')) > 0
                && $this->options['non_cached_functions']->in($function)) {
                $use_cache = false;
            }
        } elseif (count($cached_functions = $this->options->get('cached_functions')) > 0
            && !$cached_functions->in($function)) {
            $use_cache = false;
        }
        if ($use_cache) {
            $result = $this->get($key);
        }
        if (false === $result) {
            $result = call_user_func_array($function, $param_arr);
            $this->set($key, $result);
        }

        return $result;
    }

    /**
     * @param array<mixed|string>|string $function
     * @param array<mixed>               $param_arr
     */
    private function generateKey(array|string $function, array $param_arr): string
    {
        // Generate a nice unique key string for this function/arg pair
        if (is_array($function)) {
            if (2 != count($function)) {
                throw new \Exception('Unsupported callback declaration calling cached function.');
            }
            $func_string = get_class($function[0]).'::'.$function[1];
        } else {
            $func_string = $function;
        }
        $args = ((count($param_arr) > 0) ? serialize($param_arr) : null);

        return $this->options->get('prefix').md5($func_string.$args);
    }
}
