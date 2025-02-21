<?php

declare(strict_types=1);

namespace Hazaar\Template\Exception;

class IncludeFileNotFound extends \Exception
{
    public function __construct(string $file)
    {
        parent::__construct("Include file not found: {$file}");
    }
}
