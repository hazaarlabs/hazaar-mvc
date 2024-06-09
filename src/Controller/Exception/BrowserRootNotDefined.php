<?php

declare(strict_types=1);

namespace Hazaar\Controller\Exception;

class BrowserRootNotDefined extends \Exception
{
    public function __construct()
    {
        parent::__construct('The internal file browser root path is not defined in the application configuration.', 503);
    }
}
