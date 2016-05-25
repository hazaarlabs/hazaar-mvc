<?php

namespace Hazaar;

class File {

    protected $backend;

    public    $source_file;

    protected $info;

    protected $mime_content_type;

    /*
     * Any overridden file contents.
     *
     * This is normally used when performing operations on the file in memory, such as resizing an image.
     */
    protected $contents;

    function __construct($file, $backend = NULL) {

        if($file instanceof \Hazaar\File) {

            $this->backend = $file->backend;

            $this->source_file = $file->source_file;

            $this->info = $file->info;

            $this->mime_content_type = $file->mime_content_type;

        } else {

            $this->source_file = $file;

            if(! $backend)
                $backend = new File\Backend\Local(array('root' => '/'));

            if(! $backend instanceof File\Backend\_Interface)
                throw new \Exception('Can not create new file object without a valid file backend!');

            $this->backend = $backend;

        }

    }

    public function set_meta($values) {

        return $this->backend->set_meta($this->source_file, $values);

    }

    public function get_meta($key = NULL) {

        return $this->backend->get_meta($this->source_file, $key);

    }

    /*
     * Basic output functions
     */
    public function toString() {

        return $this->fullpath();

    }

    public function __tostring() {

        return $this->toString();

    }

    /*
     * Standard filesystem functions
     */
    public function basename() {

        return basename($this->source_file);

    }

    public function dirname() {

        return dirname($this->source_file);

    }

    public function name() {

        return pathinfo($this->source_file, PATHINFO_FILENAME);

    }

    public function fullpath() {

        $dir = $this->dirname();

        return $dir . ((substr($dir, -1, 1) != '/') ? '/' : NULL) . $this->basename();

    }

    public function extension() {

        return pathinfo($this->source_file, PATHINFO_EXTENSION);

    }

    public function size() {

        return $this->backend->filesize($this->source_file);

    }

    /*
     * Standard file modification functions
     */
    public function exists() {

        return $this->backend->exists($this->source_file);

    }

    public function realpath() {

        return $this->backend->realpath($this->source_file);

    }

    public function is_readable() {

        return $this->backend->is_readable($this->source_file);

    }

    public function is_writable() {

        return $this->backend->is_writable($this->source_file);

    }

    public function is_dir() {

        return $this->backend->is_dir($this->source_file);

    }

    public function dir() {

        if($this->is_dir())
            return new File\Dir($this->source_file);

        return FALSE;

    }

    public function is_link() {

        return $this->backend->is_link($this->source_file);

    }

    public function is_file() {

        return $this->backend->is_file($this->source_file);

    }

    public function parent() {

        return new File\Dir($this->dirname(), $this->backend);

    }

    public function type() {

        return $this->backend->filetype($this->source_file);

    }

    public function mtime() {

        return $this->backend->filemtime($this->source_file);

    }

    public function has_contents() {

        return ($this->backend->filesize($this->source_file) > 0);

    }

    public function get_contents($offset = -1, $maxlen = NULL) {

        if($this->contents)
            return $this->contents;

        return $this->backend->read($this->source_file, $offset, $maxlen);

    }

    public function put_contents($data, $overwrite = FALSE) {

        $content_type = $this->mime_content_type();

        if(! $content_type)
            $content_type = 'text/text';

        return $this->backend->write($this->source_file, $data, $content_type, $overwrite);

    }

    public function set_contents($bytes) {

        $this->contents = $bytes;

    }

    public function save() {

        return $this->put_contents($this->contents, TRUE);

    }

    public function saveAs($filename, $overwrite = FALSE) {

        return $this->backend->write($filename, $this->contents, $overwrite);

    }

    public function unlink() {

        if(! $this->exists())
            return FALSE;

        if($this->is_dir()) {

            return $this->backend->rmdir($this->source_file, TRUE);

        } else {

            return $this->backend->unlink($this->source_file);

        }

    }

    /*
     * Enhanced file functions
     */
    public function md5() {

        return $this->backend->md5Checksum($this->source_file);

    }

    public function base64() {

        return base64_encode($this->get_contents());

    }

    /*
     * Custom built-in functions
     */
    public function parseJSON($assoc = FALSE) {

        return json_decode($this->get_contents(), $assoc);

    }

    public function copyTo($destination, $create_dest = FALSE, $dstBackend = NULL) {

        if(! $this->exists())
            throw new File\Exception\SourceNotFound($this->source_file, $destination);

        if(! $dstBackend)
            $dstBackend = $this->backend;

        if(! $dstBackend->exists($destination)) {

            if($create_dest)
                $dstBackend->mkdir($destination);

            else
                throw new File\Exception\TargetNotFound($destination, $this->source_file);

        }

        return $dstBackend->copy($this->source_file, $destination, $this->backend);

    }

    public function mime_content_type() {

        if($this->mime_content_type)
            return $this->mime_content_type;

        return $this->backend->mime_content_type($this->fullpath());

    }

    public function set_mime_content_type($type) {

        $this->mime_content_type = $type;

    }

    public function thumbnail($params = array()) {

        return $this->backend->thumbnail($this->fullpath(), $params);

    }

    public function preview_uri($params = array()) {

        return $this->backend->preview_uri($this->fullpath(), $params);

    }

    public function direct_uri() {

        return $this->backend->direct_uri($this->fullpath());

    }

}