<?php
/**
 * @file        Controller/Script.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar\Controller;

/**
 * @brief       Basic controller class
 *
 * @detail      This controller does basic stuff
 */
class Script extends \Hazaar\Controller {

    private $source;

    private $filename;
    
    private $compress = false;

    public function __initialize($request) {

        $this->filename = $request->getRawPath();
        
        $this->source = $this->application->loader->getFilePath(FILE_PATH_VIEW, 'scripts/' . $this->filename);

        if($request->get('compress') == 'no') {

            $this->compress = false;

        } elseif($request->get('compress') == 'yes') {

            $this->compress = true;

        } else {

            $this->compress = $this->application->config->app['compress'];

        }

    }

    public function __run() {

        $out = '';

        if($this->source) {

            $out = new Response\Javascript();

            $out->load($this->source);

            $out->setCompression($this->compress);

        } else {

            throw new \Hazaar\Exception\FileNotFound($this->filename);

        }

        return $out;
    }

}
