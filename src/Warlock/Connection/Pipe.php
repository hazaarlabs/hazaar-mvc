<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Connection;

use Hazaar\Warlock\Enum\PacketType;
use Hazaar\Warlock\Interface\Connection;
use Hazaar\Warlock\Protocol;

final class Pipe implements Connection
{
    public int $bytesReceived = 0;
    public ?string $guid;
    private Protocol $protocol;
    private string $buffer = '';

    public function __construct(Protocol $protocol, ?string $guid = null)
    {
        $this->protocol = $protocol;
        $this->guid = $guid;
    }

    public function configure(array $config): void
    {
        // No configuration needed for pipe connection
    }

    public function getHost(): string
    {
        return 'pipe';
    }

    public function getPort(): int
    {
        return 0; // No port for pipe connection
    }

    public function connect(): bool
    {
        return true;
    }

    public function disconnect(): bool
    {
        flush();

        return false;
    }

    public function connected(): bool
    {
        return true;
    }

    public function send(PacketType $command, mixed $payload = null): bool
    {
        if (!($packet = $this->protocol->encode($command, $payload))) {
            return false;
        }
        $len = strlen($packet .= "\n");
        $attempts = 0;
        $totalSent = 0;
        while ($packet) {
            ++$attempts;
            $bytesSent = @fwrite(STDOUT, $packet, $len);
            if ($bytesSent < $len) {
                return false;
            }
            $totalSent += $bytesSent;
            if ($totalSent === $len) { // If all the bytes sent then don't waste time processing the leftover frame
                break;
            }
            if ($attempts >= 100) {
                throw new \Exception('Unable to write to pipe.  Pipe appears to be stuck.');
            }
            $packet = substr($packet, $bytesSent);
        }

        return true;
    }

    public function recv(mixed &$payload = null, int $tvSec = 3, int $tvUsec = 0): null|false|PacketType
    {
        if ($this->buffer && false !== strpos($this->buffer, "\n")) {
            while ($packet = $this->processPacket($this->buffer)) {
                if (true === $packet) {
                    break;
                }

                return $this->protocol->decode($packet, $payload);
            }
        }
        $read = [STDIN];
        $write = $except = null;
        while (stream_select($read, $write, $except, $tvSec, $tvUsec) > 0) {
            // will block to wait server response
            $this->buffer .= $buffer = fread(STDIN, 65536);
            $this->bytesReceived += ($bytesReceived = strlen($buffer));
            if ($bytesReceived > 0) {
                if (($packet = $this->processPacket($this->buffer)) === true) {
                    continue;
                }
                if (false === $packet) {
                    break;
                }

                return $this->protocol->decode($packet, $payload);
            }

            return false;
        }

        return null;
    }

    private function processPacket(?string &$buffer = null): bool|string
    {
        if (!$buffer) {
            return false;
        }
        if (($pos = strpos($buffer, "\n")) === false) {
            return true;
        }
        $packet = substr($buffer, 0, $pos);
        $buffer = substr($buffer, $pos + 1);

        return $packet;
    }
}
