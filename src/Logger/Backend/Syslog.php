<?php

declare(strict_types=1);

namespace Hazaar\Logger\Backend;

use Hazaar\Logger\Backend;

class Syslog extends Backend
{
    public function write(string $message, int $level = LOG_INFO, ?string $tag = null): void
    {
        syslog($level, ($tag ? $tag.': ' : '').$message);
    }

    public function trace(): void {}
}
