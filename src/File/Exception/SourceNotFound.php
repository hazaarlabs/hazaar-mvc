<?php

declare(strict_types=1);

namespace Hazaar\File\Exception;

use Hazaar\Exception;

class SourceNotFound extends Exception
{
    public function __construct(string $source, string $target)
    {
        parent::__construct("Source file '{$source}' does not exist while copying to '{$target}'.");
    }
}
