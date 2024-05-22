<?php

declare(strict_types=1);

namespace Hazaar\Cache;

use Hazaar\Cache;

class Output extends Cache
{
    private string $key;

    public function start(string $key): false|string
    {
        if (($buffer = $this->get($key)) === false) {
            $this->key = $key;
            ob_start();

            return false;
        }

        return $buffer;
    }

    public function stop(): string
    {
        $buffer = ob_get_contents();
        ob_end_clean();
        $this->set($this->key, $buffer);

        return $buffer;
    }
}
