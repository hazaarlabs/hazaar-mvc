<?php

declare(strict_types=1);

namespace Hazaar\File;

use Hazaar\Application;
use Hazaar\File;

/**
 * Create a temporary file.
 *
 * This class will create and manage a temporary file that is stored in the application runtime directory in a
 * subdirectory called 'temp'.  The file is not stateful, meaning it can only be used for the lifetime of the execution
 * and once the file object falls out of scope it will be deleted automatically.
 */
class Temp extends File
{
    /**
     * Temporary file constructor.
     *
     * @param mixed $name An option name to give the temp file.  Leaving this null will auto-generate a name.
     */
    public function __construct($name = null)
    {
        if (!$name) {
            $name = uniqid().'.tmp';
        }
        $name = Application::getInstance()->runtimePath('temp', true).DIRECTORY_SEPARATOR.$name;
        parent::__construct($name);
    }

    /**
     * Temporary file destructor.
     *
     * This method will clean up the file on disk when the object falls out of scope.
     */
    public function __destruct()
    {
        $this->unlink();
    }
}
