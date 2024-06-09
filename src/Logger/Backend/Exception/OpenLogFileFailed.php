<?php

declare(strict_types=1);

namespace Hazaar\Logger\Backend\Exception;

use Hazaar\Exception;

class OpenLogFileFailed extends \Exception
{
    public function __construct(string $file)
    {
        parent::__construct("Unable to open log file '{$file}'.");
    }
}
