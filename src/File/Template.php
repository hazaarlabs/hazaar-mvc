<?php

namespace Hazaar\File;

/**
 * The View\Template class
 *
 */
class Template extends \Hazaar\Text\Template {

    static public $cache_enabled = true;

    private $__source_file = null;

    private $__cache_enabled = false;

    function __construct($file, $cache_enabled = null){

        $this->loadFromFile($file);

        if($cache_enabled === null)
            $cache_enabled = Template::$cache_enabled;

        $this->__cache_enabled = $cache_enabled;

    }

    public function loadFromFile($file) {

        if(!$file instanceof \Hazaar\File)
            $file = new \Hazaar\File($file);

        if(!$file->exists())
            throw new \Exception('Template file not found!');

        $this->__source_file = $file;

    }

    protected function compile(){

        $cache_file = null;

        if(!$this->__source_file instanceof \Hazaar\File)
            throw new \Exception('Template compilation failed! No source file or template content has been loaded!');

        if($this->__cache_enabled){

            $cache_id = md5($this->__source_file->fullpath());

            $cache_dir = new \Hazaar\File\Dir(\Hazaar\Application::getInstance()->runtimePath('template_cache', true));

            $cache_file = $cache_dir->get($cache_id . '.tpl');

            if($cache_file->exists() && $cache_file->mtime() > $this->__source_file->mtime()){

                $this->__compiled_content = $cache_file->get_contents();

                return true;

            }

        }

        $this->__content = $this->__source_file->get_contents();

        if(!parent::compile($this->__content))
            return false;

        if($cache_file)
            $cache_file->put_contents($this->__compiled_content);

        return true;

    }

}
