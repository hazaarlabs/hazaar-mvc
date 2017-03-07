<?php

namespace Hazaar\File;

use \Hazaar\Controller\Response\File;

class Controller extends \Hazaar\Controller\Basic {

    private $path;

    /**
     * Sets the support path to use to search for files.
     *
     * If this is not set, then the application configured support paths are used.
     *
     * @param mixed $path
     */
    public function setPath($path){

        return $this->path = realpath($path);

    }

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

        if($this->path)
            $source = $this->path . DIRECTORY_SEPARATOR . $file;
        else
            $source = \Hazaar\Loader::getInstance()->getFilePath(FILE_PATH_SUPPORT, $file);

        if(!file_exists($source))
            throw new Exception\InternalFileNotFound($file);

        $response = new File($source);

        $response->setUnmodified($this->request->getHeader('If-Modified-Since'));

        return $response;

    }

}
