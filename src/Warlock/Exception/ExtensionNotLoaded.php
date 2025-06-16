<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Exception;

class ExtensionNotLoaded extends \Exception
{
    public function __construct(string $extension)
    {
        parent::__construct("The extension '{$extension}' is not loaded.");
    }
}
