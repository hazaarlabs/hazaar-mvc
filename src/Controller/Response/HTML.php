<?php

declare(strict_types=1);

namespace Hazaar\Controller\Response;

use Hazaar\Controller\Response;

class HTML extends Response
{
    private string $content = '';

    public function __construct(?string $content = null, int $status = 200)
    {
        parent::__construct('text/html', $status);
        if (null !== $content) {
            $this->setContent($content);
        }
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
