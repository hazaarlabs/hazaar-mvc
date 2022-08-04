<?php
/**
 * @file        Hazaar/View/Helper/Gui.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\View\Helper;

/**
 * @brief       Hazaar Built-in GUI Tools
 *
 * @since       2.3.12
 */
class Gui extends \Hazaar\View\Helper {

    private $parser;

    public function import(){

        $this->requires('jQuery');

        $this->requires('Fontawesome');

    }

    public function init(\Hazaar\View\Layout $view, $args = []) {

        $view->requires($this->application->url('hazaar', 'file/js/popup.js'));

    }

    public function popup($content, $args = []) {

        $id = 'popup_' . uniqid();

        $this->jquery->exec("$('#$id').popup();");

        return $this->html->div($content)->id($id);

    }

}

