<?php

namespace Hazaar\RateLimiter\Backend;

use Hazaar\Application;
use Hazaar\BTree;
use Hazaar\RateLimiter\Backend;

class File extends Backend
{
    private BTree $db;

    /**
     * @var array<string,array<mixed>>
     */
    private array $index = [];

    /**
     * File rate limiter constructor.
     *
     * @param array<mixed> $options the options for the file
     */
    public function __construct(array $options = [])
    {
        if (!($app = Application::getInstance())) {
            throw new \Exception('Application not initialized!');
        }
        $this->db = new BTree($app->getRuntimePath(ake($options, 'file', 'rate_limiter.db')));
    }

    public function __destruct()
    {
        if (0 === count($this->index)) {
            return;
        }
        foreach ($this->index as $identifier => $info) {
            $this->db->set($identifier, $info);
        }
        // $this->db->compact();
    }

    public function check(string $identifier): array
    {
        $info = $this->get($identifier);
        $info['log'][] = time();

        return $this->index[$identifier] = $info;
    }

    public function get(string $identifier): array
    {
        $info = $this->db->get($identifier);
        if (!$info) {
            $info = [];
        }
        $time = time();
        if (isset($info['log'])) {
            foreach ($info['log'] as $index => $timestamp) {
                if ($timestamp < $time - $this->windowLength) {
                    unset($info['log'][$index]);
                }
            }
        } else {
            $info['log'] = [];
        }

        return $this->index[$identifier] = $info;
    }

    public function remove(string $identifier): void
    {
        $this->db->remove($identifier);
    }
}
