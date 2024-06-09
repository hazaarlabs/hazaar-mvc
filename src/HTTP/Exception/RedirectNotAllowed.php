<?php

declare(strict_types=1);

namespace Hazaar\HTTP\Exception;

use Hazaar\Exception;

class RedirectNotAllowed extends \Exception
{
    public function __construct(int $status)
    {
        parent::__construct('Received a '.$status.' redirection on a non-GET request.');
    }
}
