<?php

declare(strict_types=1);

namespace Hazaar\Mail;

use Hazaar\Map;

abstract class Transport implements Interfaces\Transport
{
    protected Map $options;

    final public function __construct(Map $options)
    {
        $this->options = $options;
        $this->init($options);
    }

    protected function init(Map $options): bool
    {
        return true;
    }
}
