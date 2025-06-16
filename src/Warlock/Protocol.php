<?php

declare(strict_types=1);

/**
 * @file        Hazaar/Application/Protocol.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2018 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\Warlock;

use Hazaar\Warlock\Enum\PacketType;

/**
 * @brief       Hazaar Application Protocol Class
 *
 * @detail      The Application Protocol is a simple protocol developed to allow communication between
 *               parts of the Hazaar framework over the wire or other IO interfaces.  It allows common information
 *               to be encoded/decoded between endpoints.
 */
class Protocol
{
    private string $sid;
    private string $lastError;
    private bool $encoded = true;

    public function __construct(string $sid, bool $encoded = true)
    {
        $this->sid = $sid;
        $this->encoded = $encoded;
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function encoded(): bool
    {
        return $this->encoded;
    }

    public function encode(PacketType $type, mixed $payload = null): false|string
    {
        $packet = (object) [
            'TYP' => $type,
            'SID' => $this->sid,
            'TME' => time(),
        ];
        if (null !== $payload) {
            $packet->PLD = $payload;
        }
        $packet = json_encode($packet);

        return $this->encoded ? base64_encode($packet) : $packet;
    }

    public function decode(string &$packet, mixed &$payload = null, ?int &$time = null): null|false|PacketType
    {
        $payload = null;
        if (!($packet = json_decode($this->encoded ? base64_decode($packet) : $packet))) {
            return $this->error('Packet decode failed');
        }
        if (!$packet instanceof \stdClass) {
            return $this->error('Invalid packet format');
        }
        if (!property_exists($packet, 'TYP')) {
            return $this->error('No packet type');
        }
        if (property_exists($packet, 'PLD')) {
            $payload = $packet->PLD;
        }
        if (property_exists($packet, 'TME')) {
            $time = $packet->TME;
        }

        return PacketType::tryFrom($packet->TYP);
    }

    private function error(string $msg): false
    {
        $this->lastError = $msg;

        return false;
    }
}
