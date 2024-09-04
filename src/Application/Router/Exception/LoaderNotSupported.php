<?php

declare(strict_types=1);

namespace Hazaar\Application\Router\Exception;

class LoaderNotSupported extends \Exception
{
    public function __construct(string $loader)
    {
        parent::__construct('The configured route loader could not be found: '.$loader);
    }
}
