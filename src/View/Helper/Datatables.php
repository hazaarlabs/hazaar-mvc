<?php
/**
 * @file        Hazaar/View/Helper/Datatables.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 *
 * @copyright   Copyright (c) 2016 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\View\Helper;

/**
 * @brief       Datatables view helper
 *
 * @since       2.1.2
 */
class Datatables extends \Hazaar\View\Helper {

    public function import() {

        $this->requires('cdnjs');

    }

    public function init(\Hazaar\View\Layout $view, $args = []) {

        $files = [
            'js/jquery.dataTables.min.js',
            'css/jquery.dataTables.min.css'
        ];

        $this->cdnjs->load('datatables', null, $files);

    }

}


