<?php

declare(strict_types=1);

namespace Hazaar\Model\Exception;

use Hazaar\Exception;

class DefineEventHookException extends \Exception
{
    public function __construct(string $class, string $hookName, ?string $reason = null)
    {
        parent::__construct("Cannot define event hook '{$hookName}' on class {$class}".($reason ? ": {$reason}" : ''));
    }
}
