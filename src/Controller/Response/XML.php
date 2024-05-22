<?php

declare(strict_types=1);

namespace Hazaar\Controller\Response;

use Hazaar\Controller\Response;

class XML extends Response
{
    private string $content = '';

    public function __construct(mixed $content = null, int $status = 200)
    {
        parent::__construct('text/xml', $status);
        $this->setContent($content);
    }

    public function setContent(mixed $content): void
    {
        $this->content = (string) $content;
    }

    public function getContent(): string
    {
        return $this->content;
    }
}
