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

    public function __initialize($request) {

        $action = $request->getActionName();

        if ($action == 'images') {

            $this->filename = 'images/' . $request->getPath();

        } else {

            $this->filename = $request->getRawPath();

        }

        $this->source = $this->application->loader->getFilePath(FILE_PATH_VIEW, 'styles/' . $this->filename);

    }

    public function __run() {

        if ($this->source) {

            $response = new Response\Style($this->source);

            $response->setUnmodified($this->request->getHeader('If-Modified-Since'));

        } else {

            throw new \Hazaar\Exception\FileNotFound($this->filename);

        }

        return $response;

    }

}

