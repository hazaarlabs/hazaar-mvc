<?php

declare(strict_types=1);

namespace Hazaar\DBI\Manager\Migration\Action\Exception;

class CreateFailed extends \Exception
{
    public function __construct(string $message, string $name)
    {
        parent::__construct(trim($message).' while creating: '.$name);
    }
}
