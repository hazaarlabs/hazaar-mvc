<?php

declare(strict_types=1);

namespace Hazaar\DBI\Interfaces\API;

interface Statement
{
    /**
     * Create a new statement object.
     *
     * This method is used to create a new statement object from a SQL string.  The SQL string is passed to the
     * database driver and prepared for execution.  The statement object is then returned.
     *
     * @param string $sql the SQL string to prepare
     */
    public function prepare(string $sql): \PDOStatement;
}
