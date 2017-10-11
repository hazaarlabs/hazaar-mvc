<?php
/**
 * @file        Controller/_Interface.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

/**
 * @brief       Controller namespace
 */
namespace Hazaar\Controller;

/**
 * @brief       Controller interface
 *
 * @detail      This interface is used to define the required methods of a controller class
 */
interface _Interface {

    public function __initialize(\Hazaar\Application\Request $request);

    public function __tostring();

    public function __run();

}

