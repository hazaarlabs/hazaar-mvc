<?php

declare(strict_types=1);

namespace Hazaar\DBI2\DBD\Traits;

use Hazaar\DBI2\Interfaces\QueryBuilder;
use Hazaar\DBI2\QueryBuilder\SQL as SQLBuilder;

trait SQL
{
    public function getQueryBuilder(): QueryBuilder
    {
        return new SQLBuilder();
    }
}
