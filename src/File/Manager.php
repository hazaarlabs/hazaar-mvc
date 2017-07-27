<?php

namespace Hazaar\File;

class Manager {

    static public  $default_config = array(
        'enabled' => true,
        'auth' => false,
        'allow' => array(
            'read' => false,    //Default disallow reads when auth enabled
            'cmd'  => false,    //Default disallow file manager commands
            'dir' => true       //Allow directory listings
        ),
        'userdef' => array()
    );

    static private $default_backend;

    static private $default_backend_options;

    private        $backend;

    private        $backend_name;

    private        $options = array();

    public         $name;

    function __construct($backend = NULL, $backend_options = array(), $name = NULL) {

        if(! $backend) {

            if(Manager::$default_backend) {

                $backend = Manager::$default_backend;

                $backend_options = Manager::$default_backend_options;

            } else {

                $backend = 'local';

            }

        }

        if(class_exists($backend))
            $class = $backend;
        else
            $class = 'Hazaar\File\Backend\\' . ucfirst($backend);

        if(! class_exists($class))
            throw new Exception\BackendNotFound($backend);

        $this->backend_name = $backend;

        $this->backend = new $class($backend_options);

        if(!$this->backend instanceof \Hazaar\File\Backend\_Interface)
            throw new Exception\InvalidBackend($backend);

        if(! $name)
            $name = strtolower($backend);

        $this->name = $name;

    }

    /**
     * Loads a Manager class by name as configured in media.json config
     *
     * @param mixed $name The name of the media source to load
     */
    static public function select($name){

        $config = new \Hazaar\Application\Config('media');

        if(!$config->has($name))
            return false;

        $source = new \Hazaar\Map(\Hazaar\File\Manager::$default_config, $config->get($name));

        $manager = new \Hazaar\File\Manager($source->type, $source->get('options'), $name);

        return $manager;

    }

    public function refresh($reset = FALSE) {

        return $this->backend->refresh($reset);

    }

    static public function configure($backend, $options) {

        if(! $options instanceof \Hazaar\Map)
            $options = new \Hazaar\Map($options);

        Manager::$default_backend = $backend;

        Manager::$default_backend_options = $options;

    }

    public function getBackend() {

        return $this->backend;

    }

    public function getBackendName() {

        return $this->backend_name;

    }

    public function setOption($name, $value) {

        $this->options[$name] = $value;

    }

    public function getOption($name) {

        return ake($this->options, $name);

    }

    public function fixPath($path, $file = NULL) {

        $path = '/' . trim($path, '/');

        if($file)
            $path .= ((substr($path, -1, 1) !== '/') ? '/' : NULL) . $file;

        return $path;

    }

    /*
     * Authorisation Methods
     *
     * These are used by certain backends that require OAuth-like user authorisation
     */
    public function authorise($redirect_uri = NULL) {

        if(! method_exists($this->backend, 'authorise'))
            return TRUE;

        $result = $this->backend->authorise($redirect_uri);

        if($result === FALSE) {

            header('Location: ' . $this->backend->buildAuthUrl($redirect_uri));

            exit;

        }

        return $result;

    }

    /**
     * Alias to authorise() which is the CORRECT spelling.
     *
     * @param array $options
     * @return bool
     */
    public function authorize($options = array()) {

        return $this->authorise($options);

    }

    public function authorised() {

        if(! method_exists($this->backend, 'authorised'))
            return TRUE;

        return $this->backend->authorised();

    }

    public function authorized() {

        return $this->authorised();

    }

    public function reset() {

        if(! method_exists($this->backend, 'reset'))
            return TRUE;

        return $this->backend->reset();

    }

    public function buildAuthURL($callback_url) {

        if(! method_exists($this->backend, 'buildAuthURL'))
            return FALSE;

        return $this->backend->buildAuthURL($callback_url);

    }

    /*
     * Files and Metadata
     */
    public function get($path) {

        return new \Hazaar\File($path, $this->backend);

    }

    /**
     * Return a directory object for a pgiven path.
     * 
     * @param mixed $path The path to create a directory object for
     * 
     * @return Dir The directory object.
     */
    public function dir($path = '/') {

        return new Dir($this->fixPath($path), $this->backend);

    }

    public function find($search = NULL, $path = '/') {

        $dir = $this->dir($path);

        $list = array();

        while(($file = $dir->read()) != FALSE) {

            if($file->is_dir()) {

                $list[] = $file->fullpath();

                $list = array_merge($list, $this->find($search, $file->fullpath()));

            } else {

                if($search) {

                    if(substr($search, 0, 1) == substr($search, -1, 1)) {

                        if(! preg_match($search, $file->basename()))
                            continue;

                    } elseif(! fnmatch($search, $file->basename())) {

                        continue;

                    }

                }

                $list[] = $file->fullpath();

            }

        }

        return $list;

    }

    public function exists($path) {

        return $this->backend->exists($this->fixPath($path));

    }

    public function read($file, $offset = NULL, $maxlen = NULL) {

        return $this->backend->read($this->fixPath($file), $offset, $maxlen);

    }

    public function write($file, $data, $content_type, $overwrite = FALSE) {

        return $this->backend->write($this->fixPath($file), $data, $content_type, $overwrite);

    }

    public function upload($path, $file, $overwrite = FALSE) {

        return $this->backend->upload($this->fixPath($path), $file, $overwrite);

    }

    public function store($source, $target) {

        dir('revamp this');

        $file = new \Hazaar\File($source);

        if(substr(trim($target), -1, 1) != '/')
            $target .= '/';

        return $this->backend->write($target . $file->filename(), $file->getContents(), $file->getMimeType());

    }

    public function search($query) {

        return $this->backend->search($query);

    }

    private function deepCopy($src, $dst, $srcBackend, $progressCallback = NULL) {

        $dstPath = rtrim($dst, '/') . '/' . basename($src);

        if(! $this->exists($dstPath))
            $this->mkdir($dstPath);

        $dir = new Dir($src, $srcBackend);

        while(($f = $dir->read()) != FALSE) {

            if($progressCallback)
                call_user_func_array($progressCallback, array('copy', $f));

            if($f->type() == 'dir')
                $this->deepCopy($f->fullpath(), $dstPath, $srcBackend, $progressCallback);

            else
                $this->backend->write($this->fixPath($dstPath, $f->basename()), $f->get_contents(), $f->mime_content_type());

        }

        return TRUE;

    }

    /*
     * File Operations
     */
    public function copy($src, $dst, $srcBackend = NULL, $recursive = FALSE, $progressCallback = NULL) {

        if($srcBackend instanceof Manager)
            $srcBackend = $srcBackend->getBackend();

        if($srcBackend !== $this->backend) {

            $file = new \Hazaar\File($src, $srcBackend);

            switch($file->type()) {
                case 'file':

                    return $this->backend->write($this->fixPath($dst, $file->basename()), $file->get_contents(), $file->mime_content_type());

                    break;

                case 'dir':

                    if(! $recursive)
                        return FALSE;

                    return $this->deepCopy($file->fullpath(), $dst, $srcBackend, $progressCallback);

                    break;

            }

            throw new \Exception("Copy of source type '" . $file->type() . "' between different sources is currently not supported");

        }

        return $this->backend->copy($src, $dst, $recursive);

    }

    public function move($src, $dst, $srcManager = NULL) {

        if($srcManager instanceof Manager && $srcManager->getBackend() !== $this->backend) {

            $file = $srcManager->get($src);

            switch($file->type()) {
                case 'file':

                    $result = $this->backend->write($this->fixPath($dst, $file->basename()), $file->get_contents(), $file->mime_content_type());

                    if($result)
                        return $srcManager->unlink($src);

                    return FALSE;

                    break;

                case 'dir':

                    $result = $this->deepCopy($file->fullpath(), $dst, $srcManager->getBackend());

                    if($result)
                        return $srcManager->rmdir($src, TRUE);

                    return FALSE;

                    break;

            }

            throw new \Exception("Move of source type '" . $file->type() . "' between different sources is currently not supported.");

        }

        return $this->backend->move($src, $dst);

    }

    public function mkdir($path) {

        return $this->backend->mkdir($this->fixPath($path));

    }

    public function rmdir($path, $recurse = FALSE) {

        return $this->backend->rmdir($this->fixPath($path), $recurse);

    }

    public function unlink($path) {

        return $this->backend->unlink($this->fixPath($path));

    }

    /*
     * Advanced backend dependant features
     */
    public function fsck() {

        if(method_exists($this->backend, 'fsck')) {

            return $this->backend->fsck();

        }

        return TRUE;

    }

    public function thumbnail($path, $width = 100, $height = 100, $format = 'jpeg') {

        if(method_exists($this->backend, 'thumbnail')) {

            return $this->backend->thumbnail($path, $width, $height, $format);

        }

        return FALSE;

    }

    public function link($path) {

        if(method_exists($this->backend, 'link')) {

            return $this->backend->link($path);

        }

        return FALSE;

    }

    public function share($path) {

        if(method_exists($this->backend, 'share')) {

            return $this->backend->share($path);

        }

        return FALSE;

    }

    public function get_meta($path, $key = NULL) {

        return $this->backend->get_meta($path, $key);

    }

    public function set_meta($path, $values) {

        return $this->backend->set_meta($path, $values);

    }

}