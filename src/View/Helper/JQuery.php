<?php
/**
 * @file        Hazaar/View/Helper/JQuery.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar\View\Helper;

if(!defined('JQUERY_CURRENT_VER'))
    define('JQUERY_CURRENT_VER', '2.2.4');

if(!defined('JQUERY_CURRENT_UI_VER'))
    define('JQUERY_CURRENT_UI_VER', '1.12.0');

class JQuery extends \Hazaar\View\Helper {

    private $jquery;

    public function import() {

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
    public function init($view, $args = array()) {

        $settings = new \Hazaar\Map(array('noload' => FALSE), $args);

        if($settings['noload'] !== TRUE) {

            /**
             * Optionally we can set a version which will use the Google hosted library as we only ship the latest
             * version
             * with Hazaar.
             */
            $version = $settings->has('version') ? $settings->get('version') : JQUERY_CURRENT_VER;

            $view->requires('https://cdnjs.cloudflare.com/ajax/libs/jquery/' . $version . '/jquery.min.js');

            if($settings->has('ui') && $settings->ui === TRUE) {

                $ui_version = $settings->has('ui-version') ? $settings->get('ui-version') : JQUERY_CURRENT_UI_VER;

                $view->requires('https://cdnjs.cloudflare.com/ajax/libs/jqueryui/' . $ui_version . '/jquery-ui.min.js');

                $theme = null;

                if($settings->has('ui-theme'))
                    $theme = '/themes/' . $settings->get('ui-theme');

                $view->link('https://cdnjs.cloudflare.com/ajax/libs/jqueryui/' . $ui_version . $theme . '/jquery-ui.min.css');

            }

            $view->requires($this->application->url('hazaar/file/js/jquery-helper.js'));

        }

    }

    public function exec($code) {

        return $this->jquery->exec($code);

    }

    public function post() {

        return $this->jquery->post();

    }

}


