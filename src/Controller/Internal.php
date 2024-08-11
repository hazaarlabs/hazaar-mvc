<?php

declare(strict_types=1);

namespace Hazaar\Controller;

use Hazaar\Application\Request;
use Hazaar\Controller;
use Hazaar\Controller\Response\File;

class Internal extends Controller
{
    public function __initialize(Request $request): ?Response
    {
        return parent::__initialize($request);
    }

    public function __run(): false|Response
    {
        $filename = $this->request->getPath();
        $file = $this->router->application->loader->getFilePath(FILE_PATH_SUPPORT, $filename);
        if (null === $file) {
            throw new \Exception("Hazaar support file '{$filename}' not found!", 404);
        }

        return new File($file);
    }

    public function __runAction(string $actionName, array $actionArgs = [], bool $namedActionArgs = false): false|Response
    {
        if (!($offset = strpos($actionName, '/'))) {
            return false;
        }
        $route = substr($actionName, 0, $offset);
        $internalClassName = '\\Hazaar\\Controller\\Internal\\'.ucfirst($route);
        if (!class_exists($internalClassName)) {
            return false;
        }
        $controller = new $internalClassName($this->router, $route);
        $response = $controller->__runAction(substr($actionName, $offset + 1), $actionArgs, $namedActionArgs);
        if (!$response) {
            throw new \Exception("Internal controller action '{$actionName}' not found!", 404);
        }

        return $response;
    }
}
