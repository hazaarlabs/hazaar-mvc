<?php
/**
 * @file        Hazaar/View/Helper/_Interface.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

/**
 * @brief       View Helpers
 */
namespace Hazaar\View\Helper;

/**
 * @brief       Base view helper interface
 */
interface _Interface {

    function import();

    function init(\Hazaar\View\Layout $view, $args = []);

}
