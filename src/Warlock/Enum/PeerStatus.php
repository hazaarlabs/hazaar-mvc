<?php

namespace Hazaar\Warlock\Enum;

enum PeerStatus: int
{
    case DISCONNECTED = 0;
    case CONNECTING = 1;
    case CONNECTED = 2;
    case NEGOTIATING = 3;
    case AUTHENTICATING = 4;
    case STREAMING = 5;

    public function isConnected(): bool
    {
        return self::CONNECTED === $this || self::STREAMING === $this;
    }

    public function toString(): string
    {
        return match ($this) {
            PeerStatus::DISCONNECTED => 'Disconnected',
            PeerStatus::CONNECTING => 'Connecting',
            PeerStatus::CONNECTED => 'Connected',
            PeerStatus::NEGOTIATING => 'Negotiating',
            PeerStatus::AUTHENTICATING => 'Authenticating',
            PeerStatus::STREAMING => 'Streaming',
        };
    }
}
