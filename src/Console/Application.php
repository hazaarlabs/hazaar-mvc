<?php

namespace Hazaar\Console;

class Application extends Module {

    public function init(){

        $this->addMenuGroup('app', 'Application', 'bars');

        $this->addMenuItem('app', 'Models', 'models', 'sitemap', 3);

        $this->addMenuItem('app', 'Views', 'views', 'binoculars', 12);

        $this->addMenuItem('app', 'Controllers', 'controllers', 'code-fork', 5);

        $this->addMenuGroup('sys', 'System', 'wrench');
        $this->addMenuItem('app', 'Configuration', 'configs', 'cogs');

        $this->addMenuItem('sys', 'PHP Info', 'phpinfo');

    }

    public function index($request){

        $this->view('index');

        $this->view->requires('console.js');

    }

    public function models($request){

        $this->view('models');

    }

    public function views($request){

        $this->view('views');

    }

    public function controllers($request){

        $this->view('controllers');

    }

    public function configs($request){

        if($request->isXMLHttpRequest()){

            $files = array();

            $search_paths = $this->application->loader->getSearchPaths(FILE_PATH_CONFIG);

            foreach($search_paths as $path){

                $dir = new \Hazaar\File\Dir($path);

                $config_files = $dir->find('*.json');

                foreach($config_files as $config_file){

                    $file = new \Hazaar\File($config_file);

                    $r = $file->open();

                    $bom = pack('H*','BADA55');  //Haha, Bad Ass!

                    $encrypted = (fread($r, 3) == $bom);

                    $file->close();

                    $files[] = array(
                        'name' => trim(str_replace($path, '', $file->fullpath()), DIRECTORY_SEPARATOR),
                        'size' => $file->size(),
                        'encrypted' => $encrypted
                    );

                }

            }

            return $files;

        }

        $this->view('configs');

        $this->view->requires('js/configs.js');

    }

    public function system($request){

        $this->view('system');


    }

    public function phpinfo($request){

        $this->view('phpinfo');

    }

}