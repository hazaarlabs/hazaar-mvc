<?php

namespace Hazaar\Controller\Response;

class File extends \Hazaar\Controller\Response\HTTP\OK {

    protected $file;

    protected $backend;

    /**
     * \Hazaar\File Constructor
     *
     * @param mixed $file Either a string filename to use or a \Hazaar\File object.
     *
     * @throws \Hazaar\Exception\FileNotFound
     */
    function __construct($file = NULL, $backend = NULL) {

        $this->backend = $backend;

        parent::__construct();

        $this->setHeader('Cache-Control', 'public');

        if($file !== NULL)
            $this->load($file, $backend);

    }

    public function modified() {

        $fmtime = $this->file->mtime();
        //The file modification time

        if($rmtime = $this->getLastModified()) {//The requested last modified time

            if($rmtime >= $fmtime)
                return FALSE;

        }

        return TRUE;

    }

    public function load($file, $backend = NULL) {

        if(! $backend)
            $backend = $this->backend;

        $this->file = ($file instanceof \Hazaar\File) ? $file : new \Hazaar\File($file, $backend);

        if(! $this->file->exists())
            throw new \Hazaar\Exception\FileNotFound($file);

        $this->setContentType($this->file->mime_content_type());

        /*
         * Here we can check if the file has been modified and if so just return a 304.
         */

        /*
         * If we have the If-Modified-Since header and the file modification date is greater than the
         * requested modification date, set modified to true and load the file.
         */
        if($this->modified()) {

            $this->setLastModified($this->file->mtime());

        } else {

            $this->setUnmodified();

        }

        return TRUE;

    }

    public function setContent($data) {

        if(! $this->file)
            $this->file = new \Hazaar\File(NULL);

        return $this->file->set_contents($data);

    }

    public function getContent() {

        if($this->file)
            return $this->file->get_contents();

        return parent::getContent();

    }

    public function getContentLength() {

        if($this->file)
            return $this->file->size();

        return 0;

    }

    public function hasContent() {

        if($this->file)
            return ($this->file->size() > 0);

        return FALSE;

    }

    public function __writeOutput() {

        if($this->file)
            $this->content = $this->file->get_contents();

        return parent::__writeOutput();

    }

    public function setUnmodified() {

        $this->setStatus(304);

    }

    public function setLastModified($fmdate) {

        if(! $fmdate instanceof \Hazaar\Date)
            $fmdate = new \Hazaar\Date($fmdate);

        $this->setHeader('Last-Modified', gmdate('r', $fmdate->sec()));

    }

    public function getLastModified() {

        return $this->getHeader('Last-Modified');

    }

    public function match($pattern, &$matches = NULL, $flags = 0, $offset = 0) {

        return preg_match($pattern, $this->content, $matches, $flags, $offset);

    }

    public function replace($pattern, $replacement, $limit = -1, &$count = NULL) {

        return $this->content = preg_replace($pattern, $replacement, $this->content, $limit, $count);

    }

    public function setDownloadable($toggle = TRUE, $filename = NULL) {

        if(! $filename)
            $filename = $this->file->basename();

        if($toggle) {

            $this->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');

        } else {

            $this->setHeader('Content-Disposition', 'inline; filename="' . $filename . '"');

        }

    }

    public function getMimeType() {

        return $this->file->mime_content_type();

    }

    public function getFile() {

        return $this->file;

    }

}