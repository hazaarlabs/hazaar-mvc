<?php
/**
 * @file        Hazaar/View/Helper/Datatables.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2016 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar\View\Helper;

/**
 * @brief       Datatables view helper
 *
 * @since       2.1.2
 */
class Datatables extends \Hazaar\View\Helper {

    public function import() {

        $this->requires('html');

        $this->requires('JQuery');

    }

    public function init($view, $args = array()) {

        $view->link('https://cdn.datatables.net/1.10.12/css/jquery.dataTables.min.css');

        $view->requires('https://cdn.datatables.net/1.10.12/js/jquery.dataTables.min.js');

    }

}


