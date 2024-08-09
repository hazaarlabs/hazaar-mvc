<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Server\Client;

use Hazaar\Warlock\Server\Client;

class Service extends Client
{
    public string $name = 'Unnamed Service';
    public ?string $address = 'stream';
    public int $port = 0;
    public \stdClass $serviceStatus;

    protected function frame(string $payload, ?string $type = null, bool $masked = true): false|string
    {
        return $payload."\n";
    }

    protected function processFrame(string &$frameBuffer): mixed
    {
        if ($this->frameBuffer) {
            $frameBuffer = $this->frameBuffer.$frameBuffer;
            $this->frameBuffer = null;

            return $this->processFrame($frameBuffer);
        }
        if (!$frameBuffer) {
            return false;
        }
        $frame = [];
        if (($lf = strpos($frameBuffer, "\n")) !== false) {
            $frame = substr($frameBuffer, 0, $lf);
            $frameBuffer = substr($frameBuffer, $lf + 1);
        }
        if (strlen($frameBuffer) > 0) {
            $this->frameBuffer = $frameBuffer;
            $frameBuffer = '';
        }

        return $frame;
    }

    protected function commandStatus(?\stdClass $payload = null): bool
    {
        $this->serviceStatus = $payload;

        return true;
    }
}
