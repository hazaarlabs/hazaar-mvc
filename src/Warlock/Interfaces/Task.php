<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Interfaces;

interface Task
{
    public function cancel(int $expire = 30): void;

    public function status(): string;

    public function recv(string &$buf): void;

    public function send(string $command, mixed $payload = null): bool;

    public function touch(): ?int;
}
