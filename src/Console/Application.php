<?php

namespace Hazaar\Console;

class Application extends Module {

    public function load(){

        $this->addMenuGroup('Application', 'bars');

        //$this->addMenuItem('Models', 'models', 'sitemap');

        //$this->addMenuItem('Views', 'views', 'binoculars');

        //$this->addMenuItem('Controllers', 'controllers', 'code-fork');

        $this->addMenuItem('Configuration', 'config', 'cogs');

        $this->addMenuItem('File Encryption', 'encrypt', 'key');

        $this->addMenuItem('Cache Settings', 'cache', 'facebook');

    }

    public function index($request){

        $this->view('index');

        $this->view->requires('js/application.js');

    }

    public function stats($request){

        if(!$request->isPost())
            throw new \Exception('Method not allowed!', 405);

        if(!$request->has('name'))
            throw new \Exception('No datasource name', 400);

        if(!$request->has('archive'))
            throw new \Exception('No archive name', 400);

        $rrd_file = $this->application->runtimePath('profile.rrd');

        $rrd = new \Hazaar\File\RRD($rrd_file);

        if(($result = $rrd->graph($request->name, $request->archive)) === false)
            throw new \Exception('No data!', 204);

        return $result;

    }

    public function models($request){

        $this->view('models');

    }

    public function views($request){

        $this->view('application/views');

    }

    public function controllers($request){

        $this->view('application/controllers');

    }

    public function config($request){

        $config = new \Hazaar\Application\Config('application', $request->get('env', APPLICATION_ENV));

        if($request->isPost()){

            if($config->fromJSON($request->config)){

                if($config->write())
                    $this->redirect($this->url('app/config', array('env' => $config->getEnv())));

                $this->notice('An error ocurred writing the config file.', 'exclamation-triangle', 'danger');

            }else
                $this->notice('Invalid JSON.  Please fix and try again!', 'exclamation-triangle', 'warning');

            $data = $request->config;

        } else
            $data = json_encode($config->getEnvConfig(), JSON_PRETTY_PRINT);

        $this->view('application/config');

        $this->view->requires('js/config.js');

        $this->view->extend(array(
            'config' => $data,
            'env' => $config->getEnv(),
            'envs' => $config->getEnvironments()
        ));

    }

    public function encrypt($request){

        if($request->isXMLHttpRequest()){

            if($request->has('encrypt')){

                if(!($filename = \Hazaar\Loader::getFilePath(FILE_PATH_CONFIG, $request->encrypt)))
                    throw new \Exception('Config file not found!', 404);

                $file = new \Hazaar\File($filename);

                if($file->isEncrypted())
                    $file->decrypt();
                else
                    $file->encrypt();

                return array('encrypt' => $file->isEncrypted());

            }

            $search_paths = $this->application->loader->getSearchPaths(FILE_PATH_CONFIG);

            $files = array();

            foreach($search_paths as $path){

                $dir = new \Hazaar\File\Dir($path);

                $config_files = $dir->find('*.json');

                foreach($config_files as $config_file){

                    $file = new \Hazaar\File($config_file);

                    $encrypted = $file->isEncrypted();

                    $files[] = array(
                        'name' => trim(str_replace($path, '', $file->fullpath()), DIRECTORY_SEPARATOR),
                        'size' => $file->size(),
                        'encrypted' => $encrypted
                    );

                }

            }

            return $files;

        }

        $this->view('application/encrypt');

        $this->view->requires('js/encrypt.js');

        if(!(\Hazaar\Loader::getFilePath(FILE_PATH_CONFIG, '.key')))
            $this->notice('There is no application .key file.  Encrypting files will use the defaut key which is definitely NOT RECOMMENDED!', 'key', 'danger');

    }

    public function cache($request){

    }

}