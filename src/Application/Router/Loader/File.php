<?php

declare(strict_types=1);

namespace Hazaar\Application\Router\Loader;

use Hazaar\Application\Request;
use Hazaar\Application\Route;
use Hazaar\Application\Router;
use Hazaar\Application\Router\Loader;

class File extends Loader
{
    public function initialise(Router $router): bool
    {
        $filename = $this->config['file'] ?? 'route.php';
        $file = APPLICATION_PATH.DIRECTORY_SEPARATOR.$filename;
        if (false === file_exists($file)) {
            throw new Exception\MissingRouteFile($filename);
        }

        include_once $file;

        return true;
    }

    public function evaluateRequest(Request $request): ?Route
    {
        return null;
    }
}
