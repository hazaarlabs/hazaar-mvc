<?php

declare(strict_types=1);

/**
 * @file        Hazaar/Application/Protocol.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2018 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\Application;

/**
 * @brief       Hazaar Application Protocol Class
 *
 * @detail      The Application Protocol is a simple protocol developed to allow communication between
 *               parts of the Hazaar framework over the wire or other IO interfaces.  It allows common information
 *               to be encoded/decoded between endpoints.
 */
class Protocol
{
    /**
     * @var array<int, string>
     */
    public static array $typeCodes = [
        // SYSTEM MESSAGES
        0x00 => 'NOOP',         // Null Opperation
        0x01 => 'SYNC',         // Sync client
        0x02 => 'OK',           // OK response
        0x03 => 'ERROR',        // Error response
        0x04 => 'STATUS',       // Status request/response
        0x05 => 'SHUTDOWN',     // Shutdown request
        0x06 => 'PING',         // Typical PING
        0x07 => 'PONG',         // Typical PONG
        // CODE EXECUTION MESSAGES
        0x10 => 'DELAY',        // Execute code after a period
        0x11 => 'SCHEDULE',     // Execute code at a set time
        0x12 => 'EXEC',         // Execute some code in the Warlock Runner.
        0x13 => 'CANCEL',       // Cancel a pending code execution
        // SIGNALLING MESSAGES
        0x20 => 'SUBSCRIBE',    // Subscribe to an event
        0x21 => 'UNSUBSCRIBE',  // Unsubscribe from an event
        0x22 => 'TRIGGER',      // Trigger an event
        0x23 => 'EVENT',        // An event
        // SERVICE MESSAGES
        0x30 => 'ENABLE',       // Start a service
        0x31 => 'DISABLE',      // Stop a service
        0x32 => 'SERVICE',      // Service status
        0x33 => 'SPAWN',        // Spawn a dynamic service
        0x34 => 'KILL',         // Kill a dynamic service instance
        0x35 => 'SIGNAL',       // Signal between a dyanmic service and it's client
        // KV STORAGE MESSAGES
        0x40 => 'KVGET',          // Get a value by key
        0x41 => 'KVSET',          // Set a value by key
        0x42 => 'KVHAS',          // Test if a key has a value
        0x43 => 'KVDEL',          // Delete a value
        0x44 => 'KVLIST',         // List all keys/values in the selected namespace
        0x45 => 'KVCLEAR',        // Clear all values in the selected namespace
        0x46 => 'KVPULL',         // Return and remove a key value
        0x47 => 'KVPUSH',         // Append one or more elements on to the end of a list
        0x48 => 'KVPOP',          // Remove and return the last element in a list
        0x49 => 'KVSHIFT',        // Remove and return the first element in a list
        0x50 => 'KVUNSHIFT',      // Prepend one or more elements to the beginning of a list
        0x51 => 'KVCOUNT',        // Count number of elements in a list
        0x52 => 'KVINCR',         // Increment an integer value
        0x53 => 'KVDECR',         // Decrement an integer value
        0x54 => 'KVKEYS',         // Return all keys in the selected namespace
        0x55 => 'KVVALS',         // Return all values in the selected namespace
        // LOGGING/OUTPUT MESSAGES
        0x90 => 'LOG',          // Generic log message
        0x91 => 'DEBUG',
    ];
    private int $id;
    private string $last_error;
    private bool $encoded = true;

    public function __construct(int $id, bool $encoded = true)
    {
        $this->id = $id;
        $this->encoded = $encoded;
    }

    /**
     * Returns the last error message.
     *
     * @return string the last error message
     */
    public function getLastError(): string
    {
        return $this->last_error;
    }

    /**
     * Returns whether the application protocol is encoded.
     *
     * @return bool true if the application protocol is encoded, false otherwise
     */
    public function encoded(): bool
    {
        return $this->encoded;
    }

    /**
     * Checks that a protocol message type is valid and returns it's numeric value.
     *
     * @param int|string $type If $type is a string, it is checked and if valid then it's numeric value is returned.  If $type is
     *                         an integer it will be returned back if valid.  If either is not valid then false is returned.
     *
     * @return bool|int The integer value of the message type. False if the type is not valid.
     */
    public function check(int|string $type): bool|int
    {
        if (is_int($type)) {
            if (array_key_exists($type, Protocol::$typeCodes)) {
                return $type;
            }

            return false;
        }

        return array_search(strtoupper($type), Protocol::$typeCodes, true);
    }

    /**
     * Get the type code for a given name.
     *
     * @param string $name the name of the protocol
     *
     * @return false|int the type code if found, otherwise false
     */
    public function getType(string $name): false|int
    {
        return array_search(strtoupper($name), Protocol::$typeCodes, true);
    }

    /**
     * Returns the name of the type based on the given type ID.
     *
     * @param int $type the type ID
     *
     * @return bool|string the name of the type
     */
    public function getTypeName(int $type): bool|string
    {
        if (!is_int($type)) {
            return $this->error('Bad packet type');
        }
        if (!array_key_exists($type, Protocol::$typeCodes)) {
            return $this->error('Unknown packet type');
        }

        return Protocol::$typeCodes[$type];
    }

    /**
     * Encodes the payload into a string representation.
     *
     * @param int   $type    the type of the payload
     * @param mixed $payload the payload to be encoded
     *
     * @return string the encoded payload as a string
     */
    public function encode(int $type, mixed $payload = null): ?string
    {
        if (($type = $this->check($type)) === false) {
            return null;
        }
        $packet = (object) [
            'TYP' => $type,
            'SID' => $this->id,
            'TME' => time(),
        ];
        if (null !== $payload) {
            $packet->PLD = $payload;
        }
        $packet = json_encode($packet);

        return $this->encoded ? base64_encode($packet) : $packet;
    }

    /**
     * Decodes a packet and extracts the payload and time information.
     *
     * @param string $packet   the packet to decode
     * @param mixed  &$payload The variable to store the payload data. Defaults to null.
     * @param int    &$time    The variable to store the time data. Defaults to null.
     *
     * @return bool|string the type name of the decoded packet
     */
    public function decode(string $packet, mixed &$payload = null, int &$time = null): bool|string
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
        // This is a security thing to ensure that the client is connecting to the correct instance of Warlock
        if (!property_exists($packet, 'SID') || $packet->SID != $this->id) {
            return $this->error('Packet decode rejected due to bad SID');
        }
        if (property_exists($packet, 'PLD')) {
            $payload = $packet->PLD;
        }
        if (property_exists($packet, 'TME')) {
            $time = $packet->TME;
        }

        return $this->getTypeName($packet->TYP);
    }

    /**
     * Sets the last error message and returns false.
     *
     * @param string $msg The error message to be set.
     * @return bool Always returns false.
     */
    private function error(string $msg): bool
    {
        $this->last_error = $msg;

        return false;
    }
}
