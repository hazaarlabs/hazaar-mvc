<?php

namespace Hazaar\File;

/**
 * Create a temporary directory
 *
 * This class will create and manage a temporary directory that is stored in the application runtime directory in a
 * subdirectory called 'temp'.  The directory is not stateful, meaning it can only be used for the lifetime of the execution
 * and once the file object falls out of scope it, and all of it's contents, will be deleted automatically.
 *
 * @since 2.3
 */
class TempDir extends \Hazaar\File\Dir {

    /**
     * Temporary directory constructor
     *
     * @param mixed $name An option name to give the temp directory.  Leaving this null will auto-generate a name.
     */
    function __construct($name = null){

        if(!$name) $name = uniqid() . '.tmp';

        $name = \Hazaar\Application::getInstance()->runtimePath('temp', true) . DIRECTORY_SEPARATOR . $name;

        parent::__construct($name);

        if(!parent::exists())
            parent::create(true);

    }

    /**
     * Temporary directory destructor
     *
     * This method will clean up the directory on disk when the object falls out of scope.  This includes deleting
     * anything that is contained in the directory.
     */
    function __destruct(){

        $this->delete(true);

    }

}