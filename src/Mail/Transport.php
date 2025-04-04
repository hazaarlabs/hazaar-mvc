<?php

declare(strict_types=1);

namespace Hazaar\Mail;

use Hazaar\Mail\Interface\Transport as TransportInterface;

abstract class Transport implements TransportInterface
{
    /**
     * @var array<mixed>
     */
    protected array $options;

    /**
     * @param array<mixed> $options
     */
    final public function __construct(array $options)
    {
        $this->options = $options;
        $this->init($options);
    }

    /**
     * @param array<mixed> $options
     */
    protected function init(array $options): bool
    {
        return true;
    }
}
