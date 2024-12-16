<?php

declare(strict_types=1);

namespace Hazaar\Controller\Response;

use Hazaar\Controller\Response;
use Hazaar\XML\Element;

class XML extends Response
{
    private ?Element $content = null;

    public function __construct(?Element $content = null, int $status = 200)
    {
        parent::__construct('text/xml', $status);
        $this->setContent($content);
    }

    public function setContent(mixed $content): void
    {
        if (!$content instanceof Element) {
            throw new \Exception('XML content must be an instance of Hazaar\XML\Element');
        }
        $this->content = $content;
    }

    public function getContent(): string
    {
        return $this->content->toXML();
    }
}
