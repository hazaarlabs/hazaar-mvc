<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Agent;

use Hazaar\Warlock\Connection\Pipe;
use Hazaar\Warlock\Interface\Connection;
use Hazaar\Warlock\Process;
use Hazaar\Warlock\Protocol;

class Container extends Process
{
    public function createConnection(Protocol $protocol, ?string $guid = null): Connection|false
    {
        return new Pipe($protocol, $guid);
    }
}
