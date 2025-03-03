<?php

declare(strict_types=1);

namespace Hazaar\File\Interface;

use Hazaar\File\Manager;

interface Driver
{
    /**
     * @param array<mixed> $options
     */
    public function __construct(array $options, Manager $manager);
}
