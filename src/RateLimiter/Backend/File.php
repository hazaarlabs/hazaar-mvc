<?php

namespace Hazaar\RateLimiter\Backend;

use Hazaar\Application;
use Hazaar\Btree;
use Hazaar\RateLimiter\Backend;

class File extends Backend
{
    private Btree $db;

    /**
     * @var array<string,array<mixed>>
     */
    private array $index = [];
    private ?int $created = null;
    private int $compactInterval = 30;

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
        $this->db = new Btree($app->runtimePath(ake($options, 'file', 'rate_limiter.db')));
        if (!($this->created = $this->db->get('created'))) {
            $this->db->set('created', $this->created = time());
        }
        $this->compactInterval = ake($options, 'compactInterval', $this->compactInterval);
    }

    public function shutdown(): void
    {
        if (0 === count($this->index)) {
            return;
        }
        foreach ($this->index as $identifier => $info) {
            $this->db->set($identifier, $info);
        }
        if (null !== $this->created && $this->created < (time() - $this->compactInterval)) {
            // Remove any entries that are older than the window length
            foreach ($this->db->toArray() as $identifier => $info) {
                if (is_array($info) 
                    && array_key_exists('last', $info)
                    && $info['last'] < time() - ($info['window'] ?? $this->windowLength)) {
                    $this->db->remove($identifier);
                }
            }
            $this->db->set('created', $this->created = time());
            $this->db->compact();
        }
    }

    public function get(string $identifier): array
    {
        $info = array_key_exists($identifier, $this->index) ? $this->index[$identifier] : $this->db->get($identifier);
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

        return $info;
    }

    public function set(string $identifier, array $info): void
    {
        $current = $this->get($identifier);
        $diff = array_diff_assoc_recursive($info, $current);
        if (0 === count($diff)) {
            return;
        }
        $info['window'] = $this->windowLength;
        $this->index[$identifier] = $info;
    }

    public function remove(string $identifier): void
    {
        $this->db->remove($identifier);
    }
}
