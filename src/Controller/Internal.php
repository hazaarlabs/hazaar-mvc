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
}
