<?php

declare(strict_types=1);

namespace Hazaar\Auth\Storage\Exception;

class NoApplication extends \Exception
{
    public function __construct(?string $storage = null)
    {
        $message = ($storage ? $storage.' a' : 'A').'uth storage requires an application instance';
        parent::__construct($message, 500);
    }
}
