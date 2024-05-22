<?php

declare(strict_types=1);

namespace Hazaar\Controller\Response\HTTP;

use Hazaar\Controller\Response;

class OK extends Response
{
    public function __construct(string $content_type = 'text/html')
    {
        parent::__construct($content_type, 200);
    }
}
