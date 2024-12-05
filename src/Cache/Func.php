<?php

declare(strict_types=1);

namespace Hazaar\Cache;

use Hazaar\Cache;
use Hazaar\Cache\Exception\NoFunction;

/**
 * Class Func.
 *
 * This class extends the Cache class and provides functionality to cache function calls.
 *
 * @throws NoFunction if no function is provided to call
 * @throws \Exception if an unsupported callback declaration is used
 */
class Func extends Cache
{
    /**
     * @param array<mixed> $options
     */
    public function __construct(?string $backend = null, array $options = [])
    {
        parent::__construct($backend, $options);
        $this->configure([
            'cache_by_default' => true,
            'cached_functions' => [],
            'non_cached_functions' => [],
        ]);
    }

    /**
     * Calls a function with the provided arguments and caches the result based on the configuration.
     *
     * This method retrieves the function name and its arguments, generates a cache key, and checks
     * whether the function result should be cached based on the options provided. If caching is enabled
     * and the result is found in the cache, it returns the cached result. Otherwise, it calls the function,
     * caches the result, and then returns it.
     *
     * @return mixed the result of the function call, either from cache or from the actual function execution
     *
     * @throws NoFunction if no function name is provided in the arguments
     */
    public function call(): mixed
    {
        $paramArray = func_get_args();
        if (!$function = array_shift($paramArray)) {
            throw new NoFunction();
        }
        $key = $this->generateKey($function, $paramArray);
        $use_cache = true;
        $result = false;
        if ($this->options['cache_by_default']) {
            if (count($nonCachedFunction = $this->options['non_cached_functions']) > 0
                && $this->options['non_cached_functions']->in($function)) {
                $use_cache = false;
            }
        } elseif (count($cachedFunctions = $this->options['cached_functions']) > 0
            && !$cachedFunctions->in($function)) {
            $use_cache = false;
        }
        if ($use_cache) {
            $result = $this->get($key);
        }
        if (false === $result) {
            $result = call_user_func_array($function, $paramArray);
            $this->set($key, $result);
        }

        return $result;
    }

    /**
     * Generates a unique key string for a given function and its arguments.
     *
     * @param array<mixed|string>|string $function   The function to generate a key for. Can be a string representing the function name
     *                                               or an array with the object and method name.
     * @param array<mixed>               $paramArray the array of parameters to be passed to the function
     *
     * @return string the generated unique key string
     *
     * @throws \Exception if the callback declaration is unsupported
     */
    private function generateKey(array|string $function, array $paramArray): string
    {
        // Generate a nice unique key string for this function/arg pair
        if (is_array($function)) {
            if (2 != count($function)) {
                throw new \Exception('Unsupported callback declaration calling cached function.');
            }
            $funcString = get_class($function[0]).'::'.$function[1];
        } else {
            $funcString = $function;
        }
        $args = ((count($paramArray) > 0) ? serialize($paramArray) : null);

        return $this->options['prefix'].md5($funcString.$args);
    }
}
