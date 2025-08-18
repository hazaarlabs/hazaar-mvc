<?php

namespace Hazaar\RateLimiter\Backend;

use Hazaar\Application\Runtime;
use Hazaar\RateLimiter\Backend;
use Hazaar\Util\BTree;

class File extends Backend
{
    private BTree $db;

    /**
     * @var array<string,array<mixed>>
     */
    private array $index = [];
    private ?int $created = null;
    private int $compactInterval = 3600;

    /**
     * File rate limiter constructor.
     *
     * @param array<mixed> $options the options for the file
     */
    public function __construct(array $options = [])
    {
        $this->db = new BTree(Runtime::getInstance()->getPath($options['file'] ?? 'rate_limiter.db'));
        if (!($this->created = $this->db->get('created'))) {
            $this->db->set('created', $this->created = time());
        }
        $this->compactInterval = $options['compactInterval'] ?? $this->compactInterval;
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
            $this->db->compact();
        }
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
