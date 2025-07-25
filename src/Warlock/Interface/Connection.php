<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Interface;

use Hazaar\Warlock\Enum\PacketType;
use Hazaar\Warlock\Protocol;

interface Connection
{
    public function __construct(Protocol $protocol, ?string $guid = null);

    /**
     * Configure the connection.
     *
     * @param array<mixed> $config
     */
    public function configure(array $config): void;

    public function getHost(): string;

    public function getPort(): int;

    public function connect(): bool;

    public function disconnect(): bool;

    public function connected(): bool;

    public function send(PacketType $command, mixed $payload = null): bool;

    public function recv(mixed &$payload = null, int $tvSec = 3, int $tvUsec = 0): null|bool|PacketType;
}
