<?php

declare(strict_types=1);

namespace Hazaar\DBI2\DBD\Traits;

use Hazaar\DBI2\Interfaces\QueryBuilder;

trait SurrealQL
{
    public function getQueryBuilder(): QueryBuilder
    {
        return new SurrealQLBuilder();
    }
}
