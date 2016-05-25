<?php

class IndexController extends \Hazaar\Controller\Action {

    protected function init() {

        $this->view->addHelper('bootstrap');

    }

    public function index() {

        /*
         * By default the Action controller (which we have extended here) uses a
         * Hazaar\View\Layout, which loads a layout view (application.phtml by default)
         * so here we add a 'sub-view' to the layout which displays out blue example box.
         */
        $this->view('index');

    }

    public function getWelcomeString() {

        /*
         * Our controller methods can be called directly from our view as well.  This
         * method just returns a string that the view uses for the header.
         */
        return "Welcome to Hazaar! MVC";

    }

}
