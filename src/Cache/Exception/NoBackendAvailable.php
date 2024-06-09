<?php

declare(strict_types=1);

namespace Hazaar\Cache\Exception;

class NoBackendAvailable extends \Exception
{
    public function __construct()
    {
        parent::__construct('None of the requested cache backends are currently available.');
    }
}
