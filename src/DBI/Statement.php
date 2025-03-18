<?php

declare(strict_types=1);

namespace Hazaar\DBI;

class Statement extends \PDOStatement
{
    protected function __construct() {}

    public function customMethod(): void
    {
        // Your added functionality
    }
}
