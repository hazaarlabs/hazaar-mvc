<?php

declare(strict_types=1);

namespace Hazaar\Controller\Exception;

class BrowserRootNotFound extends \Exception
{
    public function __construct()
    {
        parent::__construct('File browser root path is not found!', 404);
    }
}
