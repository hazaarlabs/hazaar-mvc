<?php

declare(strict_types=1);

namespace Hazaar\Controller\Response;

use Hazaar\Controller\Response;

class HTML extends Response
{
    public function __construct(?string $content = null, int $status = 200)
    {
        parent::__construct('text/html', $status);
        if (null !== $content) {
            $this->setContent($content);
        }
    }
}
