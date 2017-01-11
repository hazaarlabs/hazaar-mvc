<?php

namespace Hazaar\Controller;

class Hazaar extends \Hazaar\Controller\Action {

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

    /**
     * Launch the Hazaar MVC Management Console
     *
     * The Management Console is a virtual desktop that allows the application to be
     * administered in a user friendly environment.
     */
    public function __default($controller, $action){

        session_start();

        $this->view->addHelper('bootstrap');

        if($this->request->getActionName() == 'logout'){

            session_unset();

            $this->redirect($this->url());

        }elseif(ake($_SESSION, 'user')){

            $this->layout('@admin/layout');

            $this->view->addHelper('jQuery');

            $this->view->addHelper('fontawesome', array('version' => '4.7.0'));

            $this->view->requires($this->application->url('hazaar/file/admin/application.js'));

            $this->view->link($this->application->url('hazaar/file/admin/layout.css'));

            $this->view('@admin/' . str_replace('_', '/', $action));

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

            if(class_exists('Hazaar\DBI\Adapter')){

                $this->view->navitems['db'] = array(
                    'label' => 'Database',
                    'items' => array(
                        'settings' => 'Settings',
                        'schema' => 'Schema Managment'
                    )
                );

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

        }elseif($this->request->getActionName() !== 'login'){

            if($action == 'authenticate'){

                if($_SESSION['user'] = $this->request->username)
                    $this->redirect($this->url());

            }

            $this->layout('@admin/login');

            $this->view->link($this->application->url('hazaar/file/admin/login.css'));

        }

    }

}
