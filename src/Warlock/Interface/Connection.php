<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Interface;

use Hazaar\Warlock\Protocol;

interface Connection
{
    public function __construct(Protocol $protocol, ?string $guid = null);

    /**
     * Connect to the Warlock server.
     *
     * @param string             $applicationName The name of the application connecting
     * @param string             $host            The host name or IP address of the Warlock server
     * @param int                $port            The port number of the Warlock server
     * @param null|array<string> $extra_headers   Additional headers to send with the connection
     */
    public function connect(string $applicationName, string $host, int $port, ?array $extra_headers = null): bool;

    public function disconnect(): bool;

    public function connected(): bool;

    public function send(string $command, mixed $payload = null): bool;

    public function recv(mixed &$payload = null, int $tv_sec = 3, int $tv_usec = 0): null|bool|string;
}
