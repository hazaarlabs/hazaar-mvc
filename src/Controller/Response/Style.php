<?php

namespace Hazaar\Controller\Response;

class Style extends File {

    function __construct($source = NULL) {

        parent::__construct();

        $this->setContentType('text/css');

        if($source)
            $this->load($source);

    }

    public function load($filename, $backend = NULL) {

        parent::load($filename, $backend);

        if($this->file->extension() == 'less') {

            $cache_dir = \Hazaar\Application::getInstance()->runtimePath('less', true);

            $cache_file = new \Hazaar\File($cache_dir . DIRECTORY_SEPARATOR . $this->file->name() . '.css');

            if(! ($cache_file->exists() && $cache_file->mtime() > $this->file->mtime())) {

                if(!class_exists('lessc'))
                    throw new Exception\NoLessSupport();

                $less = new \lessc;

                $content = $less->compile($this->getContent());

                $cache_file->put_contents($content, true);

            }

             $this->setContent($cache_file, 'text/css');

        }

    }

}