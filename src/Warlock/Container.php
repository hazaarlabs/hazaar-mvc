<?php

declare(strict_types=1);

namespace Hazaar\Warlock;

use Hazaar\Warlock\Connection\Pipe;

class Container extends Process
{
    /**
     * @param null|array<mixed> $params
     */
    public function exec(?string $code, null|array $params = null): int
    {
        $exitcode = 1;

        try {
            if (null === $code) {
                throw new \Exception('Unable to evaulate container code.');
            }
            eval($code);
            // @phpstan-ignore-next-line
            if (!(isset($_function) && $_function instanceof \Closure)) {
                throw new \Exception('Function is not callable!');
            }
            // @phpstan-ignore-next-line
            if (null === $params) {
                $params = [];
            }
            $result = call_user_func_array($_function, $params);
            // Any of these are considered an OK response.
            if (null === $result
            || true === $result
            || 0 == $result) {
                $exitcode = 0;
            } else { // Anything else is an error and we display it.
                $exitcode = $result;
            }
        } catch (\Throwable $e) {
            $this->log(W_ERR, $e->getMessage());
            $exitcode = 2;
        }

        return $exitcode;
    }

    protected function connect(Protocol $protocol, ?string $guid = null): Pipe
    {
        return new Pipe($protocol);
    }
}
