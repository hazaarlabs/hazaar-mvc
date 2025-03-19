<?php

declare(strict_types=1);

namespace Hazaar\Controller\Response\HTTP;

use Hazaar\Controller\Response;

class BadRequest extends Response
{
    public function __construct(string $contentType = 'text/html')
    {
        parent::__construct($contentType, 400);
    }
}
