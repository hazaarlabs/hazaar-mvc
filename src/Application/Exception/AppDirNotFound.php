<?php

declare(strict_types=1);

namespace Hazaar\Application\Exception;

class AppDirNotFound extends \Exception
{
    public function __construct(){
        $msg = 'app directory not found';
        parent::__construct($msg, 500);
    }
}
