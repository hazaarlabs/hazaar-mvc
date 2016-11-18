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
    public function index(){

        $this->layout('@admin/layout');

        $this->view->addHelper('jQuery');

        $this->view->addHelper('fontawesome', array('version' => '4.7.0'));

        $this->view->requires($this->application->url('hazaar/file/admin/desktop.js'));

        $this->view->link($this->application->url('hazaar/file/admin/desktop.css'));

        $this->view->link($this->application->url('hazaar/file/admin/layout.css'));

    }

}
