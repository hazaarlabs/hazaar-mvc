<?php

declare(strict_types=1);

namespace Hazaar\Logger\Backend;

use Hazaar\Logger\Backend;

class Memory extends Backend
{
    /**
     * The log entries.
     *
     * @var array<array{level: int, message: string}>
     */
    private array $log = [];

    public function __construct($options = [])
    {
        parent::__construct($options);
    }

    public function write(string $message, int $level = LOG_INFO, ?string $tag = null): void
    {
        $this->log[] = [
            'level' => $level,
            'message' => $message,
            'tag' => $tag,
        ];
    }

    /**
     * Read the log.
     *
     * @param int $level The log level to read.  If null, all log entries are returned.
     *
     * @return array<array{level: int, message: string}> The log entries
     */
    public function read(?int $level = null): array
    {
        if (null === $level) {
            return $this->log;
        }

        $log = [];

        foreach ($this->log as $entry) {
            if ($entry['level'] >= $level) {
                $log[] = $entry;
            }
        }

        return $log;
    }

    public function clear(): void
    {
        $this->log = [];
    }

    public function trace(): void {}
}
