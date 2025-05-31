<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Server\Client;

use Hazaar\Warlock\Server\Client;

class Agent extends Client
{
    public string $name = 'Unnamed Agent';
    public ?string $address = 'stream';

    public \stdClass $serviceStatus = new \stdClass();

    protected function commandStatus(?\stdClass $payload = null): bool
    {
        $this->serviceStatus = $payload;

        return true;
    }
}
