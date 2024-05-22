<?php

declare(strict_types=1);

namespace Hazaar\File\Backend\Interfaces;

use Hazaar\File\Manager;
use Hazaar\Map;

interface Driver
{
    /**
     * @param array<mixed> $options
     */
    public function __construct(array|Map $options, Manager $manager);
}
