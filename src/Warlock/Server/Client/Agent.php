<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Server\Client;

use Hazaar\Warlock\Enum\ClientType;
use Hazaar\Warlock\Enum\LogLevel;
use Hazaar\Warlock\Enum\PacketType;
use Hazaar\Warlock\Server\Client;

class Agent extends Client
{
    public string $name = 'Unnamed Agent';
    public ClientType $type = ClientType::AGENT;
    public ?string $address = 'stream';

    public ?\stdClass $serviceStatus = null;

    private bool $authorized = false;

    public function initiateHandshake(string $request, array &$headers = []): bool
    {
        if (!parent::initiateHandshake($request, $headers)) {
            return false;
        }
        if (!array_key_exists('x-warlock-access-key', $headers)) {
            return false;
        }
        $this->log->write('Agent connecting in!', LogLevel::NOTICE);
        $this->authorized = true;

        return true;
    }

    public function isAuthorized(): bool
    {
        return $this->authorized;
    }

    public function processCommand(PacketType $command, mixed $payload = null): bool
    {
        if (!$this->isAuthorized()) {
            $this->log->write('Unauthorized command received from agent.', LogLevel::WARN);

            return false;
        }

        return parent::processCommand($command, $payload);
    }

    protected function commandStatus(?\stdClass $payload = null): bool
    {
        $this->serviceStatus = $payload;

        return true;
    }
}
