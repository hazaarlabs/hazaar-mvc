<?php

namespace Hazaar\Controller;

class Hazaar extends \Hazaar\Controller\Action {

    private $open_actions = array('file', 'login');

    private $passwd = null;

    public function init(){

        if(in_array($this->request->getActionName(), $this->open_actions))
            return;

        $this->passwd = CONFIG_PATH . DIRECTORY_SEPARATOR . '.passwd';

        if(!file_exists($this->passwd))
            die('Hazaar admin console is currently disabled!');

        session_start();

        $this->view->addHelper('bootstrap');

        if(!ake($_SESSION, 'user'))
            $this->redirect($this->url('login'));

        $this->layout('@admin/layout');

        $this->view->addHelper('jQuery');

        $this->view->addHelper('fontawesome', array('version' => '4.7.0'));

        $this->view->requires($this->application->url('hazaar/file/admin/application.js'));

        $this->view->link($this->application->url('hazaar/file/admin/layout.css'));

        $this->view->navitems = array(
            'app' => array(
                'label' => 'Application',
                'items' => array(
                    'index' => 'Overview',
                    'models' => 'Models',
                    'views' => 'Views',
                    'controllers' => 'Controllers'
                )
            )
        );

        if(class_exists('Hazaar\Cache')){

            $this->view->navitems['cache'] = array(
                'label' => 'Cache',
                'items' => array(
                    'settings' => 'Settings'
                )
            );

        }

        if(class_exists('Hazaar\DBI\Adapter')){

            $this->view->navitems['db'] = array(
                'label' => 'Database',
                'items' => array(
                    'settings' => 'Settings',
                    'schema' => 'Schema Managment',
                    'sync' => 'Data Sync'
                )
            );

            if($this->action == 'db_schema'){

                $db = new \Hazaar\DBI\Adapter();

                $this->view->versions = array('latest' => 'Latest Version') + $db->getSchemaVersions();

            }

        }

        if(class_exists('Hazaar\Warlock\Control')){

            $this->view->navitems['warlock'] = array(
                'label' => 'Warlock',
                'items' => array(
                    'index' => 'Overview',
                    'connections' => 'Connections',
                    'processes' => 'Processes',
                    'services' => 'Services',
                    'log' => 'Log File'
                )
            );

        }

    }


    public function login(){

        session_start();

        if($this->request->isPOST()){

            $users = array();

            $lines = explode("\n", trim(file_get_contents($this->passwd)));

            foreach($lines as $line){

                if(!$line)
                    continue;

                list($identity, $userhash) = explode(':', $line);

                $users[$identity] = $userhash;

            }

            $credential = trim(ake($users, $this->request->username));

            if(strlen($credential) > 0){

                $hash = '';

                if(substr($credential, 0, 6) == '$apr1$'){

                    throw new \Exception('APR1-MD5 encoded passwords are not supported!');

                }elseif(substr($credential, 0, 5) == '{SHA}'){

                    $hash = '{SHA}' . base64_encode(sha1($this->request->password, TRUE));

                }

                if($hash == $credential){

                    $_SESSION['user'] = $this->request->username;

                    $this->redirect($this->url());

                }else{

                    $this->view->msg = 'Password is incorrect!';

                }

            }else{

                $this->view->msg = 'User is not found!';

            }

        }elseif(ake($_SESSION, 'user')){

            $this->redirect($this->url());

        }

        $this->layout('@admin/login');

        $this->view->addHelper('bootstrap');

        $this->view->link($this->application->url('hazaar/file/admin/login.css'));

    }

    public function logout(){

        session_unset();

        $this->redirect($this->url());

    }


    /**
     * Launch the Hazaar MVC Management Console
     *
     * The Management Console is a virtual desktop that allows the application to be
     * administered in a user friendly environment.
     */
    public function __default($controller, $action){

        $this->view('@admin/' . str_replace('_', '/', $action));

    }

    public function snapshot(){

        if(!$this->request->isPOST())
            return false;

        $db = new \Hazaar\DBI\Adapter();

        $result = $db->snapshot($this->request->get('comment'), boolify($this->request->get('testmode', false)));

        return array('ok' => $result, 'log' => $db->getMigrationLog());

    }

    public function migrate(){

        if(!$this->request->isPOST())
            return false;

        $version = $this->request->get('version', 'latest');

        if($version == 'latest')
            $version = null;

        $db = new \Hazaar\DBI\Adapter();

        $result = $db->migrate($version, boolify($this->request->get('testmode', false)));

        return array('ok' => $result, 'log' => $db->getMigrationLog());

    }

    public function syncdata(){

        if(!$this->request->isPOST())
            return false;

        $db = new \Hazaar\DBI\Adapter();

        $result = $db->syncSchemaData();

        return array('ok' => $result, 'log' => $db->getMigrationLog());

    }

    /**
     * Directly access a file stored in the Hazaar libs directory.
     *
     * This is used for accessing files in the libs directory, such as internal built-in JavaScript,
     * CSS, views and other files that are shipped as part of the core Hazaar MVC package.
     *
     * @param string $action
     * @throws Exception\InternalFileNotFound
     * @throws \Exception
     * @return Response\File
     */
    public function file() {

        $response = NULL;

        //Grab the file and strip the action name.
        if($file = substr($this->request->getRawPath(), 5)) {

            if($source = \Hazaar\Loader::getInstance()->getFilePath(FILE_PATH_SUPPORT, $file)) {

                $response = new Response\File($source);

                $response->setUnmodified($this->request->getHeader('If-Modified-Since'));

            } else {

                throw new Exception\InternalFileNotFound($file);

            }

        }else{

            throw new \Exception('Bad request', 400);

        }

        return $response;

    }
}
