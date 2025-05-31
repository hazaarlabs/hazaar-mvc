<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Interface;

use Hazaar\Warlock\Enum\PacketType;

interface Client
{
    public function recv(string &$buf): void;

    public function send(PacketType $command, mixed $payload = null): bool;

    public function disconnect(): bool;
}
