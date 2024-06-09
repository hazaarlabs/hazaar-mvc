<?php

declare(strict_types=1);

/**
 * @file        Hazaar/Cache/Backend/Redis.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2016 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\Cache\Backend;

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
    private bool $updateExpire = false;

    /**
     * @var array<mixed>
     */
    private array $local = [];

    /**
     * @var array<string>
     */
    private array $garbage = []; // This keeps a list of keys that should be deleted on close because they have expired.

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
            'keeplocalcopy' => true,
        ]);
        $this->socket = $this->connect($this->options['server'], $this->options['port']);
        if ($this->options->has('serverpass') && ($serverpass = $this->options['serverpass'])) {
            $this->cmd(['AUTH', $serverpass]);
        }
        $cmds = [
            ['SELECT', (string) $this->options['dbIndex']],
            ['ROLE'],
            ['TTL', $this->namespace],
        ];
        $result = $this->cmd($cmds);
        if ('OK' !== $result[0]) {
            throw new \Exception('Redis: Unable to select DB index '.$this->options['dbIndex']);
        }
        $this->role = $result[1];
        // Check that there is a TTL set when there is supposed to be.  Redis will return -1 for no TTL (-2 means keys doesn't exist).
        if ($this->options['lifetime'] > 0 && -1 == $result[2]) {
            $this->updateExpire = true;
        }
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
                    if ($this->options->has('serverpass')) {
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
        return boolify($this->cmd(['SELECT', "{$db}"]));
    }

    public function close(): bool
    {
        if ($this->socket) {
            if (true === $this->updateExpire) {
                $this->cmd(['EXPIRE', $this->namespace, (string) $this->options['lifetime']]);
            }

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
        // This value is due to be deleted so just return null now.
        if (in_array($key, $this->garbage)) {
            return null;
        }
        $this->keepalive();
        if (!array_key_exists($key, $this->local)) {
            $serial = $this->cmd(['HGET', $this->namespace, $key]);
            if (!($serial && is_string($serial) && ($data = unserialize($serial)))) {
                return null;
            }
            if (array_key_exists('expire', $data) && time() > $data['expire']) {
                $this->garbage[] = $key;

                return null;
            }
            if (!$this->options['keeplocalcopy']) {
                return $data['value'];
            }
            $this->local[$key] = $data['value'];
        }

        return $this->local[$key];
    }

    public function set(string $key, mixed $value, int $timeout = 0): bool
    {
        // If this has expired and is being bined, recycle the garbage (see what I did there?).
        if (($gkey = array_search($key, $this->garbage)) !== false) {
            unset($this->garbage[$gkey]);
        }
        if ($this->options['keeplocalcopy']) {
            $this->local[$key] = $value;
        }
        $data = [
            'value' => $value,
        ];
        if ($timeout > 0) {
            $data['expire'] = time() + $timeout;
        }
        // Pipelining!
        $cmds = [
            ['EXISTS', $this->namespace],
            ['HSET', $this->namespace, $key, serialize($data)],
        ];
        $result = $this->cmd($cmds, true);
        $this->updateExpire = ($this->options['lifetime'] > 0 && !boolify($result[0]));
        if (!(0 === $result[1] || 1 === $result[1])) {
            return false;
        }
        $this->keepalive();

        return true;
    }

    public function remove(string $key): bool
    {
        if (array_key_exists($key, $this->local)) {
            unset($this->local[$key]);
        }

        return boolify($this->cmd(['HDEL', $this->namespace, $key], true));
    }

    public function clear(): bool
    {
        $this->local = [];

        return boolify($this->cmd(['DEL', $this->namespace], true));
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        $array = [];
        $items = $this->cmd(['HGETALL', $this->namespace]);
        for ($i = 0; $i < count($items); $i += 2) {
            if (!($data = unserialize($items[$i + 1]))) {
                continue;
            }
            if (array_key_exists('expire', $data) && time() > $data['expire']) {
                continue;
            }
            $array[$items[$i]] = $data['value'];
        }
        $this->local = $array;

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
            // Keep receiving data from the socket if the current chunk is incomplete
            while (!($offset = strpos($this->buffer, $this->delim, $this->offset))) {
                $this->buffer .= $socket->recv();
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
                if (is_array($payload)) {
                    throw new Exception\RedisError('Storing arrays is not supported by the RESP protocol');
                }
                if (is_string($payload)) {
                    $packet .= '$'.strlen($payload)."\r\n{$payload}\r\n";
                } elseif (is_int($payload)) {
                    $packet .= ':'.$payload."\r\n";
                } else {
                    throw new Exception\RedisError('Error setting unknown data type!');
                }
            }
            $packets[] = $packet;
        }

        return $packets;
    }

    private function keepalive(): void
    {
        if (true === $this->options['keepalive'] && $this->options['lifetime'] > 0) {
            $this->updateExpire = true;
        }
    }
}
