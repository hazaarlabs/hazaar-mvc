<?php

declare(strict_types=1);

namespace Hazaar\Controller\Response\HTTP;

use Hazaar\Controller\Response;

class NoContent extends Response
{
    public function __construct()
    {
        parent::__construct('text/plain', 204);
    }
}
