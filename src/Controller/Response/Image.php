<?php

namespace Hazaar\Controller\Response;

class Image extends File {

    private $cache_enabled = false;

    private $quality = 100;

    private $cache_dir = RUNTIME_PATH . DIRECTORY_SEPARATOR . 'response_image_cache';

    private $cache_file = null;

    private $cache = false;

    private $cache_key = [];

    function __construct($filename = NULL, $quality = NULL, $backend = NULL, $enable_image_cache = false) {

        parent::__construct($filename, $backend);

        $this->quality = $quality;

        if($this->file)
            $this->file->quality($this->quality);

        $this->cache_enabled = $enable_image_cache;

    }

    function __destruct(){

        if($this->cache === true && $this->cache_file !== null && $this->file){

            if(!file_exists($this->cache_dir))
                mkdir($this->cache_dir);

            file_put_contents($this->cache_file, $this->getContent());

        }

    }

    public function load($file, $backend = NULL) {

        if(! $file instanceof \Hazaar\File\Image)
            $file = new \Hazaar\File\Image($file, 100, $backend);

        return parent::load($file);

    }

    public function setContent($data, $content_type = NULL) {

        if(! $this->file)
            $this->file = new \Hazaar\File\Image(NULL);

        return parent::setContent($data, $content_type);

    }

    public function setFormat($format) {

        $this->file->set_mime_content_type('image/' . $format);

    }

    private function addToCacheKey($key){

        if(is_array($key))
            $this->cache_key += $key;
        else
            $this->cache_key[] = $key;


        return $this->cache_key;

    }

    private function getCacheKey(){

        return md5(serialize($this->cache_key));

    }

    public function width() {

        if(!$this->file)
            return null;

        return $this->file->width();

    }

    public function height() {

        if(!$this->file)
            return null;

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

    public function checkCacheFile($args){

        if($this->cache_enabled === false || !$this->file)
            return false;

        $this->cache_file = $this->cache_dir . DIRECTORY_SEPARATOR . md5($this->file->md5() . serialize($args));

        if(!file_exists($this->cache_file)){

            $this->cache = true;

            return false;

        }

        $this->file->set_contents(file_get_contents($this->cache_file));

        return true;

    }

}
