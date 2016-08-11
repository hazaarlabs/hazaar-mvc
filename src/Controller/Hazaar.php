<?php

namespace Hazaar\Controller;

class Hazaar extends \Hazaar\Controller\Basic {

    public function init($request) {

    }

    public function __default($controller) {

        $response = NULL;

        if($file = $this->request->getRawPath()) {

            if($source = \Hazaar\Loader::getInstance()->getFilePath(FILE_PATH_SUPPORT, $file)) {

                $response = new Response\File($source);

                $response->setUnmodified($this->request->getHeader('If-Modified-Since'));

            } else {

                throw new Exception\InternalFileNotFound($file);

            }

        }else{

            throw new \Exception('Bad request', 400);

        }

        return $response;

    }

}
