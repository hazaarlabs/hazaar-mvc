<?php

declare(strict_types=1);

namespace Hazaar\DBI;

class LogEntry
{
    public float $timestamp;
    public string $message;

    public function __construct(string $message)
    {
        $this->timestamp = microtime(true);
        $this->message = $message;
    }

    public function __toString()
    {
        return date('Y-m-d H:i:s', intval($this->timestamp)).' - '.$this->message;
    }
}
