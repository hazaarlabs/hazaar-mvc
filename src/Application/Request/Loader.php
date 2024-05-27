<?php

declare(strict_types=1);

/**
 * @file        Hazaar/Application/Request/Loader.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\Application\Request;

use Hazaar\Application\Request;

/**
 * Application Request Loader.
 *
 * This class determines from where the application execution was initiated.  Normally this will result in a
 * [[Hazaar\Application\Request\Http]] object being returned as Hazaar applications are most often used as web-based
 * applications.  However it is possible to build and execute command line Hazaar applications.  When executing an
 * application of this type this method will return a [[Hazaar\Application\Request\Cli]] object.
 */
class Loader
{
    /**
     * Loads the appropriate request object for the current application execution.
     */
    public static function load(): Request
    {
        switch (strtolower(php_sapi_name())) {
            case 'cli':
                global $argv;
                $request = new CLI($argv);

                break;

            default:
                $request = new HTTP($_SERVER, $_REQUEST, true);

                break;
        }

        return $request;
    }
}
