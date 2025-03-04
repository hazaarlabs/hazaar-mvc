<?php

declare(strict_types=1);

namespace Hazaar\Cache;

class Benchmark
{
    /**
     * @var array<string>
     */
    private array $backends;

    /**
     * @var array<mixed>
     */
    private array $configs;

    /**
     * @param array<string>|string $backends
     * @param array<mixed>         $config
     */
    public function __construct(array|string $backends = [], array $config = [])
    {
        if ($backends && !is_array($backends)) {
            $backends = [$backends];
        }
        if (0 == count($backends)) {
            $backends = $this->getAvailableBackends();
        }
        $this->backends = $backends;
        $this->configs = $config;
    }

    /**
     * @return array<string>
     */
    public static function getAvailableBackends(): array
    {
        $all = ['apc', 'database', 'file', 'memcached', 'redis', 'session', 'shm', 'sqlite3'];
        $available = [];
        foreach ($all as $backend) {
            $class = 'Hazaar\Cache\Backend\\'.ucfirst($backend);
            if ($class::available()) {
                $available[] = $backend;
            }
        }

        return $available;
    }

    /**
     * @return array<string, mixed>
     */
    public function run(int $start = 2, int $end = 2048): array
    {
        $results = [];
        foreach ($this->backends as $backend) {
            try {
                $tests = [];
                $cache = new Adapter($backend, $this->configs[$backend] ?? []);
                for ($i = $start; $i <= $end; $i = $i * 2) {
                    $w_bytes = str_repeat('.', $i);
                    // Test write speed
                    $s = microtime(true);
                    $cache->set('test_value', $w_bytes);
                    $tests[$i]['w'] = round((microtime(true) - $s) * 1000, 4);
                    // Test read speed
                    $s = microtime(true);
                    $r_bytes = $cache->get('test_value');
                    $tests[$i]['r'] = round((microtime(true) - $s) * 1000, 4);
                    $tests[$i]['valid'] = ($r_bytes === $w_bytes);
                }
                $results[$backend] = $tests;
            } catch (\Exception $e) {
                continue;
            }
        }

        return $results;
    }
}
