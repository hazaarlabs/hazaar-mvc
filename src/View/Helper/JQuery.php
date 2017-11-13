<?php
/**
 * @file        Hazaar/View/Helper/JQuery.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar\View\Helper;

class JQuery extends \Hazaar\View\Helper {

    private $jquery;

    public function import() {

        $this->requires('cdnjs');

        $this->jquery = \Hazaar\Html\jQuery::getInstance();

    }

    /**
     * @detail      Initialise the jQuery view helper.  This view helper includes the jQuery JavaScript library that is
     *              shipped with Hazaar.  Optionally you can specify a version and that version will be downloaded from
     *              the Google APIs hosted libraries.
     *
     * @since       1.0.0
     *
     * @param       \\Hazaar\\View $view The view the helper is being added to.
     *
     * @param       string $version (Optional) version of the jQuery library to use from the Google hosted libraries
     *              server.
     */
    public function init(\Hazaar\View\Layout $view, $args = array()) {

        $settings = new \Hazaar\Map(array('noload' => FALSE), $this->args);

        if($settings['noload'] !== TRUE) {

            /**
             * Optionally we can set a version which will use the Google hosted library as we only ship the latest
             * version
             * with Hazaar.
             */
            $version = $settings->has('version') ? $settings->get('version') : null;

            $this->cdnjs->load('jquery', $version, null, 100);

            if($settings->has('ui') && $settings->ui === TRUE) {

                $ui_version = $settings->has('ui-version') ? $settings->get('ui-version') : null;

                $files = array('jquery-ui.min.js');

                if($settings->has('ui-theme'))
                    $files[] = 'themes/' . $settings->get('ui-theme') . '/jquery-ui.min.css';

                $this->cdnjs->load('jqueryui', $ui_version, $files);

            }

        }

        $view->setImportPriority(99);

        $view->requires($this->application->url('hazaar/file/js/jquery-helper.js'));

    }

    public function exec($code, $priority = 0) {

        return $this->jquery->exec($code, $priority);

    }

    public function post() {

        return $this->jquery->post();

    }

}


