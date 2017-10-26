<?php
/**
 * @file        Hazaar/View/Helper/Dropzone.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar\View\Helper;

/**
 * @brief       A nice and simple Ajax file uploader
 *
 * @detail      View helper to allow easier access to the Dropzone.js ajax file uploader.
 *
 *              Dropzone.js by Matias Meno <m@tias.me> - http://www.dropzonejs.com
 *
 * @since       2.0.0
 */
class Dropzone extends \Hazaar\View\Helper {

    private $options;

    private $dropzones = array();

    public function import() {

        $this->requires('html');

        $this->requires('cdnjs');

        $this->options = new \Hazaar\Map( array(
            'stylesheet' => false,
            'default_class' => 'dropzone'
        ));

    }

    /**
     * @detail      View helper initialisation method.  Includes the DropZone style-sheet and HTML helper.
     *
     * @since       2.0.0
     */
    public function init($view, $args = array()) {

        $files = array(
            'min/dropzone.min.js',
            'min/dropzone.min.css'
        );

        $this->cdnjs->load('dropzone', ake($args, 'version'), $files);

    }

    /**
     * @detail      Adds a new dropzone file upload element
     *
     * @since       2.0.0
     *
     * @param       string $name The name of the dropzone.
     *
     * @param       string $target The URL to upload the file to.
     *
     * @param       string $class The class to set on the dropzone element
     *
     * @param       Array $args Optional additional arguments to pass to the Dropzone constructor.
     */
    public function add($name, $target, $class = null, $args = array()) {

        $div = $this->html->div()->id($name);

        if(!$class)
            $class = $this->options->default_class;

        if($class)
            $div->class($class);

        $args['url'] = $target;

        $this->dropzones[] = array(
            'name' => $name,
            'args' => $args
        );
        return $div;

    }

    public function post() {

        $script = $this->html->script();

        foreach($this->dropzones as $dz) {

            if(is_array($dz['args'])) {

                $args = json_encode($dz['args']);

            } else {

                $args = 'null';

            }

            $script->add('var dropzone_' . $dz['name'] . ' = new Dropzone("div#' . $dz['name'] . '", ' . $args . ');');

        }

        return $script;

    }

}

