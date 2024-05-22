<?php

declare(strict_types=1);

namespace Hazaar\DBI\Exception;

class MissingJoin extends \Exception
{
    public function __construct(string $ref)
    {
        parent::__construct("Missing join while referencing '{$ref}'.");
    }
}
