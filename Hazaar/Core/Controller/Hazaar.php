<?php

namespace Hazaar\Controller;

class Hazaar extends \Hazaar\Controller\Basic {

    public function init($request) {

    }

    public function __default($controller) {

        $response = NULL;

        if($file = $this->request->getRawPath()) {

            if($source = \Hazaar\Loader::getFilePath(FILE_PATH_SUPPORT, $file)) {

                $response = new Response\File();

                $response->load($source);

            } else {

                throw new Exception\InternalFileNotFound($file);

            }

        }

        return $response;

    }

}
