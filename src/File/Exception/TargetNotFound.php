<?php

declare(strict_types=1);

namespace Hazaar\File\Exception;

use Hazaar\Exception;

class TargetNotFound extends \Exception
{
    public function __construct(string $target, string $source)
    {
        parent::__construct("Destination '{$target}' does not exist while trying to copy '{$source}'.");
    }
}
