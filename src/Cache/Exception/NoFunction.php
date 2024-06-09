<?php

declare(strict_types=1);

namespace Hazaar\Cache\Exception;

class NoFunction extends \Exception
{
    public function __construct()
    {
        parent::__construct('Cached function call attempted without specifying a function to call!');
    }
}
