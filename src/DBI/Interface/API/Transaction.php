<?php

declare(strict_types=1);

namespace Hazaar\DBI\Interface\API;

interface Transaction
{
    /**
     * Begin a transaction.
     */
    public function begin(): bool;

    /**
     * Commit a transaction.
     */
    public function commit(): bool;

    /**
     * Rollback a transaction.
     */
    public function cancel(): bool;
}
