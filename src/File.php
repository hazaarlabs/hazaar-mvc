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

    protected $resource;

    protected $handle;

    function __construct($file = null, $backend = NULL) {

        if($file instanceof \Hazaar\File) {

            $this->backend = $file->backend;

            $this->source_file = $file->source_file;

            $this->info = $file->info;

            $this->mime_content_type = $file->mime_content_type;

        }elseif(is_resource($file)){

            $meta = stream_get_meta_data($file);

            $this->backend = new \Hazaar\File\Backend\Local();

            $this->source_file = $meta['uri'];

        } else {

            if(empty($file))
                $file = Application::getInstance()->runtimePath('tmp', true) . DIRECTORY_SEPARATOR . uniqid();

            $this->source_file = \Hazaar\Loader::fixDirectorySeparator($file);

            if(! $backend)
                $backend = new File\Backend\Local(array('root' => ((substr(PHP_OS, 0, 3) == 'WIN') ? substr(APPLICATION_PATH, 0, 3) : DIRECTORY_SEPARATOR)));

            if(! $backend instanceof File\Backend\_Interface)
                throw new \Exception('Can not create new file object without a valid file backend!');

            $this->backend = $backend;

        }

    }

    public function __destruct(){

        $this->close();

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

        return $dir . ((substr($dir, -1, 1) != DIRECTORY_SEPARATOR) ? DIRECTORY_SEPARATOR : NULL) . $this->basename();

    }

    public function extension() {

        return pathinfo($this->source_file, PATHINFO_EXTENSION);

    }

    public function size() {

        if($this->contents)
            return strlen($this->contents);

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

        $content_type = null;

        if(!is_resource($this->source_file))
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

    public function toArray($delimiter = "\n"){

        return explode($delimiter, $this->get_contents());

    }

    /**
     * Return the CSV content as a parsed array
     *
     * @return array
     */
    public function readCSV(){

        return array_map('str_getcsv', $this->toArray("\n"));

    }

    public function unzip($filename){

        $file = false;

        $zip = zip_open($this->source_file);

        if(!is_resource($zip))
            return false;

        while($zip_entry = zip_read($zip)){

            $name = zip_entry_name($zip_entry);

            if(preg_match('/' . $filename . '/', $name)){

                $file = new File($filename);

                $file->set_contents(zip_entry_read($zip_entry, zip_entry_filesize($zip_entry)));

                break;

            }

        }

        zip_close($zip);

        return $file;

    }

    /**
     * Open a file and return it's file handle
     *
     * This is useful for using the file with standard (yet unsupported) file functions.
     *
     * @param string $mode
     *
     * @return resource
     */
    public function open($mode = 'r'){

        if($this->handle)
            return $this->handle;

        return $this->handle = fopen($this->source_file, $mode);

    }

    /**
     * Close the file handle if it is currently open
     *
     * @return boolean
     */
    public function close(){

        if(!$this->handle)
            return false;

        fclose($this->handle);

        $this->handle = null;

        return true;

    }

    /**
     * Returns a character from the file pointer
     *
     * @return string
     */
    public function getc(){

        if(!$this->handle)
            return null;

        return fgetc($this->handle);


    }

    /**
     * Returns a line from the file pointer
     *
     * @return string
     */
    public function gets(){

        if(!$this->handle)
            return null;

        return fgets($this->handle);

    }

    /**
     * Returns a line from the file pointer and strips HTML tags
     *
     * @return string
     */
    public function getss(){

        if(!$this->handle)
            return null;

        return fgetss($this->handle);

    }

    /**
     * Returns a line from the file pointer and parse for CSV fields
     *
     * @return string
     */
    public function getcsv($length = 0, $delimiter = ',', $enclosure = '"', $escape = '\\'){

        if(!$this->handle)
            return null;

        return fgetcsv($this->handle, $length, $delimiter, $enclosure, $escape);

    }

}