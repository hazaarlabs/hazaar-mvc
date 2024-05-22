<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Interfaces;

interface Client
{
    public function recv(string &$buf): void;

    public function send(string $command, mixed $payload = null): bool;

    public function disconnect(): bool;
}
