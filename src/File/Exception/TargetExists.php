<?php

declare(strict_types=1);

namespace Hazaar\File\Exception;

use Hazaar\Exception;

class TargetExists extends \Exception
{
    public function __construct(string $target)
    {
        parent::__construct("Destination file already exists at '{$target}'");
    }
}
