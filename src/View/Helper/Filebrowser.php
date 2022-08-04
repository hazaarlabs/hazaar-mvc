<?php
/**
 * @file        Hazaar/View/Helper/Dropzone.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\View\Helper;

/**
 * @brief       A nice and simple Ajax file browser
 *
 * @detail      View helper to allow easier access to Hazaar's built-in file browser widget.
 *
 *              See [[Using The File Browser Widget]] for more information.
 *
 * @since       2.0.0
 */
class Filebrowser extends \Hazaar\View\Helper {

    private $options;

    public function import() {

        $this->requires('html');

        $this->requires('jQuery', ['ui' => TRUE]);

        $this->options = new \Hazaar\Map([
            'stylesheet' => true
        ]);

    }

    /**
     * @detail      View helper initialisation method.
     *
     * @since       2.0.0
     */
    public function init(\Hazaar\View\Layout $view, $args = []) {

        $view->requires($this->application->url('hazaar', 'file/js/filebrowser.js'));

        $this->options->extend($args);

        if($this->options->stylesheet === true)
            $view->link($this->application->url('hazaar', 'file/css/filebrowser.css'));

    }

    public function get($name, $settings = null, $params = []) {

        return new \Hazaar\View\Widgets\FileBrowser($name, $this->options->merge($settings), $params);

    }

}
