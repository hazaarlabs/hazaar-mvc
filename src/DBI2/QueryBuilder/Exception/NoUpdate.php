<?php

declare(strict_types=1);

namespace Hazaar\DBI2\QueryBuilder\Exception;

class NoUpdate extends \Exception
{
    public function __construct()
    {
        parent::__construct('No columns are being updated!');
    }
}