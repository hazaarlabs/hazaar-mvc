<?php

declare(strict_types=1);

namespace Hazaar\Application\Exception;

class RuntimeDirNotWritable extends \Exception
{
    public function __construct(string $path)
    {
        $dir = basename($path);
        $msg = "The application runtime directory exists, but is not writable.\n\n";

        if (ini_get('display_errors')) {
            $msg .= "Please run the following:\n\n";
            $msg .= 'cd '.dirname($path)."\n";
            $msg .= "chmod 0775 {$dir}\n";
            $group = coalesce(getenv('APACHE_RUN_GROUP'), '{your http server group}');
            $msg .= "chgrp {$group} {$dir}\n\n";
        }
        parent::__construct($msg, 500);
    }
}
