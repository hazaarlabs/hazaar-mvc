<?php

namespace Hazaar\Console;

class Controller extends \Hazaar\Controller\Action {

    private $open_actions = array('file', 'login');

    private $passwd = null;

    public function init(){

        if(in_array($this->request->getActionName(), $this->open_actions))
            return;

        $passwd = CONFIG_PATH . DIRECTORY_SEPARATOR . '.passwd';

        if(!file_exists($passwd))
            die('Hazaar admin console is currently disabled!');

        session_start();

        $this->view->layout('@console/layout');

        $this->view->addHelper('bootstrap');

        if(!ake($_SESSION, 'user'))
            $this->redirect($this->url('login'));

        $this->view->addHelper('jQuery');

        $this->view->addHelper('fontawesome', array('version' => '4.7.0'));

        $this->view->requires($this->application->url('file/console/application.js'));

        $this->view->link($this->application->url('file/console/layout.css'));

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

                $current = $db->getSchemaVersion();

                $versions = array('latest' => 'Latest Version') + $db->getSchemaVersions();

                $this->view->current_version = $current . ' - ' . ake($versions, $current, 'missing');

                $this->view->versions = $versions;

                $this->view->latest = $db->isSchemaLatest();

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

        $passwd = CONFIG_PATH . DIRECTORY_SEPARATOR . '.passwd';

        if(!file_exists($passwd))
            die('Hazaar admin console is currently disabled!');

        session_start();

        if($this->request->isPOST()){

            $users = array();

            $lines = explode("\n", trim(file_get_contents($passwd)));

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

        $this->layout('@console/login');

        $this->view->addHelper('bootstrap');

        $this->view->link('console/login.css');

        $this->view->requires('console/test.js');

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

}
