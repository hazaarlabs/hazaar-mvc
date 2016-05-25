<?php

namespace Hazaar\Controller\Response;

class Image extends File {

    private $quality = 100;

    function __construct($filename = NULL, $quality = NULL, $backend = NULL) {

        parent::__construct($filename, $backend);

        $this->quality = $quality;
        
        if($this->file)
            $this->file->quality($this->quality);

    }

    public function load($file, $backend = NULL) {

        if(! $file instanceof \Hazaar\File\Image)
            $file = new \Hazaar\File\Image($file, 100, $backend);

        return parent::load($file);

    }

    public function setContent($data) {

        if(! $this->file)
            $this->file = new \Hazaar\File\Image(NULL);

        return parent::setContent($data);

    }

    public function setFormat($format) {

        $this->file->set_mime_content_type('image/' . $format);

    }

    public function width() {

        return $this->file->width();

    }

    public function height() {

        return $this->file->height();

    }

    public function quality($quality = NULL) {

        return $this->file->quality($quality);

    }

    public function encodeDataStream() {

        if($content = $this->getContent()) {

            $this->setContent('data:' . $this->getContentType() . ';base64,' . base64_encode($content));

            $this->setContentType('text/css');

        }

    }

    public function resize($width = NULL, $height = NULL, $crop = FALSE, $align = NULL, $keep_aspect = TRUE, $reduce_only = TRUE, $ratio = NULL, $offsetTop = 0, $offsetLeft = 0) {

        return $this->file->resize($width, $height, $crop, $align, $keep_aspect, $reduce_only, $ratio, $offsetTop, $offsetLeft);

    }

    public function expand($width = NULL, $height = NULL, $align = 'topleft', $offsettop = 0, $offsetleft = 0) {

        return $this->file->expand($width, $height, $align, $offsettop, $offsetleft);

    }

    public function filter($filters) {

        return $this->file->filter($filters);

    }

}
