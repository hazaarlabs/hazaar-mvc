<?php

declare(strict_types=1);

namespace Hazaar\DBI\DBD\Traits;

use Hazaar\DBI\Interfaces\QueryBuilder;

trait SurrealQL
{
    public function getQueryBuilder(): QueryBuilder
    {
        return new SurrealQLBuilder();
    }
}
