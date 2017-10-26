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

        $this->requires('cdnjs');

    }

    public function init($view, $args = array()) {

        $files = array(
            'js/jquery.dataTables.min.js',
            'css/jquery.dataTables.min.css'
        );

        $this->cdnjs->load('datatables', null, $files);

    }

}


