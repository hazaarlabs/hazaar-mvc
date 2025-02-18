<?php

declare(strict_types=1);

namespace Hazaar\Cache;

use Hazaar\Cache;

class Output extends Adapter
{
    private string $key;

    /**
     * Starts output buffering and retrieves cached content if available.
     *
     * This method attempts to retrieve cached content associated with the given key.
     * If the content is not found in the cache, it starts output buffering and returns false.
     * If the content is found, it returns the cached content.
     *
     * @param string $key the key used to identify the cached content
     *
     * @return false|string returns the cached content if available, otherwise false
     */
    public function start(string $key): false|string
    {
        if (($buffer = $this->get($key)) === false) {
            $this->key = $key;
            ob_start();

            return false;
        }

        return $buffer;
    }

    /**
     * Stops the output buffering, retrieves the buffer contents, stores it in the cache,
     * and returns the buffer contents as a string.
     *
     * @return string the contents of the output buffer
     */
    public function stop(): string
    {
        $buffer = ob_get_contents();
        ob_end_clean();
        $this->set($this->key, $buffer);

        return $buffer;
    }
}
