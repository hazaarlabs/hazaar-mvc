<?php

namespace Hazaar\DBI2\DBD\Interfaces\SQL;

interface Transaction
{
    /**
     * Begins a database transaction.
     *
     * @return bool returns true if the transaction was successfully started, false otherwise
     */
    public function begin(): bool;

    /**
     * Commits the current database transaction.
     *
     * @return bool returns true if the transaction was successfully committed, false otherwise
     */
    public function commit(): bool;

    /**
     * Cancel and rollback the current database transaction.
     *
     * @return bool returns true if the operation was successfully canceled, false otherwise
     */
    public function cancel(): bool;
}
