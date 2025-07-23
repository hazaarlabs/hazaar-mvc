<?php

declare(strict_types=1);

namespace Hazaar\Controller\Response;

use Hazaar\Controller\Response;

class Text extends Response
{
    public function __construct(?string $content = null, int $status = 200)
    {
        parent::__construct('text/plain', $status);
        $this->setContent($content);
    }
}
