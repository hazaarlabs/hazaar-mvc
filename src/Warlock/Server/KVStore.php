<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Server;

use Hazaar\File;
use Hazaar\File\BTree;

class KVStore
{
    private Logger $log;

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $kvStore = [];

    /**
     * @var array<string, array<int, array<string>>>
     */
    private array $kvExpire = [];
    private ?BTree $db = null;
    private int $compactTime = 0;
    private int $lastCompact = 0;

    public function __construct(Logger $log, bool $persistent = false, ?int $compactTime = null)
    {
        $this->log = $log;
        if (true === $persistent) {
            $db_file = new File(Master::$instance->getRuntimePath('kvstore.db'));
            $this->db = new BTree($db_file);
            if ($compactTime > 0) {
                $this->log->write(W_NOTICE, 'KV Store persistent storage compaction enabled');
                $this->compactTime = $compactTime;
                $this->lastCompact = $db_file->ctime();
            }
        }
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
                    $this->log->write(W_DEBUG, 'KVEXPIRE: '.$namespace.'::'.$key);
                    unset($this->kvStore[$namespace][$key]);
                }
                unset($this->kvExpire[$namespace][$time]);
            }
            if (0 === count($slots)) {
                unset($this->kvExpire[$namespace]);
            }
        }
        if (null !== $this->db
            && $this->compactTime > 0
            && $this->lastCompact + $this->compactTime <= ($now = time())) {
            $this->log->write(W_INFO, 'Compacting KV Persistent Storage');
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
            if (($slot = $this->db->get($key))
                && (array_key_exists('e', $slot) && $slot['e'] > time())) {
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
            $this->db->set($key, $slot);
        }

        return $slot;
    }

    public function process(Client $client, string $command, mixed &$payload): ?bool
    {
        if (!$payload) {
            return false;
        }
        $namespace = (property_exists($payload, 'n') ? $payload->n : 'default');
        $this->log->write(W_DEBUG, $command.': '.$namespace.(property_exists($payload, 'k') ? '::'.$payload->k : ''));

        switch ($command) {
            case 'KVGET':
                return $this->get($client, $payload, $namespace);

            case 'KVSET':
                return $this->set($client, $payload, $namespace);

            case 'KVHAS':
                return $this->has($client, $payload, $namespace);

            case 'KVDEL':
                return $this->del($client, $payload, $namespace);

            case 'KVLIST':
                return $this->list($client, $payload, $namespace);

            case 'KVCLEAR':
                return $this->clear($client, $payload, $namespace);

            case 'KVPULL':
                return $this->pull($client, $payload, $namespace);

            case 'KVPUSH':
                return $this->push($client, $payload, $namespace);

            case 'KVPOP':
                return $this->pop($client, $payload, $namespace);

            case 'KVSHIFT':
                return $this->shift($client, $payload, $namespace);

            case 'KVUNSHIFT':
                return $this->unshift($client, $payload, $namespace);

            case 'KVCOUNT':
                return $this->count($client, $payload, $namespace);

            case 'KVINCR':
                return $this->incr($client, $payload, $namespace);

            case 'KVDECR':
                return $this->decr($client, $payload, $namespace);

            case 'KVKEYS':
                return $this->keys($client, $payload, $namespace);

            case 'KVVALS':
                return $this->values($client, $payload, $namespace);
        }

        return null;
    }

    public function get(Client $client, mixed $payload, string $namespace): bool
    {
        $value = null;
        if (property_exists($payload, 'k')) {
            $slot = $this->touch($namespace, $payload->k);
            $value = $slot['v'];
        } else {
            $this->log->write(W_ERR, 'KVGET requires \'k\'');
        }

        return $client->send('KVGET', $value);
    }

    public function set(Client $client, mixed $payload, string $namespace): bool
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
            $slot = ['v' => ake($payload, 'v')];
            if (property_exists($payload, 't')) {
                $slot['t'] = $payload->t;
                $slot['e'] = time() + $payload->t;
                $this->kvExpire[$namespace][$slot['e']][] = $payload->k;
            }
            $this->kvStore[$namespace][$payload->k] = $slot;
            $result = true;
            $this->db->set($payload->k, $slot);
        } else {
            $this->log->write(W_ERR, 'KVSET requires \'k\'');
        }

        return $client->send('KVSET', $result);
    }

    public function has(Client $client, \stdClass $payload, string $namespace): bool
    {
        $result = false;
        if (property_exists($payload, 'k')) {
            if (!($result = (array_key_exists($namespace, $this->kvStore) && array_key_exists($payload->k, $this->kvStore[$namespace])))) {
                if (($slot = $this->db->get($payload->k))
                    && (!array_key_exists('e', $slot) || $slot['e'] > time())) {
                    $result = true;
                    $this->kvStore[$namespace][$payload->k] = &$slot;
                }
            }
        } else {
            $this->log->write(W_ERR, 'KVHAS requires \'k\'');
        }
        $client->send('KVHAS', $result);

        return true;
    }

    public function del(Client $client, \stdClass $payload, string $namespace): bool
    {
        $result = false;
        if (property_exists($payload, 'k')) {
            $result = (array_key_exists($namespace, $this->kvStore) && array_key_exists($payload->k, $this->kvStore[$namespace]));
            if (true === $result) {
                unset($this->kvStore[$namespace][$payload->k]);
                $this->db->remove($payload->k);
            }
        } else {
            $this->log->write(W_ERR, 'KVDEL requires \'k\'');
        }

        return $client->send('KVDEL', $result);
    }

    public function list(Client $client, \stdClass $payload, string $namespace): bool
    {
        $list = null;
        if (array_key_exists($namespace, $this->kvStore)) {
            $list = [];
            foreach ($this->kvStore[$namespace] as $key => $data) {
                $list[$key] = $data['v'];
            }
        }

        return $client->send('KVLIST', $list);
    }

    public function clear(Client $client, \stdClass $payload, string $namespace): bool
    {
        $this->kvStore[$namespace] = [];

        return $client->send('KVCLEAR', true);
    }

    public function pull(Client $client, \stdClass $payload, string $namespace): bool
    {
        $result = null;
        if (property_exists($payload, 'k')) {
            if (array_key_exists($namespace, $this->kvStore) && array_key_exists($payload->k, $this->kvStore[$namespace])) {
                $result = $this->kvStore[$namespace][$payload->k]['v'];
                unset($this->kvStore[$namespace][$payload->k]);
            }
        } else {
            $this->log->write(W_ERR, 'KVPULL requires \'k\'');
        }

        return $client->send('KVPULL', $result);
    }

    public function push(Client $client, \stdClass $payload, string $namespace): bool
    {
        $result = false;
        if (property_exists($payload, 'k')) {
            $slot = &$this->touch($namespace, $payload->k);
            if (is_array($slot['v']) && property_exists($payload, 'v')) {
                $result = array_push($slot['v'], $payload->v);
            }
        } else {
            $this->log->write(W_ERR, 'KVPUSH requires \'k\'');
        }

        return $client->send('KVPUSH', $result);
    }

    public function pop(Client $client, \stdClass $payload, string $namespace): bool
    {
        $result = null;
        if (property_exists($payload, 'k')) {
            $slot = &$this->touch($namespace, $payload->k);
            if (is_array($slot['v'])) {
                $result = array_pop($slot['v']);
            }
        } else {
            $this->log->write(W_ERR, 'KVPOP requires \'k\'');
        }

        return $client->send('KVPOP', $result);
    }

    public function shift(Client $client, \stdClass $payload, string $namespace): bool
    {
        $result = null;
        if (property_exists($payload, 'k')) {
            $slot = &$this->touch($namespace, $payload->k);
            if (is_array($slot['v'])) {
                $result = array_shift($slot['v']);
            }
        } else {
            $this->log->write(W_ERR, 'KVSHIFT requires \'k\'');
        }

        return $client->send('KVSHIFT', $result);
    }

    public function unshift(Client $client, \stdClass $payload, string $namespace): bool
    {
        $result = false;
        if (property_exists($payload, 'k')) {
            $slot = &$this->touch($namespace, $payload->k);
            if (is_array($slot['v']) && property_exists($payload, 'v')) {
                $result = array_unshift($slot['v'], $payload->v);
            }
        } else {
            $this->log->write(W_ERR, 'KVUNSHIFT requires \'k\'');
        }

        return $client->send('KVUNSHIFT', $result);
    }

    public function count(Client $client, \stdClass $payload, string $namespace): bool
    {
        $result = null;
        if (property_exists($payload, 'k')) {
            $slot = &$this->touch($namespace, $payload->k);
            if (is_array($slot['v'])) {
                $result = count($slot['v']);
            }
        } else {
            $this->log->write(W_ERR, 'KVCOUNT requires \'k\'');
        }

        return $client->send('KVCOUNT', $result);
    }

    public function incr(Client $client, \stdClass $payload, string $namespace): bool
    {
        $result = false;
        if (property_exists($payload, 'k')) {
            $slot = &$this->touch($namespace, $payload->k);
            if (!is_int($slot['v'])) {
                $slot['v'] = 0;
            }
            $result = ($slot['v'] += (property_exists($payload, 's') ? $payload->s : 1));
        } else {
            $this->log->write(W_ERR, 'KVINCR requires \'k\'');
        }

        return $client->send('KVINCR', $result);
    }

    public function decr(Client $client, \stdClass $payload, string $namespace): bool
    {
        $result = false;
        if (property_exists($payload, 'k')) {
            $slot = &$this->touch($namespace, $payload->k);
            if (!is_int($slot['v'])) {
                $slot['v'] = 0;
            }
            $result = ($slot['v'] -= (property_exists($payload, 's') ? $payload->s : 1));
        } else {
            $this->log->write(W_ERR, 'KVDECR requires \'k\'');
        }

        return $client->send('KVDECR', $result);
    }

    public function keys(Client $client, \stdClass $payload, string $namespace): bool
    {
        $result = null;
        if (array_key_exists($namespace, $this->kvStore)) {
            $result = array_keys($this->kvStore[$namespace]);
        }

        return $client->send('KVKEYS', $result);
    }

    public function values(Client $client, \stdClass $payload, string $namespace): bool
    {
        $result = null;
        if (array_key_exists($namespace, $this->kvStore)) {
            $result = array_values($this->kvStore[$namespace]);
        }

        return $client->send('KVVALS', $result);
    }
}
