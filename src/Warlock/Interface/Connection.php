<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Interface;

use Hazaar\Warlock\Protocol;

interface Connection
{
    public function __construct(Protocol $protocol, ?string $guid = null);

    public function connect(): bool;

    public function disconnect(): bool;

    public function connected(): bool;

    public function send(string $command, mixed $payload = null): bool;

    public function recv(mixed &$payload = null, int $tvSec = 3, int $tvUsec = 0): null|bool|string;
}
