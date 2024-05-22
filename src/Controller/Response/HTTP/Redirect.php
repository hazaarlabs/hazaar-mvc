<?php

declare(strict_types=1);

namespace Hazaar\Controller\Response\HTTP;

use Hazaar\Controller\Response;

class Redirect extends Response
{
    public function __construct(string $url)
    {
        parent::__construct('text/text', 302);

        $this->setHeader('Location', $url);
    }
}
