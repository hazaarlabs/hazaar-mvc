<?php

declare(strict_types=1);

namespace Hazaar\Application\Exception;

use Hazaar\Exception;

class RuntimeDirUncreatable extends Exception
{
    public function __construct(string $path)
    {
        $dir = basename($path);
        $msg = "Your application runtime directory does not exist and can not be created automatically.  Please run the following:\n\n<pre>";
        $msg .= 'cd '.dirname($path)."\n";
        $msg .= "mkdir {$dir}\n";
        $msg .= "chmod 0775 {$dir}\n";
        $group = coalesce(getenv('APACHE_RUN_GROUP'), '{your http server group}');
        $msg .= "chgrp {$group} {$dir}\n</pre>";
        parent::__construct($msg);
    }
}
