<?php

declare(strict_types=1);

namespace Hazaar\Auth\Adapter\Exception;

use Hazaar\Exception;

class UnknownStorageAdapter extends Exception
{
    public function __construct(string $adapter)
    {
        parent::__construct('Unknown storage adapter: '.$adapter);
    }
}
