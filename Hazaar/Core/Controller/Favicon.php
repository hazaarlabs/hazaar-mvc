<?php

namespace Hazaar\Controller;

class Favicon extends \Hazaar\Controller {

    public function __initialize($request) {

    }

    public function __run() {

        $out = new Response\Image();

        $out->setController($this);

        if($this->application->config->app->has('favicon'))
            $filename = \Hazaar\Loader::getFilePath(FILE_PATH_PUBLIC, $this->application->config->app->favicon);

        if(! isset($filename))
            $filename = \Hazaar\Loader::getFilePath(FILE_PATH_SUPPORT, 'favicon.png');

        $max_width = 16;

        $max_height = 16;

        $out->load($filename);

        if($out->width() > $max_width || $out->height() > $max_height)
            $out->resize($max_width, $max_height);

        return $out;

    }

}
