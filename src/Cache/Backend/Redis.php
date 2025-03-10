<?php

declare(strict_types=1);

/**
 * @file        Hazaar/Cache/Backend/Redis.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2016 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\Cache\Backend;

use Hazaar\Util\Arr;
use Hazaar\Cache\Backend;
use Hazaar\Socket\Client;

/**
 * @brief The Redis cache backend.
 *
 * @detail This is a nice, reliable caching backend that allows for clustering using the Redis clustering feature.
 *
 * Available config options:
 *
 * * server - The redis server to connect to.  Currently only a single server is supported. Default: localhost.
 * * port - The port to connect to the server on.  Default: 6379
 */
class Redis extends Backend
{
    protected int $weight = 2;

    /**
     * @var array<int,mixed>
     */
    private array $role;
    private ?Client $socket = null;        // The socket for the configured host.
    private ?Client $master = null;        // The socket for the master host if we are on a slave.
    private string $buffer = '';
    private int $offset = 0;
    private string $delim = "\r\n";

    public static function available(): bool
    {
        return function_exists('socket_create');
    }

    public function init(string $namespace): void
    {
        $this->addCapabilities('store_objects', 'array', 'expire_ns', 'expire_val', 'keepalive');
        $this->configure([
            'server' => 'localhost',
            'port' => 6379,
            'dbIndex' => 0,
            'keepalive' => false,
            'lifetime' => 0,
        ]);
        $this->socket = $this->connect($this->options['server'], $this->options['port']);
        if (isset($this->options['serverpass']) && ($serverpass = $this->options['serverpass'])) {
            $this->cmd(['AUTH', $serverpass]);
        }
        $cmds = [
            ['SELECT', (string) $this->options['dbIndex']],
            ['ROLE'],
        ];
        $result = $this->cmd($cmds);
        if ('OK' !== $result[0]) {
            throw new \Exception('Redis: Unable to select DB index '.$this->options['dbIndex']);
        }
        $this->role = $result[1];
    }

    /**
     * @param array<mixed> $cmds
     */
    public function cmd(array $cmds, bool $useMaster = false): mixed
    {
        if ($useMaster) {
            if (!$this->master instanceof Client) {
                // If we are on the master, just set the master to the current socket.
                if ('master' == $this->role[0]) {
                    $this->master = $this->socket;
                } elseif ('slave' == $this->role[0]) {
                    $this->master = $this->connect($this->role[1], (int) $this->role[2]);
                    if (isset($this->options['serverpass'])) {
                        $this->cmd(['AUTH', $this->options['serverpass']], true);
                    }
                    $index = (string) $this->options['dbIndex'];
                    $result = $this->cmd(['SELECT', $index], true);
                    if (!$result) {
                        throw new \Exception('Could not select DB '.$index.' on master');
                    }
                } else {
                    throw new \Exception("Redis cache backend does not support writing to hosts of role '{$this->role[0]}'");
                }
            }
            $socket = $this->master;
        } else {
            $socket = $this->socket;
        }
        if (!$socket->isConnected()) {
            return false;
        }
        $packets = $this->encode($cmds);
        $count = count($packets);
        if (!$socket->send(implode('', $packets)) > 0) {
            throw new Exception\RedisError("Error sending {$count} packets!");
        }
        $this->offset = 0;
        $this->buffer = '';
        $result = [];
        for ($i = 0; $i < $count; ++$i) {
            $result[] = $this->decode($socket);
        }
        if (1 == $count) {
            return $result[0];
        }

        return $result;
    }

    public function select(int $db): bool
    {
        return \Hazaar\Util\Boolean::from($this->cmd(['SELECT', "{$db}"]));
    }

    public function close(): bool
    {
        if ($this->socket) {
            return $this->socket->close();
        }

        return false;
    }

    public function has(string $key, bool $checkEmpty = false): bool
    {
        return 1 === $this->cmd(['HEXISTS', $this->namespace, $key]);
    }

    public function get(string $key): mixed
    {
        $keyName = $this->namespace.':'.$key;
        $cmds = [
            ['TTL', $keyName],
            ['HGETALL', $keyName],
        ];
        if ($this->options['keepalive'] && $this->options['lifetime'] > 0) {
            $cmds[] = ['EXPIRE', $keyName, (string) $this->options['lifetime']];
        }
        $results = $this->cmd($cmds);
        $rawValues = $results[1];
        if (!$rawValues) {
            return null;
        }

        return $this->reconstructValue($rawValues);
    }

    public function set(string $key, mixed $value, ?int $timeout = null): bool
    {
        $keyName = $this->namespace.':'.$key;
        $cmds = [
            ['EXISTS', $this->namespace],
            array_merge(['HSET', $keyName], $this->deconstructValue($value)),
        ];
        if (null === $timeout) {
            $timeout = $this->options['lifetime'];
        }
        if ($timeout > 0) {
            $cmds[] = ['EXPIRE', $keyName, (string) $timeout];
        }
        $result = $this->cmd($cmds, true);
        if ((is_string($value) && 1 !== $result[1])
            || (is_array($value) && count($value) !== $result[1])) {
            return false;
        }

        return true;
    }

    public function remove(string $key): bool
    {
        return \Hazaar\Util\Boolean::from($this->cmd(['HDEL', $this->namespace, $key], true));
    }

    public function clear(): bool
    {
        return \Hazaar\Util\Boolean::from($this->cmd(['DEL', $this->namespace], true));
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        $array = [];
        $keys = $this->cmd(['KEYS', $this->namespace.':*']);
        $cmds = [];
        foreach ($keys as $key) {
            $cmds[] = ['HGETALL', $key];
        }
        $items = $this->cmd($cmds);
        if (1 === count($items)) {
            $items = [$items];
        }
        foreach ($items as $index => $item) {
            [$namespace, $key] = explode(':', $keys[$index], 2);
            $array[$key] = $this->reconstructValue($item);
        }

        return $array;
    }

    public function count(): int
    {
        return (int) $this->cmd(['HLEN', $this->namespace]);
    }

    private function connect(string $host, int $port = 6379): Client
    {
        $socket = new Client();
        if (!$socket->connect($host, $port)) {
            throw new \Exception('Could not connect to Redis server: '.$host.':'.$port);
        }

        return $socket;
    }

    private function getChunk(Client $socket, ?int $bytes = null): string
    {
        if (null !== $bytes) {
            // Check there is enough data in the buffer to satisfy the request
            while (($this->offset + $bytes) > strlen($this->buffer)) {
                $this->buffer .= $socket->recv();
            }
            $chunk = substr($this->buffer, $this->offset, $bytes);
            $this->offset += ($bytes + 2);
        } else {
            while ($this->offset > strlen($this->buffer)) {
                $this->buffer .= $socket->recv();
            }
            // Keep receiving data from the socket if the current chunk is incomplete
            while (!($offset = strpos($this->buffer, $this->delim, $this->offset))) {
                $buf = $socket->recv();
                if (!$buf) {
                    throw new Exception\RedisError('Error reading from socket!');
                }
                $this->buffer .= $buf;
            }
            $chunk = substr($this->buffer, $this->offset, $offset - $this->offset);
            $this->offset = $offset + 2;
        }

        return $chunk;
    }

    /**
     * Decodes a RESP data chunk.
     */
    private function decode(Client $socket, ?string $chunk = null): mixed
    {
        if (null === $chunk) {
            $chunk = $this->getChunk($socket);
        }
        do {
            $prefix = $chunk[0];

            switch ($prefix) {
                case '-': // Error response
                    throw new Exception\RedisError($chunk);

                case '+': // Simple string response
                    return substr($chunk, 1);

                case ':': // Integer response
                    return (int) substr($chunk, 1);

                case '$': // Bulk string response
                    $size = (int) substr($chunk, 1);
                    if (-1 === $size) {
                        return null;
                    }

                    return $this->getChunk($socket, $size);

                case '*': // Array response
                    $count = (int) substr($chunk, 1);
                    if (-1 === $count) {
                        return null;
                    }
                    $array = [];
                    for ($i = 0; $i < $count; ++$i) {
                        $array[$i] = $this->decode($socket, $this->getChunk($socket));
                    }

                    return $array;
            }
        } while ($chunk = $this->getChunk($socket));

        return null;
    }

    /**
     * Encodes data into the RESP protocol.
     *
     * @param mixed $data The data to encode
     *
     * @return array<string> The encoded data
     */
    private function encode(mixed $data): array
    {
        if (!is_array($data)) {
            $data = [[$data]];
        } elseif (!is_array($data[0])) {
            $data = [$data];
        }
        $packets = [];
        foreach ($data as &$command) {
            $packet = '*'.count($command)."\r\n";
            foreach ($command as $payload) {
                $packet .= $this->encodeValue($payload);
            }
            $packets[] = $packet;
        }

        return $packets;
    }

    private function encodeValue(mixed $value): string
    {
        if (is_string($value)) {
            return '$'.strlen($value)."\r\n{$value}\r\n";
        }
        if (is_int($value)) {
            return ':'.$value."\r\n";
        }
        if (is_bool($value)) {
            return '#'.$value."\r\n";
        }
        if (is_array($value)) {
            $encoded = '';
            if (Arr::isAssoc($value)) {
                $encoded = '%'.count($value)."\r\n";
                foreach ($value as $key => $item) {
                    $encoded .= $this->encodeValue($key).$this->encodeValue($item);
                }
            } else {
                $encoded = '*'.count($value)."\r\n";
                foreach ($value as $item) {
                    $encoded .= $this->encodeValue($item);
                }
            }

            return $encoded;
        }

        throw new Exception\RedisError('Error setting unknown data type!');
    }

    /**
     * Deconstructs a value into an array of keys and values.
     *
     * @param mixed $value The value to deconstruct
     *
     * @return array<mixed> The deconstructed value
     */
    private function deconstructValue(mixed $value): array
    {
        if ($value instanceof \stdClass) {
            $value = (array) $value;
        }
        if (!is_array($value)) {
            return ['__value__', $value];
        }
        $values = [];
        foreach ($value as $key => $item) {
            $values[] = $key;
            $values[] = is_string($item) ? $item : serialize($item);
        }

        return $values;
    }

    /**
     * Reconstructs a value from an array of keys and values.
     *
     * @param array<mixed> $rawValues The raw values to reconstruct
     *
     * @return mixed The reconstructed value
     */
    private function reconstructValue(array $rawValues): mixed
    {
        $values = [];
        do {
            $key = current($rawValues);
            $rawValue = next($rawValues);
            if (!is_string($rawValue) || false === ($value = @unserialize($rawValue))) {
                $value = $rawValue;
            }
            if ('__value__' === $key) {
                return $value;
            }
            $values[$key] = $value;
        } while (next($rawValues));

        return $values;
    }
}
