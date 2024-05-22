<?php

declare(strict_types=1);

namespace Hazaar\Logger\Backend;

use Hazaar\Logger\Backend;

class Syslog extends Backend
{
    public function write(string $tag, string $msg, int $level = LOG_NOTICE): void
    {
        syslog($level, $tag.': '.$msg);
    }

    public function trace(): void {}
}
