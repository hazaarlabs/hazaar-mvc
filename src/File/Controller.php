<?php

namespace Hazaar\File;

use \Hazaar\Controller\Response\File;

class Controller extends \Hazaar\Controller\Basic {

    /**
     * Directly access a file stored in the Hazaar libs directory.
     *
     * This is used for accessing files in the libs directory, such as internal built-in JavaScript,
     * CSS, views and other files that are shipped as part of the core Hazaar MVC package.
     *
     * @param string $action
     * @throws Exception\InternalFileNotFound
     * @throws \Exception
     * @return \Hazaar\Controller\Response\File
     */
    public function __default($controller, $action) {

        $response = NULL;

        $file = $this->request->getRawPath();

        if($source = \Hazaar\Loader::getInstance()->getFilePath(FILE_PATH_SUPPORT, $file)) {

            $response = new File($source);

            $response->setUnmodified($this->request->getHeader('If-Modified-Since'));

        } else {

            throw new Exception\InternalFileNotFound($file);

        }

        return $response;

    }

}
