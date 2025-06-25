<?php

declare(strict_types=1);

namespace Hazaar\File\Interface;

interface Driver
{
    /**
     * @param array<mixed> $options
     */
    public function __construct(array $options = []);
}
