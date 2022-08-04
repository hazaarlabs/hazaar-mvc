<?php

namespace Hazaar\Controller\Helper;

class Response extends \Hazaar\Controller\Helper {

    public function file($file, $backend = null){

        return new \Hazaar\Controller\Response\File($file, $backend);

    }

    public function html($content, $status = 200){

        $html = new \Hazaar\Controller\Response\Html($status);

        $html->setContent($content);

        return $html;

    }

    public function image($filename, $quality = 8, $backend = null, $enable_image_cache = true){

        return new \Hazaar\Controller\Response\Image($filename, $quality, $backend, $enable_image_cache);

    }

    public function javascript($source){

        return new \Hazaar\Controller\Response\Javascript($source);

    }

    public function json($data = [], $status = 200){

        return new \Hazaar\Controller\Response\Json($data, $status);

    }

    public function PDF($file, $downloadable = true){

        return new \Hazaar\Controller\Response\PDF($file, $downloadable);

    }

    public function style($source){

        return new \Hazaar\Controller\Response\Style($source);

    }

    public function text($content, $status = 200){

        return new \Hazaar\Controller\Response\Text($content, $status);

    }

    public function view($name){

        return new \Hazaar\Controller\Response\View($name);

    }

    public function xml($content, $status = 200){

        return new \Hazaar\Controller\Response\Xml($content, $status);

    }

}