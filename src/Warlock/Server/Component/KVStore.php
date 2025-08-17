<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Server\Component;

use Hazaar\File\Dir;
use Hazaar\Util\BTree;
use Hazaar\Warlock\Enum\LogLevel;
use Hazaar\Warlock\Enum\PacketType;
use Hazaar\Warlock\Logger;
use Hazaar\Warlock\Server\Client;
use Hazaar\Warlock\Server\Main;

class KVStore
{
    private Main $server;
    private Logger $log;

    /**
     * Configuration for the KV Store component.
     *
     * @var array<string,mixed>
     */
    private array $config = [];

    /**
     * @var array<string, array<string,mixed>>
     */
    private array $kvStore = [];

    /**
     * @var array<string, array<int,array<string>>>
     */
    private array $kvExpire = [];
    private ?BTree $db = null;
    private int $compactTime = 0;
    private int $lastCompact = 0;

    /**
     * KVStore constructor.
     *
     * @param Main                $server the server instance
     * @param array<string,mixed> $config configuration for the KV Store component
     */
    public function __construct(Main $server, array $config = [])
    {
        $this->server = $server;
        $this->config = $config;
        $this->log = $server->getLogger('kvstore');
        $this->log->setLevel($this->config['logLevel'] ?? LogLevel::INFO);
    }

    public function enablePersistentStorage(int $compactTime = 3600): bool
    {
        $dbDir = new Dir($this->server->config['runtime']);
        if (!$dbDir->exists()) {
            if (!$dbDir->parent()->isWritable()) {
                return false;
            }
            $dbDir->create();
        }
        $dbFile = $dbDir->get('kvstore.db');
        $this->db = new BTree((string) $dbFile);
        if ($compactTime > 0) {
            $this->log->write('KV Store persistent storage compaction enabled', LogLevel::INFO);
            $this->compactTime = $compactTime;
            $this->lastCompact = $dbFile->ctime();
        }

        return true;
    }

    public function disablePersistentStorage(): void
    {
        $this->db = null;
        $this->compactTime = 0;
        $this->lastCompact = 0;
    }

    public function expireKeys(): void
    {
        $now = time();
        foreach ($this->kvExpire as $namespace => &$slots) {
            ksort($this->kvExpire[$namespace], SORT_NUMERIC);
            foreach ($slots as $time => &$keys) {
                if ($time > $now) {
                    break;
                }
                foreach ($keys as $key) {
                    $this->log->write('KVEXPIRE: '.$namespace.'::'.$key, LogLevel::DEBUG);
                    unset($this->kvStore[$namespace][$key]);
                }
                unset($this->kvExpire[$namespace][$time]);
            }
            if (0 === count($slots)) {
                unset($this->kvExpire[$namespace]);
            }
        }
        if (isset($this->db)
            && $this->compactTime > 0
            && $this->lastCompact + $this->compactTime <= ($now = time())) {
            $this->log->write('Compacting KV Persistent Storage', LogLevel::INFO);
            $this->db->compact();
            $this->lastCompact = $now;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function &touch(string $namespace, string $key): array
    {
        if (!(array_key_exists($namespace, $this->kvStore) && array_key_exists($key, $this->kvStore[$namespace]))) {
            if (isset($this->db) && ($slot = $this->db->get($key))
                && (!array_key_exists('e', $slot) || $slot['e'] > time())) {
                $this->kvStore[$namespace][$key] = &$slot;
            } else {
                $this->kvStore[$namespace][$key] = ['v' => null];

                return $this->kvStore[$namespace][$key];
            }
        } else {
            $slot = &$this->kvStore[$namespace][$key];
        }
        if (array_key_exists('e', $slot)
            && array_key_exists($namespace, $this->kvExpire)
            && array_key_exists($slot['e'], $this->kvExpire[$namespace])
            && ($index = array_search($key, $this->kvExpire[$namespace][$slot['e']])) !== false) {
            unset($this->kvExpire[$namespace][$slot['e']][$index]);
        }
        if (array_key_exists('t', $slot)) {
            $slot['e'] = time() + $slot['t'];
            $this->kvExpire[$namespace][$slot['e']][] = $key;
            if (isset($this->db)) {
                $this->db->set($key, $slot);
            }
        }

        return $slot;
    }

    public function process(Client $client, PacketType $command, mixed &$payload): void
    {
        if (!$payload) {
            throw new \InvalidArgumentException('Payload cannot be null for command: '.$command->name);
        }
        $namespace = (property_exists($payload, 'n') ? $payload->n : 'default');
        $this->log->write($command->name.': '.$namespace.(property_exists($payload, 'k') ? '::'.$payload->k : ''), LogLevel::DEBUG);

        switch ($command) {
            case PacketType::KVGET:
                $this->get($client, $payload, $namespace);

                break;

            case PacketType::KVSET:
                $this->set($client, $payload, $namespace);

                break;

            case PacketType::KVHAS:
                $this->has($client, $payload, $namespace);

                break;

            case PacketType::KVDEL:
                $this->del($client, $payload, $namespace);

                break;

            case PacketType::KVLIST:
                $this->list($client, $payload, $namespace);

                break;

            case PacketType::KVCLEAR:
                $this->clear($client, $payload, $namespace);

                break;

            case PacketType::KVPULL:
                $this->pull($client, $payload, $namespace);

                break;

            case PacketType::KVPUSH:
                $this->push($client, $payload, $namespace);

                break;

            case PacketType::KVPOP:
                $this->pop($client, $payload, $namespace);

                break;

            case PacketType::KVSHIFT:
                $this->shift($client, $payload, $namespace);

                break;

            case PacketType::KVUNSHIFT:
                $this->unshift($client, $payload, $namespace);

                break;

            case PacketType::KVCOUNT:
                $this->count($client, $payload, $namespace);

                break;

            case PacketType::KVINCR:
                $this->incr($client, $payload, $namespace);

                break;

            case PacketType::KVDECR:
                $this->decr($client, $payload, $namespace);

                break;

            case PacketType::KVKEYS:
                $this->keys($client, $payload, $namespace);

                break;

            case PacketType::KVVALS:
                $this->values($client, $payload, $namespace);

                break;
        }
    }

    public function get(Client $client, mixed $payload, string $namespace): void
    {
        $value = null;
        if (property_exists($payload, 'k')) {
            $slot = $this->touch($namespace, $payload->k);
            $value = $slot['v'];
        } else {
            $this->log->write('KVGET requires \'k\'', LogLevel::ERROR);
        }
        $client->send(PacketType::KVGET, $value);
    }

    public function set(Client $client, mixed $payload, string $namespace): void
    {
        $result = false;
        if (property_exists($payload, 'k')) {
            if (array_key_exists($namespace, $this->kvStore)
                && array_key_exists($payload->k, $this->kvStore[$namespace])
                && array_key_exists('e', $this->kvStore[$namespace][$payload->k])) {
                $e = $this->kvStore[$namespace][$payload->k]['e'];
                if (($key = array_search($payload->k, $this->kvExpire[$namespace][$e])) !== false) {
                    unset($this->kvExpire[$namespace][$e][$key]);
                }
            }
            $slot = ['v' => $payload->v ?? null];
            if (property_exists($payload, 't')) {
                $slot['t'] = $payload->t;
                $slot['e'] = time() + $payload->t;
                $this->kvExpire[$namespace][$slot['e']][] = $payload->k;
            }
            $this->kvStore[$namespace][$payload->k] = $slot;
            $result = true;
            if (isset($this->db)) {
                $this->db->set($payload->k, $slot);
            }
        } else {
            $this->log->write('KVSET requires \'k\'', LogLevel::ERROR);
        }
        $client->send(PacketType::KVSET, $result);
    }

    public function has(Client $client, \stdClass $payload, string $namespace): void
    {
        $result = false;
        if (property_exists($payload, 'k')) {
            if (!($result = (array_key_exists($namespace, $this->kvStore) && array_key_exists($payload->k, $this->kvStore[$namespace])))) {
                if (isset($this->db) && ($slot = $this->db->get($payload->k))
                    && (!array_key_exists('e', $slot) || $slot['e'] > time())) {
                    $result = true;
                    $this->kvStore[$namespace][$payload->k] = &$slot;
                }
            }
        } else {
            $this->log->write('KVHAS requires \'k\'', LogLevel::ERROR);
        }
        $client->send(PacketType::KVHAS, $result);
    }

    public function del(Client $client, \stdClass $payload, string $namespace): void
    {
        $result = false;
        if (property_exists($payload, 'k')) {
            $result = (array_key_exists($namespace, $this->kvStore) && array_key_exists($payload->k, $this->kvStore[$namespace]));
            if (true === $result) {
                unset($this->kvStore[$namespace][$payload->k]);
                if (isset($this->db)) {
                    $this->db->remove($payload->k);
                }
            }
        } else {
            $this->log->write('KVDEL requires \'k\'', LogLevel::ERROR);
        }
        $client->send(PacketType::KVDEL, $result);
    }

    public function list(Client $client, \stdClass $payload, string $namespace): void
    {
        $list = null;
        if (array_key_exists($namespace, $this->kvStore)) {
            $list = [];
            foreach ($this->kvStore[$namespace] as $key => $data) {
                $list[$key] = $data['v'];
            }
        }
        $client->send(PacketType::KVLIST, $list);
    }

    public function clear(Client $client, \stdClass $payload, string $namespace): void
    {
        $this->kvStore[$namespace] = [];
        $client->send(PacketType::KVCLEAR, true);
    }

    public function pull(Client $client, \stdClass $payload, string $namespace): void
    {
        $result = null;
        if (property_exists($payload, 'k')) {
            if (array_key_exists($namespace, $this->kvStore) && array_key_exists($payload->k, $this->kvStore[$namespace])) {
                $result = $this->kvStore[$namespace][$payload->k]['v'];
                unset($this->kvStore[$namespace][$payload->k]);
            }
        } else {
            $this->log->write('KVPULL requires \'k\'', LogLevel::ERROR);
        }
        $client->send(PacketType::KVPULL, $result);
    }

    public function push(Client $client, \stdClass $payload, string $namespace): void
    {
        $result = false;
        if (property_exists($payload, 'k')) {
            $slot = &$this->touch($namespace, $payload->k);
            if (is_array($slot['v']) && property_exists($payload, 'v')) {
                $result = array_push($slot['v'], $payload->v);
            }
        } else {
            $this->log->write('KVPUSH requires \'k\'', LogLevel::ERROR);
        }
        $client->send(PacketType::KVPUSH, $result);
    }

    public function pop(Client $client, \stdClass $payload, string $namespace): void
    {
        $result = null;
        if (property_exists($payload, 'k')) {
            $slot = &$this->touch($namespace, $payload->k);
            if (is_array($slot['v'])) {
                $result = array_pop($slot['v']);
            }
        } else {
            $this->log->write('KVPOP requires \'k\'', LogLevel::ERROR);
        }
        $client->send(PacketType::KVPOP, $result);
    }

    public function shift(Client $client, \stdClass $payload, string $namespace): void
    {
        $result = null;
        if (property_exists($payload, 'k')) {
            $slot = &$this->touch($namespace, $payload->k);
            if (is_array($slot['v'])) {
                $result = array_shift($slot['v']);
            }
        } else {
            $this->log->write('KVSHIFT requires \'k\'', LogLevel::ERROR);
        }
        $client->send(PacketType::KVSHIFT, $result);
    }

    public function unshift(Client $client, \stdClass $payload, string $namespace): void
    {
        $result = false;
        if (property_exists($payload, 'k')) {
            $slot = &$this->touch($namespace, $payload->k);
            if (is_array($slot['v']) && property_exists($payload, 'v')) {
                $result = array_unshift($slot['v'], $payload->v);
            }
        } else {
            $this->log->write('KVUNSHIFT requires \'k\'', LogLevel::ERROR);
        }
        $client->send(PacketType::KVUNSHIFT, $result);
    }

    public function count(Client $client, \stdClass $payload, string $namespace): void
    {
        $result = null;
        if (property_exists($payload, 'k')) {
            $slot = &$this->touch($namespace, $payload->k);
            if (is_array($slot['v'])) {
                $result = count($slot['v']);
            }
        } else {
            $this->log->write('KVCOUNT requires \'k\'', LogLevel::ERROR);
        }
        $client->send(PacketType::KVCOUNT, $result);
    }

    public function incr(Client $client, \stdClass $payload, string $namespace): void
    {
        $result = false;
        if (property_exists($payload, 'k')) {
            $slot = &$this->touch($namespace, $payload->k);
            if (!is_int($slot['v'])) {
                $slot['v'] = 0;
            }
            $result = ($slot['v'] += (property_exists($payload, 's') ? $payload->s : 1));
        } else {
            $this->log->write('KVINCR requires \'k\'', LogLevel::ERROR);
        }
        $client->send(PacketType::KVINCR, $result);
    }

    public function decr(Client $client, \stdClass $payload, string $namespace): void
    {
        $result = false;
        if (property_exists($payload, 'k')) {
            $slot = &$this->touch($namespace, $payload->k);
            if (!is_int($slot['v'])) {
                $slot['v'] = 0;
            }
            $result = ($slot['v'] -= (property_exists($payload, 's') ? $payload->s : 1));
        } else {
            $this->log->write('KVDECR requires \'k\'', LogLevel::ERROR);
        }
        $client->send(PacketType::KVDECR, $result);
    }

    public function keys(Client $client, \stdClass $payload, string $namespace): void
    {
        $result = null;
        if (array_key_exists($namespace, $this->kvStore)) {
            $result = array_keys($this->kvStore[$namespace]);
        }
        $client->send(PacketType::KVKEYS, $result);
    }

    public function values(Client $client, \stdClass $payload, string $namespace): void
    {
        $result = null;
        if (array_key_exists($namespace, $this->kvStore)) {
            $result = array_values($this->kvStore[$namespace]);
        }
        $client->send(PacketType::KVVALS, $result);
    }
}
