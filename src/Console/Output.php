<?php

declare(strict_types=1);

namespace Hazaar\Console;

use Hazaar\Console\Formatter\OutputFormatter;

class Output
{
    private OutputFormatter $formatter;

    public function __construct()
    {
        $this->formatter = new OutputFormatter();
    }

    public function write(string $message): void
    {
        echo $this->formatter->format($message);
    }
}
