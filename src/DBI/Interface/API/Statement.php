<?php

declare(strict_types=1);

namespace Hazaar\DBI\Interface\API;

use Hazaar\DBI\Interface\QueryBuilder;
use Hazaar\DBI\Statement as DBIStatement;

interface Statement
{
    /**
     * Create a new statement object.
     *
     * This method is used to create a new statement object from a SQL string.  The SQL string is passed to the
     * database driver and prepared for execution.  The statement object is then returned.
     *
     * @param QueryBuilder $sql the SQL string to prepare
     */
    public function prepareQuery(QueryBuilder $sql): DBIStatement|false;

    public function prepare(string $sql): DBIStatement|false;
}
