<?php

declare(strict_types=1);

namespace Hazaar\Controller\Response;

use Hazaar\Controller\Response;
use Hazaar\XML\Element;

class XML extends Response
{
    private ?Element $xmlContent = null;

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
        $this->xmlContent = $content;
    }

    public function getContent(): string
    {
        return $this->xmlContent->toXML();
    }
}
