<?php
/**
 * @file        Controller/Style.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar\Controller;

/**
 * @brief       Basic controller class
 *
 * @detail      THis controller does basic stuff
 */
class Style extends \Hazaar\Controller {

    private $source;

    private $filename;

    public function __initialize(\Hazaar\Application\Request $request) {

        $this->filename = $this->application->loader->getFilePath(FILE_PATH_VIEW)
            . DIRECTORY_SEPARATOR . 'styles' . DIRECTORY_SEPARATOR . $request->getRawPath();

        $this->source = new \Hazaar\File($this->filename);

    }

    public function __run() {

        if (!$this->source->exists())
            throw new \Hazaar\Exception\FileNotFound($this->filename);

        $mime_type = $this->source->mime_content_type();

        if(substr($mime_type, 0, strpos($mime_type, '/')) === 'image'){

            $response = new Response\Image($this->source);

            if($response->setUnmodified($this->request->getHeader('If-Modified-Since')) === false){

                $w = $this->request->get('w');

                $h = $this->request->get('h');

                if($w || $h)
                    $response->resize($w, $h, boolify($this->request->get('crop', false)));

            }

        }else{

            $response = new Response\Style($this->source);

            $response->setUnmodified($this->request->getHeader('If-Modified-Since'));

        }

        return $response;

    }

}

