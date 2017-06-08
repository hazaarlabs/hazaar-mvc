<?php

namespace Hazaar\File;

class GZFile extends \Hazaar\File {

    function __construct($file = null, $backend = NULL){

        parent::__construct($file, $backend);

        $this->set_mime_content_type('application/gzip');

    }

    public function open($mode = 'r'){

        if($this->handle)
            return $this->handle;

        $this->handle = gzopen($this->source_file , $mode);

    }

    public function close(){

        if(!$this->handle)
            return false;

        gzclose($this->handle);

        $this->handle = null;

    }

    /**
     * Returns a character from the file pointer
     *
     * @return string
     */
    public function getc(){

        if(!$this->handle)
            return null;

        return gzgetc($this->handle);


    }

    /**
     * Returns a line from the file pointer
     *
     * @return string
     */
    public function gets(){

        if(!$this->handle)
            return null;

        return gzgets($this->handle);

    }

    /**
     * Returns a line from the file pointer and strips HTML tags
     *
     * @return string
     */
    public function getss($allowable_tags = null){

        if(!$this->handle)
            return null;
        
        return strip_tags(gzgets($this->handle), $allowable_tags);

    }

}